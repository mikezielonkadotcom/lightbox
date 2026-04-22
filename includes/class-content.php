<?php
/**
 * MZV Lightbox Content Filter — DOM parsing + image wrapping for both modes.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_Content {

	/** @var MZV_LB_Settings */
	private $settings;

	/** @var bool|null Cached recipe detection for current post. */
	private $post_has_recipe = null;

	/** @var int|null Current post ID for cache invalidation. */
	private $cached_post_id = null;

	public function __construct( MZV_LB_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_filter( 'the_content', [ $this, 'wrap_images' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue frontend assets based on mode.
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$opts = MZV_LB_Settings::get_options();

		if ( 'css' === $opts['lightbox_mode'] ) {
			// CSS-Only mode: inline styles, zero JS.
			wp_register_style( 'mzv-lightbox-base', false );
			wp_enqueue_style( 'mzv-lightbox-base' );
			wp_add_inline_style( 'mzv-lightbox-base', MZV_LB_CSS_Mode::get_inline_css() );
			return;
		}

		// Enhanced mode: linked CSS + JS.
		wp_enqueue_style(
			'mzv-lightbox',
			MZV_LB_URL . 'assets/mzv-lightbox.css',
			[],
			MZV_LB_VERSION
		);

		wp_enqueue_script(
			'mzv-lightbox',
			MZV_LB_URL . 'assets/mzv-lightbox.js',
			[],
			MZV_LB_VERSION,
			true
		);

		$config = [
			'captionSource'      => $opts['caption_source'],
			'galleryEnabled'     => (bool) $opts['gallery_enabled'],
			'animationsEnabled'  => (bool) $opts['animations_enabled'],
			'animationDurationMs' => (int) $opts['animation_duration_ms'],
			'wprmJumpEnabled'    => (bool) $opts['wprm_jump_enabled'],
			'recipeCardSelector' => '.wprm-recipe-container',
			'i18n'               => [
				'close'        => __( 'Close image', 'mzv-lightbox' ),
				'prev'         => __( 'Previous image', 'mzv-lightbox' ),
				'next'         => __( 'Next image', 'mzv-lightbox' ),
				'counter'      => /* translators: %1$d current image, %2$d total */ __( '%1$d of %2$d', 'mzv-lightbox' ),
				'jumpToRecipe' => __( 'Jump to Recipe ↓', 'mzv-lightbox' ),
				'openImage'    => __( 'Open image in lightbox', 'mzv-lightbox' ),
			],
		];

		wp_localize_script( 'mzv-lightbox', 'mzvLbConfig', $config );
	}

	/**
	 * Filter the_content to wrap eligible images.
	 */
	public function wrap_images( string $content ): string {
		if ( is_admin() || empty( $content ) || ! is_singular() ) {
			return $content;
		}

		if ( stripos( $content, '<img' ) === false ) {
			return $content;
		}

		$opts = MZV_LB_Settings::get_options();

		$charset       = get_bloginfo( 'charset' );
		$libxml_errors = libxml_use_internal_errors( true );

		$doc = new DOMDocument();
		$doc->loadHTML(
			'<!DOCTYPE html><html><head><meta charset="' . esc_attr( $charset ) . '"></head><body>' . $content . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_errors );

		$xpath  = new DOMXPath( $doc );
		$images = $xpath->query( '//img' );

		if ( ! $images || 0 === $images->length ) {
			return $content;
		}

		$to_process = [];

		foreach ( $images as $img ) {
			if ( $this->should_skip_img( $img, $opts ) ) {
				continue;
			}

			// Determine gallery group.
			$group = 'content';
			if ( $this->is_inside_recipe_card( $img ) ) {
				$group = 'recipe';
			}

			$to_process[] = [ 'img' => $img, 'group' => $group ];
		}

		if ( empty( $to_process ) ) {
			return $content;
		}

		$counter = 0;
		$mode    = $opts['lightbox_mode'];

		foreach ( $to_process as $item ) {
			$counter++;
			$img      = $item['img'];
			$group    = $item['group'];
			$id       = 'mzv-lb-' . $counter;
			$alt      = $img->getAttribute( 'alt' );
			$full_src = $this->get_full_size_url( $img );

			if ( 'css' === $mode ) {
				$markup = MZV_LB_CSS_Mode::build_markup( $id, $img, $full_src, $alt, $doc );
			} else {
				$markup = $this->build_enhanced_markup( $img, $full_src, $group, $opts, $doc );
			}

			$img->parentNode->replaceChild( $markup, $img );
		}

		$body   = $doc->getElementsByTagName( 'body' )->item( 0 );
		$output = '';
		foreach ( $body->childNodes as $child ) {
			$output .= $doc->saveHTML( $child );
		}

		return $output;
	}

	/**
	 * Build Enhanced mode wrapper for an image.
	 */
	private function build_enhanced_markup( DOMElement $img, string $full_src, string $group, array $opts, DOMDocument $doc ): DOMElement {
		$wrap = $doc->createElement( 'span' );
		$wrap->setAttribute( 'class', 'mzv-lb-wrap' );
		$wrap->setAttribute( 'data-mzv-lb-src', $full_src );
		$wrap->setAttribute( 'data-mzv-lb-group', $group );
		$wrap->setAttribute( 'role', 'button' );
		$wrap->setAttribute( 'tabindex', '0' );
		$wrap->setAttribute( 'aria-label', __( 'Open image in lightbox', 'mzv-lightbox' ) );

		// Caption.
		$caption = $this->get_caption_value( $img, $opts );
		$wrap->setAttribute( 'data-mzv-lb-caption', $caption );

		// WPRM jump.
		$has_jump = '0';
		if ( $opts['wprm_jump_enabled'] && $this->current_post_has_recipe() ) {
			$has_jump = '1';
		}
		$wrap->setAttribute( 'data-mzv-lb-has-jump', $has_jump );

		// Clone original image.
		$img_clone = $img->cloneNode( true );
		$wrap->appendChild( $img_clone );

		// Hover overlay.
		$hover = $doc->createElement( 'span' );
		$hover->setAttribute( 'class', 'mzv-lb-hover' );
		$hover->setAttribute( 'aria-hidden', 'true' );
		$wrap->appendChild( $hover );

		// Mobile hint.
		$mobile = $doc->createElement( 'span' );
		$mobile->setAttribute( 'class', 'mzv-lb-mobile-hint' );
		$mobile->setAttribute( 'aria-hidden', 'true' );
		$wrap->appendChild( $mobile );

		return $wrap;
	}

	/**
	 * Determine if an image should be skipped.
	 */
	private function should_skip_img( DOMElement $img, array $opts ): bool {
		// Empty or data URI src.
		$src = $img->getAttribute( 'src' );
		if ( empty( $src ) || 0 === strpos( $src, 'data:' ) ) {
			return true;
		}

		// Build exclusion class list.
		$excluded = [ 'no-lightbox', 'mzv-lb-wrap' ];
		if ( ! empty( $opts['excluded_classes'] ) ) {
			$user_classes = array_map( 'trim', explode( ',', $opts['excluded_classes'] ) );
			$user_classes = array_filter( $user_classes );
			$excluded     = array_merge( $excluded, $user_classes );
		}

		// Check image's own classes.
		$img_classes = $img->getAttribute( 'class' );
		foreach ( $excluded as $cls ) {
			if ( '' !== $cls && false !== strpos( $img_classes, $cls ) ) {
				return true;
			}
		}

		// Min width check.
		$min_width = (int) $opts['min_image_width'];
		if ( $min_width > 0 ) {
			$width_attr = $img->getAttribute( 'width' );
			if ( '' !== $width_attr && is_numeric( $width_attr ) && (int) $width_attr < $min_width ) {
				return true;
			}
		}

		// Walk ancestors.
		$parent = $img->parentNode;
		while ( $parent && 'body' !== $parent->nodeName ) {
			// Skip if inside <a> tag.
			if ( 'a' === $parent->nodeName ) {
				return true;
			}

			if ( $parent instanceof DOMElement ) {
				$ancestor_classes = $parent->getAttribute( 'class' );

				// Check excluded classes on ancestors.
				foreach ( $excluded as $cls ) {
					if ( '' !== $cls && false !== strpos( $ancestor_classes, $cls ) ) {
						return true;
					}
				}

				// Recipe card exclusion.
				if ( ! $opts['recipe_card_lightbox'] && false !== strpos( $ancestor_classes, 'wprm-recipe-container' ) ) {
					return true;
				}
			}

			$parent = $parent->parentNode;
		}

		return false;
	}

	/**
	 * Check if an image is inside a WPRM recipe card.
	 */
	private function is_inside_recipe_card( DOMElement $img ): bool {
		$parent = $img->parentNode;
		while ( $parent && 'body' !== $parent->nodeName ) {
			if ( $parent instanceof DOMElement ) {
				if ( false !== strpos( $parent->getAttribute( 'class' ), 'wprm-recipe-container' ) ) {
					return true;
				}
			}
			$parent = $parent->parentNode;
		}
		return false;
	}

	/**
	 * Get the full-size URL for an image.
	 */
	private function get_full_size_url( DOMElement $img ): string {
		$src     = $img->getAttribute( 'src' );
		$classes = $img->getAttribute( 'class' );

		if ( preg_match( '/wp-image-(\d+)/', $classes, $matches ) ) {
			$attachment_id = (int) $matches[1];
			$full          = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $full ) {
				return $full;
			}
		}

		// Fallback: strip dimension suffix.
		return preg_replace( '/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src );
	}

	/**
	 * Resolve caption value based on settings.
	 */
	private function get_caption_value( DOMElement $img, array $opts ): string {
		$source = $opts['caption_source'] ?? 'alt';

		switch ( $source ) {
			case 'alt':
				return $img->getAttribute( 'alt' );

			case 'title':
				return $img->getAttribute( 'title' );

			case 'description':
				$classes = $img->getAttribute( 'class' );
				if ( preg_match( '/wp-image-(\d+)/', $classes, $matches ) ) {
					$caption = wp_get_attachment_caption( (int) $matches[1] );
					return $caption ? $caption : '';
				}
				return '';

			case 'none':
			default:
				return '';
		}
	}

	/**
	 * Check if the current post has a WPRM recipe.
	 */
	private function current_post_has_recipe(): bool {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		// Cache per post.
		if ( $this->cached_post_id === $post_id && null !== $this->post_has_recipe ) {
			return $this->post_has_recipe;
		}

		$this->cached_post_id = $post_id;

		if ( ! ( function_exists( 'WPRM' ) || class_exists( 'WP_Recipe_Maker' ) ) ) {
			$this->post_has_recipe = false;
			return false;
		}

		if ( class_exists( 'WPRM_Recipe_Manager' ) && method_exists( 'WPRM_Recipe_Manager', 'get_recipe_ids_from_post' ) ) {
			$this->post_has_recipe = ! empty( WPRM_Recipe_Manager::get_recipe_ids_from_post( $post_id ) );
			return $this->post_has_recipe;
		}

		// Fallback: check content for WPRM shortcodes/blocks.
		$post = get_post( $post_id );
		if ( ! $post ) {
			$this->post_has_recipe = false;
			return false;
		}

		$this->post_has_recipe = false !== strpos( $post->post_content, 'wprm-recipe-id' )
			|| false !== strpos( $post->post_content, '[wprm-recipe' );

		return $this->post_has_recipe;
	}
}
