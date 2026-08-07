<?php
/**
 * Plugin Name: noindex SEO
 * Plugin URI: https://wordpress.org/plugins/noindex-seo/
 * Description: Control search engine indexing with robots directives (noindex, nofollow, noarchive, nosnippet, noimageindex) for specific parts of your WordPress site.
 * Requires at least: 5.7
 * Requires PHP: 7.2
 * Version: 3.1.1
 * Author: ROBOTSTXT
 * Author URI: https://www.robotstxt.es/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: noindex-seo
 * Domain Path: /languages
 *
 * @package noindex-seo
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || die( 'Bye bye!' );

/**
 * Plugin version constant. Used for asset cache-busting and internal version checks.
 *
 * @since 2.0.1
 * @since 2.0.2 Bumped to 2.0.2 for bulk-action postmeta fix and dead-context cleanup.
 * @since 3.0.0 Major release: storage consolidated into a single autoloaded option
 *             (`noindex_seo_settings`); legacy options kept as non-autoloaded for
 *             rollback safety. Filterable conflict-detection list. One-click
 *             "Apply recommended defaults" button.
 * @since 3.0.1 Self-healing migration: re-runs the v3 migration if the
 *             consolidated option is missing despite the version claim.
 * @since 3.0.2 Added per-term granular control for taxonomies (category,
 *             post_tag, and public custom taxonomies like product_cat).
 * @since 3.0.3 Added the "Robots" column to taxonomy list tables (mirrors
 *             the existing post-list Robots column) so admins can see at a
 *             glance which terms have an override.
 * @since 3.0.4 Moved the "Apply Recommended Defaults" button from the top
 *             of the settings page to immediately above the Search section,
 *             so it stops competing with the stat cards for above-the-fold
 *             attention.
 * @since 3.1.0 Three HIGH-priority audit items: (1) WP core XML sitemap
 *             integration — noindexed URLs drop out of /wp-sitemap.xml;
 *             (2) per-CPT global defaults — every public custom post type
 *             gets its own directive settings; (3) REST API exposure —
 *             /wp-json/noindex-seo/v1/{settings,effective} so headless
 *             consumers can read the configuration.
 * @since 3.1.1 Security hardening on the REST endpoints: /settings now
 *             requires `manage_options`; /effective is public only for
 *             published + non-password-protected posts and requires
 *             `edit_post` for everything else. The previous `__return_true`
 *             callback would have leaked the full configuration to anyone.
 */
define( 'NOINDEX_SEO_VERSION', '3.1.1' );

/**
 * Option key holding the consolidated plugin settings array.
 *
 * @since 3.0.0
 */
define( 'NOINDEX_SEO_SETTINGS_KEY', 'noindex_seo_settings' );

/**
 * Returns the full consolidated settings array, merged with defaults.
 *
 * The shape is:
 *
 *     [
 *         'version'   => 3,
 *         'contexts'  => [ '<context>' => [ '<directive>' => 0|1, ... ], ... ],
 *         'config'    => [ 'method' => 'meta', 'granular' => 0, ... ],
 *     ]
 *
 * The array is cached in a static variable per-request after the first read.
 *
 * @since 3.0.0
 *
 * @param bool $force_refresh Optional. Bypass the in-memory cache. Default false.
 * @return array{
 *     version: int,
 *     contexts: array<string, array<string, int>>,
 *     config: array{
 *         method: string,
 *         granular: int,
 *         seoplugins: int,
 *         delete_on_uninstall: int
 *     }
 * }
 */
function noindex_seo_get_settings( bool $force_refresh = false ): array {
	static $cached = null;

	if ( null !== $cached && ! $force_refresh ) {
		return $cached;
	}

	$defaults = array(
		'version'  => 3,
		'contexts' => array(),
		'config'   => array(
			'method'              => 'meta',
			'granular'            => 0,
			'taxonomies_granular' => 0,
			'seoplugins'          => 0,
			'delete_on_uninstall' => 0,
		),
	);

	// Seed every context × directive combination with 0.
	foreach ( noindex_seo_get_contexts() as $context ) {
		$defaults['contexts'][ $context ] = array(
			'noindex'      => 0,
			'nofollow'     => 0,
			'noarchive'    => 0,
			'nosnippet'    => 0,
			'noimageindex' => 0,
		);
	}

	$saved = get_option( NOINDEX_SEO_SETTINGS_KEY, array() );
	$saved = is_array( $saved ) ? $saved : array();

	// Merge saved contexts without reordering defaults (so iteration is stable).
	if ( isset( $saved['contexts'] ) && is_array( $saved['contexts'] ) ) {
		foreach ( $defaults['contexts'] as $ctx => $directive_defaults ) {
			if ( isset( $saved['contexts'][ $ctx ] ) && is_array( $saved['contexts'][ $ctx ] ) ) {
				$saved_ctx = $saved['contexts'][ $ctx ];
				foreach ( $directive_defaults as $dir => $default_value ) {
					$defaults['contexts'][ $ctx ][ $dir ] = isset( $saved_ctx[ $dir ] )
						? ( is_numeric( $saved_ctx[ $dir ] ) ? (int) $saved_ctx[ $dir ] : $default_value )
						: $default_value;
				}
			}
		}
	}

	if ( isset( $saved['config'] ) && is_array( $saved['config'] ) ) {
		foreach ( $defaults['config'] as $key => $default_value ) {
			if ( ! isset( $saved['config'][ $key ] ) ) {
				continue;
			}
			$saved_config_value = $saved['config'][ $key ];
			if ( is_int( $default_value ) ) {
				$defaults['config'][ $key ] = is_numeric( $saved_config_value )
					? (int) $saved_config_value
					: $default_value;
			} elseif ( is_string( $saved_config_value ) ) {
				$defaults['config'][ $key ] = $saved_config_value;
			}
		}
	}

	if ( isset( $saved['version'] ) && is_numeric( $saved['version'] ) ) {
		$defaults['version'] = (int) $saved['version'];
	}

	$cached = $defaults;
	return $cached;
}

/**
 * Reads a single directive value for a context from the consolidated settings.
 *
 * @since 3.0.0
 *
 * @param string $context   Context slug (e.g. 'single', 'attachment').
 * @param string $directive Directive slug (noindex|nofollow|noarchive|nosnippet|noimageindex).
 * @return int 1 if the directive is active for the context, 0 otherwise.
 */
function noindex_seo_get_setting( string $context, string $directive ): int {
	$settings = noindex_seo_get_settings();
	if ( ! isset( $settings['contexts'][ $context ][ $directive ] ) ) {
		return 0;
	}
	return $settings['contexts'][ $context ][ $directive ] ? 1 : 0;
}

/**
 * Reads a single value from the plugin's "config" sub-array.
 *
 * @since 3.0.0
 *
 * @param string $key     Config key (method|granular|seoplugins|delete_on_uninstall).
 * @param mixed  $fallback Default if the key is missing.
 * @return mixed
 */
function noindex_seo_get_config( string $key, $fallback = null ) {
	$settings = noindex_seo_get_settings();
	if ( ! isset( $settings['config'][ $key ] ) ) {
		return $fallback;
	}
	return $settings['config'][ $key ];
}

/**
 * Persists the full settings array and invalidates the runtime cache.
 *
 * @since 3.0.0
 *
 * @param array<string, mixed> $settings Settings array (same shape as noindex_seo_get_settings()).
 * @return void
 */
function noindex_seo_update_settings( array $settings ): void {
	// Keep the version tag current.
	$settings['version'] = 3;
	update_option( NOINDEX_SEO_SETTINGS_KEY, $settings );

	// Reset the static cache inside noindex_seo_get_settings() so the next read
	// picks up the new values without waiting for a new request.
	noindex_seo_get_settings( true );
}

/**
 * Outputs robots directives using the configured implementation method.
 *
 * This function adds robots directives (noindex, nofollow, noarchive, nosnippet, noimageindex)
 * to instruct search engines how to handle the current page. It supports three implementation methods:
 *
 * - 'meta': HTML meta tags via wp_robots filter (default)
 * - 'header': HTTP X-Robots-Tag header
 * - 'both': Both HTML meta tags and HTTP headers
 *
 * The HTTP header method is more robust and works with non-HTML content (PDFs, images, feeds).
 * The meta tag method is more visible and easier for users to verify.
 *
 * @since 1.1.0
 * @since 2.0.0 Removed fallback for WordPress < 5.7. Requires WP 5.7+ (the 6.6 minimum in the 2.0.0 header was overly conservative; corrected in 2.0.1).
 * @since 2.0.0 Added support for HTTP X-Robots-Tag headers and multiple implementation methods.
 * @since 2.0.0 Added support for multiple directives (noindex, nofollow, noarchive, nosnippet, noimageindex).
 *
 * @see https://developer.wordpress.org/reference/hooks/wp_robots/
 * @see https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag
 *
 * @param string             $method     Implementation method: 'meta', 'header', or 'both'. Default 'meta'.
 * @param array<int, string> $directives Array of directives to apply. Default array('noindex').
 * @return void
 */
function noindex_seo_metarobots( string $method = 'meta', array $directives = array( 'noindex' ) ): void {
	// Sanitize method.
	$valid_methods = array( 'meta', 'header', 'both' );
	$method        = in_array( $method, $valid_methods, true ) ? $method : 'meta';

	// Sanitize directives.
	$valid_directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	$directives       = array_intersect( $directives, $valid_directives );

	if ( empty( $directives ) ) {
		return; // No valid directives to apply.
	}

	// Send HTTP header if requested.
	$header_sent = false;
	if ( in_array( $method, array( 'header', 'both' ), true ) ) {
		if ( ! headers_sent() ) {
			$header_value = implode( ', ', $directives );
			header( 'X-Robots-Tag: ' . $header_value, false );
			$header_sent = true;
		}
	}

	// Add HTML meta tag if requested, or as fallback if headers already sent.
	$use_meta        = in_array( $method, array( 'meta', 'both' ), true );
	$fallback_needed = in_array( $method, array( 'header', 'both' ), true ) && ! $header_sent;

	if ( $use_meta || $fallback_needed ) {
		add_filter(
			'wp_robots',
			function ( array $robots ) use ( $directives ): array {
				foreach ( $directives as $directive ) {
					$robots[ $directive ] = true;
				}
				return $robots;
			},
			99 // High priority to ensure our directives take precedence over other plugins.
		);
	}
}

/**
 * Clear all robots directive meta values for a post.
 *
 * This helper function deletes all robots directive post meta fields for a given post ID.
 * Used when disabling granular control override or resetting directive settings.
 *
 * @since 2.0.0
 *
 * @param int $post_id The post ID to clear directives for.
 * @return void
 */
function noindex_seo_clear_post_directives( int $post_id ): void {
	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	foreach ( $directives as $directive ) {
		delete_post_meta( $post_id, '_noindex_seo_' . $directive );
	}
}

/**
 * Determines whether to output robots directives based on page context and plugin settings.
 *
 * This function checks the current page context (e.g., single post, category archive, 404 page, etc.)
 * and evaluates plugin settings to determine which robots directives (noindex, nofollow, noarchive,
 * nosnippet, noimageindex) should be added.
 *
 * It retrieves settings efficiently using a transient cache. If the cache is not set, it pulls values
 * from the WordPress options API and rebuilds the cache.
 *
 * The list of contexts can be filtered via the {@see 'noindex_seo_contexts'} filter.
 * Once a matching context is found, it calls {@see noindex_seo_metarobots()} to apply the directives.
 *
 * @since 1.1.0
 * @since 2.0.0 Added support for multiple directives (noindex, nofollow, noarchive, nosnippet, noimageindex).
 *
 * @global WP_Post $post The global post object, if available.
 *
 * @return void
 */
