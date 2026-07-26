<?php
/**
 * Little Lightbox React admin shell and WPRM conflict notices.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Admin {

	const SCRIPT_HANDLE = 'little-lightbox-admin';

	/** @var MZV_LB_Onboarding */
	private $onboarding;

	/** @var string[] */
	private $page_hooks = [];

	public function __construct( MZV_LB_Onboarding $onboarding ) {
		$this->onboarding = $onboarding;
	}

	/**
	 * Register the single plugin interface in site and Network Admin settings.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'network_admin_menu', [ $this, 'add_network_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_notices', [ $this, 'render_conflict_notice' ] );
		add_action( 'wp_ajax_mzv_lb_dismiss_conflict', [ $this, 'dismiss_conflict_notice' ] );
	}

	/**
	 * Add one site-level menu entry under Settings.
	 */
	public function add_settings_page(): void {
		$hook = add_options_page(
			__( 'This Little Lightbox of Mine Settings', 'little-lightbox' ),
			__( 'This Little Lightbox of Mine', 'little-lightbox' ),
			'manage_options',
			MZV_LB_Onboarding::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
		if ( is_string( $hook ) ) {
			$this->page_hooks[] = $hook;
		}
	}

	/**
	 * Network-active installs get one equivalent entry under Network Settings.
	 */
	public function add_network_settings_page(): void {
		if ( ! $this->onboarding->is_network() ) {
			return;
		}

		$hook = add_submenu_page(
			'settings.php',
			__( 'This Little Lightbox of Mine Settings', 'little-lightbox' ),
			__( 'This Little Lightbox of Mine', 'little-lightbox' ),
			'manage_network_options',
			MZV_LB_Onboarding::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
		if ( is_string( $hook ) ) {
			$this->page_hooks[] = $hook;
		}
	}

	/**
	 * Load the compiled WordPress Components interface only on plugin pages.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, $this->page_hooks, true ) ) {
			return;
		}

		$asset_file = MZV_LB_DIR . 'assets/admin/index.asset.php';
		$script     = MZV_LB_DIR . 'assets/admin/index.js';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script ) ) {
			return;
		}

		$asset = require $asset_file;
		if ( ! is_array( $asset ) ) {
			$asset = [];
		}
		$dependencies = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : [];
		$version      = is_string( $asset['version'] ?? null ) ? $asset['version'] : MZV_LB_VERSION;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			MZV_LB_URL . 'assets/admin/index.js',
			$dependencies,
			$version,
			true
		);
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.MZVLittleLightboxAdmin = ' . wp_json_encode( $this->client_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
			'before'
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'little-lightbox' );

		$style = MZV_LB_DIR . 'assets/admin/style-index.css';
		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				self::SCRIPT_HANDLE,
				MZV_LB_URL . 'assets/admin/style-index.css',
				[ 'wp-components' ],
				$version
			);
			wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
		}
	}

	/**
	 * Render the shared mount point. React owns presentation, not persistence.
	 */
	public function render_admin_page(): void {
		$network = is_network_admin() && $this->onboarding->is_network();
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap llb-admin-wrap">
			<div id="little-lightbox-admin-root"></div>
			<noscript>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'JavaScript is required to manage Little Lightbox settings.', 'little-lightbox' ); ?></p></div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Return escaped-by-serialization bootstrap data for the admin application.
	 */
	public function client_data(): array {
		// This query parameter selects presentation only; writes remain nonce protected.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$network        = is_network_admin() && $this->onboarding->is_network();
		$welcome        = $this->onboarding->client_data();

		return [
			'network'  => $network,
			'plugin'   => [
				'name'    => __( 'This Little Lightbox of Mine', 'little-lightbox' ),
				'version' => MZV_LB_VERSION,
			],
			'settings' => [
				'actionUrl'   => admin_url( 'options.php' ),
				'nonce'       => wp_create_nonce( MZV_LB_Settings::OPTION_KEY . '-options' ),
				'nonceName'   => '_wpnonce',
				'optionName'  => MZV_LB_Settings::OPTION_KEY,
				'optionPage'  => MZV_LB_Settings::OPTION_KEY,
				'options'     => MZV_LB_Settings::get_options(),
				'wprmActive'  => function_exists( 'WPRM' ) || class_exists( 'WP_Recipe_Maker' ),
			],
			'telemetry' => [
				'details'      => $welcome['telemetryDetails'],
				'enabled'      => $welcome['sharingEnabled'],
				'fieldName'    => $welcome['sharingFieldName'],
				'network'      => $this->onboarding->is_network(),
				'networkUrl'   => $this->onboarding->is_network() ? $this->onboarding->settings_url() : '',
				'nonce'        => wp_create_nonce( 'um_telemetry_preference_little-lightbox' ),
				'nonceName'    => '_um_telemetry_nonce_little-lightbox',
				'privacyUrl'   => $welcome['privacyUrl'],
			],
			'view'      => 'welcome' === $requested_view ? 'welcome' : 'settings',
			'welcome'   => $welcome,
		];
	}

	/**
	 * Check if WPRM's clickable images feature is active.
	 */
	private function is_wprm_lightbox_active(): bool {
		if ( ! ( function_exists( 'WPRM' ) || class_exists( 'WP_Recipe_Maker' ) ) ) {
			return false;
		}
		if ( ! class_exists( 'WPRM_Settings' ) ) {
			return false;
		}
		return WPRM_Settings::get( 'recipe_image_clickable' )
			|| WPRM_Settings::get( 'instruction_image_clickable' );
	}

	/**
	 * Render WPRM conflict admin notice.
	 */
	public function render_conflict_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$activation = get_transient( 'mzv_lb_activation_notice' );
		if ( $activation ) {
			delete_transient( 'mzv_lb_activation_notice' );
		}

		if ( ! $this->is_wprm_lightbox_active() ) {
			return;
		}

		$options = MZV_LB_Settings::get_options();
		if ( ! empty( $options['wprm_conflict_dismissed'] ) && ! $activation ) {
			return;
		}

		$nonce = wp_create_nonce( 'mzv_lb_dismiss_nonce' );
		?>
		<div class="notice notice-warning is-dismissible" id="llb-conflict-notice">
			<p>
				<strong><?php esc_html_e( 'Lightbox:', 'little-lightbox' ); ?></strong>
				<?php esc_html_e( "WP Recipe Maker's clickable images feature is enabled. This wraps recipe images in links, which prevents This Little Lightbox of Mine from handling them. To let This Little Lightbox of Mine manage recipe images, disable clickable images in WPRM Settings.", 'little-lightbox' ); ?>
			</p>
		</div>
		<script>
		(function(){
			var notice = document.getElementById('llb-conflict-notice');
			if (!notice) return;
			notice.addEventListener('click', function(e) {
				if (!e.target.closest('.notice-dismiss')) return;
				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxurl);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=mzv_lb_dismiss_conflict&_ajax_nonce=<?php echo esc_js( $nonce ); ?>');
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissing the conflict notice.
	 */
	public function dismiss_conflict_notice(): void {
		check_ajax_referer( 'mzv_lb_dismiss_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$options = MZV_LB_Settings::get_options();
		$options['wprm_conflict_dismissed'] = true;
		update_option( MZV_LB_Settings::OPTION_KEY, $options );

		wp_send_json_success();
	}
}
