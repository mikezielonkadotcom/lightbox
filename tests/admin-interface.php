<?php
/**
 * Static contract checks for the React admin shell and package assets.
 */

$root       = dirname( __DIR__ );
$admin      = file_get_contents( $root . '/includes/class-admin.php' );
$onboarding = file_get_contents( $root . '/includes/class-onboarding.php' );
$bootstrap  = file_get_contents( $root . '/little-lightbox.php' );
$javascript = file_get_contents( $root . '/src/admin/index.js' );
$asset      = require $root . '/assets/admin/index.asset.php';

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert( 1 === substr_count( $admin, 'add_options_page(' ), 'site admin must register exactly one plugin settings entry' );
$assert( 1 === substr_count( $admin, "add_submenu_page(\n\t\t\t'settings.php'" ), 'network-active installs must register one Network Settings entry' );
$assert( false === strpos( $onboarding, 'add_options_page(' ), 'onboarding must not register a second settings entry' );
$assert( false === strpos( $onboarding, 'little-lightbox-setup' ), 'legacy setup menu slug must not return' );
$assert( false !== strpos( $admin, "'welcome' === \$requested_view" ), 'welcome must be a view of the shared settings page' );
$assert( false !== strpos( $admin, 'wp_add_inline_script' ), 'the PHP shell must provide bounded bootstrap data to React' );
$assert( false !== strpos( $admin, 'JSON_HEX_TAG' ), 'inline bootstrap data must be safe inside a script element' );
$assert( false !== strpos( $admin, "wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' )" ), 'the compiled RTL stylesheet must be enabled' );
$assert( false !== strpos( $bootstrap, 'new MZV_LB_Admin( $onboarding )' ), 'bootstrap must create the unified admin interface' );
$assert( false === strpos( $bootstrap, 'function( bool $network_wide' ), 'activation callback must tolerate null from WordPress 6.0 and older WP-CLI' );
$assert( false !== strpos( $bootstrap, '$network_wide = true === $network_wide;' ), 'activation scope must be normalized before onboarding state is written' );
$assert( false !== strpos( $javascript, "document.getElementById( 'little-lightbox-admin-root' )" ), 'React must mount only in the plugin shell' );
$assert( false !== strpos( $javascript, '<TabPanel' ), 'settings must use the WordPress tab interface' );
$assert( is_array( $asset ) && ! empty( $asset['version'] ), 'compiled asset metadata must include a version' );

foreach ( [ 'wp-components', 'wp-element', 'wp-i18n' ] as $dependency ) {
	$assert( in_array( $dependency, $asset['dependencies'] ?? [], true ), "compiled asset must depend on {$dependency}" );
}

echo "Little Lightbox React admin interface tests passed.\n";