function noindex_seo_show(): void {
	/**
	 * Filter the contexts and corresponding option keys used for robots directives.
	 *
	 * Custom option keys MUST follow the pattern `noindex_seo_{context}`.
	 * Directive option keys for non-noindex directives are derived by replacing
	 * the leading `noindex` with the directive name via str_replace(), e.g.
	 * `noindex_seo_single` → `nofollow_seo_single`. Keys that do not start with
	 * `noindex_seo_` are silently discarded.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $contexts Associative array of context slug => option_key.
	 */
	$contexts = apply_filters(
		'noindex_seo_contexts',
		array(
			'single'            => 'noindex_seo_single',
			'page'              => 'noindex_seo_page',
			'privacy_policy'    => 'noindex_seo_privacy_policy',
			'attachment'        => 'noindex_seo_attachment',
			'category'          => 'noindex_seo_category',
			'tag'               => 'noindex_seo_tag',
			'author'            => 'noindex_seo_author',
			'post_type_archive' => 'noindex_seo_post_type_archive',
			'date'              => 'noindex_seo_date',
			'day'               => 'noindex_seo_day',
			'month'             => 'noindex_seo_month',
			'year'              => 'noindex_seo_year',
			'archive'           => 'noindex_seo_archive',
			'search'            => 'noindex_seo_search',
			'error'             => 'noindex_seo_error',
			'front_page'        => 'noindex_seo_front_page',
			'home'              => 'noindex_seo_home',
			'singular'          => 'noindex_seo_singular',
			'paged'             => 'noindex_seo_paged',
			'preview'           => 'noindex_seo_preview',
			'customize_preview' => 'noindex_seo_customize_preview',
			'time'              => 'noindex_seo_time',
		)
	);

	// Validate filtered contexts to prevent injection of invalid option names.
	// phpstan-wordpress types apply_filters() based on its default argument (array).
	// The cast below ensures runtime safety if a filter callback returns a non-array.
	foreach ( (array) $contexts as $context => $option_key ) {
		// Ensure option_key follows expected pattern.
		// is_string() check is omitted: PHPStan guarantees $option_key is string
		// because apply_filters returns array<string,string> based on its default argument.
		if ( 0 !== strpos( $option_key, 'noindex_seo_' ) ) {
			unset( $contexts[ $context ] );
		}
	}

	// PRIORITY 1: Check for per-post/page override (granular control).
	// If granular control is enabled and we're on a singular post/page,
	// check if there's an override for this specific content.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( $granular_enabled && is_singular() ) {
		$post_id = get_queried_object_id();
		if ( $post_id ) {
			$override = get_post_meta( $post_id, '_noindex_seo_override', true );
			if ( $override ) {
				// Collect active directives from post meta.
				$post_directives      = array();
				$available_directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

				foreach ( $available_directives as $directive ) {
					$meta_value = get_post_meta( $post_id, '_noindex_seo_' . $directive, true );
					// Explicitly check for 1 or '1' to avoid false positives with '0' string.
					if ( is_scalar( $meta_value ) && 1 === absint( $meta_value ) ) {
						$post_directives[] = $directive;
					}
				}

				// Apply post-specific directives if any are enabled.
				if ( ! empty( $post_directives ) ) {
					$opt                   = noindex_seo_get_config( 'method', 'meta' );
					$implementation_method = is_string( $opt ) ? $opt : 'meta';
					noindex_seo_metarobots( $implementation_method, $post_directives );
					return; // Exit early - post meta takes precedence over global settings.
				}
			}
		}
	}

	// PRIORITY 1.5: Check for per-term override (granular control on taxonomies).
	// Runs after per-post (more specific) but before the global context settings.
	// Triggered on is_category() / is_tag() / is_tax() when 'taxonomies_granular'
	// is on AND the queried term has the override flag set in term meta.
	if ( noindex_seo_get_config( 'taxonomies_granular', 0 ) && ( is_category() || is_tag() || is_tax() ) ) {
		$term_id = get_queried_object_id();
		if ( $term_id ) {
			$term_override = get_term_meta( $term_id, '_noindex_seo_override', true );
			if ( $term_override ) {
				$term_directives      = array();
				$available_directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

				foreach ( $available_directives as $directive ) {
					$meta_value = get_term_meta( $term_id, '_noindex_seo_' . $directive, true );
					if ( is_scalar( $meta_value ) && 1 === absint( $meta_value ) ) {
						$term_directives[] = $directive;
					}
				}

				if ( ! empty( $term_directives ) ) {
					$opt                   = noindex_seo_get_config( 'method', 'meta' );
					$implementation_method = is_string( $opt ) ? $opt : 'meta';
					noindex_seo_metarobots( $implementation_method, $term_directives );
					return;
				}
			}
		}
	}

	// PRIORITY 1.6: Per-CPT global defaults.
	// When the current request is a single from a public custom post type that
	// has at least one directive configured in its `cpt_{post_type}` context,
	// apply those directives and short-circuit before falling through to the
	// generic `single` global setting. Built-in post / page / attachment do
	// not have a `cpt_*` context (they have their own dedicated contexts), so
	// this branch is a no-op for them.
	if ( is_singular() ) {
		$post_type = get_post_type();
		if ( $post_type && 'post' !== $post_type && 'page' !== $post_type && 'attachment' !== $post_type ) {
			$cpt_context        = 'cpt_' . $post_type;
			$cpt_directives     = array();
			$cpt_directive_list = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

			foreach ( $cpt_directive_list as $directive ) {
				if ( 1 === noindex_seo_get_setting( $cpt_context, $directive ) ) {
					$cpt_directives[] = $directive;
				}
			}

			if ( ! empty( $cpt_directives ) ) {
				$opt                   = noindex_seo_get_config( 'method', 'meta' );
				$implementation_method = is_string( $opt ) ? $opt : 'meta';
				noindex_seo_metarobots( $implementation_method, $cpt_directives );
				return;
			}
		}
	}

	// PRIORITY 2: Apply global settings (existing behavior).
	// Try to get the options from the transient.
	$options = get_transient( 'noindex_seo_options' );

	// Available directives.
	$available_directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	if ( false === $options || ! is_array( $options ) || empty( $options ) ) {
		// Transient not set; pull directive values from the consolidated settings.
		// The cache stores them keyed by the legacy `{directive}_seo_{context}`
		// option name so the rest of noindex_seo_show() does not need to know
		// about the storage layout change introduced in 3.0.
		$options = array();

		foreach ( $contexts as $context => $option_key ) {
			foreach ( $available_directives as $directive ) {
				$directive_key             = str_replace( 'noindex', $directive, (string) $option_key );
				$options[ $directive_key ] = noindex_seo_get_setting( $context, $directive );
			}
		}

		// Set the transient for 1 hour to cache the options.
		set_transient( 'noindex_seo_options', $options, HOUR_IN_SECONDS );
	}

	// Define current conditions, ordered from most specific to most general.
	// Note on 'date' context: is_date() returns true for any date-based archive (day/month/year/time).
	// In normal WordPress, if is_date() is true, at least one specific date function should also be true.
	// However, this catch-all condition is kept for edge cases, custom implementations, or future
	// WordPress versions that might introduce new date archive types not covered by the specific functions.
	// While this condition may rarely (or never) be true in practice, it provides defensive coverage.
	$current_conditions = array(
		'single'            => is_single(),
		'page'              => is_page(),
		'attachment'        => is_attachment(),
		'privacy_policy'    => is_privacy_policy(),
		'category'          => is_category(),
		'tag'               => is_tag(),
		'author'            => is_author(),
		'post_type_archive' => is_post_type_archive(),
		'day'               => is_day(),
		'month'             => is_month(),
		'year'              => is_year(),
		'time'              => is_time(),
		'date'              => is_date() && ! ( is_day() || is_month() || is_year() || is_time() ), // Catch-all for date archives.
		'archive'           => is_archive() && ! ( is_category() || is_tag() || is_author() || is_post_type_archive() || is_date() ),
		'search'            => is_search(),
		'error'             => is_404(),
		'front_page'        => is_front_page() && ! is_paged() && ! is_home(),
		'home'              => is_home() && ! is_paged(),
		'singular'          => is_singular() && ! ( is_single() || is_page() || is_attachment() ),
		'paged'             => is_paged() && ! is_front_page() && ! is_home(),
		'preview'           => is_preview(),
		'customize_preview' => is_customize_preview(),
	);

	// Get implementation method configuration.
	$opt                   = noindex_seo_get_config( 'method', 'meta' );
	$implementation_method = is_string( $opt ) ? $opt : 'meta';

	// Iterate through the contexts and collect active directives.
	foreach ( $contexts as $context => $option_key ) {

		if (
			isset( $current_conditions[ $context ] ) &&
			$current_conditions[ $context ]
		) {
			// Collect all active directives for this context.
			$active_directives = array();

			foreach ( $available_directives as $directive ) {
				$directive_key = str_replace( 'noindex', $directive, (string) $option_key );

				if ( isset( $options[ $directive_key ] ) && (bool) $options[ $directive_key ] ) {
					$active_directives[] = $directive;
				}
			}

			// Apply directives if any are active.
			if ( ! empty( $active_directives ) ) {
				noindex_seo_metarobots( $implementation_method, $active_directives );
				break; // Prevent multiple meta tags from being added.
			}
		}
	}

	unset( $contexts, $options, $current_conditions, $available_directives );
}

/**
 * Checks if configuration migration is needed and executes it.
 *
 * This function runs on plugin load and checks the configuration version stored in the database.
 * If the version is less than 2 (or doesn't exist), it migrates old single-directive options
 * to the new multi-directive system introduced in version 2.0.
 *
 * If the version is less than 3, it migrates the 110 individual `{directive}_seo_{context}`
 * options plus the five `noindex_seo_config_*` options into a single consolidated
 * `noindex_seo_settings` array (introduced in 3.0.0).
 *
 * Self-healing: even if the version claims migration is complete, the function
 * re-runs the v3 migration if the consolidated option is somehow missing. This
 * guards against partial failures (object cache staleness, request interruption
 * mid-migration, third-party plugins clearing options) that would otherwise leave
 * the site with no settings until manual intervention.
 *
 * @since 2.0.0
 * @since 3.0.0 Added v2→v3 storage consolidation step and self-healing.
 *
 * @return void
 */
function noindex_seo_check_migration(): void {
	$current_config_version = get_option( 'noindex_seo_config_version', 0 );

	// Check if we need to migrate to version 2.
	if ( $current_config_version < 2 ) {
		noindex_seo_migrate_to_v2();
	}

	// Run the v3 migration if version < 3 OR if the consolidated option is
	// somehow missing despite the version claiming we already migrated.
	// Self-healing: this catches scenarios where a previous migration bumped
	// the version but did not persist the consolidated option (object cache
	// staleness, request interrupted mid-migration, third-party delete, etc.).
	$consolidated_exists = false !== get_option( NOINDEX_SEO_SETTINGS_KEY );
	if ( $current_config_version < 3 || ! $consolidated_exists ) {
		noindex_seo_migrate_to_v3();
	}
}

/**
 * Migrates configuration from version 1.x to version 2.0.
 *
 * Version 1.x had single options per context (e.g., noindex_seo_attachment).
 * Version 2.0 has 5 independent directives per context (e.g., noindex_seo_attachment,
 * nofollow_seo_attachment, noarchive_seo_attachment, etc.).
 *
 * This function:
 * 1. Reads existing noindex_seo_* options
 * 2. Preserves their values (they're already in the correct format)
 * 3. Initializes new directive options (nofollow, noarchive, nosnippet, noimageindex) to 0
 * 4. Marks migration as complete by setting config version to 2
 * 5. Clears the transient cache
 *
 * @since 2.0.0
 *
 * @return void
 */
function noindex_seo_migrate_to_v2(): void {
	$contexts = noindex_seo_get_contexts();

	$new_directives = array( 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	// For each context, initialize new directives to 0.
	// The noindex directive already exists and will keep its value.
	foreach ( $contexts as $context ) {
		foreach ( $new_directives as $directive ) {
			$option_key = $directive . '_seo_' . $context;

			// Only set if it doesn't exist (shouldn't exist in v1.x).
			if ( false === get_option( $option_key ) ) {
				add_option( $option_key, 0 );
			}
		}
	}

	// Mark migration as complete.
	update_option( 'noindex_seo_config_version', 2 );

	// Clear transient cache.
	delete_transient( 'noindex_seo_options' );
}

/**
 * Migrates configuration from version 2.x to version 3.0.
 *
 * Version 2.x stored settings across ~110 individual options: one option per
 * (directive, context) pair plus five `noindex_seo_config_*` options. Each of
 * those rows was autoloaded on every WordPress request.
 *
 * Version 3.0 consolidates them into a single autoloaded array option,
 * `noindex_seo_settings`, that the rest of the plugin reads through
 * `noindex_seo_get_settings()` / `noindex_seo_get_setting()` /
 * `noindex_seo_get_config()`.
 *
 * Migration is idempotent and safe to re-run:
 *
 * 1. Reads every legacy option (defaulting to 0 / 'meta' as appropriate).
 * 2. Builds the consolidated array.
 * 3. Writes the array via `update_option()` (autoloaded by default).
 * 4. Rewrites each legacy option with autoload=false so it stops counting
 *    against the per-request autoload budget but remains readable for a
 *    safe rollback to 2.x. These will be deleted in 4.0.
 * 5. Bumps `noindex_seo_config_version` to 3.
 * 6. Clears the transient cache.
 *
 * @since 3.0.0
 *
 * @return void
 */
function noindex_seo_migrate_to_v3(): void {
	$contexts   = noindex_seo_get_contexts();
	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	$consolidated = array(
		'version'  => 3,
		'contexts' => array(),
		'config'   => array(
			// Read directly from the legacy individual options — NOT via
			// noindex_seo_get_config(), which would read the consolidated option
			// we are in the middle of building.
			'method'              => get_option( 'noindex_seo_config_method', 'meta' ),
			'granular'            => get_option( 'noindex_seo_config_granular', 0 ),
			'seoplugins'          => get_option( 'noindex_seo_config_seoplugins', 0 ),
			'delete_on_uninstall' => get_option( 'noindex_seo_config_delete_on_uninstall', 0 ),
		),
	);

	// Normalise config value types.
	$consolidated['config']['granular']            = $consolidated['config']['granular'] ? 1 : 0;
	$consolidated['config']['seoplugins']          = $consolidated['config']['seoplugins'] ? 1 : 0;
	$consolidated['config']['delete_on_uninstall'] = $consolidated['config']['delete_on_uninstall'] ? 1 : 0;
	if ( ! in_array( $consolidated['config']['method'], array( 'meta', 'header', 'both' ), true ) ) {
		$consolidated['config']['method'] = 'meta';
	}

	foreach ( $contexts as $context ) {
		$consolidated['contexts'][ $context ] = array();
		foreach ( $directives as $directive ) {
			$option_key = $directive . '_seo_' . $context;
			$value      = get_option( $option_key, 0 );
			$consolidated['contexts'][ $context ][ $directive ] = ( is_numeric( $value ) && 1 === (int) $value ) ? 1 : 0;
		}
	}

	// Write the consolidated option (autoloaded by default — single wp_options row).
	update_option( NOINDEX_SEO_SETTINGS_KEY, $consolidated );

	// Mark each legacy option as non-autoloaded. update_option() short-circuits
	// when the value is unchanged (which is the case here — we are only flipping
	// the autoload flag), so we use a direct SQL UPDATE on the options table.
	// The options themselves remain readable for a safe rollback to 2.x; they
	// will be deleted entirely in 4.0.
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	// Migration is a one-shot operation. Direct update is necessary because
	// update_option() does not change autoload when the value is unchanged.
	// This is a UPDATE (no result set), so caching is not applicable.
	foreach ( $contexts as $context ) {
		foreach ( $directives as $directive ) {
			$option_key = $directive . '_seo_' . $context;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name = %s",
					$option_key
				)
			);
		}
	}

	foreach ( array( 'method', 'granular', 'seoplugins', 'delete_on_uninstall' ) as $config_key ) {
		$option_key = 'noindex_seo_config_' . $config_key;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name = %s",
				$option_key
			)
		);
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Mark migration complete and clear cache.
	update_option( 'noindex_seo_config_version', 3 );
	delete_transient( 'noindex_seo_options' );

	// Reset the in-memory settings cache so the rest of this request reads
	// from the new option instead of the just-stale state.
	noindex_seo_get_settings( true );
}

add_action( 'template_redirect', 'noindex_seo_show' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'noindex_seo_settings_link' );
add_action( 'admin_init', 'noindex_seo_register' );
add_action( 'admin_menu', 'noindex_seo_menu' );
add_action( 'admin_enqueue_scripts', 'noindex_seo_enqueue_admin_assets' );
add_action( 'plugins_loaded', 'noindex_seo_check_migration' );

/**
 * Excludes noindexed posts from the core XML sitemap (WP 5.5+).
 *
 * Two layers of exclusion:
 *  1. Whole-post-type exclusion: if the global setting for the context that
 *     matches the post type has `noindex=1`, the entire post type is hidden
 *     from the sitemap by setting `post__in = [0]` (an impossible ID, so
 *     the query returns no rows). The relevant contexts are:
 *       - 'singular'  → applies to every public post type (catch-all).
 *       - 'single'    → applies to the built-in `post` post type.
 *       - 'page'      → applies to the built-in `page` post type.
 *       - 'attachment'→ applies to the built-in `attachment` post type.
 *       - 'cpt_{type}'→ applies to that custom post type (3.1.0+).
 *  2. Per-post exclusion: if granular control is on, individual posts that
 *     have `_noindex_seo_override=1` AND `_noindex_seo_noindex=1` in postmeta
 *     are added to `post__not_in`.
 *
 * Hooked to the {@see 'wp_sitemaps_posts_query_args'} filter.
 *
 * @since 3.1.0
 *
 * @param array<string, mixed> $args      WP_Query arguments for the sitemap post query.
 * @param string               $post_type Post type being listed.
 * @return array<string, mixed> Filtered arguments.
 */
