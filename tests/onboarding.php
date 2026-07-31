<?php
/**
 * Little Lightbox onboarding state and rendering smoke tests.
 */

define( 'ABSPATH', __DIR__ );
define( 'MZV_LB_VERSION', '2.7.3' );
define( 'MZV_LB_FILE', dirname( __DIR__ ) . '/little-lightbox.php' );

$GLOBALS['llb_options']      = [];
$GLOBALS['llb_site_options'] = [];
$GLOBALS['llb_multisite']    = false;
$GLOBALS['llb_capabilities'] = [
	'manage_options'         => true,
	'manage_network_options' => true,
];

function get_option( string $key, $default = false ) {
	return $GLOBALS['llb_options'][ $key ] ?? $default;
}

function update_option( string $key, $value, bool $autoload = false ): bool {
	unset( $autoload );
	$GLOBALS['llb_options'][ $key ] = $value;
	return true;
}

function get_site_option( string $key, $default = false ) {
	return $GLOBALS['llb_site_options'][ $key ] ?? $default;
}

function update_site_option( string $key, $value ): bool {
	$GLOBALS['llb_site_options'][ $key ] = $value;
	return true;
}

function is_multisite(): bool {
	return $GLOBALS['llb_multisite'];
}

function current_user_can( string $capability ): bool {
	return $GLOBALS['llb_capabilities'][ $capability ] ?? false;
}

