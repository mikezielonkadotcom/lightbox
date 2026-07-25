<?php
/**
 * Uninstall cleanup for This Little Lightbox of Mine.
 *
 * @package MZV_Lightbox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$updater_file = __DIR__ . '/includes/um-updater.php';

if ( ! class_exists( '\\UM\\PluginUpdater\\Updater' ) && file_exists( $updater_file ) ) {
	require_once $updater_file;
}

if ( class_exists( '\\UM\\PluginUpdater\\Updater' ) ) {
	\UM\PluginUpdater\Updater::cleanup( 'little-lightbox' );
}

$onboarding_option = 'mzv_lb_onboarding_state';
delete_option( $onboarding_option );
delete_site_option( $onboarding_option );

if ( is_multisite() ) {
	$original_blog_id = get_current_blog_id();
	foreach ( get_networks( [ 'fields' => 'ids', 'number' => 0 ] ) as $network_id ) {
		delete_network_option( (int) $network_id, $onboarding_option );
	}
	foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( $onboarding_option );
		restore_current_blog();
	}
	if ( get_current_blog_id() !== $original_blog_id ) {
		switch_to_blog( $original_blog_id );
	}
}