function noindex_seo_filter_sitemap_posts( array $args, string $post_type ): array {
	// Layer 1: whole-post-type exclusion via global context settings.
	// 'singular' is the catch-all — if it is noindexed, hide everything.
	if ( 1 === noindex_seo_get_setting( 'singular', 'noindex' ) ) {
		$args['post__in'] = array( 0 );
		return $args;
	}

	// Then check the specific context for the post type.
	$context_for_type = '';
	if ( 'post' === $post_type ) {
		$context_for_type = 'single';
	} elseif ( 'page' === $post_type ) {
		$context_for_type = 'page';
	} elseif ( 'attachment' === $post_type ) {
		$context_for_type = 'attachment';
	} else {
		$context_for_type = 'cpt_' . $post_type;
	}

	if ( 1 === noindex_seo_get_setting( $context_for_type, 'noindex' ) ) {
		$args['post__in'] = array( 0 );
		return $args;
	}

	// Layer 2: per-post exclusion via granular override.
	if ( noindex_seo_get_config( 'granular', 0 ) ) {
		$excluded = get_posts(
			array(
				'post_type'        => $post_type,
				'fields'           => 'ids',
				'nopaging'         => true,
				'suppress_filters' => false,
				'no_found_rows'    => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_noindex_seo_override',
						'value' => 1,
					),
					array(
						'key'   => '_noindex_seo_noindex',
						'value' => 1,
					),
				),
			)
		);

		if ( ! empty( $excluded ) ) {
			$existing             = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
			$args['post__not_in'] = array_merge( $existing, $excluded );
		}
	}

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'noindex_seo_filter_sitemap_posts', 10, 2 );

/**
 * Excludes noindexed terms from the core XML sitemap (WP 5.5+).
 *
 * Two layers, mirroring {@see noindex_seo_filter_sitemap_posts()}:
 *  1. Whole-taxonomy exclusion: if the global setting for the matching
 *     context (`category`, `tag`, or any custom public taxonomy mapped to
 *     its own context if present) has `noindex=1`, hide the entire taxonomy.
 *  2. Per-term exclusion: if per-term granular control is on, individual
 *     terms with `_noindex_seo_override=1` AND `_noindex_seo_noindex=1` are
 *     added to `exclude`.
 *
 * Hooked to the {@see 'wp_sitemaps_taxonomies_query_args'} filter.
 *
 * @since 3.1.0
 *
 * @param array<string, mixed> $args     WP_Term_Query arguments for the sitemap term query.
 * @param string               $taxonomy Taxonomy being listed.
 * @return array<string, mixed> Filtered arguments.
 */
function noindex_seo_filter_sitemap_taxonomies( array $args, string $taxonomy ): array {
	// Layer 1: whole-taxonomy exclusion via global context settings.
	$context_for_tax = '';
	if ( 'category' === $taxonomy ) {
		$context_for_tax = 'category';
	} elseif ( 'post_tag' === $taxonomy ) {
		$context_for_tax = 'tag';
	}

	if ( $context_for_tax && 1 === noindex_seo_get_setting( $context_for_tax, 'noindex' ) ) {
		// An empty `include` makes WP_Term_Query return zero rows.
		$args['include'] = array( 0 );
		return $args;
	}

	// Layer 2: per-term exclusion via granular override.
	if ( noindex_seo_get_config( 'taxonomies_granular', 0 ) ) {
		$excluded = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'fields'     => 'ids',
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_noindex_seo_override',
						'value' => 1,
					),
					array(
						'key'   => '_noindex_seo_noindex',
						'value' => 1,
					),
				),
			)
		);

		if ( is_array( $excluded ) && ! empty( $excluded ) ) {
			$existing        = isset( $args['exclude'] ) && is_array( $args['exclude'] ) ? $args['exclude'] : array();
			$args['exclude'] = array_merge( $existing, $excluded );
		}
	}

	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'noindex_seo_filter_sitemap_taxonomies', 10, 2 );

/**
 * Excludes user URLs from the core XML sitemap when author archives are
 * noindexed globally.
 *
 * The users sitemap exists to surface author archive URLs. If the global
 * `author` context has `noindex=1`, every author archive is non-indexable,
 * so the entire users sitemap should be empty. We hide every user by
 * setting `include = [0]` (an impossible ID).
 *
 * Hooked to the {@see 'wp_sitemaps_users_query_args'} filter.
 *
 * @since 3.1.0
 *
 * @param array<string, mixed> $args WP_User_Query arguments for the sitemap user query.
 * @return array<string, mixed> Filtered arguments.
 */
function noindex_seo_filter_sitemap_users( array $args ): array {
	if ( 1 === noindex_seo_get_setting( 'author', 'noindex' ) ) {
		$args['include'] = array( 0 );
	}
	return $args;
}
add_filter( 'wp_sitemaps_users_query_args', 'noindex_seo_filter_sitemap_users' );

/**
 * Registers the plugin's REST API routes.
 *
 * Two routes under the `noindex-seo/v1` namespace:
 *
 *   - GET /wp-json/noindex-seo/v1/settings
 *     Returns the consolidated settings array (contexts × directives + config).
 *     Lets headless consumers read the global configuration. Read-only, public.
 *
 *   - GET /wp-json/noindex-seo/v1/effective?post_id=123
 *     Returns the list of active directives for a specific post ID, computed
 *     by the same precedence rules used on the front-end: per-post override →
 *     per-CPT default → built-in `single` / `page` / `attachment` context.
 *     Read-only, public (the directive result is the same info that would
 *     appear in the HTML `<meta name="robots">` tag of a rendered page).
 *
 * Hooked to the {@see 'rest_api_init'} action.
 *
 * @since 3.1.0
 *
 * @return void
 */
function noindex_seo_register_rest_routes(): void {
	register_rest_route(
		'noindex-seo/v1',
		'/settings',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'noindex_seo_rest_get_settings',
				'permission_callback' => 'noindex_seo_rest_settings_permission_check',
			),
		)
	);

	register_rest_route(
		'noindex-seo/v1',
		'/effective',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'noindex_seo_rest_get_effective',
				'permission_callback' => 'noindex_seo_rest_effective_permission_check',
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'description'       => __( 'Post ID to compute effective directives for.', 'noindex-seo' ),
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'noindex_seo_register_rest_routes' );

/**
 * Permission callback for the /settings endpoint.
 *
 * The consolidated settings array reveals the site's full SEO strategy in a
 * single response (every context × directive combination plus internal
 * flags like delete_on_uninstall). That is admin-only information; an
 * unauthenticated caller could otherwise enumerate the entire noindex
 * configuration by hitting the endpoint once. Restrict to `manage_options`.
 *
 * @since 3.1.1
 *
 * @return true|WP_Error True if the current user can manage options, WP_Error otherwise.
 */
function noindex_seo_rest_settings_permission_check() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'noindex_seo_rest_forbidden',
			__( 'You do not have permission to read the noindex SEO settings.', 'noindex-seo' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
	return true;
}

/**
 * Permission callback for the /effective endpoint.
 *
 * The effective directives for a post are the same information that would
 * appear in the HTML `<meta name="robots">` tag (or `X-Robots-Tag` header)
 * when the post is rendered on the front-end. They are therefore public for
 * posts that are themselves publicly viewable:
 *
 *  - `publish` status AND no password → public access (no auth required).
 *
 * For everything else (drafts, private posts, future/scheduled posts,
 * password-protected posts, trashed posts), the directives are admin-only
 * and the caller must have the `edit_post` capability for that post.
 *
 * If the requested post does not exist, permission is granted so that the
 * callback can return a proper 404 instead of a 401 (this matches the
 * behaviour of WP core's posts controller).
 *
 * @since 3.1.1
 *
 * @param WP_REST_Request $request The current request, with `post_id`.
 * @return true|WP_Error True if access is allowed, WP_Error otherwise.
 */
