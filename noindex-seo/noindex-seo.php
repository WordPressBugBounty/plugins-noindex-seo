<?php
/**
 * Plugin Name: noindex SEO
 * Plugin URI: https://wordpress.org/plugins/noindex-seo/
 * Description: Allows to add a meta-tag for robots noindex in some parts of your WordPress site.
 * Requires at least: 5.2
 * Requires PHP: 5.6
 * Version: 1.0.12
 * Author: Javier Casares
 * Author URI: https://www.javiercasares.com/
 * License: EUPL v1.2
 * License URI: https://eupl.eu/1.2/en/
 * Text Domain: noindex-seo
 * Domain Path: /languages
 *
 * @package noindex-seo
 */

defined( 'ABSPATH' ) || die( 'Bye bye!' );

/**
 * Retrieves and sets the SEO 'noindex' values for various WordPress contexts.

 * @global WP_Post $post The post global for the current post, if within The Loop.
 *
 * @return void
 *
 * @since 1.0.0
 *
 * @uses get_option()  Fetches an option value based on an option name.
 */
function noindex_seo_show() {
	global $post;
	$enter              = true;
	$noindex_seo_values = array(
		'error'             => (bool) get_option( 'noindex_seo_error' ),
		'archive'           => (bool) get_option( 'noindex_seo_archive' ),
		'attachment'        => (bool) get_option( 'noindex_seo_attachment' ),
		'author'            => (bool) get_option( 'noindex_seo_author' ),
		'category'          => (bool) get_option( 'noindex_seo_category' ),
		'comment_feed'      => (bool) get_option( 'noindex_seo_comment_feed' ),
		'customize_preview' => (bool) get_option( 'noindex_seo_customize_preview' ),
		'date'              => (bool) get_option( 'noindex_seo_date' ),
		'day'               => (bool) get_option( 'noindex_seo_day' ),
		'feed'              => (bool) get_option( 'noindex_seo_feed' ),
		'front_page'        => (bool) get_option( 'noindex_seo_front_page' ),
		'home'              => (bool) get_option( 'noindex_seo_home' ),
		'month'             => (bool) get_option( 'noindex_seo_month' ),
		'page'              => (bool) get_option( 'noindex_seo_page' ),
		'paged'             => (bool) get_option( 'noindex_seo_paged' ),
		'post_type_archive' => (bool) get_option( 'noindex_seo_post_type_archive' ),
		'preview'           => (bool) get_option( 'noindex_seo_preview' ),
		'privacy_policy'    => (bool) get_option( 'noindex_seo_privacy_policy' ),
		'robots'            => (bool) get_option( 'noindex_seo_robots' ),
		'search'            => (bool) get_option( 'noindex_seo_search' ),
		'single'            => (bool) get_option( 'noindex_seo_single' ),
		'singular'          => (bool) get_option( 'noindex_seo_singular' ),
		'tag'               => (bool) get_option( 'noindex_seo_tag' ),
		'time'              => (bool) get_option( 'noindex_seo_time' ),
		'year'              => (bool) get_option( 'noindex_seo_year' ),
	);

	// GLOBAL IMPORTANT PAGES.
	if ( $enter && $noindex_seo_values['front_page'] && function_exists( 'is_front_page' ) && is_front_page() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['home'] && function_exists( 'is_home' ) && is_home() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// PAGES / POSTS.
	if ( $enter && $noindex_seo_values['page'] && function_exists( 'is_page' ) && is_page() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['privacy_policy'] && function_exists( 'is_privacy_policy' ) && is_privacy_policy() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['single'] && function_exists( 'is_single' ) && is_single() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['singular'] && function_exists( 'is_singular' ) && is_singular() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// CATEGORIES / TAGS.
	if ( $enter && $noindex_seo_values['category'] && function_exists( 'is_category' ) && is_category() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['tag'] && function_exists( 'is_tag' ) && is_tag() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// DATES.
	if ( $enter && $noindex_seo_values['date'] && function_exists( 'is_date' ) && is_date() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['day'] && function_exists( 'is_day' ) && is_day() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['month'] && function_exists( 'is_month' ) && is_month() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['time'] && function_exists( 'is_time' ) && is_time() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['year'] && function_exists( 'is_year' ) && is_year() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// ARCHIVE.
	if ( $enter && $noindex_seo_values['archive'] && function_exists( 'is_archive' ) && is_archive() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['author'] && function_exists( 'is_author' ) && is_author() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['post_type_archive'] && function_exists( 'is_post_type_archive' ) && is_post_type_archive() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// PAGINATION.
	if ( $enter && $noindex_seo_values['paged'] && function_exists( 'is_paged' ) && is_paged() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// SEARCH.
	if ( $enter && $noindex_seo_values['search'] && function_exists( 'is_search' ) && is_search() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// ATTACHMENT.
	if ( $enter && $noindex_seo_values['attachment'] && function_exists( 'is_attachment' ) && is_attachment() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// PREVIEW.
	if ( $enter && $noindex_seo_values['customize_preview'] && function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		noindex_seo_metarobots();
		$enter = false;
	}
	if ( $enter && $noindex_seo_values['preview'] && function_exists( 'is_preview' ) && is_preview() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	// ERROR.
	if ( $enter && $noindex_seo_values['error'] && function_exists( 'is_404' ) && is_404() ) {
		noindex_seo_metarobots();
		$enter = false;
	}

	unset( $enter, $noindex_seo_values );
}

/**
 * Outputs a 'noindex' meta robots tag to the page.
 *
 * This function prints the 'noindex' meta robots tag, instructing search engines
 * not to index the content of the current page.
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @uses echo         Outputs the meta robots tag to the webpage.
 */
function noindex_seo_metarobots() {
	echo '<meta name="robots" content="noindex">' . "\n";
}

add_action( 'wp_head', 'noindex_seo_show' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'noindex_seo_settings_link' );
add_action( 'admin_init', 'noindex_seo_register' );
add_action( 'admin_menu', 'noindex_seo_menu' );

/**
 * Adds a 'Settings' link to the plugin action links on the plugins page.
 *
 * This function appends a direct link to the plugin's settings page within the WordPress admin dashboard,
 * making it easier for users to access and configure the plugin settings.
 *
 * @since 1.0.0
 *
 * @param array $links An array of action links already present for the plugin.
 *
 * @return array       Returns the updated array of action links including the 'Settings' link.
 *
 * @uses get_admin_url()  Fetches the admin URL for the current site with optional path attached.
 * @uses __()             Retrieves a translated string.
 */
function noindex_seo_settings_link( $links ) {
	$links[] = '<a href="' . get_admin_url( null, 'options-general.php?page=noindex_seo' ) . '">' . esc_html( __( 'Settings' ) ) . '</a>';
	return $links;
}

/**
 * Adds a 'noindex SEO' options page to the WordPress admin menu.
 *
 * This function creates an options page titled 'noindex SEO' within the WordPress admin dashboard.
 * The page provides a UI for configuring settings related to the 'noindex SEO' functionality.
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @uses add_options_page()  Adds a new options page to the admin menu.
 * @uses __()                 Retrieves a translated string.
 */
function noindex_seo_menu() {
	add_options_page( __( 'noindex SEO', 'noindex-seo' ), __( 'noindex SEO', 'noindex-seo' ), 'manage_options', 'noindex_seo', 'noindex_seo_admin' );
}

/**
 * Registers settings for the 'noindex SEO' plugin.
 *
 * This function registers a variety of settings associated with the 'noindex SEO' functionality in WordPress.
 * Each setting determines whether specific pages or post types should be excluded from search engine indexing.
 * The settings values are integers, with a default value of 0 (which typically represents 'false' or 'off' in boolean context).
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @uses register_setting()  Registers a setting and its data.
 */
function noindex_seo_register() {
	register_setting(
		'noindexseo',
		'noindex_seo_error',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_archive',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_attachment',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_author',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_category',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_comment_feed',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_customize_preview',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_date',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_day',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_feed',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_front_page',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_home',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_month',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_page',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_paged',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_post_type_archive',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_preview',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_privacy_policy',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_robots',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_search',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_single',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_singular',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_tag',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_time',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
	register_setting(
		'noindexseo',
		'noindex_seo_year',
		array(
			'type'    => 'integer',
			'default' => 0,
		)
	);
}

/**
 * Displays the administration settings page for the 'noindex SEO' plugin.
 *
 * This function provides the markup for the settings page of the plugin in the WordPress admin area.
 * It shows the header for the settings, followed by the form that contains the settings fields.
 * Each setting determines if specific pages or post types should be excluded from search engine indexing.
 * The options are retrieved from the WordPress database and are displayed on the form.
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @uses settings_fields()   Outputs nonce, action, and option_page fields for a settings page in the form.
 * @uses get_option()        Retrieves option value based on option name from WordPress database.
 */
function noindex_seo_admin() {
	?>
		<div class="wrap">
		<h1><?php echo esc_html( __( 'noindex SEO Settings', 'noindex-seo' ) ); ?></h1>
		<form method="post" action="options.php">
	<?php
	settings_fields( 'noindexseo' );
	?>
	<p><?php echo esc_html( __( 'Important note: if you have any doubt about any of the following items it is best not to activate the option as you could lose results in the search engines.', 'noindex-seo' ) ); ?></p>
	<?php
	$noindex_seo_error = 0;
	if ( (bool) get_option( 'noindex_seo_error' ) ) {
		$noindex_seo_error = 1;
	}

	$noindex_seo_archive = 0;
	if ( (bool) get_option( 'noindex_seo_archive' ) ) {
		$noindex_seo_archive = 1;
	}

	$noindex_seo_attachment = 0;
	if ( (bool) get_option( 'noindex_seo_attachment' ) ) {
		$noindex_seo_attachment = 1;
	}

	$noindex_seo_author = 0;
	if ( (bool) get_option( 'noindex_seo_author' ) ) {
		$noindex_seo_author = 1;
	}

	$noindex_seo_category = 0;
	if ( (bool) get_option( 'noindex_seo_category' ) ) {
		$noindex_seo_category = 1;
	}

	$noindex_seo_comment_feed = 0;
	if ( (bool) get_option( 'noindex_seo_comment_feed' ) ) {
		$noindex_seo_comment_feed = 1;
	}

	$noindex_seo_customize_preview = 0;
	if ( (bool) get_option( 'noindex_seo_customize_preview' ) ) {
		$noindex_seo_customize_preview = 1;
	}

	$noindex_seo_date = 0;
	if ( (bool) get_option( 'noindex_seo_date' ) ) {
		$noindex_seo_date = 1;
	}

	$noindex_seo_day = 0;
	if ( (bool) get_option( 'noindex_seo_day' ) ) {
		$noindex_seo_day = 1;
	}

	$noindex_seo_feed = 0;
	if ( (bool) get_option( 'noindex_seo_feed' ) ) {
		$noindex_seo_feed = 1;
	}

	$noindex_seo_front_page = 0;
	if ( (bool) get_option( 'noindex_seo_front_page' ) ) {
		$noindex_seo_front_page = 1;
	}

	$noindex_seo_home = 0;
	if ( (bool) get_option( 'noindex_seo_home' ) ) {
		$noindex_seo_home = 1;
	}

	$noindex_seo_month = 0;
	if ( (bool) get_option( 'noindex_seo_month' ) ) {
		$noindex_seo_month = 1;
	}

	$noindex_seo_page = 0;
	if ( (bool) get_option( 'noindex_seo_page' ) ) {
		$noindex_seo_page = 1;
	}

	$noindex_seo_paged = 0;
	if ( (bool) get_option( 'noindex_seo_paged' ) ) {
		$noindex_seo_paged = 1;
	}

	$noindex_seo_post_type_archive = 0;
	if ( (bool) get_option( 'noindex_seo_post_type_archive' ) ) {
		$noindex_seo_post_type_archive = 1;
	}

	$noindex_seo_preview = 0;
	if ( (bool) get_option( 'noindex_seo_preview' ) ) {
		$noindex_seo_preview = 1;
	}

	$noindex_seo_privacy_policy = 0;
	if ( (bool) get_option( 'noindex_seo_privacy_policy' ) ) {
		$noindex_seo_privacy_policy = 1;
	}

	$noindex_seo_robots = 0;
	if ( (bool) get_option( 'noindex_seo_robots' ) ) {
		$noindex_seo_robots = 1;
	}

	$noindex_seo_search = 0;
	if ( (bool) get_option( 'noindex_seo_search' ) ) {
		$noindex_seo_search = 1;
	}

	$noindex_seo_single = 0;
	if ( (bool) get_option( 'noindex_seo_single' ) ) {
		$noindex_seo_single = 1;
	}

	$noindex_seo_singular = 0;
	if ( (bool) get_option( 'noindex_seo_singular' ) ) {
		$noindex_seo_singular = 1;
	}

	$noindex_seo_tag = 0;
	if ( (bool) get_option( 'noindex_seo_tag' ) ) {
		$noindex_seo_tag = 1;
	}

	$noindex_seo_time = 0;
	if ( (bool) get_option( 'noindex_seo_time' ) ) {
		$noindex_seo_time = 1;
	}

	$noindex_seo_year = 0;
	if ( (bool) get_option( 'noindex_seo_year' ) ) {
		$noindex_seo_year = 1;
	}

	?>
			<h2><?php echo esc_html( __( 'Main pages', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_front_page"><?php echo esc_html( __( 'Front Page', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_front_page" name="noindex_seo_front_page" value="1"
			<?php
			if ( $noindex_seo_front_page ) {
				echo 'checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site\'s front page.', 'noindex-seo' ) ); ?> <a href="<?php echo esc_url( get_site_url() ); ?>" target="_blank"><?php echo esc_html( __( 'View', 'noindex-seo' ) ); ?></a></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_home"><?php echo esc_html( __( 'Home', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_home" name="noindex_seo_home" value="1"
			<?php
			if ( $noindex_seo_home ) {
				echo 'checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site\'s home page.', 'noindex-seo' ) ); ?> <a href="<?php echo esc_url( get_home_url() ); ?>" target="_blank"><?php echo esc_html( __( 'View', 'noindex-seo' ) ); ?></a></span></fieldset></td>
		</tr>
		</table>
	
		<h2><?php echo esc_html( __( 'Pages and Posts', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_page"><?php echo esc_html( __( 'Page', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_page" name="noindex_seo_page" value="1"
			<?php
			if ( $noindex_seo_page ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site\'s pages.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_privacy_policy"><?php echo esc_html( __( 'Privacy Policy', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_privacy_policy" name="noindex_seo_privacy_policy" value="1"
			<?php
			if ( $noindex_seo_privacy_policy ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site\'s privacy policy page.', 'noindex-seo' ) ); ?> <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" target="_blank"><?php echo esc_html( __( 'View', 'noindex-seo' ) ); ?></a></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_single"><?php echo esc_html( __( 'Single', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_single" name="noindex_seo_single" value="1"
			<?php
			if ( $noindex_seo_single ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of a post on the site.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_singular"><?php echo esc_html( __( 'Singular', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_singular" name="noindex_seo_singular" value="1"
			<?php
			if ( $noindex_seo_singular ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of a post or a page of the site.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Taxonomies', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_category"><?php echo esc_html( __( 'Category', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_category" name="noindex_seo_category" value="1"
			<?php
			if ( $noindex_seo_category ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site categories. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_tag"><?php echo esc_html( __( 'Tag', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_tag" name="noindex_seo_tag" value="1"
			<?php
			if ( $noindex_seo_tag ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the site\'s tags. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Dates', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_date"><?php echo esc_html( __( 'Date', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_date" name="noindex_seo_date" value="1"
			<?php
			if ( $noindex_seo_date ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when any date-based archive page (i.e. a monthly, yearly, daily or time-based archive) of the site. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_day"><?php echo esc_html( __( 'Day', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_day" name="noindex_seo_day" value="1"
			<?php
			if ( $noindex_seo_day ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when a daily archive of the site. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_month"><?php echo esc_html( __( 'Month', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_month" name="noindex_seo_month" value="1"
			<?php
			if ( $noindex_seo_month ) {
				echo ' checked';
			}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when a monthly archive of the site. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_time"><?php echo esc_html( __( 'Time', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_time" name="noindex_seo_time" value="1"
			<?php
			if ( $noindex_seo_time ) {
				echo ' checked';
			}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when an hourly, "minutely", or "secondly" archive of the site. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_year"><?php echo esc_html( __( 'Year', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_year" name="noindex_seo_year" value="1"
			<?php
			if ( $noindex_seo_year ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when a yearly archive of the site. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Archives', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_archive"><?php echo esc_html( __( 'Archive', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_archive" name="noindex_seo_archive" value="1"
			<?php
			if ( $noindex_seo_archive ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of any type of Archive page. Category, Tag, Author and Date based pages are all types of Archives. The lists where the posts appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_author"><?php echo esc_html( __( 'Author', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_author" name="noindex_seo_author" value="1"
			<?php
			if ( $noindex_seo_author ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the author\'s page, where the author\'s publications appear.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_post_type_archive"><?php echo esc_html( __( 'Post Type Archive', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_post_type_archive" name="noindex_seo_post_type_archive" value="1"
			<?php
			if ( $noindex_seo_post_type_archive ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-no" title="No"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of any post type page.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Pagination', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_paged"><?php echo esc_html( __( 'Pagination', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_paged" name="noindex_seo_paged" value="1"
			<?php
			if ( $noindex_seo_paged ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the pagination, i.e. all pages other than the main page of an archive.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Search', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_search"><?php echo esc_html( __( 'Search', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_search" name="noindex_seo_search" value="1"
			<?php
			if ( $noindex_seo_search ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of the internal search result pages.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Attachments', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_attachment"><?php echo esc_html( __( 'Attachment', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_attachment" name="noindex_seo_attachment" value="1"
			<?php
			if ( $noindex_seo_attachment ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing of an attachment document to a post or page. An attachment is an image or other file uploaded through the post editor\'s upload utility. Attachments can be displayed on their own "page" or template. This will not cause the indexing of the image or file to be blocked.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Previews', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_customize_preview"><?php echo esc_html( __( 'Customize Preview', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_customize_preview" name="noindex_seo_customize_preview" value="1"
			<?php
			if ( $noindex_seo_customize_preview ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when a content is being displayed in customize mode.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		<tr>
			<th scope="row"><label for="noindex_seo_preview"><?php echo esc_html( __( 'Preview', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_preview" name="noindex_seo_preview" value="1"
			<?php
			if ( $noindex_seo_preview ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will block the indexing when a single post is being displayed in draft mode.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<h2><?php echo esc_html( __( 'Error Page', 'noindex-seo' ) ); ?></h2>
		<table class="form-table">
		<tr>
			<th scope="row"><label for="noindex_seo_error"><?php echo esc_html( __( 'Error 404', 'noindex-seo' ) ); ?></label></th>
			<td><fieldset><input type="checkbox" id="noindex_seo_error" name="noindex_seo_error" value="1"
			<?php
			if ( $noindex_seo_error ) {
				echo ' checked';}
			?>
			> <?php echo esc_html( __( 'Recommended', 'noindex-seo' ) ); ?>: <span class="dashicons dashicons-yes" title="Yes"></span>. <span class="description"><?php echo esc_html( __( 'This will cause an error page to be blocked from being indexed. As it is an error page, it should not be indexed per se, but just in case.', 'noindex-seo' ) ); ?></span></fieldset></td>
		</tr>
		</table>

			<?php submit_button(); ?>
		</form>
		</div>
		<?php
		unset( $noindex_seo_error, $noindex_seo_archive, $noindex_seo_attachment, $noindex_seo_author, $noindex_seo_category, $noindex_seo_comment_feed, $noindex_seo_customize_preview, $noindex_seo_date, $noindex_seo_day, $noindex_seo_feed, $noindex_seo_front_page, $noindex_seo_home, $noindex_seo_month, $noindex_seo_page, $noindex_seo_paged, $noindex_seo_post_type_archive, $noindex_seo_preview, $noindex_seo_privacy_policy, $noindex_seo_robots, $noindex_seo_search, $noindex_seo_single, $noindex_seo_singular, $noindex_seo_tag, $noindex_seo_time, $noindex_seo_year );
}
