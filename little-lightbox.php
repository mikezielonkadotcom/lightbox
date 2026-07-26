<?php
/**
 * Plugin Name: This Little Lightbox of Mine
 * Plugin URI:  https://github.com/mikezielonkadotcom/little-lightbox
 * Description: Lightweight image lightbox for WordPress with CSS-Only and Enhanced modes, gallery browsing, captions, swipe, keyboard navigation, and WPRM integration.
 * Version:     2.7.0
 * Author:      Mike Zielonka Ventures
 * Author URI:  https://mikezielonka.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: little-lightbox
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MZV_LB_VERSION', '2.7.0' );
define( 'MZV_LB_FILE', __FILE__ );
define( 'MZV_LB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MZV_LB_URL', plugin_dir_url( __FILE__ ) );

require_once MZV_LB_DIR . 'includes/class-settings.php';
require_once MZV_LB_DIR . 'includes/class-content.php';
require_once MZV_LB_DIR . 'includes/class-css-mode.php';
require_once MZV_LB_DIR . 'includes/class-admin.php';
require_once MZV_LB_DIR . 'includes/class-feature-telemetry.php';
require_once MZV_LB_DIR . 'includes/class-onboarding.php';
require_once MZV_LB_DIR . 'includes/um-updater.php';

$GLOBALS['little_lightbox_updater'] = \UM\PluginUpdater\register( [
	'file'                       => MZV_LB_FILE,
	'slug'                       => 'little-lightbox',
	'update_url'                 => 'https://updatemachine.com/little-lightbox/update.json',
	'server'                     => 'https://updatemachine.com',
	'feature_telemetry'          => MZV_LB_Feature_Telemetry::config(),
	'telemetry_consent_mode'     => 'opt_out',
	'telemetry_privacy_url'      => 'https://updatemachine.com/privacy',
	'telemetry_data_description' => MZV_LB_Onboarding::telemetry_disclosure( false ),
] );

add_action( 'init', function() {
	$settings = new MZV_LB_Settings();
	$settings->hooks();

	$content = new MZV_LB_Content( $settings );
	$content->hooks();

	$onboarding = new MZV_LB_Onboarding( $settings, $GLOBALS['little_lightbox_updater'] ?? null );
	$onboarding->hooks();
	$GLOBALS['little_lightbox_onboarding'] = $onboarding;

	$admin = new MZV_LB_Admin( $onboarding );
	$admin->hooks();
} );

// Activation hook for WPRM conflict check.
register_activation_hook( MZV_LB_FILE, function( $network_wide = false ) {
	$network_wide = true === $network_wide;
	MZV_LB_Onboarding::mark_pending( $network_wide );

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