function noindex_seo_rest_effective_permission_check( WP_REST_Request $request ) {
	$raw_post_id = $request->get_param( 'post_id' );
	$post_id     = is_numeric( $raw_post_id ) ? (int) $raw_post_id : 0;
	$post        = get_post( $post_id );

	// Missing post: defer to the callback, which returns a 404.
	if ( ! $post ) {
		return true;
	}

	// Public post (published + not password-protected): directives are
	// equivalent to what would appear in the rendered HTML.
	if ( 'publish' === $post->post_status && empty( $post->post_password ) ) {
		return true;
	}

	// Everything else requires edit permission for the specific post.
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error(
			'noindex_seo_rest_forbidden',
			__( 'You do not have permission to read directives for this post.', 'noindex-seo' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	return true;
}

/**
 * REST callback: returns the consolidated settings array.
 *
 * Strips the internal `version` key — that is an implementation detail that
 * callers should not depend on. Everything else (contexts, config) is exposed
 * verbatim so headless consumers can replicate the directive logic on their
 * own front-end.
 *
 * @since 3.1.0
 *
 * @return WP_REST_Response The settings payload.
 */
function noindex_seo_rest_get_settings(): WP_REST_Response {
	$settings = noindex_seo_get_settings();
	unset( $settings['version'] );
	return new WP_REST_Response( $settings, 200 );
}

/**
 * REST callback: computes the active directives for a specific post ID.
 *
 * Mirrors the precedence used by {@see noindex_seo_show()} for the singular
 * branch:
 *
 *   1. Per-post override (postmeta `_noindex_seo_override=1` + the five
 *      directive meta keys).
 *   2. Per-CPT default (`cpt_{post_type}` context in consolidated settings).
 *   3. Built-in context for the post type (`single` for posts, `page` for
 *      pages, `attachment` for attachments).
 *
 * Returns the directive list and the implementation method so the consumer
 * knows whether to render an HTML meta tag, send an X-Robots-Tag header, or
 * both.
 *
 * @since 3.1.0
 *
 * @param WP_REST_Request $request Request with `post_id` param.
 * @return WP_REST_Response The effective-directives payload.
 */
function noindex_seo_rest_get_effective( WP_REST_Request $request ): WP_REST_Response {
	$raw_post_id = $request->get_param( 'post_id' );
	$post_id     = is_numeric( $raw_post_id ) ? (int) $raw_post_id : 0;
	$post        = get_post( $post_id );

	if ( ! $post ) {
		return new WP_REST_Response(
			array(
				'code'    => 'noindex_seo_post_not_found',
				'message' => __( 'No post exists with that post_id.', 'noindex-seo' ),
				'data'    => array( 'status' => 404 ),
			),
			404
		);
	}

	$directives = array();
	$available  = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	// Step 1: per-post override.
	$override_raw = get_post_meta( $post_id, '_noindex_seo_override', true );
	$override     = ( is_scalar( $override_raw ) && 1 === absint( $override_raw ) ) ? 1 : 0;

	if ( $override ) {
		foreach ( $available as $directive ) {
			$value = get_post_meta( $post_id, '_noindex_seo_' . $directive, true );
			if ( is_scalar( $value ) && 1 === absint( $value ) ) {
				$directives[] = $directive;
			}
		}
	}

	// Step 2 + 3: fall back to per-CPT or built-in context.
	if ( empty( $directives ) ) {
		$post_type = $post->post_type;
		if ( in_array( $post_type, array( 'post', 'page', 'attachment' ), true ) ) {
			$context = $post_type; // single / page / attachment contexts are not name-aligned, but the storage context for built-in posts is 'single'.
			if ( 'post' === $post_type ) {
				$context = 'single';
			}
		} else {
			$context = 'cpt_' . $post_type;
		}

		foreach ( $available as $directive ) {
			if ( 1 === noindex_seo_get_setting( $context, $directive ) ) {
				$directives[] = $directive;
			}
		}
	}

	$method_raw = noindex_seo_get_config( 'method', 'meta' );
	$method     = is_string( $method_raw ) ? $method_raw : 'meta';

	return new WP_REST_Response(
		array(
			'post_id'    => $post_id,
			'post_type'  => $post->post_type,
			'directives' => $directives,
			'method'     => $method,
			'source'     => $override ? 'post_meta' : 'global_setting',
		),
		200
	);
}

/**
 * Invalidates the hourly transient cache whenever the consolidated settings
 * option is written — no matter who wrote it (admin form, WP-CLI, REST, or
 * a third-party plugin calling update_option / add_option directly).
 *
 * The transient caches the rendered directive map for `noindex_seo_show()`.
 * Without these hooks, programmatic edits would stay stale for up to one hour.
 *
 * Two hooks are needed because WordPress dispatches writes differently:
 *  - `update_option_{key}` fires only when an existing option is updated.
 *  - `add_option_{key}` fires when a new option row is created. WordPress
 *    routes `update_option()` through `add_option()` internally when the
 *    option does not yet exist, so without this second hook the first write
 *    after install would not clear the transient.
 *
 * Hooked to both the {@see 'update_option_noindex_seo_settings'} and
 * {@see 'add_option_noindex_seo_settings'} actions.
 *
 * @since 3.0.0
 *
 * @return void
 */
function noindex_seo_invalidate_cache_on_settings_update(): void {
	delete_transient( 'noindex_seo_options' );
}
add_action( 'update_option_' . NOINDEX_SEO_SETTINGS_KEY, 'noindex_seo_invalidate_cache_on_settings_update' );
add_action( 'add_option_' . NOINDEX_SEO_SETTINGS_KEY, 'noindex_seo_invalidate_cache_on_settings_update' );

/**
 * Enqueues admin CSS and JavaScript assets.
 *
 * Loads the modern admin panel styles and interactive JavaScript only on the
 * noindex SEO settings page for better performance.
 *
 * @since 2.0.0
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function noindex_seo_enqueue_admin_assets( string $hook ): void {
	// Only load on our settings page..
	if ( 'settings_page_noindex_seo' !== $hook ) {
		return;
	}

	// Enqueue admin CSS..
	wp_enqueue_style(
		'noindex-seo-admin',
		plugins_url( 'assets/css/admin.css', __FILE__ ),
		array(),
		NOINDEX_SEO_VERSION,
		'all'
	);

	// Enqueue admin JavaScript..
	wp_enqueue_script(
		'noindex-seo-admin',
		plugins_url( 'assets/js/admin.js', __FILE__ ),
		array( 'jquery' ),
		NOINDEX_SEO_VERSION,
		true
	);

	// Localize script with translations..
	wp_localize_script(
		'noindex-seo-admin',
		'noindexSeoAdmin',
		array(
			'successMessage' => __( 'Settings saved successfully!', 'noindex-seo' ),
			'expandAll'      => __( 'Expand All', 'noindex-seo' ),
			'collapseAll'    => __( 'Collapse All', 'noindex-seo' ),
		)
	);
}

/**
 * Enqueue Gutenberg sidebar panel assets.
 *
 * Loads the JavaScript for the native Gutenberg sidebar panel
 * that allows editing robots directives in the Block Editor.
 *
 * @since 2.0.0
 *
 * @return void
 */
function noindex_seo_enqueue_editor_assets(): void {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Get the current screen.
	$screen = get_current_screen();

	// Only load in block editor for supported post types.
	if ( ! $screen || ! $screen->is_block_editor() ) {
		return;
	}

	// Enqueue editor sidebar script.
	wp_enqueue_script(
		'noindex-seo-editor-sidebar',
		plugins_url( 'assets/js/editor-sidebar.js', __FILE__ ),
		array(
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-data',
			'wp-i18n',
		),
		NOINDEX_SEO_VERSION,
		true
	);

	// Set up translations for the script.
	wp_set_script_translations(
		'noindex-seo-editor-sidebar',
		'noindex-seo'
	);
}
add_action( 'enqueue_block_editor_assets', 'noindex_seo_enqueue_editor_assets' );

/**
 * Adds a "Settings" link to the plugin row actions on the Plugins admin screen.
 *
 * This function appends a direct link to the plugin's settings page within the list of action links
 * shown for the plugin on the Plugins page (`/wp-admin/plugins.php`). This improves user accessibility
 * by allowing quick access to the plugin's configuration page.
 *
 * Hooked to the {@see 'plugin_action_links_{plugin_basename}'} filter.
 *
 * @since 1.0.0
 *
 * @param array<string|int, string> $links Array of existing action links for the plugin.
 * @return array<string|int, string> Modified array including the "Settings" link.
 */
function noindex_seo_settings_link( array $links ): array {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=noindex_seo' ) ) . '">' . esc_html__( 'Settings', 'noindex-seo' ) . '</a>';
	$links[]       = $settings_link;
	return $links;
}

/**
 * Registers the "noindex SEO" settings page in the WordPress admin menu.
 *
 * This function adds an entry under the "Settings" menu in the WordPress admin area,
 * which links to the plugin's main configuration page. The settings page is only accessible
 * to users with the 'manage_options' capability.
 *
 * Internally uses {@see add_options_page()} to register the page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function noindex_seo_menu(): void {
	add_options_page(
		__( 'noindex SEO', 'noindex-seo' ),
		__( 'noindex SEO', 'noindex-seo' ),
		'manage_options',
		'noindex_seo',
		'noindex_seo_admin'
	);
}

/**
 * Returns the canonical list of page contexts recognised by the plugin.
 *
 * Single source of truth for the contexts that the plugin registers options for,
 * migrates, processes from form submissions, and cleans up on uninstall.
 *
 * The list is intentionally a flat indexed array of slugs; the per-context page
 * detection map lives separately in {@see noindex_seo_show()} (where it can be
 * filtered via the `noindex_seo_contexts` filter).
 *
 * As of 3.1.0 the list also includes one `cpt_{post_type}` slug per public
 * custom post type (i.e. public + non-built-in), so the plugin can apply
 * per-CPT global defaults instead of every CPT falling into the generic
 * 'single' bucket.
 *
 * Note: `uninstall.php` cannot call this function (the main plugin file is not
 * loaded during uninstall), so it keeps its own copy in sync.
 *
 * @since 2.0.2
 * @since 3.1.0 Appended per-CPT contexts (cpt_{post_type}) for public custom post types.
 *
 * @return array<int, string> List of context slugs.
 */
function noindex_seo_get_contexts(): array {
	$base = array(
		'archive',
		'attachment',
		'author',
		'category',
		'customize_preview',
		'date',
		'day',
		'front_page',
		'home',
		'month',
		'page',
		'paged',
		'post_type_archive',
		'preview',
		'privacy_policy',
		'search',
		'single',
		'singular',
		'tag',
		'time',
		'year',
		'error',
	);

	// Append one context per public custom post type (excluding the built-in
	// post / page / attachment which already have their own contexts).
	// CPTs are registered on init; calling this before init returns no CPTs,
	// which is fine — the contexts list is only consulted at runtime / in admin.
	$cpts = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'names'
	);
	foreach ( $cpts as $cpt_name ) {
		$base[] = 'cpt_' . $cpt_name;
	}

	return $base;
}

/**
 * Registers all settings used by the 'noindex SEO' plugin.
 *
 * This function registers individual options for each context and directive combination.
 * Each context (e.g., single posts, category pages, archives, etc.) can have multiple
 * directives (noindex, nofollow, noarchive, nosnippet, noimageindex) applied independently.
 *
 * Each setting is stored as an integer (0 or 1), where 1 indicates that the directive is enabled
 * for that context.
 *
 * All settings are grouped under the option group 'noindexseo' and will be handled by the
 * WordPress Settings API when the options form is submitted.
 *
 * Also registers the general configuration options.
 * The transient cache is cleared inside noindex_seo_process_form() after every successful form save.
 *
 * @since 1.0.0
 * @since 2.0.0 Added support for multiple directives per context.
 * @since 2.0.2 Removed dead `update_option_noindexseo` reference (the hook never fired).
 *
 * @return void
 */
function noindex_seo_register(): void {
	$contexts = noindex_seo_get_contexts();

	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	// Register each directive for each context.
	foreach ( $contexts as $context ) {
		foreach ( $directives as $directive ) {
			register_setting(
				'noindexseo',
				$directive . '_seo_' . $context,
				array(
					'type'    => 'integer',
					'default' => 0,
				)
			);
		}
	}

	register_setting(
		'noindexseo',
		'noindex_seo_config_seoplugins',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);

	register_setting(
		'noindexseo',
		'noindex_seo_config_method',
		array(
			'type'              => 'string',
			'default'           => 'meta',
			'sanitize_callback' => function ( $value ): string {
				return in_array( $value, array( 'meta', 'header', 'both' ), true ) ? $value : 'meta';
			},
		)
	);

	register_setting(
		'noindexseo',
		'noindex_seo_config_granular',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);

	register_setting(
		'noindexseo',
		'noindex_seo_config_delete_on_uninstall',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);

	// NOTE: 'noindexseo' is the Settings API *group* name, not an option name.
	// update_option_noindexseo would never fire; the transient is cleared explicitly
	// inside noindex_seo_process_form() after every successful form save.
}

/**
 * Deprecated: clears the cached plugin settings stored in the transient.
 *
 * Was previously hooked to `update_option_noindexseo`, a hook that never fires
 * because `noindexseo` is the Settings API group name, not an option name. The
 * transient is now cleared directly inside {@see noindex_seo_process_form()}
 * after every successful form save.
 *
 * Kept as a deprecation shim for one release cycle in case third-party code
 * called the function directly.
 *
 * @since 1.0.0
 * @since 2.0.2 Deprecated; no longer hooked by the plugin. Callers should
 *              delete the `noindex_seo_options` transient directly via
 *              `delete_transient( 'noindex_seo_options' )`.
 *
 * @return void
 */
function noindex_seo_clear_transient(): void {
	_deprecated_function( __FUNCTION__, '2.0.2', 'delete_transient( \'noindex_seo_options\' )' );
	delete_transient( 'noindex_seo_options' );
}

/**
 * Detects potential conflicts with other SEO plugins and displays an admin notice.
 *
 * This function checks for the presence of known SEO plugins that may conflict with
 * the functionality of 'noindex SEO'. If a conflicting plugin is active and the user
 * has not opted to suppress warnings (via the 'noindex_seo_config_seoplugins' option),
 * a dismissible admin notice is displayed to alert the site administrator.
 *
 * The list of conflicting plugins includes popular SEO tools such as Yoast SEO, Rank Math,
 * SEOPress, and others. The check is performed using {@see is_plugin_active()}.
 *
 * Hooked to the {@see 'admin_init'} action.
 *
 * @since 1.1.0
 *
 * @return void
 */
function noindex_seo_detect_conflicts(): void {

	$option_config_seoplugins = noindex_seo_get_config( 'seoplugins', 0 );

	if ( ! ( is_scalar( $option_config_seoplugins ) ? absint( $option_config_seoplugins ) : 0 ) ) {

		// Include the plugin.php file if the function is not available..
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Define an associative array of conflicting plugins: slug/file => real plugin name.
		// Filterable since 3.0.0 so third-party code can extend or replace the list
		// (e.g. to add The SEO Framework Pro, Slim SEO Pack vs Slim SEO, etc.).
		$conflicting_plugins = apply_filters(
			'noindex_seo_conflicting_plugins',
			array(
				'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
				'premium-seo-pack/index.php'          => 'Premium SEO Pack',
				'seo-by-rank-math/rank-math.php'      => 'Rank Math SEO',
				'wp-seopress/seopress.php'            => 'SEOPress',
				'slim-seo/slim-seo.php'               => 'Slim SEO',
				'squirrly-seo/squirrly.php'           => 'Squirrly SEO',
				'autodescription/autodescription.php' => 'The SEO Framework',
				'wordpress-seo/wp-seo.php'            => 'Yoast SEO',
			)
		);

		// Iterate through the conflicting plugins to check if any are active.
		foreach ( $conflicting_plugins as $plugin_path => $plugin_name ) {
			if ( is_plugin_active( $plugin_path ) ) {
				// Add an admin notice if a conflicting plugin is active..
				add_action(
					'admin_notices',
					function () use ( $plugin_name ) {
						echo '<div class="notice notice-warning is-dismissible"><p>';
						// translators: plugin name.
						printf( esc_html__( 'noindex SEO has detected that %s is active. This may cause conflicts. Please configure the options accordingly.', 'noindex-seo' ), esc_html( $plugin_name ) );
						echo '</p></div>';
					}
				);
				break; // Stop checking after finding the first conflict.
			}
		}
	}
}
add_action( 'admin_init', 'noindex_seo_detect_conflicts' );

/**
 * Processes the form submission for the 'noindex SEO' plugin settings.
 *
 * This function handles the saving of plugin options submitted from the custom admin form.
 * It first verifies the current user's capability and nonce for security. Then it resets all
 * registered options to `0`, and selectively updates those submitted as checked in the form.
 *
 * Additionally, it updates the general configuration settings,
 * clears the plugin's transient cache, and redirects back to the settings page.
 *
 * Hooked to the {@see 'admin_post_update_noindex_seo'} action.
 *
 * @since 1.2.0
 * @since 2.0.0 Added support for multiple directives (noindex, nofollow, noarchive, nosnippet, noimageindex).
 *
 * @return void
 */
function noindex_seo_process_form(): void {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'update_noindex_seo_nonce' ) ) {
		wp_die( esc_html__( 'Permission denied or invalid nonce.', 'noindex-seo' ) );
	}

	$contexts   = noindex_seo_get_contexts();
	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	// Get the implementation method to validate field compatibility.
	$method_value = ( isset( $_POST['noindex_seo_config_method'] ) && is_string( $_POST['noindex_seo_config_method'] ) )
		? sanitize_text_field( wp_unslash( $_POST['noindex_seo_config_method'] ) )
		: 'meta';

	// Validate method.
	$method_value = in_array( $method_value, array( 'meta', 'header', 'both' ), true ) ? $method_value : 'meta';

	// Fields that only work with HTTP headers.
	$header_only_contexts = array( 'attachment' );
	$is_header_enabled    = in_array( $method_value, array( 'header', 'both' ), true );

	// Start from the current consolidated settings so we only overwrite what
	// the form actually touches. The shape is preserved by noindex_seo_update_settings().
	$settings = noindex_seo_get_settings();

	// Process checkbox grid: one entry per (context, directive).
	foreach ( $contexts as $context ) {
		foreach ( $directives as $directive ) {
			$option_key = $directive . '_seo_' . $context;

			// Header-only contexts are forced to 0 when headers aren't enabled.
			if ( in_array( $context, $header_only_contexts, true ) && ! $is_header_enabled ) {
				$settings['contexts'][ $context ][ $directive ] = 0;
				continue;
			}

			$option_value = ( isset( $_POST[ $option_key ] ) && is_string( $_POST[ $option_key ] ) )
				? sanitize_text_field( wp_unslash( $_POST[ $option_key ] ) )
				: '';

			// Only '1' counts as checked; everything else (including invalid input) is 0.
			$settings['contexts'][ $context ][ $directive ] = ( '1' === $option_value ) ? 1 : 0;
		}
	}

	// Save general configuration values.
	$config_value                     = ( isset( $_POST['noindex_seo_config_seoplugins'] ) && is_string( $_POST['noindex_seo_config_seoplugins'] ) )
		? absint( $_POST['noindex_seo_config_seoplugins'] )
		: 0;
	$settings['config']['seoplugins'] = ( 1 === $config_value ) ? 1 : 0;

	// Implementation method (validated above).
	$settings['config']['method'] = $method_value;

	// Granular control.
	$granular_value                 = ( isset( $_POST['noindex_seo_config_granular'] ) && is_string( $_POST['noindex_seo_config_granular'] ) )
		? absint( $_POST['noindex_seo_config_granular'] )
		: 0;
	$settings['config']['granular'] = ( 1 === $granular_value ) ? 1 : 0;

	// Per-term granular control on taxonomies.
	$tax_granular_value                        = ( isset( $_POST['noindex_seo_config_taxonomies_granular'] ) && is_string( $_POST['noindex_seo_config_taxonomies_granular'] ) )
		? absint( $_POST['noindex_seo_config_taxonomies_granular'] )
		: 0;
	$settings['config']['taxonomies_granular'] = ( 1 === $tax_granular_value ) ? 1 : 0;

	// Delete-on-uninstall opt-in.
	$delete_value                              = ( isset( $_POST['noindex_seo_config_delete_on_uninstall'] ) && is_string( $_POST['noindex_seo_config_delete_on_uninstall'] ) )
		? absint( $_POST['noindex_seo_config_delete_on_uninstall'] )
		: 0;
	$settings['config']['delete_on_uninstall'] = ( 1 === $delete_value ) ? 1 : 0;

	// Persist. This fires update_option_noindex_seo_settings, which clears the
	// transient via noindex_seo_invalidate_cache_on_settings_update() — so we
	// no longer need to delete_transient() by hand here.
	noindex_seo_update_settings( $settings );

	wp_safe_redirect( admin_url( 'options-general.php?page=noindex_seo&updated=true' ) );
	exit;
}
add_action( 'admin_post_update_noindex_seo', 'noindex_seo_process_form' );

/**
 * Handles the "Apply recommended defaults" form action.
 *
 * Iterates the settings page section definitions and enables every directive
 * for every context flagged `'suggestion' => true`. The 12 contexts currently
 * flagged are: privacy_policy, date, day, month, time, year, paged, search,
 * attachment, customize_preview, preview, error.
 *
 * All five directives are enabled on each suggested context. This is the same
 * shape a manual click-through would produce, so the form is fully editable
 * afterwards.
 *
 * Hooked to the {@see 'admin_post_noindex_seo_apply_suggestions'} action.
 *
 * @since 3.0.0
 *
 * @return void
 */
