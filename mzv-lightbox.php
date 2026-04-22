<?php
/**
 * Plugin Name: MZV Lightbox
 * Plugin URI:  https://github.com/mikezielonkadotcom/lightbox
 * Description: Lightweight lightbox for WordPress with CSS-Only and Enhanced modes. Gallery, captions, swipe, keyboard nav, WPRM integration.
 * Version:     2.0.0
 * Author:      Mike Zielonka Ventures
 * Author URI:  https://mikezielonka.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mzv-lightbox
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 */

defined( 'ABSPATH' ) || exit;

define( 'MZV_LB_VERSION', '2.0.0' );
define( 'MZV_LB_FILE', __FILE__ );
define( 'MZV_LB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MZV_LB_URL', plugin_dir_url( __FILE__ ) );

require_once MZV_LB_DIR . 'includes/class-settings.php';
require_once MZV_LB_DIR . 'includes/class-content.php';
require_once MZV_LB_DIR . 'includes/class-css-mode.php';
require_once MZV_LB_DIR . 'includes/class-admin.php';
require_once MZV_LB_DIR . 'includes/um-updater.php';

add_action( 'init', function() {
	$settings = new MZV_LB_Settings();
	$settings->hooks();

	$content = new MZV_LB_Content( $settings );
	$content->hooks();

	$admin = new MZV_LB_Admin( $settings );
	$admin->hooks();

	if ( is_admin() ) {
		\UM\PluginUpdater\register( [
			'file'       => MZV_LB_FILE,
			'slug'       => 'mzv-lightbox',
			'update_url' => 'https://updates.mikezielonka.com/plugins/mzv-lightbox/info.json',
			'server'     => 'https://updates.mikezielonka.com',
		] );
	}
} );

// Activation hook for WPRM conflict check.
register_activation_hook( MZV_LB_FILE, function() {
	// Check for WPRM conflict on activation.
	if ( function_exists( 'WPRM' ) || class_exists( 'WP_Recipe_Maker' ) ) {
		if ( class_exists( 'WPRM_Settings' ) ) {
			$conflict = WPRM_Settings::get( 'recipe_image_clickable' )
				|| WPRM_Settings::get( 'instruction_image_clickable' );
			if ( $conflict ) {
				set_transient( 'mzv_lb_activation_notice', true, WEEK_IN_SECONDS );
			}
		}
	}
} );
