<?php
/**
 * Plugin-owned welcome and setup state for Little Lightbox.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Onboarding {

	const PAGE_SLUG    = 'little-lightbox';
	const STATE_OPTION = 'mzv_lb_onboarding_state';
	const NONCE_ACTION = 'mzv_lb_onboarding';
	const NONCE_NAME   = '_mzv_lb_onboarding_nonce';
	const TELEMETRY_DISCLOSURE = 'Shares the site URL and name; plugin, SDK, WordPress, and PHP versions; environment and multisite scope; bounded Little Lightbox settings; and coarse eligible-render active-day and recency ranges. It does not count browser opens or send raw events, dates, post content, image details, user data, license or site keys, arbitrary URLs, or free-form text. Updates keep working when sharing is off.';

	/** @var MZV_LB_Settings */
	private $settings;

	/** @var object|null */
	private $updater;

	/** @var bool */
	private $network;

	public function __construct( MZV_LB_Settings $settings, $updater = null ) {
		$this->settings = $settings;
		$this->updater  = is_object( $updater ) ? $updater : null;
		$this->network  = $this->detect_network_scope();
	}

	/**
	 * Register state handling without creating a second admin menu entry.
	 */
	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'handle_submission' ], 5 );
		add_action( 'admin_init', [ $this, 'maybe_redirect' ], 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( MZV_LB_FILE ), [ $this, 'plugin_action_links' ] );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( MZV_LB_FILE ), [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Seed onboarding only on the first activation in the relevant scope.
	 */
	public static function mark_pending( bool $network_wide = false ): void {
		$state = [
			'status'  => 'pending',
			'step'    => 1,
			'version' => MZV_LB_VERSION,
		];

		if ( $network_wide && is_multisite() ) {
			if ( false === get_site_option( self::STATE_OPTION, false ) ) {
				update_site_option( self::STATE_OPTION, $state );
			}
			return;
		}

		if ( false === get_option( self::STATE_OPTION, false ) ) {
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	public function is_network(): bool {
		return $this->network;
	}

	public function url(): string {
		return $this->welcome_url();
	}

	public function settings_url(): string {
		$path = $this->network ? 'settings.php' : 'options-general.php';
		$base = $this->network ? network_admin_url( $path ) : admin_url( $path );
		return add_query_arg( 'page', self::PAGE_SLUG, $base );
	}

	public static function telemetry_disclosure( bool $translate = true ): string {
		if ( ! $translate ) {
			return self::TELEMETRY_DISCLOSURE;
		}
		return __( 'Shares the site URL and name; plugin, SDK, WordPress, and PHP versions; environment and multisite scope; bounded Little Lightbox settings; and coarse eligible-render active-day and recency ranges. It does not count browser opens or send raw events, dates, post content, image details, user data, license or site keys, arbitrary URLs, or free-form text. Updates keep working when sharing is off.', 'little-lightbox' );
	}

	/**
	 * Return normalized progress for rendering and resume behavior.
	 */
	public function state(): array {
		$state = $this->get_scoped_option( self::STATE_OPTION, [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}

		$status = $state['status'] ?? 'pending';
		if ( ! in_array( $status, [ 'pending', 'in_progress', 'completed', 'skipped' ], true ) ) {
			$status = 'pending';
		}

		return [
			'status'  => $status,
			'step'    => 2 === (int) ( $state['step'] ?? 1 ) ? 2 : 1,
			'version' => sanitize_text_field( (string) ( $state['version'] ?? MZV_LB_VERSION ) ),
		];
	}

	/**
	 * Return the bounded state needed by the React welcome interface.
	 */
	public function client_data(): array {
		return [
			'actionUrl'        => $this->welcome_url(),
			'network'          => $this->network,
			'nonce'            => wp_create_nonce( self::NONCE_ACTION ),
			'nonceName'        => self::NONCE_NAME,
			'options'          => $this->network ? [] : MZV_LB_Settings::get_options(),
			'privacyUrl'       => 'https://updatemachine.com/privacy',
			'settingsUrl'      => $this->settings_url(),
			'sharingEnabled'   => $this->sharing_enabled(),
			'sharingFieldName' => $this->sharing_field_name(),
			'state'            => $this->state(),
			'telemetryDetails' => self::telemetry_disclosure(),
		];
	}

	/**
	 * Redirect once after activation or an upgrade that introduced onboarding.
	 */
	public function maybe_redirect(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'GET' !== $method ) {
			return;
		}

		if ( ! $this->can_manage() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		// These core flags only suppress a redirect; they never change stored data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) || isset( $_GET['bulk-activate'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG === $page ) {
			return;
		}

		$raw = $this->get_scoped_option( self::STATE_OPTION, false );
		if ( false === $raw ) {
			$this->save_state( 'pending', 1 );
		}

		$state = $this->state();
		if ( 'pending' !== $state['status'] ) {
			return;
		}

		$this->save_state( 'in_progress', 1 );
		$this->redirect_to_welcome();
	}

	/**
	 * Process wizard actions under one plugin-owned nonce.
	 */
	public function handle_submission(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		// Route selection is read-only; the action nonce is verified below before mutation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_key( wp_unslash( $_POST['llb_onboarding_action'] ?? '' ) );
		if ( 'POST' !== $method || self::PAGE_SLUG !== $page || '' === $action ) {
			return;
		}
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage Little Lightbox setup.', 'little-lightbox' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'save_telemetry' === $action ) {
			$field = $this->sharing_field_name();
			// The plugin nonce and capability are verified immediately above.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$enabled = ! empty( $_POST[ $field ] );
			$this->set_sharing_enabled( $enabled );
			$this->save_state( 'in_progress', 2 );
			$this->redirect_to_welcome();
		}

		if ( 'save_setup' === $action ) {
			if ( ! $this->network ) {
				$this->save_plugin_setup();
			}
			$this->save_state( 'completed', 2 );
			$this->redirect_to_welcome();
		}

		if ( 'back' === $action ) {
			$this->save_state( 'in_progress', 1 );
			$this->redirect_to_welcome();
		}

		if ( 'save_preference' === $action ) {
			$field = $this->sharing_field_name();
			// The plugin nonce and capability are verified immediately above.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->set_sharing_enabled( ! empty( $_POST[ $field ] ) );
			$this->redirect_to_settings();
		}

		if ( 'skip' === $action ) {
			$field = $this->sharing_field_name();
			// Step one posts the current choice; step two intentionally does not.
			// The plugin nonce and capability are verified immediately above.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $field ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$this->set_sharing_enabled( ! empty( $_POST[ $field ] ) );
			}
			$this->save_state( 'skipped', $this->state()['step'] );
			$this->redirect_to_welcome();
		}

		if ( 'restart' === $action ) {
			$this->save_state( 'in_progress', 1 );
			$this->redirect_to_welcome();
		}
	}

	/**
	 * Keep setup and settings reachable from the Plugins screen.
	 */
	public function plugin_action_links( array $links ): array {
		$finished = in_array( $this->state()['status'], [ 'completed', 'skipped' ], true );
		$label    = $finished ? __( 'Settings', 'little-lightbox' ) : __( 'Setup', 'little-lightbox' );
		$url      = $finished ? $this->settings_url() : $this->welcome_url();

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' );
		return $links;
	}

	private function save_plugin_setup(): void {
		$options = MZV_LB_Settings::get_options();
		// The wizard nonce and capability are verified before this method is called.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mode = sanitize_key( wp_unslash( $_POST['lightbox_mode'] ?? '' ) );
		$options['lightbox_mode'] = in_array( $mode, [ 'css', 'enhanced' ], true ) ? $mode : 'enhanced';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options['gallery_enabled'] = ! empty( $_POST['gallery_enabled'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options['animations_enabled'] = ! empty( $_POST['animations_enabled'] );
		update_option( MZV_LB_Settings::OPTION_KEY, $options, false );
	}

	private function preference() {
		if ( ! $this->updater ) {
			return null;
		}
		if ( method_exists( $this->updater, 'telemetry_preference' ) ) {
			return $this->updater->telemetry_preference();
		}
		if ( method_exists( $this->updater, 'telemetry_opt_out' ) ) {
			return $this->updater->telemetry_opt_out();
		}
		return null;
	}

	private function sharing_enabled(): bool {
		$preference = $this->preference();
		if ( $preference && method_exists( $preference, 'is_enabled' ) ) {
			return $preference->is_enabled();
		}
		if ( $preference && method_exists( $preference, 'is_opted_out' ) ) {
			return ! $preference->is_opted_out();
		}
		return true;
	}

	private function set_sharing_enabled( bool $enabled ): void {
		$preference = $this->preference();
		if ( $preference && method_exists( $preference, 'set_enabled' ) ) {
			$preference->set_enabled( $enabled );
			return;
		}
		if ( $preference && method_exists( $preference, 'set_opted_out' ) ) {
			$preference->set_opted_out( ! $enabled );
		}
	}

	private function sharing_field_name(): string {
		$preference = $this->preference();
		return $preference && method_exists( $preference, 'field_name' )
			? $preference->field_name()
			: 'mzv_lb_share_telemetry';
	}

	private function detect_network_scope(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		$active = (array) get_site_option( 'active_sitewide_plugins', [] );
		return isset( $active[ plugin_basename( MZV_LB_FILE ) ] );
	}

	private function can_manage(): bool {
		return current_user_can( $this->network ? 'manage_network_options' : 'manage_options' );
	}

	private function get_scoped_option( string $key, $default = false ) {
		return $this->network ? get_site_option( $key, $default ) : get_option( $key, $default );
	}

	private function save_state( string $status, int $step ): void {
		$state = [
			'status'  => $status,
			'step'    => 2 === $step ? 2 : 1,
			'version' => MZV_LB_VERSION,
		];
		if ( $this->network ) {
			update_site_option( self::STATE_OPTION, $state );
			return;
		}
		update_option( self::STATE_OPTION, $state, false );
	}

	private function welcome_url(): string {
		return add_query_arg( 'view', 'welcome', $this->settings_url() );
	}

	private function redirect_to_welcome(): void {
		wp_safe_redirect( $this->welcome_url() );
		exit;
	}

	private function redirect_to_settings(): void {
		wp_safe_redirect( $this->settings_url() );
		exit;
	}
}