function noindex_seo_process_apply_suggestions(): void {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'noindex_seo_apply_suggestions_nonce' ) ) {
		wp_die( esc_html__( 'Permission denied or invalid nonce.', 'noindex-seo' ) );
	}

	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	$settings   = noindex_seo_get_settings();

	foreach ( noindex_seo_get_suggested_contexts() as $context ) {
		foreach ( $directives as $directive ) {
			$settings['contexts'][ $context ][ $directive ] = 1;
		}
	}

	noindex_seo_update_settings( $settings );

	wp_safe_redirect( admin_url( 'options-general.php?page=noindex_seo&suggestions_applied=1' ) );
	exit;
}
add_action( 'admin_post_noindex_seo_apply_suggestions', 'noindex_seo_process_apply_suggestions' );

/**
 * Returns the list of contexts flagged as suggested defaults on the settings page.
 *
 * Centralised so the "Apply recommended defaults" handler and any future
 * WP-CLI command share the same definition of "recommended".
 *
 * @since 3.0.0
 *
 * @return string[] List of context slugs.
 */
function noindex_seo_get_suggested_contexts(): array {
	/**
	 * Filter the contexts that the "Apply recommended defaults" button enables.
	 *
	 * Each returned slug MUST be one of the contexts declared by
	 * noindex_seo_get_contexts(). Unknown slugs are silently ignored at the
	 * call site.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $suggested Context slugs flagged as recommended defaults.
	 */
	return apply_filters(
		'noindex_seo_suggested_contexts',
		array(
			'privacy_policy',
			'date',
			'day',
			'month',
			'time',
			'year',
			'paged',
			'search',
			'attachment',
			'customize_preview',
			'preview',
			'error',
		)
	);
}

/**
 * Register post meta for REST API and Gutenberg support.
 *
 * Registers all robots directive post meta fields with REST API support
 * to enable Gutenberg sidebar panel to read and write values.
 *
 * @since 2.0.0
 *
 * @return void
 */
