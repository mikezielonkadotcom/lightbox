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
	 */
	public function sanitize( array $input ): array {
		$existing = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing = wp_parse_args( $existing, self::defaults() );

		$clean = [];

		$clean['lightbox_mode'] = in_array( ( $input['lightbox_mode'] ?? '' ), [ 'css', 'enhanced' ], true )
			? $input['lightbox_mode'] : 'enhanced';

		$is_css_save = 'css' === $clean['lightbox_mode'];

		if ( array_key_exists( 'caption_source', $input ) ) {
			$clean['caption_source'] = in_array( $input['caption_source'], [ 'alt', 'title', 'description', 'none' ], true )
				? $input['caption_source'] : 'alt';
		} else {
			$clean['caption_source'] = $existing['caption_source'];
		}

		$clean['wprm_jump_enabled']    = ! empty( $input['wprm_jump_enabled'] );
		$clean['min_image_width']      = max( 0, (int) ( $input['min_image_width'] ?? 0 ) );
		$clean['excluded_classes']     = sanitize_text_field( $input['excluded_classes'] ?? '' );
		$clean['recipe_card_lightbox'] = ! empty( $input['recipe_card_lightbox'] );

		// Enhanced-only controls are disabled in CSS mode and therefore omitted from
		// the Settings API payload. Preserve saved values instead of resetting them.
		$clean['gallery_enabled'] = ( $is_css_save && ! array_key_exists( 'gallery_enabled', $input ) )
			? (bool) $existing['gallery_enabled']
			: ! empty( $input['gallery_enabled'] );

		$clean['animations_enabled'] = ( $is_css_save && ! array_key_exists( 'animations_enabled', $input ) )
			? (bool) $existing['animations_enabled']
			: ! empty( $input['animations_enabled'] );

		$clean['animation_duration_ms'] = ( $is_css_save && ! array_key_exists( 'animation_duration_ms', $input ) )
			? (int) $existing['animation_duration_ms']
			: min( 1000, max( 50, (int) ( $input['animation_duration_ms'] ?? 200 ) ) );

		// Preserve dismissed state across saves.
		$clean['wprm_conflict_dismissed'] = ! empty( $existing['wprm_conflict_dismissed'] );

		return $clean;
	}
}