function plugin_basename( string $file ): string {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function wp_parse_args( $args, $defaults = [] ): array {
	return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function sanitize_text_field( $value ): string {
	return trim( (string) $value );
}

function sanitize_key( $value ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( __( $text, $domain ) );
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo esc_html__( $text, $domain );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_url( string $url ): string {
	return $url;
}

function checked( $checked, $current = true, bool $display = true ): string {
	$value = $checked === $current ? 'checked="checked"' : '';
	if ( $display ) {
		echo $value;
	}
	return $value;
}

function wp_nonce_field( string $action, string $name ): void {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $action ) . '">';
}

function wp_create_nonce( string $action ): string {
	return 'nonce-' . $action;
}

function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	unset( $wrap );
	echo '<button class="button button-' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function network_admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/network/' . ltrim( $path, '/' );
}

function add_query_arg( string $key, string $value, string $url ): string {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function add_action( ...$args ): void {
	unset( $args );
}

function add_filter( ...$args ): void {
	unset( $args );
}

function add_options_page( ...$args ): void {
	unset( $args );
}

function add_submenu_page( ...$args ): void {
	unset( $args );
}

function wp_doing_ajax(): bool {
	return false;
}

function wp_doing_cron(): bool {
	return false;
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-onboarding.php';

function llb_onboarding_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function llb_onboarding_invoke( object $object, string $method, array $args = [] ) {
	$reflection = new ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}
	return $reflection->invokeArgs( $object, $args );
}

final class LLB_Test_Preference {
	public bool $enabled;
	public bool $rendered_without_nonce = false;

	public function __construct( bool $enabled ) {
		$this->enabled = $enabled;
	}

	public function field_name(): string {
		return 'um_telemetry_consent_little-lightbox';
	}

	public function is_enabled(): bool {
		return $this->enabled;
	}

	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	public function render_control( bool $include_nonce = true ): void {
		$this->rendered_without_nonce = ! $include_nonce;
		echo '<label><input type="checkbox" name="' . esc_attr( $this->field_name() ) . '" value="1" ' . checked( $this->enabled, true, false ) . '> Share optional update and feature telemetry</label>';
		echo '<p>' . esc_html( MZV_LB_Onboarding::telemetry_disclosure() ) . ' <a href="https://updatemachine.com/privacy">Privacy policy</a></p>';
	}
}

final class LLB_Test_Updater {
	private LLB_Test_Preference $preference;

	public function __construct( LLB_Test_Preference $preference ) {
		$this->preference = $preference;
	}

	public function telemetry_preference(): LLB_Test_Preference {
		return $this->preference;
	}
}

final class LLB_Test_Legacy_Preference {
	public bool $opted_out = false;

	public function is_opted_out(): bool {
		return $this->opted_out;
	}

	public function set_opted_out( bool $opted_out ): void {
		$this->opted_out = $opted_out;
	}
}

final class LLB_Test_Legacy_Updater {
	public LLB_Test_Legacy_Preference $preference;

	public function __construct() {
		$this->preference = new LLB_Test_Legacy_Preference();
	}

	public function telemetry_opt_out(): LLB_Test_Legacy_Preference {
		return $this->preference;
	}
}

$settings   = new MZV_LB_Settings();
$preference = new LLB_Test_Preference( false );
$wizard     = new MZV_LB_Onboarding( $settings, new LLB_Test_Updater( $preference ) );

MZV_LB_Onboarding::mark_pending( false );
$state = $wizard->state();
llb_onboarding_assert( 'pending' === $state['status'] && 1 === $state['step'], 'Site activation should seed step one.' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET                      = [];
$wizard->maybe_redirect();
$state = $wizard->state();
llb_onboarding_assert( 'pending' === $state['status'] && 1 === $state['step'], 'POST requests must not consume pending onboarding or redirect before WordPress processes the write.' );
unset( $_SERVER['REQUEST_METHOD'] );

$privacy_data = $wizard->client_data();
llb_onboarding_assert( false === $privacy_data['sharingEnabled'], 'Opt-in mode should start with sharing disabled.' );
llb_onboarding_assert( false !== strpos( $privacy_data['telemetryDetails'], 'coarse eligible-render active-day and recency ranges' ), 'The exact bounded-data disclosure should be exposed to React.' );
llb_onboarding_assert( 'https://updatemachine.com/privacy' === $privacy_data['privacyUrl'], 'The privacy-policy URL should be exposed to React.' );
llb_onboarding_assert( false !== strpos( $privacy_data['actionUrl'], 'page=little-lightbox' ), 'Welcome must use the existing plugin settings slug.' );
llb_onboarding_assert( false !== strpos( $privacy_data['actionUrl'], 'view=welcome' ), 'Welcome must be a view within the plugin settings interface.' );
llb_onboarding_assert( false === strpos( $privacy_data['actionUrl'], 'little-lightbox-setup' ), 'The retired duplicate menu slug must not remain.' );

llb_onboarding_invoke( $wizard, 'set_sharing_enabled', [ true ] );
llb_onboarding_assert( $preference->enabled, 'An explicit positive sharing choice should enable telemetry.' );
llb_onboarding_invoke( $wizard, 'set_sharing_enabled', [ false ] );
llb_onboarding_assert( ! $preference->enabled, 'The positive sharing choice should persist through the SDK preference.' );

llb_onboarding_invoke( $wizard, 'save_state', [ 'in_progress', 2 ] );
$setup_data = $wizard->client_data();
llb_onboarding_assert( 2 === $setup_data['state']['step'], 'An in-progress wizard should resume at step two.' );
llb_onboarding_assert( 'enhanced' === $setup_data['options']['lightbox_mode'], 'The React setup view should receive current plugin settings.' );

$GLOBALS['llb_options'][ MZV_LB_Settings::OPTION_KEY ] = array_merge(
	MZV_LB_Settings::defaults(),
	[ 'caption_source' => 'description', 'trigger_icon_size' => 'super' ]
);
$_POST = [ 'lightbox_mode' => 'css' ];
llb_onboarding_invoke( $wizard, 'save_plugin_setup' );
$saved = $GLOBALS['llb_options'][ MZV_LB_Settings::OPTION_KEY ];
llb_onboarding_assert( 'css' === $saved['lightbox_mode'], 'Wizard should save its reviewed mode.' );
llb_onboarding_assert( false === $saved['gallery_enabled'] && false === $saved['animations_enabled'], 'Unchecked wizard features should save false.' );
llb_onboarding_assert( 'description' === $saved['caption_source'] && 'super' === $saved['trigger_icon_size'], 'Wizard must preserve unrelated plugin settings.' );

llb_onboarding_invoke( $wizard, 'save_state', [ 'skipped', 2 ] );
$skipped_data = $wizard->client_data();
llb_onboarding_assert( 'skipped' === $skipped_data['state']['status'], 'Skipped setup must remain revisitable.' );
llb_onboarding_assert( false !== strpos( $skipped_data['settingsUrl'], 'page=little-lightbox' ), 'Finished setup should return to the same settings interface.' );

llb_onboarding_invoke( $wizard, 'save_state', [ 'in_progress', 1 ] );
llb_onboarding_assert( 1 === $wizard->state()['step'], 'Restart should return to the privacy question.' );

$legacy_updater = new LLB_Test_Legacy_Updater();
$legacy_wizard  = new MZV_LB_Onboarding( $settings, $legacy_updater );
llb_onboarding_invoke( $legacy_wizard, 'set_sharing_enabled', [ false ] );
llb_onboarding_assert( $legacy_updater->preference->opted_out, 'An older winning SDK should receive the equivalent legacy opt-out.' );

$GLOBALS['llb_multisite'] = true;
$GLOBALS['llb_site_options']['active_sitewide_plugins'] = [ plugin_basename( MZV_LB_FILE ) => time() ];
$network_wizard = new MZV_LB_Onboarding( $settings, new LLB_Test_Updater( new LLB_Test_Preference( true ) ) );
MZV_LB_Onboarding::mark_pending( true );
llb_onboarding_assert( $network_wizard->is_network(), 'Network-active plugin scope should be detected.' );
llb_onboarding_assert( 'pending' === $GLOBALS['llb_site_options'][ MZV_LB_Onboarding::STATE_OPTION ]['status'], 'Network activation should use network option storage.' );
llb_onboarding_assert( false === ( $GLOBALS['llb_options'][ MZV_LB_Onboarding::STATE_OPTION ]['network'] ?? false ), 'Network state must not be written into site options.' );

$GLOBALS['llb_capabilities']['manage_network_options'] = false;
llb_onboarding_assert( false === llb_onboarding_invoke( $network_wizard, 'can_manage' ), 'Site administrators must not manage network onboarding.' );
$GLOBALS['llb_capabilities']['manage_network_options'] = true;
llb_onboarding_assert( true === llb_onboarding_invoke( $network_wizard, 'can_manage' ), 'Network administrators should manage network onboarding.' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-onboarding.php' );
llb_onboarding_assert( false !== $source, 'Onboarding source should be readable.' );
llb_onboarding_assert( false === strpos( $source, 'add_options_page(' ), 'Onboarding must not register a duplicate site settings menu.' );
llb_onboarding_assert( false === strpos( $source, 'add_submenu_page(' ), 'Onboarding must not register a duplicate network settings menu.' );
llb_onboarding_assert( false !== strpos( $source, "if ( 'GET' !== \$method )" ), 'The onboarding redirect must remain limited to safe GET requests.' );
llb_onboarding_assert( false !== strpos( $source, 'isset( $_POST[ $field ] )' ), 'Skipping privacy setup must preserve a posted telemetry choice.' );
llb_onboarding_assert( false !== strpos( $source, "if ( 'back' === \$action )" ), 'Step two must provide a server-handled path back to privacy.' );

echo "Little Lightbox onboarding tests passed.\n";