function noindex_seo_register_post_meta(): void {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Get all public post types.
	$post_types = get_post_types( array( 'public' => true ), 'names' );

	// Meta fields to register.
	$meta_fields = array(
		'_noindex_seo_override',
		'_noindex_seo_noindex',
		'_noindex_seo_nofollow',
		'_noindex_seo_noarchive',
		'_noindex_seo_nosnippet',
		'_noindex_seo_noimageindex',
	);

	// Register each meta field for each post type.
	foreach ( $post_types as $post_type ) {
		foreach ( $meta_fields as $meta_key ) {
			register_post_meta(
				$post_type,
				$meta_key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'default'       => 0,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
add_action( 'init', 'noindex_seo_register_post_meta' );

/**
 * Register meta boxes for granular per-post/page control.
 *
 * Only registers meta boxes if granular control is enabled in settings.
 * Adds meta box to all public post types (posts, pages, custom post types).
 *
 * @since 2.0.0
 *
 * @return void
 */
function noindex_seo_add_meta_boxes(): void {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Skip the classic meta box when the current request is using the block
	// editor — the native PluginDocumentSettingPanel registered by
	// assets/js/editor-sidebar.js handles those cases. Registering the classic
	// meta box as well caused two panels with the same "Search Engine
	// Visibility" title to render in the block editor (visible since WP 5.0,
	// reported as jarring on WP 7.1 — see 3.0.2 fix).
	//
	// We check the per-request screen flag (not the per-post-type default)
	// so the Classic Editor plugin's "?classic-editor" override still gets
	// the classic meta box when the user opens a block-editor post type in
	// classic mode.
	$screen = get_current_screen();
	if ( $screen && $screen->is_block_editor ) {
		return;
	}

	// Get all public post types.
	$post_types = get_post_types( array( 'public' => true ), 'names' );

	// Add meta box to each public post type.
	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'noindex_seo_meta_box',
			__( 'Search Engine Visibility', 'noindex-seo' ),
			'noindex_seo_render_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'noindex_seo_add_meta_boxes' );

/**
 * Render the meta box content for per-post/page control.
 *
 * Displays a checkbox to override global settings and 5 directive checkboxes.
 * Shows current global settings as reference.
 *
 * @since 2.0.0
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function noindex_seo_render_meta_box( WP_Post $post ): void {
	// Add nonce for security.
	wp_nonce_field( 'noindex_seo_meta_box', 'noindex_seo_meta_box_nonce' );

	// Get current post meta values.
	$override     = get_post_meta( $post->ID, '_noindex_seo_override', true );
	$noindex      = get_post_meta( $post->ID, '_noindex_seo_noindex', true );
	$nofollow     = get_post_meta( $post->ID, '_noindex_seo_nofollow', true );
	$noarchive    = get_post_meta( $post->ID, '_noindex_seo_noarchive', true );
	$nosnippet    = get_post_meta( $post->ID, '_noindex_seo_nosnippet', true );
	$noimageindex = get_post_meta( $post->ID, '_noindex_seo_noimageindex', true );

	// Get global settings for reference.
	$global_directives = array();
	$directives        = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

	// Determine which context applies to this post type.
	$post_type = get_post_type( $post );
	$context   = ( 'page' === $post_type ) ? 'page' : 'single';

	foreach ( $directives as $directive ) {
		$option_key = $directive . '_seo_' . $context;
		if ( get_option( $option_key, 0 ) ) {
			$global_directives[] = $directive;
		}
	}

	?>
	<div class="noindex-seo-meta-box">
		<p style="margin-top: 0;">
			<label>
				<input type="checkbox" name="noindex_seo_override" value="1" <?php checked( 1, $override ); ?> id="noindex-seo-override-toggle">
				<strong><?php esc_html_e( 'Override global settings', 'noindex-seo' ); ?></strong>
			</label>
		</p>

		<p class="description" style="margin: 8px 0 12px 0; font-size: 12px; line-height: 1.4;">
			<?php esc_html_e( 'When enabled, these directives will override the global settings for this specific content.', 'noindex-seo' ); ?>
		</p>

		<div id="noindex-seo-directives-container" style="<?php echo $override ? '' : 'display: none;'; ?>">
			<div style="border-top: 1px solid #ddd; padding-top: 12px; margin-bottom: 12px;">
				<label style="display: flex; align-items: center; margin-bottom: 8px;">
					<input type="checkbox" name="noindex_seo_noindex" value="1" <?php checked( 1, $noindex ); ?> style="margin: 0 8px 0 0;">
					<span><strong>🔍 noindex</strong> — <?php esc_html_e( 'Prevent indexing', 'noindex-seo' ); ?></span>
				</label>

				<label style="display: flex; align-items: center; margin-bottom: 8px;">
					<input type="checkbox" name="noindex_seo_nofollow" value="1" <?php checked( 1, $nofollow ); ?> style="margin: 0 8px 0 0;">
					<span><strong>🔗 nofollow</strong> — <?php esc_html_e( 'Prevent link following', 'noindex-seo' ); ?></span>
				</label>

				<label style="display: flex; align-items: center; margin-bottom: 8px;">
					<input type="checkbox" name="noindex_seo_noarchive" value="1" <?php checked( 1, $noarchive ); ?> style="margin: 0 8px 0 0;">
					<span><strong>💾 noarchive</strong> — <?php esc_html_e( 'Prevent caching', 'noindex-seo' ); ?></span>
				</label>

				<label style="display: flex; align-items: center; margin-bottom: 8px;">
					<input type="checkbox" name="noindex_seo_nosnippet" value="1" <?php checked( 1, $nosnippet ); ?> style="margin: 0 8px 0 0;">
					<span><strong>📄 nosnippet</strong> — <?php esc_html_e( 'Prevent snippets', 'noindex-seo' ); ?></span>
				</label>

				<label style="display: flex; align-items: center; margin-bottom: 8px;">
					<input type="checkbox" name="noindex_seo_noimageindex" value="1" <?php checked( 1, $noimageindex ); ?> style="margin: 0 8px 0 0;">
					<span><strong>🖼️ noimageindex</strong> — <?php esc_html_e( 'Prevent image indexing', 'noindex-seo' ); ?></span>
				</label>
			</div>
		</div>

		<!-- Information Section -->
		<div style="margin-top: 12px; padding: 10px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px;">
			<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #333;">
				<?php esc_html_e( 'Effective Directives:', 'noindex-seo' ); ?>
			</p>

			<?php if ( $override ) : ?>
				<?php
				// Show what will be applied with override.
				$active_directives = array();
				if ( $noindex ) {
					$active_directives[] = 'noindex';
				}
				if ( $nofollow ) {
					$active_directives[] = 'nofollow';
				}
				if ( $noarchive ) {
					$active_directives[] = 'noarchive';
				}
				if ( $nosnippet ) {
					$active_directives[] = 'nosnippet';
				}
				if ( $noimageindex ) {
					$active_directives[] = 'noimageindex';
				}
				?>
				<?php if ( ! empty( $active_directives ) ) : ?>
					<p style="margin: 0 0 4px 0; padding: 6px; background: #e3f2fd; border-left: 3px solid #2196f3; font-size: 11px;">
						<strong style="color: #1976d2;"><?php esc_html_e( 'Override active:', 'noindex-seo' ); ?></strong><br>
						<code style="font-size: 10px;"><?php echo esc_html( implode( ', ', $active_directives ) ); ?></code>
					</p>
				<?php else : ?>
					<p style="margin: 0; padding: 6px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 11px; color: #856404;">
						<?php esc_html_e( 'Override enabled but no directives selected', 'noindex-seo' ); ?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( ! empty( $global_directives ) ) : ?>
					<p style="margin: 0; padding: 6px; background: #fff; border-left: 3px solid #9e9e9e; font-size: 11px;">
						<strong style="color: #666;"><?php esc_html_e( 'Global settings:', 'noindex-seo' ); ?></strong><br>
						<code style="font-size: 10px;"><?php echo esc_html( implode( ', ', $global_directives ) ); ?></code>
					</p>
				<?php else : ?>
					<p style="margin: 0; padding: 6px; background: #e8f5e9; border-left: 3px solid #4caf50; font-size: 11px; color: #2e7d32;">
						<?php esc_html_e( 'No restrictions (indexable)', 'noindex-seo' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<script type="text/javascript">
		(function() {
			var toggle = document.getElementById('noindex-seo-override-toggle');
			var container = document.getElementById('noindex-seo-directives-container');
			if (toggle && container) {
				toggle.addEventListener('change', function() {
					container.style.display = this.checked ? 'block' : 'none';
				});
			}
		})();
		</script>
	</div>
	<?php
}

/**
 * Persist robots override and directive post meta from the current $_POST data.
 *
 * Shared by the meta box save path and the Quick Edit save path.
 * Callers are responsible for nonce verification and capability checks
 * before invoking this helper.
 *
 * @since 2.0.1
 *
 * @param int $post_id The post ID to update.
 * @return void
 */
function noindex_seo_save_directives_from_post( int $post_id ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	// Nonce verification and capability checks are performed by the callers:
	// noindex_seo_save_post_meta() and noindex_seo_save_quick_edit().
	$override = isset( $_POST['noindex_seo_override'] ) ? 1 : 0;
	update_post_meta( $post_id, '_noindex_seo_override', $override );

	if ( $override ) {
		$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
		foreach ( $directives as $directive ) {
			$value = isset( $_POST[ 'noindex_seo_' . $directive ] ) ? 1 : 0;
			update_post_meta( $post_id, '_noindex_seo_' . $directive, $value );
		}
	} else {
		noindex_seo_clear_post_directives( $post_id );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}

/**
 * Save post meta when post is saved.
 *
 * Validates nonce, checks user permissions, and saves the override settings.
 * Only saves meta if override is enabled.
 *
 * @since 2.0.0
 *
 * @param int $post_id The post ID being saved.
 * @return void
 */
function noindex_seo_save_post_meta( int $post_id ): void {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Verify nonce. The is_string() ternary narrows the type from mixed to string
	// so wp_unslash() returns string and sanitize_text_field() receives string.
	if ( ! isset( $_POST['noindex_seo_meta_box_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( is_string( $_POST['noindex_seo_meta_box_nonce'] ) ? $_POST['noindex_seo_meta_box_nonce'] : '' ) ),
			'noindex_seo_meta_box'
		)
	) {
		return;
	}

	// Check if this is an autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check user permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	noindex_seo_save_directives_from_post( $post_id );
}
add_action( 'save_post', 'noindex_seo_save_post_meta' );

/**
 * Registers term meta for per-term granular control on every public taxonomy.
 *
 * Mirrors {@see noindex_seo_register_post_meta()} but for taxonomy terms.
 * Active only when the 'taxonomies_granular' config flag is on, so the meta
 * keys (and the REST exposure that goes with them) only exist when the
 * administrator has opted in to per-term control.
 *
 * Public taxonomies are auto-detected via {@see get_taxonomies()}. This covers
 * `category`, `post_tag`, and any custom public taxonomy registered by plugins
 * or themes (e.g. WooCommerce's `product_cat` / `product_tag`).
 *
 * Hooked to the {@see 'init'} action.
 *
 * @since 3.0.2
 *
 * @return void
 */
function noindex_seo_register_term_meta(): void {
	if ( ! noindex_seo_get_config( 'taxonomies_granular', 0 ) ) {
		return;
	}

	$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

	$meta_keys = array(
		'_noindex_seo_override',
		'_noindex_seo_noindex',
		'_noindex_seo_nofollow',
		'_noindex_seo_noarchive',
		'_noindex_seo_nosnippet',
		'_noindex_seo_noimageindex',
	);

	foreach ( $taxonomies as $taxonomy ) {
		foreach ( $meta_keys as $meta_key ) {
			register_term_meta(
				$taxonomy,
				$meta_key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'default'       => 0,
					'auth_callback' => static function () {
						return current_user_can( 'manage_categories' );
					},
				)
			);
		}

		// Render the form fields on the Edit Tag screen.
		add_action( "{$taxonomy}_edit_form_fields", 'noindex_seo_render_term_form_fields' );
		// Persist the values when the term is updated.
		add_action( "edited_{$taxonomy}", 'noindex_seo_save_term_meta', 10, 2 );
	}
}
add_action( 'init', 'noindex_seo_register_term_meta' );

/**
 * Renders the "Search Engine Visibility" panel on the Edit Term screen.
 *
 * Hooked to `{$taxonomy}_edit_form_fields` for every public taxonomy by
 * {@see noindex_seo_register_term_meta()}. Renders the same six controls as
 * the post meta box: one override checkbox and five directive checkboxes.
 *
 * @since 3.0.2
 *
 * @param WP_Term $term The term being edited.
 * @return void
 */
function noindex_seo_render_term_form_fields( WP_Term $term ): void {
	wp_nonce_field( 'noindex_seo_term_meta', 'noindex_seo_term_meta_nonce' );

	$override_raw = get_term_meta( $term->term_id, '_noindex_seo_override', true );
	$override     = ( is_numeric( $override_raw ) ? (int) $override_raw : 0 ) ? 1 : 0;
	$directives   = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	$values       = array();
	foreach ( $directives as $directive ) {
		$raw_value            = get_term_meta( $term->term_id, '_noindex_seo_' . $directive, true );
		$values[ $directive ] = ( is_numeric( $raw_value ) ? (int) $raw_value : 0 ) ? 1 : 0;
	}
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Search Engine Visibility', 'noindex-seo' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="noindex_seo_override" value="1" <?php checked( 1, $override ); ?> />
				<?php esc_html_e( 'Override global settings for this term', 'noindex-seo' ); ?>
			</label>

			<div class="noindex-seo-term-directives" style="margin-top: 8px; <?php echo $override ? '' : 'display:none;'; ?>">
				<?php foreach ( $directives as $directive ) : ?>
					<label style="display: inline-block; margin-right: 12px;">
						<input type="checkbox" name="noindex_seo_<?php echo esc_attr( $directive ); ?>" value="1" <?php checked( 1, $values[ $directive ] ); ?> />
						<?php echo esc_html( $directive ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<p class="description">
				<?php esc_html_e( 'When override is enabled, the directives checked above apply to this term\'s archive page and take precedence over the global category / tag / archive settings.', 'noindex-seo' ); ?>
			</p>
		</td>
	</tr>
	<script>
		// Toggle directive visibility based on override checkbox.
		(function () {
			var override = document.querySelector('input[name="noindex_seo_override"]');
			var group    = document.querySelector('.noindex-seo-term-directives');
			if (!override || !group) return;
			override.addEventListener('change', function () {
				group.style.display = override.checked ? '' : 'none';
			});
		})();
	</script>
	<?php
}

/**
 * Saves term meta for per-term granular control.
 *
 * Hooked to `edited_{$taxonomy}` for every public taxonomy by
 * {@see noindex_seo_register_term_meta()}. Verifies the nonce, checks the
 * user capability, then persists the override flag and — if override is on —
 * the five directive values. When override is off, all directive meta is
 * cleared so the term falls back to the global context settings.
 *
 * @since 3.0.2
 *
 * @param int $term_id  Term ID.
 * @param int $tt_id    Term taxonomy ID (unused; required by the edited_{$taxonomy} hook signature).
 * @return void
 */
function noindex_seo_save_term_meta( int $term_id, int $tt_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( ! noindex_seo_get_config( 'taxonomies_granular', 0 ) ) {
		return;
	}

	if ( ! isset( $_POST['noindex_seo_term_meta_nonce'] ) || ! is_string( $_POST['noindex_seo_term_meta_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['noindex_seo_term_meta_nonce'] ) ), 'noindex_seo_term_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing
	// Nonce verified above.
	$override = isset( $_POST['noindex_seo_override'] ) ? 1 : 0;
	update_term_meta( $term_id, '_noindex_seo_override', $override );

	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	if ( $override ) {
		foreach ( $directives as $directive ) {
			$value = isset( $_POST[ 'noindex_seo_' . $directive ] ) ? 1 : 0;
			update_term_meta( $term_id, '_noindex_seo_' . $directive, $value );
		}
	} else {
		foreach ( $directives as $directive ) {
			delete_term_meta( $term_id, '_noindex_seo_' . $directive );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}

/**
 * Add custom column to post/page list showing robots directives override status.
 *
 * Only adds column if granular control is enabled.
 *
 * @since 2.0.0
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string> Modified columns.
 */
function noindex_seo_add_custom_column( array $columns ): array {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return $columns;
	}

	// Insert column after title (or at the end if title doesn't exist).
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( 'title' === $key ) {
			$new_columns['noindex_seo_directives'] = __( 'Robots', 'noindex-seo' );
		}
	}

	// If title column doesn't exist, add at the end.
	if ( ! isset( $new_columns['noindex_seo_directives'] ) ) {
		$new_columns['noindex_seo_directives'] = __( 'Robots', 'noindex-seo' );
	}

	return $new_columns;
}

/**
 * Display content of custom robots directives column.
 *
 * @since 2.0.0
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 * @return void
 */
function noindex_seo_display_custom_column( string $column, int $post_id ): void {
	if ( 'noindex_seo_directives' !== $column ) {
		return;
	}

	$override = get_post_meta( $post_id, '_noindex_seo_override', true );

	// Collect directive values for Quick Edit.
	$meta_noindex      = get_post_meta( $post_id, '_noindex_seo_noindex', true );
	$meta_nofollow     = get_post_meta( $post_id, '_noindex_seo_nofollow', true );
	$meta_noarchive    = get_post_meta( $post_id, '_noindex_seo_noarchive', true );
	$meta_nosnippet    = get_post_meta( $post_id, '_noindex_seo_nosnippet', true );
	$meta_noimageindex = get_post_meta( $post_id, '_noindex_seo_noimageindex', true );

	$directives_values = array(
		'override'     => ( is_scalar( $override ) ? absint( $override ) : 0 ),
		'noindex'      => ( is_scalar( $meta_noindex ) ? absint( $meta_noindex ) : 0 ),
		'nofollow'     => ( is_scalar( $meta_nofollow ) ? absint( $meta_nofollow ) : 0 ),
		'noarchive'    => ( is_scalar( $meta_noarchive ) ? absint( $meta_noarchive ) : 0 ),
		'nosnippet'    => ( is_scalar( $meta_nosnippet ) ? absint( $meta_nosnippet ) : 0 ),
		'noimageindex' => ( is_scalar( $meta_noimageindex ) ? absint( $meta_noimageindex ) : 0 ),
	);

	// Output hidden data for Quick Edit to read.
	echo '<div class="noindex-seo-override-data hidden" ';
	foreach ( $directives_values as $key => $value ) {
		echo 'data-' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '" ';
	}
	echo '></div>';

	if ( ! $override ) {
		echo '<span style="color: #999;">—</span>';
		return;
	}

	// Collect active directives for display.
	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	$active     = array();

	foreach ( $directives as $directive ) {
		if ( 1 === $directives_values[ $directive ] ) {
			$active[] = $directive;
		}
	}

	if ( empty( $active ) ) {
		echo '<span style="color: #999;">' . esc_html__( 'Override (none)', 'noindex-seo' ) . '</span>';
		return;
	}

	// Display active directives as badges.
	echo '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
	foreach ( $active as $directive ) {
		$emoji = '';
		switch ( $directive ) {
			case 'noindex':
				$emoji = '🔍';
				break;
			case 'nofollow':
				$emoji = '🔗';
				break;
			case 'noarchive':
				$emoji = '💾';
				break;
			case 'nosnippet':
				$emoji = '📄';
				break;
			case 'noimageindex':
				$emoji = '🖼️';
				break;
		}
		echo '<span style="display: inline-block; padding: 2px 6px; background: #eff6ff; border: 1px solid #667eea; border-radius: 3px; font-size: 11px; line-height: 1.2;">';
		echo esc_html( $emoji . ' ' . $directive );
		echo '</span>';
	}
	echo '</div>';
}

// Register column hooks after all CPTs are registered (admin_init fires after init).
add_action(
	'admin_init',
	function (): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", 'noindex_seo_add_custom_column' );
			add_action( "manage_{$post_type}_posts_custom_column", 'noindex_seo_display_custom_column', 10, 2 );
		}

		// Mirror the Robots column on taxonomy list tables when per-term
		// granular control is on, so admins can see at a glance which terms
		// have an override the same way they can for posts.
		if ( noindex_seo_get_config( 'taxonomies_granular', 0 ) ) {
			$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
			foreach ( $taxonomies as $taxonomy ) {
				add_filter( "manage_edit-{$taxonomy}_columns", 'noindex_seo_add_term_custom_column' );
				add_filter( "manage_{$taxonomy}_custom_column", 'noindex_seo_display_term_custom_column', 10, 3 );
			}
		}
	}
);

/**
 * Adds the "Robots" column to taxonomy list tables.
 *
 * Term-meta counterpart of {@see noindex_seo_add_custom_column()}.
 *
 * @since 3.0.2
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string> Modified columns.
 */
function noindex_seo_add_term_custom_column( array $columns ): array {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		// Insert after the name column (the first one on term list tables).
		if ( 'name' === $key ) {
			$new_columns['noindex_seo_directives'] = __( 'Robots', 'noindex-seo' );
		}
	}

	// Fallback: add at the end if the name column wasn't found.
	if ( ! isset( $new_columns['noindex_seo_directives'] ) ) {
		$new_columns['noindex_seo_directives'] = __( 'Robots', 'noindex-seo' );
	}

	return $new_columns;
}

/**
 * Displays the Robots column content on taxonomy list tables.
 *
 * Renders the same emoji + directive badges as the post column so the UX is
 * consistent across post-type and taxonomy list tables. Shows an em dash when
 * the term has no override.
 *
 * Term-meta counterpart of {@see noindex_seo_display_custom_column()}.
 *
 * @since 3.0.2
 *
 * @param string $content    Existing column content (filter hook signature).
 * @param string $column     Column name.
 * @param int    $term_id    Term ID.
 * @return string Filtered content.
 */
function noindex_seo_display_term_custom_column( string $content, string $column, int $term_id ): string {
	if ( 'noindex_seo_directives' !== $column ) {
		return $content;
	}

	$override_raw = get_term_meta( $term_id, '_noindex_seo_override', true );
	$override     = ( is_scalar( $override_raw ) && 1 === absint( $override_raw ) ) ? 1 : 0;

	if ( ! $override ) {
		return '<span style="color: #999;">—</span>';
	}

	$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
	$active     = array();
	foreach ( $directives as $directive ) {
		$value = get_term_meta( $term_id, '_noindex_seo_' . $directive, true );
		if ( is_scalar( $value ) && 1 === absint( $value ) ) {
			$active[] = $directive;
		}
	}

	if ( empty( $active ) ) {
		return '<span style="color: #999;">' . esc_html__( 'Override (none)', 'noindex-seo' ) . '</span>';
	}

	$emojis = array(
		'noindex'      => '🔍',
		'nofollow'     => '🔗',
		'noarchive'    => '💾',
		'nosnippet'    => '📄',
		'noimageindex' => '🖼️',
	);

	$out = '<div style="display: flex; flex-wrap: wrap; gap: 4px;">';
	foreach ( $active as $directive ) {
		$out .= '<span style="display: inline-block; padding: 2px 6px; background: #eff6ff; border: 1px solid #667eea; border-radius: 3px; font-size: 11px; line-height: 1.2;">';
		$out .= esc_html( $emojis[ $directive ] . ' ' . $directive );
		$out .= '</span>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * Add Quick Edit fields for robots directives.
 *
 * Displays the same directive checkboxes in Quick Edit interface.
 *
 * @since 2.0.0
 *
 * @param string $column_name Column name.
 * @param string $post_type   Post type (unused but required by hook).
 * @return void
 */
function noindex_seo_quick_edit_fields( string $column_name, string $post_type ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	if ( 'noindex_seo_directives' !== $column_name ) {
		return;
	}

	// Add nonce field.
	wp_nonce_field( 'noindex_seo_quick_edit', 'noindex_seo_quick_edit_nonce' );
	?>
	<fieldset class="inline-edit-col-right">
		<div class="inline-edit-col">
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'Robots Directives', 'noindex-seo' ); ?></span>
				<div style="padding: 5px 0;">
					<label style="display: block; margin-bottom: 6px;">
						<input type="checkbox" name="noindex_seo_override" value="1" id="noindex-seo-quick-edit-override">
						<strong><?php esc_html_e( 'Override global settings', 'noindex-seo' ); ?></strong>
					</label>
					<div id="noindex-seo-quick-edit-directives" style="margin-left: 20px; margin-top: 8px; display: none;">
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="noindex_seo_noindex" value="1">
							<?php esc_html_e( '🔍 noindex', 'noindex-seo' ); ?>
						</label>
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="noindex_seo_nofollow" value="1">
							<?php esc_html_e( '🔗 nofollow', 'noindex-seo' ); ?>
						</label>
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="noindex_seo_noarchive" value="1">
							<?php esc_html_e( '💾 noarchive', 'noindex-seo' ); ?>
						</label>
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="noindex_seo_nosnippet" value="1">
							<?php esc_html_e( '📄 nosnippet', 'noindex-seo' ); ?>
						</label>
						<label style="display: block; margin-bottom: 4px;">
							<input type="checkbox" name="noindex_seo_noimageindex" value="1">
							<?php esc_html_e( '🖼️ noimageindex', 'noindex-seo' ); ?>
						</label>
					</div>
				</div>
			</label>
		</div>
	</fieldset>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Toggle directives visibility when override checkbox changes
		$('#noindex-seo-quick-edit-override').on('change', function() {
			if ($(this).is(':checked')) {
				$('#noindex-seo-quick-edit-directives').show();
			} else {
				$('#noindex-seo-quick-edit-directives').hide();
			}
		});

		// Populate Quick Edit fields when "Edit" is clicked
		$('#the-list').on('click', '.editinline', function() {
			var post_id = $(this).closest('tr').attr('id').replace('post-', '');
			var $row = $('#post-' + post_id);

			// Get current values from the column (we'll use data attributes)
			var override = $row.find('.noindex-seo-override-data').data('override');
			var noindex = $row.find('.noindex-seo-override-data').data('noindex');
			var nofollow = $row.find('.noindex-seo-override-data').data('nofollow');
			var noarchive = $row.find('.noindex-seo-override-data').data('noarchive');
			var nosnippet = $row.find('.noindex-seo-override-data').data('nosnippet');
			var noimageindex = $row.find('.noindex-seo-override-data').data('noimageindex');

			// Set checkbox values
			$('#noindex-seo-quick-edit-override').prop('checked', override == 1);
			$('input[name="noindex_seo_noindex"]').prop('checked', noindex == 1);
			$('input[name="noindex_seo_nofollow"]').prop('checked', nofollow == 1);
			$('input[name="noindex_seo_noarchive"]').prop('checked', noarchive == 1);
			$('input[name="noindex_seo_nosnippet"]').prop('checked', nosnippet == 1);
			$('input[name="noindex_seo_noimageindex"]').prop('checked', noimageindex == 1);

			// Show/hide directives based on override
			if (override == 1) {
				$('#noindex-seo-quick-edit-directives').show();
			} else {
				$('#noindex-seo-quick-edit-directives').hide();
			}
		});
	});
	</script>
	<?php
}
add_action( 'quick_edit_custom_box', 'noindex_seo_quick_edit_fields', 10, 2 );
add_action( 'bulk_edit_custom_box', 'noindex_seo_quick_edit_fields', 10, 2 );

/**
 * Save Quick Edit data for robots directives.
 *
 * @since 2.0.0
 *
 * @param int $post_id Post ID being saved.
 * @return void
 */
function noindex_seo_save_quick_edit( int $post_id ): void {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Verify nonce. The is_string() ternary narrows the type from mixed to string
	// so wp_unslash() returns string and sanitize_text_field() receives string.
	if ( ! isset( $_POST['noindex_seo_quick_edit_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( is_string( $_POST['noindex_seo_quick_edit_nonce'] ) ? $_POST['noindex_seo_quick_edit_nonce'] : '' ) ),
			'noindex_seo_quick_edit'
		)
	) {
		return;
	}

	// Check user permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Check if we're in Quick Edit (not regular post save).
	if ( ! isset( $_POST['_inline_edit'] ) ) {
		return;
	}

	noindex_seo_save_directives_from_post( $post_id );
}
add_action( 'save_post', 'noindex_seo_save_quick_edit' );

/**
 * Register custom bulk actions for robots directives.
 *
 * Adds "Enable Override" and "Disable Override" bulk actions.
 *
 * @since 2.0.0
 *
 * @param array<string, string> $bulk_actions Existing bulk actions.
 * @return array<string, string> Modified bulk actions.
 */
function noindex_seo_register_bulk_actions( array $bulk_actions ): array {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return $bulk_actions;
	}

	$bulk_actions['noindex_seo_enable_override']  = __( 'Enable Robots Override', 'noindex-seo' );
	$bulk_actions['noindex_seo_disable_override'] = __( 'Disable Robots Override', 'noindex-seo' );

	return $bulk_actions;
}

/**
 * Handle custom bulk actions for robots directives.
 *
 * @since 2.0.0
 *
 * @param string             $redirect_to Redirect URL.
 * @param string             $action      Action being taken.
 * @param array<int, string> $post_ids    Array of post IDs.
 * @return string Modified redirect URL.
 */
function noindex_seo_handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return $redirect_to;
	}

	// Validate that we have post IDs to process.
	if ( empty( $post_ids ) ) {
		return $redirect_to;
	}

	// Check user permissions - user must be able to edit posts.
	if ( ! current_user_can( 'edit_posts' ) ) {
		return $redirect_to;
	}

	// Filter post IDs to only include ones the current user can edit.
	$editable_post_ids = array();
	foreach ( $post_ids as $post_id ) {
		$post_id = intval( $post_id );
		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			$editable_post_ids[] = $post_id;
		}
	}

	// If no editable posts remain after filtering, return early.
	if ( empty( $editable_post_ids ) ) {
		return $redirect_to;
	}

	// Replace original post IDs with filtered list.
	$post_ids = $editable_post_ids;

	if ( 'noindex_seo_enable_override' === $action ) {
		// Use update_post_meta() — the WP postmeta table has no unique key on
		// (post_id, meta_key), so a raw INSERT ... ON DUPLICATE KEY UPDATE would
		// silently create duplicate rows on every bulk run. update_post_meta()
		// correctly handles the single-value semantics enforced by registered meta.
		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_noindex_seo_override', 1 );
		}

		$redirect_to = add_query_arg( 'noindex_seo_bulk_enabled', count( $post_ids ), $redirect_to );
	}

	if ( 'noindex_seo_disable_override' === $action ) {
		// Remove the override flag entirely and clear any directive meta so the
		// post falls back to the global context settings.
		$directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

		foreach ( $post_ids as $post_id ) {
			delete_post_meta( $post_id, '_noindex_seo_override' );

			foreach ( $directives as $directive ) {
				delete_post_meta( $post_id, '_noindex_seo_' . $directive );
			}
		}

		$redirect_to = add_query_arg( 'noindex_seo_bulk_disabled', count( $post_ids ), $redirect_to );
	}

	return $redirect_to;
}

/**
 * Display admin notice after bulk actions.
 *
 * @since 2.0.0
 *
 * @return void
 */
function noindex_seo_bulk_actions_admin_notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter from redirect after bulk action, not form data.
	if ( ! empty( $_REQUEST['noindex_seo_bulk_enabled'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter from redirect after bulk action, not form data.
		$count = is_scalar( $_REQUEST['noindex_seo_bulk_enabled'] ) ? absint( $_REQUEST['noindex_seo_bulk_enabled'] ) : 0;
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: Number of posts updated */
					_n(
						'Robots override enabled for %d post.',
						'Robots override enabled for %d posts.',
						$count,
						'noindex-seo'
					),
					$count
				)
			)
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter from redirect after bulk action, not form data.
	if ( ! empty( $_REQUEST['noindex_seo_bulk_disabled'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter from redirect after bulk action, not form data.
		$count = is_scalar( $_REQUEST['noindex_seo_bulk_disabled'] ) ? absint( $_REQUEST['noindex_seo_bulk_disabled'] ) : 0;
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: Number of posts updated */
					_n(
						'Robots override disabled for %d post.',
						'Robots override disabled for %d posts.',
						$count,
						'noindex-seo'
					),
					$count
				)
			)
		);
	}
}
add_action( 'admin_notices', 'noindex_seo_bulk_actions_admin_notice' );

// Register bulk action hooks after all CPTs are registered.
add_action(
	'admin_init',
	function (): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", 'noindex_seo_register_bulk_actions' );
			add_filter( "handle_bulk_actions-edit-{$post_type}", 'noindex_seo_handle_bulk_actions', 10, 3 );
		}
	}
);

