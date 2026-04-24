<?php
/**
 * MZV Lightbox Settings — WP Settings API registration + defaults.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Settings {

	const OPTION_KEY = 'mzv_lightbox_options';

	/**
	 * Canonical defaults.
	 */
	public static function defaults(): array {
		return [
			'lightbox_mode'           => 'enhanced',
			'caption_source'          => 'alt',
			'wprm_jump_enabled'       => true,
			'min_image_width'         => 0,
			'excluded_classes'        => '',
			'recipe_card_lightbox'    => true,
			'gallery_enabled'         => true,
			'animations_enabled'      => true,
			'animation_duration_ms'   => 200,
			'wprm_conflict_dismissed' => false,
		];
	}

	/**
	 * Get all options merged with defaults.
	 */
	public static function get_options(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single option value.
	 */
	public static function get_option( string $key ) {
		$opts = self::get_options();
		return $opts[ $key ] ?? null;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings with WP Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	/**
	 * Sanitize callback for settings save.
	 *
	 * When CSS-Only mode is active the Enhanced-only fields are disabled in
	 * the browser and therefore NOT submitted with the form.  To prevent
	 * those values from being silently reset to their defaults we merge the
	 * incoming $input against the currently stored values before sanitising.
	 */
	public function sanitize( array $input ): array {
		$clean = [];

		// Fetch what is currently stored so we can fall back to it for any
		// field that was omitted from the submitted form (e.g. because the
		// field was disabled by the CSS-Only mode toggle).
		$existing = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing = wp_parse_args( $existing, self::defaults() );

		// Helper: use submitted value when present, otherwise keep existing.
		$submitted = function ( string $key ) use ( $input, $existing ) {
			return array_key_exists( $key, $input ) ? $input[ $key ] : $existing[ $key ];
		};

		$clean['lightbox_mode'] = in_array( ( $input['lightbox_mode'] ?? '' ), [ 'css', 'enhanced' ], true )
			? $input['lightbox_mode'] : 'enhanced';

		// Enhanced-only fields: fall back to existing value when not submitted.
		$caption_source_raw = $submitted( 'caption_source' );
		$clean['caption_source'] = in_array( $caption_source_raw, [ 'alt', 'title', 'description', 'none' ], true )
			? $caption_source_raw : 'alt';

		$clean['wprm_jump_enabled']     = (bool) $submitted( 'wprm_jump_enabled' );
		$clean['gallery_enabled']       = (bool) $submitted( 'gallery_enabled' );
		$clean['animations_enabled']    = (bool) $submitted( 'animations_enabled' );
		$clean['animation_duration_ms'] = min( 1000, max( 50, (int) $submitted( 'animation_duration_ms' ) ) );

		// Fields available in both modes — always use submitted value.
		$clean['min_image_width']      = max( 0, (int) ( $input['min_image_width'] ?? 0 ) );
		$clean['excluded_classes']     = sanitize_text_field( $input['excluded_classes'] ?? '' );
		$clean['recipe_card_lightbox'] = ! empty( $input['recipe_card_lightbox'] );

		// Preserve dismissed state across saves.
		$clean['wprm_conflict_dismissed'] = ! empty( $existing['wprm_conflict_dismissed'] );

		return $clean;
	}
}
