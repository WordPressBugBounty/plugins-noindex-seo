<?php
/**
 * Uninstall the noindex SEO plugin.
 *
 * This file is called by WordPress when the plugin is deleted through the admin interface.
 * By default, plugin data is preserved. Data is only deleted when the administrator has
 * explicitly enabled the "Delete all plugin data on uninstall" option in settings.
 *
 * @package noindex-seo
 * @since 1.0.0
 * @since 2.0.0 Added cleanup for new implementation method option and transients.
 * @since 2.0.0 Added cleanup for multiple directives (noindex, nofollow, noarchive, nosnippet, noimageindex).
 * @since 2.0.1 Data is now preserved by default; deletion requires explicit opt-in.
 * @since 3.0.0 Added cleanup for the consolidated noindex_seo_settings option. Legacy
 *              individual options are still cleaned up so that sites which upgrade from
 *              2.x and then uninstall (with or without migration having run) are fully
 *              cleaned. Retrocompat: if a user rolls back to 2.x, their data is intact
 *              until they explicitly uninstall.
 */

declare(strict_types=1);

// Exit if uninstall not called from WordPress.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect the user's data-preservation preference.
// Default (0) = preserve data. Only delete when the user has explicitly opted in (1).
// Read from both possible storage locations: the consolidated 3.0 option first, then
// the legacy 2.x option as a fallback (covers the case where migration never ran).
$noindex_seo_delete           = 0;
$noindex_seo_consolidated     = get_option( 'noindex_seo_settings', array() );
$noindex_seo_consolidated     = is_array( $noindex_seo_consolidated ) ? $noindex_seo_consolidated : array();
$noindex_seo_consolidated_cfg = $noindex_seo_consolidated['config'] ?? array();
$noindex_seo_consolidated_cfg = is_array( $noindex_seo_consolidated_cfg ) ? $noindex_seo_consolidated_cfg : array();
if ( isset( $noindex_seo_consolidated_cfg['delete_on_uninstall'] ) ) {
	$noindex_seo_delete = $noindex_seo_consolidated_cfg['delete_on_uninstall'] ? 1 : 0;
} else {
	$noindex_seo_delete = get_option( 'noindex_seo_config_delete_on_uninstall', 0 );
}

if ( ! $noindex_seo_delete ) {
	// Data is preserved. Nothing to do.
	exit;
}

// User has opted in to full data removal — proceed with cleanup.

// Define contexts and directives.
// NOTE: this list must stay in sync with noindex_seo_get_contexts() in
// noindex-seo.php. The helper cannot be called here because the main plugin
// file is not loaded during uninstall.
$noindex_seo_contexts   = array(
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
$noindex_seo_directives = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );

// 3.0+ consolidated option (single row, autoloaded).
delete_option( 'noindex_seo_settings' );

// Delete all legacy directive options for each context (2.x storage).
// Kept for retrocompat: covers installs that upgrade from 2.x and then uninstall
// even if the 3.0 migration did not run (e.g. plugin was never activated, only
// installed). Each delete_option() is a no-op if the row does not exist.
foreach ( $noindex_seo_contexts as $noindex_seo_context ) {
	foreach ( $noindex_seo_directives as $noindex_seo_directive ) {
		delete_option( $noindex_seo_directive . '_seo_' . $noindex_seo_context );
	}
}

// Delete legacy configuration options.
delete_option( 'noindex_seo_config_seoplugins' );
delete_option( 'noindex_seo_config_method' );
delete_option( 'noindex_seo_config_granular' );
delete_option( 'noindex_seo_config_version' );
delete_option( 'noindex_seo_config_delete_on_uninstall' );

// Delete transient cache.
delete_transient( 'noindex_seo_options' );

// Clean up any leftover options (in case of partial uninstall or pre-3.0 installs).
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// Direct database queries are necessary here for complete cleanup during uninstall.
// This is a DELETE operation (not SELECT), so caching is not applicable.
// Using wildcards with delete_option() is not possible, requiring direct SQL.
// Clean up all directive-related options (noindex, nofollow, noarchive, nosnippet, noimageindex).
foreach ( $noindex_seo_directives as $noindex_seo_directive ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $noindex_seo_directive . '_seo_%' ) );
}

// Belt-and-suspenders: clean up the consolidated option one more time in case
// delete_option() above failed due to a corrupted cache. The wildcard LIKE
// matches both noindex_seo_settings and any stray noindex_seo_config_* rows.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'noindex_seo_%'
	)
);

// Clean up transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_noindex_seo_%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_timeout_noindex_seo_%'
	)
);

// Clean up post meta (granular control).
$noindex_seo_meta_keys = array(
	'_noindex_seo_override',
	'_noindex_seo_noindex',
	'_noindex_seo_nofollow',
	'_noindex_seo_noarchive',
	'_noindex_seo_nosnippet',
	'_noindex_seo_noimageindex',
);

foreach ( $noindex_seo_meta_keys as $noindex_seo_meta_key ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
			$noindex_seo_meta_key
		)
	);
}

// Clean up term meta (per-term granular control, added in 3.0.2).
// delete_term_meta() cannot be used here because term IDs are not known at
// uninstall time and there is no built-in "delete all term meta by key".
// $wpdb->termmeta is set by WP when the table exists; older WP versions
// (pre-4.4) did not have it, hence the property_exists() check.
if ( property_exists( $wpdb, 'termmeta' ) && $wpdb->termmeta ) {
	foreach ( $noindex_seo_meta_keys as $noindex_seo_meta_key ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
				$noindex_seo_meta_key
			)
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