/**
 * Add filter dropdown to post list for robots override status.
 *
 * Allows filtering posts by override status: all, with override, without override.
 *
 * @since 2.0.0
 *
 * @param string $post_type Current post type (unused but required by hook).
 * @return void
 */
function noindex_seo_add_list_filter( string $post_type ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Get current filter value from URL parameters (not a form submission, no nonce needed).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for filtering, not form data.
	$current_filter = ( isset( $_GET['noindex_seo_filter'] ) && is_string( $_GET['noindex_seo_filter'] ) ) ? sanitize_text_field( wp_unslash( $_GET['noindex_seo_filter'] ) ) : '';

	?>
	<select name="noindex_seo_filter">
		<option value=""><?php esc_html_e( 'All robots settings', 'noindex-seo' ); ?></option>
		<option value="with_override" <?php selected( $current_filter, 'with_override' ); ?>>
			<?php esc_html_e( 'With override', 'noindex-seo' ); ?>
		</option>
		<option value="without_override" <?php selected( $current_filter, 'without_override' ); ?>>
			<?php esc_html_e( 'Without override', 'noindex-seo' ); ?>
		</option>
	</select>
	<?php
}

/**
 * Filter posts query by robots override status.
 *
 * Modifies the query to show only posts with or without override
 * based on the selected filter.
 *
 * @since 2.0.0
 *
 * @param WP_Query $query Current query object.
 * @return void
 */
