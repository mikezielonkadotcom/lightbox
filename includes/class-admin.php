<?php
/**
 * MZV Lightbox Admin — settings page + WPRM conflict notices.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Admin {

	/** @var MZV_LB_Settings */
	private $settings;

	public function __construct( MZV_LB_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_sections_and_fields' ] );
		add_action( 'admin_notices', [ $this, 'render_conflict_notice' ] );
		add_action( 'wp_ajax_mzv_lb_dismiss_conflict', [ $this, 'dismiss_conflict_notice' ] );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'MZV Lightbox Settings', 'mzv-lightbox' ),
			__( 'MZV Lightbox', 'mzv-lightbox' ),
			'manage_options',
			'mzv-lightbox',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings sections and fields.
	 */
	public function register_sections_and_fields(): void {
		$page   = 'mzv-lightbox';
		$option = MZV_LB_Settings::OPTION_KEY;

		// Section: Mode.
		add_settings_section( 'mzv_lb_mode', __( 'Lightbox Mode', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'lightbox_mode', __( 'Mode', 'mzv-lightbox' ), [ $this, 'field_lightbox_mode' ], $page, 'mzv_lb_mode' );

		// Section: Caption.
		add_settings_section( 'mzv_lb_caption', __( 'Caption', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'caption_source', __( 'Caption Source', 'mzv-lightbox' ), [ $this, 'field_caption_source' ], $page, 'mzv_lb_caption' );

		// Section: Visibility.
		add_settings_section( 'mzv_lb_visibility', __( 'Visibility', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'min_image_width', __( 'Min Image Width', 'mzv-lightbox' ), [ $this, 'field_min_image_width' ], $page, 'mzv_lb_visibility' );
		add_settings_field( 'excluded_classes', __( 'Excluded Classes', 'mzv-lightbox' ), [ $this, 'field_excluded_classes' ], $page, 'mzv_lb_visibility' );
		add_settings_field( 'recipe_card_lightbox', __( 'Recipe Card Images', 'mzv-lightbox' ), [ $this, 'field_recipe_card_lightbox' ], $page, 'mzv_lb_visibility' );

		// Section: Gallery.
		add_settings_section( 'mzv_lb_gallery', __( 'Gallery', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'gallery_enabled', __( 'Gallery Browsing', 'mzv-lightbox' ), [ $this, 'field_gallery_enabled' ], $page, 'mzv_lb_gallery' );

		// Section: Animations.
		add_settings_section( 'mzv_lb_animations', __( 'Animations', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'animations_enabled', __( 'Animations', 'mzv-lightbox' ), [ $this, 'field_animations_enabled' ], $page, 'mzv_lb_animations' );
		add_settings_field( 'animation_duration_ms', __( 'Duration', 'mzv-lightbox' ), [ $this, 'field_animation_duration' ], $page, 'mzv_lb_animations' );

		// Section: WPRM.
		add_settings_section( 'mzv_lb_wprm', __( 'WPRM Integration', 'mzv-lightbox' ), '__return_false', $page );
		add_settings_field( 'wprm_jump_enabled', __( 'Jump to Recipe', 'mzv-lightbox' ), [ $this, 'field_wprm_jump' ], $page, 'mzv_lb_wprm' );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( MZV_LB_Settings::OPTION_KEY );
				do_settings_sections( 'mzv-lightbox' );
				submit_button();
				?>
			</form>
		</div>
		<style>
			.mzv-lb-enhanced-only { transition: opacity .2s; }
			.mzv-lb-enhanced-only.is-disabled { opacity: .5; pointer-events: none; }
		</style>
		<script>
		(function(){
			var radios = document.querySelectorAll('input[name="mzv_lightbox_options[lightbox_mode]"]');
			var enhanced = document.querySelectorAll('.mzv-lb-enhanced-only');
			function toggle() {
				var mode = document.querySelector('input[name="mzv_lightbox_options[lightbox_mode]"]:checked');
				var isCss = mode && mode.value === 'css';
				enhanced.forEach(function(el) {
					el.classList.toggle('is-disabled', isCss);
					var inputs = el.querySelectorAll('input, select');
					inputs.forEach(function(inp) { inp.disabled = isCss; });
				});
			}
			radios.forEach(function(r) { r.addEventListener('change', toggle); });
			toggle();
		})();
		</script>
		<?php
	}

	// ── Field Renderers ──────────────────────────────────────────────────

	public function field_lightbox_mode(): void {
		$opts = MZV_LB_Settings::get_options();
		$val  = $opts['lightbox_mode'];
		?>
		<fieldset>
			<label>
				<input type="radio" name="mzv_lightbox_options[lightbox_mode]" value="enhanced" <?php checked( $val, 'enhanced' ); ?>>
				<?php esc_html_e( 'Enhanced', 'mzv-lightbox' ); ?>
				<span class="description"><?php esc_html_e( '— Full JS lightbox with gallery, captions, animations & keyboard nav.', 'mzv-lightbox' ); ?></span>
			</label><br>
			<label>
				<input type="radio" name="mzv_lightbox_options[lightbox_mode]" value="css" <?php checked( $val, 'css' ); ?>>
				<?php esc_html_e( 'CSS-Only', 'mzv-lightbox' ); ?>
				<span class="description"><?php esc_html_e( '— Zero JavaScript. Pure CSS lightbox with open/close only.', 'mzv-lightbox' ); ?></span>
			</label>
		</fieldset>
		<?php
	}

	public function field_caption_source(): void {
		$opts = MZV_LB_Settings::get_options();
		$val  = $opts['caption_source'];
		$options = [
			'alt'         => __( 'Alt text', 'mzv-lightbox' ),
			'title'       => __( 'Title attribute', 'mzv-lightbox' ),
			'description' => __( 'Description (attachment)', 'mzv-lightbox' ),
			'none'        => __( 'None (no caption)', 'mzv-lightbox' ),
		];
		echo '<fieldset class="mzv-lb-enhanced-only">';
		foreach ( $options as $key => $label ) {
			printf(
				'<label><input type="radio" name="mzv_lightbox_options[caption_source]" value="%s" %s> %s</label><br>',
				esc_attr( $key ),
				checked( $val, $key, false ),
				esc_html( $label )
			);
		}
		echo '<p class="description">' . esc_html__( 'Available in Enhanced mode.', 'mzv-lightbox' ) . '</p>';
		echo '</fieldset>';
	}

	public function field_min_image_width(): void {
		$opts = MZV_LB_Settings::get_options();
		printf(
			'<input type="number" name="mzv_lightbox_options[min_image_width]" value="%d" min="0" step="1" class="small-text"> px',
			(int) $opts['min_image_width']
		);
		echo '<p class="description">' . esc_html__( '0 = all images eligible.', 'mzv-lightbox' ) . '</p>';
	}

	public function field_excluded_classes(): void {
		$opts = MZV_LB_Settings::get_options();
		printf(
			'<input type="text" name="mzv_lightbox_options[excluded_classes]" value="%s" class="regular-text" placeholder="alignright, sponsor-logo">',
			esc_attr( $opts['excluded_classes'] )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated CSS class names. .no-lightbox is always excluded.', 'mzv-lightbox' ) . '</p>';
	}

	public function field_recipe_card_lightbox(): void {
		$opts = MZV_LB_Settings::get_options();
		printf(
			'<label><input type="checkbox" name="mzv_lightbox_options[recipe_card_lightbox]" value="1" %s> %s</label>',
			checked( $opts['recipe_card_lightbox'], true, false ),
			esc_html__( 'Enable lightbox on WPRM recipe card images', 'mzv-lightbox' )
		);
	}

	public function field_gallery_enabled(): void {
		$opts = MZV_LB_Settings::get_options();
		echo '<div class="mzv-lb-enhanced-only">';
		printf(
			'<label><input type="checkbox" name="mzv_lightbox_options[gallery_enabled]" value="1" %s> %s</label>',
			checked( $opts['gallery_enabled'], true, false ),
			esc_html__( 'Enable prev/next gallery navigation', 'mzv-lightbox' )
		);
		echo '<p class="description">' . esc_html__( 'Available in Enhanced mode.', 'mzv-lightbox' ) . '</p>';
		echo '</div>';
	}

	public function field_animations_enabled(): void {
		$opts = MZV_LB_Settings::get_options();
		echo '<div class="mzv-lb-enhanced-only">';
		printf(
			'<label><input type="checkbox" name="mzv_lightbox_options[animations_enabled]" value="1" %s> %s</label>',
			checked( $opts['animations_enabled'], true, false ),
			esc_html__( 'Enable open/close animations', 'mzv-lightbox' )
		);
		echo '<p class="description">' . esc_html__( 'Available in Enhanced mode.', 'mzv-lightbox' ) . '</p>';
		echo '</div>';
	}

	public function field_animation_duration(): void {
		$opts = MZV_LB_Settings::get_options();
		echo '<div class="mzv-lb-enhanced-only">';
		printf(
			'<input type="number" name="mzv_lightbox_options[animation_duration_ms]" value="%d" min="50" max="1000" step="10" class="small-text"> ms',
			(int) $opts['animation_duration_ms']
		);
		echo '<p class="description">' . esc_html__( '50–1000 ms. Shown only when animations are enabled.', 'mzv-lightbox' ) . '</p>';
		echo '</div>';
	}

	public function field_wprm_jump(): void {
		$opts = MZV_LB_Settings::get_options();
		echo '<div class="mzv-lb-enhanced-only">';
		printf(
			'<label><input type="checkbox" name="mzv_lightbox_options[wprm_jump_enabled]" value="1" %s> %s</label>',
			checked( $opts['wprm_jump_enabled'], true, false ),
			esc_html__( 'Show "Jump to Recipe" link in lightbox (requires WPRM)', 'mzv-lightbox' )
		);

		if ( ! ( function_exists( 'WPRM' ) || class_exists( 'WP_Recipe_Maker' ) ) ) {
			echo '<p class="description">' . esc_html__( 'WPRM not detected.', 'mzv-lightbox' ) . '</p>';
		}

		echo '<p class="description">' . esc_html__( 'Available in Enhanced mode.', 'mzv-lightbox' ) . '</p>';
		echo '</div>';
	}

	// ── WPRM Conflict Notice ─────────────────────────────────────────────

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

		// Check activation transient.
		$activation = get_transient( 'mzv_lb_activation_notice' );
		if ( $activation ) {
			delete_transient( 'mzv_lb_activation_notice' );
		}

		if ( ! $this->is_wprm_lightbox_active() ) {
			return;
		}

		$opts = MZV_LB_Settings::get_options();
		if ( ! empty( $opts['wprm_conflict_dismissed'] ) && ! $activation ) {
			return;
		}

		$nonce = wp_create_nonce( 'mzv_lb_dismiss_nonce' );
		?>
		<div class="notice notice-warning is-dismissible" id="mzv-lb-conflict-notice">
			<p>
				<strong><?php esc_html_e( 'MZV Lightbox:', 'mzv-lightbox' ); ?></strong>
				<?php esc_html_e( "WP Recipe Maker's clickable images feature is enabled. This wraps recipe images in links, which prevents MZV Lightbox from handling them. To let MZV Lightbox manage recipe images, disable clickable images in WPRM → Settings → Lightbox.", 'mzv-lightbox' ); ?>
			</p>
		</div>
		<script>
		(function(){
			var notice = document.getElementById('mzv-lb-conflict-notice');
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

		$opts = MZV_LB_Settings::get_options();
		$opts['wprm_conflict_dismissed'] = true;
		update_option( MZV_LB_Settings::OPTION_KEY, $opts );

		wp_send_json_success();
	}
}
