<?php
/**
 * Plugin-owned welcome and setup flow for Little Lightbox.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Onboarding {

	const PAGE_SLUG    = 'little-lightbox-setup';
	const STATE_OPTION = 'mzv_lb_onboarding_state';
	const NONCE_ACTION = 'mzv_lb_onboarding';
	const NONCE_NAME   = '_mzv_lb_onboarding_nonce';
	const TELEMETRY_DISCLOSURE = 'Shares the site URL and name; plugin, SDK, WordPress, and PHP versions; environment and multisite scope; and bounded Little Lightbox values for mode, caption source, gallery, animations, recipe-card images, Jump to Recipe, ad layering, and icon size. It never shares post content, image details, user data, license or site keys, arbitrary URLs, or free-form text. Updates keep working when sharing is off.';

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
	 * Register site and Network Admin integration.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_site_page' ] );
		add_action( 'network_admin_menu', [ $this, 'add_network_page' ] );
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
		return $this->page_url();
	}

	public static function telemetry_disclosure( bool $translate = true ): string {
		if ( ! $translate ) {
			return self::TELEMETRY_DISCLOSURE;
		}
		return __( 'Shares the site URL and name; plugin, SDK, WordPress, and PHP versions; environment and multisite scope; and bounded Little Lightbox values for mode, caption source, gallery, animations, recipe-card images, Jump to Recipe, ad layering, and icon size. It never shares post content, image details, user data, license or site keys, arbitrary URLs, or free-form text. Updates keep working when sharing is off.', 'little-lightbox' );
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

	public function add_site_page(): void {
		if ( $this->network ) {
			return;
		}

		add_options_page(
			__( 'Little Lightbox Setup', 'little-lightbox' ),
			__( 'Little Lightbox Setup', 'little-lightbox' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function add_network_page(): void {
		if ( ! $this->network ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			__( 'Little Lightbox Setup', 'little-lightbox' ),
			__( 'Little Lightbox Setup', 'little-lightbox' ),
			'manage_network_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Redirect once after activation or an upgrade that introduced onboarding.
	 */
	public function maybe_redirect(): void {
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
		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/**
	 * Process wizard actions under one plugin-owned nonce.
	 */
	public function handle_submission(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		// Route selection is read-only; the action nonce is verified below before mutation.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'POST' !== $method || self::PAGE_SLUG !== $page ) {
			return;
		}
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage Little Lightbox setup.', 'little-lightbox' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
		$action = sanitize_key( wp_unslash( $_POST['llb_onboarding_action'] ?? '' ) );

		if ( 'save_telemetry' === $action ) {
			$field   = $this->sharing_field_name();
			$enabled = ! empty( $_POST[ $field ] );
			$this->set_sharing_enabled( $enabled );
			$this->save_state( 'in_progress', 2 );
			$this->redirect_to_page();
		}

		if ( 'save_setup' === $action ) {
			if ( ! $this->network ) {
				$this->save_plugin_setup();
			}
			$this->save_state( 'completed', 2 );
			$this->redirect_to_page();
		}

		if ( 'skip' === $action ) {
			$this->save_state( 'skipped', $this->state()['step'] );
			$this->redirect_to_page();
		}

		if ( 'restart' === $action ) {
			$this->save_state( 'in_progress', 1 );
			$this->redirect_to_page();
		}
	}

	/**
	 * Keep setup and settings reachable from the Plugins screen.
	 */
	public function plugin_action_links( array $links ): array {
		$label = in_array( $this->state()['status'], [ 'completed', 'skipped' ], true )
			? __( 'Settings', 'little-lightbox' )
			: __( 'Setup', 'little-lightbox' );
		$url   = in_array( $this->state()['status'], [ 'completed', 'skipped' ], true ) && ! $this->network
			? admin_url( 'options-general.php?page=little-lightbox' )
			: $this->page_url();

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' );
		return $links;
	}

	public function render_page(): void {
		if ( ! $this->can_manage() ) {
			return;
		}

		$state = $this->state();
		?>
		<div class="wrap llb-setup-wrap">
			<h1><?php esc_html_e( 'Welcome to This Little Lightbox of Mine', 'little-lightbox' ); ?></h1>
			<?php if ( in_array( $state['status'], [ 'completed', 'skipped' ], true ) ) : ?>
				<?php $this->render_finished( $state['status'] ); ?>
			<?php elseif ( 2 === $state['step'] ) : ?>
				<?php $this->render_setup_step(); ?>
			<?php else : ?>
				<?php $this->render_privacy_step(); ?>
			<?php endif; ?>
		</div>
		<?php $this->render_styles(); ?>
		<?php
	}

	private function render_privacy_step(): void {
		?>
		<p class="llb-setup-progress"><?php esc_html_e( 'Step 1 of 2: Privacy', 'little-lightbox' ); ?></p>
		<div class="llb-setup-panel">
			<h2><?php esc_html_e( 'Help improve Little Lightbox', 'little-lightbox' ); ?></h2>
			<?php if ( $this->network ) : ?>
				<p><?php esc_html_e( 'Choose whether this WordPress network shares optional update and feature-state telemetry with Update Machine. Sharing is on by default and can be changed later from Network Admin.', 'little-lightbox' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Choose whether this site shares optional update and feature-state telemetry with Update Machine. Sharing is on by default and can be changed later under Settings.', 'little-lightbox' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="llb_onboarding_action" value="save_telemetry">
				<?php $this->render_sharing_control(); ?>
				<div class="llb-setup-actions">
					<?php submit_button( __( 'Continue', 'little-lightbox' ), 'primary', 'submit', false ); ?>
					<button type="submit" class="button" name="llb_onboarding_action" value="skip"><?php esc_html_e( 'Skip setup', 'little-lightbox' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_setup_step(): void {
		$options = MZV_LB_Settings::get_options();
		?>
		<p class="llb-setup-progress"><?php esc_html_e( 'Step 2 of 2: Lightbox setup', 'little-lightbox' ); ?></p>
		<div class="llb-setup-panel">
			<h2><?php esc_html_e( 'Choose the starting behavior', 'little-lightbox' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="llb_onboarding_action" value="save_setup">
				<?php if ( $this->network ) : ?>
					<p><?php esc_html_e( 'Little Lightbox is network active. Lightbox behavior remains site-specific, so configure each site from its own Settings screen.', 'little-lightbox' ); ?></p>
				<?php else : ?>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Mode', 'little-lightbox' ); ?></strong></legend>
						<label><input type="radio" name="lightbox_mode" value="enhanced" <?php checked( $options['lightbox_mode'], 'enhanced' ); ?>> <?php esc_html_e( 'Enhanced: galleries, captions, animation, keyboard and swipe controls', 'little-lightbox' ); ?></label><br>
						<label><input type="radio" name="lightbox_mode" value="css" <?php checked( $options['lightbox_mode'], 'css' ); ?>> <?php esc_html_e( 'CSS-only: open and close with no JavaScript', 'little-lightbox' ); ?></label>
					</fieldset>
					<p><label><input type="checkbox" name="gallery_enabled" value="1" <?php checked( ! empty( $options['gallery_enabled'] ) ); ?>> <?php esc_html_e( 'Enable gallery browsing', 'little-lightbox' ); ?></label></p>
					<p><label><input type="checkbox" name="animations_enabled" value="1" <?php checked( ! empty( $options['animations_enabled'] ) ); ?>> <?php esc_html_e( 'Enable lightbox animations', 'little-lightbox' ); ?></label></p>
				<?php endif; ?>
				<div class="llb-setup-actions">
					<?php submit_button( __( 'Finish setup', 'little-lightbox' ), 'primary', 'submit', false ); ?>
					<button type="submit" class="button" name="llb_onboarding_action" value="skip"><?php esc_html_e( 'Skip setup', 'little-lightbox' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_finished( string $status ): void {
		$skipped = 'skipped' === $status;
		?>
		<div class="llb-setup-panel">
			<h2><?php echo esc_html( $skipped ? __( 'Setup skipped', 'little-lightbox' ) : __( 'Setup complete', 'little-lightbox' ) ); ?></h2>
			<p><?php esc_html_e( 'Your telemetry choice and Little Lightbox settings remain available after this wizard.', 'little-lightbox' ); ?></p>
			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>" class="llb-setup-actions">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<button type="submit" class="button" name="llb_onboarding_action" value="restart"><?php esc_html_e( 'Review setup', 'little-lightbox' ); ?></button>
				<?php if ( ! $this->network ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'options-general.php?page=little-lightbox' ) ); ?>"><?php esc_html_e( 'Open settings', 'little-lightbox' ); ?></a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	private function render_sharing_control(): void {
		$preference = $this->preference();
		if ( $preference && method_exists( $preference, 'render_control' ) ) {
			$preference->render_control( false );
			return;
		}

		$checked = $this->sharing_enabled();
		?>
		<fieldset class="um-telemetry-preference">
			<label><input type="checkbox" name="<?php echo esc_attr( $this->sharing_field_name() ); ?>" value="1" <?php checked( $checked ); ?>> <?php esc_html_e( 'Share optional update and feature telemetry', 'little-lightbox' ); ?></label>
			<p class="description"><?php echo esc_html( self::telemetry_disclosure() ); ?> <a href="https://updatemachine.com/privacy" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'little-lightbox' ); ?></a></p>
		</fieldset>
		<?php
	}

	private function save_plugin_setup(): void {
		$options = MZV_LB_Settings::get_options();
		// The wizard nonce and capability are verified before this method is called.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mode    = sanitize_key( wp_unslash( $_POST['lightbox_mode'] ?? '' ) );
		$options['lightbox_mode']      = in_array( $mode, [ 'css', 'enhanced' ], true ) ? $mode : 'enhanced';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options['gallery_enabled']    = ! empty( $_POST['gallery_enabled'] );
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

	private function page_url(): string {
		$path = $this->network ? 'settings.php' : 'options-general.php';
		$base = $this->network ? network_admin_url( $path ) : admin_url( $path );
		return add_query_arg( 'page', self::PAGE_SLUG, $base );
	}

	private function redirect_to_page(): void {
		wp_safe_redirect( $this->page_url() );
		exit;
	}

	private function render_styles(): void {
		?>
		<style>
		.llb-setup-wrap { max-width: 760px; }
		.llb-setup-progress { color: #50575e; font-weight: 600; }
		.llb-setup-panel { background: #fff; border: 1px solid #c3c4c7; padding: 24px; }
		.llb-setup-panel h2 { margin-top: 0; }
		.llb-setup-panel fieldset { margin: 20px 0; }
		.llb-setup-actions { align-items: center; display: flex; gap: 8px; margin-top: 24px; }
		.llb-setup-actions .submit { margin: 0; padding: 0; }
		</style>
		<?php
	}
}