function noindex_seo_filter_posts_by_override( WP_Query $query ): void {
	// Only in admin list view.
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Check if granular control is enabled.
	$granular_enabled = noindex_seo_get_config( 'granular', 0 );
	if ( ! $granular_enabled ) {
		return;
	}

	// Check if filter is set (URL parameter, not form submission, no nonce needed).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for filtering, not form data.
	if ( ! isset( $_GET['noindex_seo_filter'] ) || empty( $_GET['noindex_seo_filter'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter for filtering, not form data.
	$filter = is_string( $_GET['noindex_seo_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['noindex_seo_filter'] ) ) : '';

	// Validate against whitelist of allowed filter values.
	$valid_filters = array( 'with_override', 'without_override' );
	if ( ! in_array( $filter, $valid_filters, true ) ) {
		return; // Invalid filter value, ignore.
	}

	// Build meta query.
	$meta_query = array();

	if ( 'with_override' === $filter ) {
		$meta_query = array(
			array(
				'key'     => '_noindex_seo_override',
				'value'   => '1',
				'compare' => '=',
			),
		);
	} elseif ( 'without_override' === $filter ) {
		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => '_noindex_seo_override',
				'value'   => '1',
				'compare' => '!=',
			),
			array(
				'key'     => '_noindex_seo_override',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'noindex_seo_filter_posts_by_override' );

// Register filter dropdown after all CPTs are registered.
add_action(
	'admin_init',
	function (): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_action(
				'restrict_manage_posts',
				function () use ( $post_type ): void {
					global $typenow;
					if ( $typenow === $post_type ) {
						noindex_seo_add_list_filter( $post_type );
					}
				}
			);
		}
	}
);

/**
 * Renders the modern, visual settings page for the 'noindex SEO' plugin.
 *
 * This function outputs a completely redesigned admin interface with:
 * - Modern card-based layout
 * - Toggle switches instead of checkboxes
 * - Tabbed navigation for better organization
 * - Visual indicators and badges
 * - Collapsible sections
 * - Search/filter functionality
 * - Statistics dashboard
 *
 * @since 1.0.0
 * @since 2.0.0 Completely redesigned with modern UI/UX.
 *
 * @return void
 */
function noindex_seo_admin(): void {
	// Verify user capabilities for defense in depth..
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'You do not have sufficient permissions to access this page.', 'noindex-seo' ),
			esc_html__( 'Permission Denied', 'noindex-seo' ),
			array( 'response' => 403 )
		);
	}

	// Define section icons (using Dashicons)..
	$section_icons = array(
		'main_pages'  => 'dashicons-admin-home',
		'pages_posts' => 'dashicons-admin-page',
		'taxonomies'  => 'dashicons-category',
		'dates'       => 'dashicons-calendar-alt',
		'archives'    => 'dashicons-archive',
		'pagination'  => 'dashicons-ellipsis',
		'search'      => 'dashicons-search',
		'attachments' => 'dashicons-paperclip',
		'previews'    => 'dashicons-visibility',
		'error_page'  => 'dashicons-warning',
	);

	// Define sections and their respective settings..
	$sections = array(
		'main_pages'  => array(
			'title'  => __( 'Main Pages', 'noindex-seo' ),
			'fields' => array(
				'front_page' => array(
					'label'       => __( 'Front Page', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of the site\'s front page.', 'noindex-seo' ),
					'view_url'    => get_site_url(),
				),
				'home'       => array(
					'label'       => __( 'Home', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of the site\'s home page.', 'noindex-seo' ),
					'view_url'    => get_home_url(),
				),
			),
		),
		'pages_posts' => array(
			'title'  => __( 'Pages and Posts', 'noindex-seo' ),
			'fields' => array(
				'page'           => array(
					'label'       => __( 'Page', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of site pages.', 'noindex-seo' ),
				),
				'privacy_policy' => array(
					'label'       => __( 'Privacy Policy', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of the privacy policy page.', 'noindex-seo' ),
					'view_url'    => get_privacy_policy_url(),
				),
				'single'         => array(
					'label'       => __( 'Single Post', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of individual posts.', 'noindex-seo' ),
				),
				'singular'       => array(
					'label'       => __( 'Singular', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of any singular content (post or page).', 'noindex-seo' ),
				),
			),
		),
		'taxonomies'  => array(
			'title'  => __( 'Taxonomies', 'noindex-seo' ),
			'fields' => array(
				'category' => array(
					'label'       => __( 'Category', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of category archive pages.', 'noindex-seo' ),
				),
				'tag'      => array(
					'label'       => __( 'Tag', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of tag archive pages.', 'noindex-seo' ),
				),
			),
		),
		'dates'       => array(
			'title'  => __( 'Date Archives', 'noindex-seo' ),
			'fields' => array(
				'date'  => array(
					'label'       => __( 'Date', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of any date-based archive page.', 'noindex-seo' ),
				),
				'day'   => array(
					'label'       => __( 'Day', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of daily archive pages.', 'noindex-seo' ),
				),
				'month' => array(
					'label'       => __( 'Month', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of monthly archive pages.', 'noindex-seo' ),
				),
				'time'  => array(
					'label'       => __( 'Time', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of time-based archive pages.', 'noindex-seo' ),
				),
				'year'  => array(
					'label'       => __( 'Year', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of yearly archive pages.', 'noindex-seo' ),
				),
			),
		),
		'archives'    => array(
			'title'  => __( 'Archives', 'noindex-seo' ),
			'fields' => array(
				'archive'           => array(
					'label'       => __( 'Archive', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of any type of archive page.', 'noindex-seo' ),
				),
				'author'            => array(
					'label'       => __( 'Author', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of author archive pages.', 'noindex-seo' ),
				),
				'post_type_archive' => array(
					'label'       => __( 'Post Type Archive', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => false,
					'description' => __( 'Block the indexing of post type archive pages.', 'noindex-seo' ),
				),
			),
		),
		'pagination'  => array(
			'title'  => __( 'Pagination', 'noindex-seo' ),
			'fields' => array(
				'paged' => array(
					'label'       => __( 'Paginated Pages', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of pagination pages (page 2, 3, etc.).', 'noindex-seo' ),
				),
			),
		),
		'search'      => array(
			'title'  => __( 'Search', 'noindex-seo' ),
			'fields' => array(
				'search' => array(
					'label'       => __( 'Search Results', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of search result pages.', 'noindex-seo' ),
				),
			),
		),
		'attachments' => array(
			'title'  => __( 'Attachments', 'noindex-seo' ),
			'fields' => array(
				'attachment' => array(
					'label'       => __( 'Attachment Pages', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of attachment pages (does not affect the file itself).', 'noindex-seo' ),
				),
			),
		),
		'previews'    => array(
			'title'  => __( 'Previews', 'noindex-seo' ),
			'fields' => array(
				'customize_preview' => array(
					'label'       => __( 'Customize Preview', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing when content is in customize preview mode.', 'noindex-seo' ),
				),
				'preview'           => array(
					'label'       => __( 'Post Preview', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing when viewing post previews.', 'noindex-seo' ),
				),
			),
		),
		'error_page'  => array(
			'title'  => __( 'Error Pages', 'noindex-seo' ),
			'fields' => array(
				'error' => array(
					'label'       => __( 'Error 404', 'noindex-seo' ),
					'recommended' => __( 'Recommended', 'noindex-seo' ),
					'suggestion'  => true,
					'description' => __( 'Block the indexing of 404 error pages.', 'noindex-seo' ),
				),
			),
		),
	);

	// Append a section listing every public custom post type with its own
	// directive grid. Each CPT becomes a row stored under the `cpt_{post_type}`
	// context. Built-in post / page / attachment are skipped (they have their
	// own dedicated contexts: single, page, attachment).
	$public_cpts = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects'
	);
	if ( ! empty( $public_cpts ) ) {
		$cpt_fields = array();
		foreach ( $public_cpts as $cpt ) {
			$cpt_fields[ 'cpt_' . $cpt->name ] = array(
				'label'       => $cpt->labels->name ?? $cpt->name,
				'recommended' => __( 'Recommended', 'noindex-seo' ),
				'suggestion'  => false,
				/* translators: %s: post type singular name. */
				'description' => sprintf( __( 'Block the indexing of individual %s.', 'noindex-seo' ), $cpt->labels->name ?? $cpt->name ),
			);
		}
		$sections['custom_post_types']      = array(
			'title'  => __( 'Custom Post Types', 'noindex-seo' ),
			'fields' => $cpt_fields,
		);
		$section_icons['custom_post_types'] = 'dashicons-admin-post';
	}

	// Get config options with explicit type narrowing.
	$opt_seoplugins                    = noindex_seo_get_config( 'seoplugins', 0 );
	$option_config_seoplugins          = is_scalar( $opt_seoplugins ) ? absint( $opt_seoplugins ) : 0;
	$opt_method                        = noindex_seo_get_config( 'method', 'meta' );
	$option_config_method              = is_string( $opt_method ) ? $opt_method : 'meta';
	$opt_granular                      = noindex_seo_get_config( 'granular', 0 );
	$option_config_granular            = is_scalar( $opt_granular ) ? absint( $opt_granular ) : 0;
	$opt_tax_granular                  = noindex_seo_get_config( 'taxonomies_granular', 0 );
	$option_config_taxonomies_granular = is_scalar( $opt_tax_granular ) ? absint( $opt_tax_granular ) : 0;
	$opt_delete                        = noindex_seo_get_config( 'delete_on_uninstall', 0 );
	$option_config_delete_on_uninstall = is_scalar( $opt_delete ) ? absint( $opt_delete ) : 0;

	// Define fields that only work with HTTP headers (non-HTML content).
	$header_only_fields = array( 'attachment' );
	$is_header_enabled  = in_array( $option_config_method, array( 'header', 'both' ), true );

	// Define available directives with config.
	$directives_config = array(
		'noindex'      => array(
			'label' => __( 'noindex', 'noindex-seo' ),
			'desc'  => __( 'Prevent search engines from indexing this page', 'noindex-seo' ),
			'icon'  => '🔍',
		),
		'nofollow'     => array(
			'label' => __( 'nofollow', 'noindex-seo' ),
			'desc'  => __( 'Prevent search engines from following links on this page', 'noindex-seo' ),
			'icon'  => '🔗',
		),
		'noarchive'    => array(
			'label' => __( 'noarchive', 'noindex-seo' ),
			'desc'  => __( 'Prevent search engines from showing a cached version', 'noindex-seo' ),
			'icon'  => '💾',
		),
		'nosnippet'    => array(
			'label' => __( 'nosnippet', 'noindex-seo' ),
			'desc'  => __( 'Prevent search engines from showing text snippets', 'noindex-seo' ),
			'icon'  => '📄',
		),
		'noimageindex' => array(
			'label' => __( 'noimageindex', 'noindex-seo' ),
			'desc'  => __( 'Prevent search engines from indexing images', 'noindex-seo' ),
			'icon'  => '🖼️',
		),
	);
	?>

	<div class="wrap noindex-seo-admin-wrap">
		<h1><?php esc_html_e( 'noindex SEO Settings', 'noindex-seo' ); ?></h1>
		<p><?php esc_html_e( 'Control how search engines index and display your WordPress content using robots directives.', 'noindex-seo' ); ?></p>

		<?php
		// Success notices for one-click actions. The form handlers redirect back
		// to this page with a query flag so the notice survives the redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter set by our own admin-post handler after a nonce check, not user form data.
		if ( isset( $_GET['suggestions_applied'] ) && is_string( $_GET['suggestions_applied'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own admin-post handler after a nonce check.
			$suggestions_applied = sanitize_key( wp_unslash( $_GET['suggestions_applied'] ) );
			if ( '1' === $suggestions_applied ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				esc_html_e( 'Recommended defaults applied. Review the changes below and click "Save Changes" to keep them, or uncheck anything you do not want.', 'noindex-seo' );
				echo '</p></div>';
			}
		}
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="update_noindex_seo">
			<?php wp_nonce_field( 'update_noindex_seo_nonce' ); ?>

			<!-- Statistics Dashboard -->
			<div class="noindex-seo-stats">
				<div class="noindex-seo-stat-card">
					<div class="noindex-seo-stat-number" id="noindex-seo-stat-total">0</div>
					<div class="noindex-seo-stat-label"><?php esc_html_e( 'Total Options', 'noindex-seo' ); ?></div>
				</div>
				<div class="noindex-seo-stat-card">
					<div class="noindex-seo-stat-number" id="noindex-seo-stat-enabled">0</div>
					<div class="noindex-seo-stat-label"><?php esc_html_e( 'Enabled', 'noindex-seo' ); ?></div>
				</div>
				<div class="noindex-seo-stat-card">
					<div class="noindex-seo-stat-number" id="noindex-seo-stat-recommended">0</div>
					<div class="noindex-seo-stat-label"><?php esc_html_e( 'Recommended to Enable', 'noindex-seo' ); ?></div>
				</div>
			</div>

			<!-- General Configuration -->
			<div class="noindex-seo-general-config">
				<h2>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'General Configuration', 'noindex-seo' ); ?>
				</h2>
				<div class="noindex-seo-config-option">
					<label class="noindex-seo-switch">
						<input
							type="checkbox"
							id="noindex_seo_config_seoplugins"
							name="noindex_seo_config_seoplugins"
							value="1"
							<?php checked( 1, $option_config_seoplugins ); ?>
						>
						<span class="noindex-seo-slider"></span>
					</label>
					<label for="noindex_seo_config_seoplugins">
						<?php esc_html_e( 'Disable compatibility warnings with other SEO plugins', 'noindex-seo' ); ?>
					</label>
				</div>

				<div class="noindex-seo-config-option" style="margin-top: 20px;">
					<div style="flex: 1;">
						<label for="noindex_seo_config_method" style="display: block; font-weight: 600; margin-bottom: 8px; color: #92400e;">
							<?php esc_html_e( 'Implementation Method', 'noindex-seo' ); ?>
						</label>
						<select
							id="noindex_seo_config_method"
							name="noindex_seo_config_method"
							style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #fcd34d; border-radius: 4px; background: #fff; color: #78350f;"
						>
							<option value="meta" <?php selected( $option_config_method, 'meta' ); ?>>
								<?php esc_html_e( 'HTML Meta Tags (default, easier to verify)', 'noindex-seo' ); ?>
							</option>
							<option value="header" <?php selected( $option_config_method, 'header' ); ?>>
								<?php esc_html_e( 'HTTP Headers (more robust, works with PDFs/images)', 'noindex-seo' ); ?>
							</option>
							<option value="both" <?php selected( $option_config_method, 'both' ); ?>>
								<?php esc_html_e( 'Both (maximum compatibility)', 'noindex-seo' ); ?>
							</option>
						</select>
						<p style="margin: 8px 0 0 0; font-size: 13px; color: #92400e; line-height: 1.5;">
							<?php esc_html_e( 'Choose how noindex directives are sent to search engines. Meta tags work for HTML pages. HTTP headers work for all content types including PDFs, images, and feeds.', 'noindex-seo' ); ?>
						</p>
					</div>
				</div>

				<div class="noindex-seo-config-option" style="margin-top: 20px;">
					<label class="noindex-seo-switch">
						<input
							type="checkbox"
							id="noindex_seo_config_granular"
							name="noindex_seo_config_granular"
							value="1"
							<?php checked( 1, $option_config_granular ); ?>
						>
						<span class="noindex-seo-slider"></span>
					</label>
					<div style="flex: 1;">
						<label for="noindex_seo_config_granular" style="display: block; font-weight: 600; color: #1e40af;">
							<?php esc_html_e( 'Enable per-post/page granular control', 'noindex-seo' ); ?>
						</label>
						<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; line-height: 1.5;">
							<?php esc_html_e( 'When enabled, a meta box will appear in the post/page editor allowing you to override global settings for individual content. Useful for specific pages that need different robots directives.', 'noindex-seo' ); ?>
						</p>
					</div>
				</div>

				<div class="noindex-seo-config-option" style="margin-top: 20px;">
					<label class="noindex-seo-switch">
						<input
							type="checkbox"
							id="noindex_seo_config_taxonomies_granular"
							name="noindex_seo_config_taxonomies_granular"
							value="1"
							<?php checked( 1, $option_config_taxonomies_granular ); ?>
						>
						<span class="noindex-seo-slider"></span>
					</label>
					<div style="flex: 1;">
						<label for="noindex_seo_config_taxonomies_granular" style="display: block; font-weight: 600; color: #1e40af;">
							<?php esc_html_e( 'Enable per-term granular control', 'noindex-seo' ); ?>
						</label>
						<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; line-height: 1.5;">
							<?php esc_html_e( 'When enabled, a "Search Engine Visibility" panel will appear on the Edit screen for every public taxonomy (categories, tags, and custom taxonomies like WooCommerce product categories). Useful for noindexing a specific category or tag without affecting the others.', 'noindex-seo' ); ?>
						</p>
					</div>
				</div>

				<div class="noindex-seo-config-option" style="margin-top: 20px;">
					<label class="noindex-seo-switch">
						<input
							type="checkbox"
							id="noindex_seo_config_delete_on_uninstall"
							name="noindex_seo_config_delete_on_uninstall"
							value="1"
							<?php checked( 1, $option_config_delete_on_uninstall ); ?>
						>
						<span class="noindex-seo-slider"></span>
					</label>
					<div style="flex: 1;">
						<label for="noindex_seo_config_delete_on_uninstall" style="display: block; font-weight: 600; color: #b91c1c;">
							<?php esc_html_e( 'Delete all plugin data on uninstall', 'noindex-seo' ); ?>
						</label>
						<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; line-height: 1.5;">
							<?php esc_html_e( 'When enabled, all settings and per-post robots directives will be permanently deleted when the plugin is uninstalled. By default data is preserved.', 'noindex-seo' ); ?>
						</p>
					</div>
				</div>
			</div>

			<!-- Alert -->
			<div class="noindex-seo-alert">
				<span class="dashicons dashicons-warning"></span>
				<p><?php esc_html_e( 'Important: Enabling noindex on the wrong pages can harm your search engine rankings. Only enable options you fully understand.', 'noindex-seo' ); ?></p>
			</div>

		<!-- "Apply Recommended Defaults" button — sits between the stat cards
			and the option-search input. Separate form so it does not collide
			with the main settings form's nonce/action. -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0 0 20px 0;">
			<input type="hidden" name="action" value="noindex_seo_apply_suggestions">
			<?php wp_nonce_field( 'noindex_seo_apply_suggestions_nonce' ); ?>
			<?php
			submit_button(
				__( 'Apply Recommended Defaults', 'noindex-seo' ),
				'secondary',
				'noindex-seo-apply-suggestions',
				false,
				array(
					'title' => __( 'Enable all directives for the 12 contexts flagged as recommended.', 'noindex-seo' ),
				)
			);
			?>
			<span class="description" style="margin-left: 8px; vertical-align: middle;">
				<?php esc_html_e( 'Enables all 5 directives on the 12 contexts flagged "Recommended" (privacy policy, date archives, pagination, search results, attachment pages, previews, 404). Review and save.', 'noindex-seo' ); ?>
			</span>
		</form>

		<!-- Search Box -->
		<div class="noindex-seo-search">
			<input
				type="search"
				placeholder="<?php esc_attr_e( 'Search options...', 'noindex-seo' ); ?>"
				aria-label="<?php esc_attr_e( 'Search options', 'noindex-seo' ); ?>"
			>
		</div>

	<!-- Sections as Cards -->
	<?php foreach ( $sections as $section_id => $section ) : ?>
		<?php
		// Default icon when a dynamically-added section (e.g. 'custom_post_types'
		// added in 3.1.0) doesn't have an entry in the static $section_icons map.
		$icon = $section_icons[ $section_id ] ?? 'dashicons-admin-generic';
		?>
				<div class="noindex-seo-card" id="noindex-seo-card-<?php echo esc_attr( $section_id ); ?>">
					<div class="noindex-seo-card-header">
						<h3>
							<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
							<?php echo esc_html( $section['title'] ); ?>
						</h3>
						<span class="dashicons dashicons-arrow-down-alt2 noindex-seo-card-toggle"></span>
					</div>
					<div class="noindex-seo-card-body">
						<?php foreach ( $section['fields'] as $field_id => $field ) : ?>
							<?php
							// Check if field should be disabled (header-only fields with meta method).
							$should_disable = in_array( $field_id, $header_only_fields, true ) && ! $is_header_enabled;
							?>
							<div class="noindex-seo-option<?php echo $should_disable ? ' disabled' : ''; ?>"<?php echo $should_disable ? ' title="' . esc_attr__( 'This option only works with HTTP Headers implementation method', 'noindex-seo' ) . '"' : ''; ?>>
								<div class="noindex-seo-option-header">
									<div class="noindex-seo-option-title">
										<strong><?php echo esc_html( $field['label'] ); ?></strong>
										<span class="noindex-seo-badge <?php echo $field['suggestion'] ? 'recommended' : 'not-recommended'; ?>">
											<?php
											if ( $field['suggestion'] ) {
												esc_html_e( 'Recommended', 'noindex-seo' );
											} else {
												esc_html_e( 'Not Recommended', 'noindex-seo' );
											}
											?>
										</span>
										<?php if ( isset( $field['view_url'] ) && ! empty( $field['view_url'] ) ) : ?>
											<a href="<?php echo esc_url( $field['view_url'] ); ?>" target="_blank" class="noindex-seo-view-link" title="<?php esc_attr_e( 'View Page', 'noindex-seo' ); ?>">
												<span class="dashicons dashicons-external"></span>
											</a>
										<?php endif; ?>
									</div>
									<p class="noindex-seo-option-description">
										<?php echo esc_html( $field['description'] ); ?>
									</p>
								</div>
								<div class="noindex-seo-directives">
								<?php foreach ( $directives_config as $directive => $config ) : ?>
									<?php
									$directive_option_key = $directive . '_seo_' . $field_id;
									$directive_value      = noindex_seo_get_setting( $field_id, $directive );
									?>
										<label class="noindex-seo-directive-checkbox">
											<input
												type="checkbox"
												id="<?php echo esc_attr( $directive_option_key ); ?>"
												name="<?php echo esc_attr( $directive_option_key ); ?>"
												value="1"
												<?php checked( 1, $directive_value ); ?>
												<?php disabled( $should_disable ); ?>
											>
											<span class="directive-icon"><?php echo esc_html( $config['icon'] ); ?></span>
											<span class="directive-label"><?php echo esc_html( $config['label'] ); ?></span>
											<span class="directive-description"><?php echo esc_html( $config['desc'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php submit_button(); ?>
		</form>
	</div>

	<?php
}
