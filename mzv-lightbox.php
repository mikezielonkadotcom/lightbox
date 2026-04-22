<?php
/**
 * Plugin Name: MZV Lightbox
 * Plugin URI:  https://github.com/mikezielonkadotcom/lightbox
 * Description: Pure-CSS, zero-JS lightbox for WordPress. Fast, lightweight, no render-blocking.
 * Version:     1.0.1
 * Author:      Mike Zielonka Ventures
 * Author URI:  https://mikezielonka.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mzv-lightbox
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline lightbox CSS via a dummy stylesheet handle.
 */
add_action( 'wp_enqueue_scripts', 'mzv_lb_enqueue_styles' );

function mzv_lb_enqueue_styles() {
	if ( is_admin() ) {
		return;
	}

	// Register an empty handle so we can attach inline CSS.
	wp_register_style( 'mzv-lightbox', false ); // phpcs:ignore
	wp_enqueue_style( 'mzv-lightbox' );
	wp_add_inline_style( 'mzv-lightbox', mzv_lb_get_css() );
}

/**
 * Return the lightbox CSS string.
 */
function mzv_lb_get_css() {
	// Magnifier-plus SVG (white, 20×20)
	$magnifier_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='none' stroke='white' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='8.5' cy='8.5' r='5.5'/%3E%3Cline x1='13' y1='13' x2='18' y2='18'/%3E%3Cline x1='6' y1='8.5' x2='11' y2='8.5'/%3E%3Cline x1='8.5' y1='6' x2='8.5' y2='11'/%3E%3C/svg%3E";

	// X close SVG (white, 24×24)
	$close_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round'%3E%3Cline x1='6' y1='6' x2='18' y2='18'/%3E%3Cline x1='18' y1='6' x2='6' y2='18'/%3E%3C/svg%3E";

	return <<<CSS
/* MZV Lightbox v1.0.1 */
html:has(input.mzv-lb-toggle:checked){overflow:hidden}
.mzv-lb-toggle{position:absolute;opacity:0;pointer-events:none;width:0;height:0}
.mzv-lb-wrap{position:relative;display:inline-block;cursor:zoom-in}
.mzv-lb-wrap img{display:block}
.mzv-lb-hover{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(rgba(0,0,0,0),rgba(0,0,0,.35));opacity:0;transition:opacity .2s ease-out;pointer-events:none}
.mzv-lb-hover::after{content:'';width:28px;height:28px;background:url("{$magnifier_svg}") center/contain no-repeat;filter:drop-shadow(0 1px 2px rgba(0,0,0,.5))}
.mzv-lb-wrap:hover .mzv-lb-hover{opacity:1}
.mzv-lb-mobile-hint{position:absolute;bottom:8px;right:8px;width:22px;height:22px;background:url("{$magnifier_svg}") center/contain no-repeat;opacity:.6;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));pointer-events:none}
@media(hover:hover){.mzv-lb-mobile-hint{display:none}}
@media(hover:none){.mzv-lb-hover{display:none}}
.mzv-lb-overlay{position:fixed;inset:0;z-index:2147483646;background:rgba(0,0,0,.92);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0;pointer-events:none}
input.mzv-lb-toggle:checked~.mzv-lb-overlay{opacity:1;pointer-events:auto;transition:opacity .2s ease-out}
input.mzv-lb-toggle:checked~.mzv-lb-overlay .mzv-lb-full{transform:scale(1);transition:transform .2s ease-out}
.mzv-lb-full{max-width:95vw;max-height:90vh;object-fit:contain;transform:scale(.96)}
.mzv-lb-close{position:absolute;top:12px;right:12px;width:32px;height:32px;background:url("{$close_svg}") center/contain no-repeat;filter:drop-shadow(0 1px 3px rgba(0,0,0,.7));cursor:pointer;z-index:1}
.mzv-lb-close:focus-visible{outline:2px solid #fff;outline-offset:4px;border-radius:2px}
.mzv-lb-backdrop{position:absolute;inset:0;cursor:default}
.mzv-lb-caption{margin-top:8px;padding:4px 14px;background:rgba(0,0,0,.6);color:#fff;font-size:.85rem;line-height:1.4;border-radius:999px;max-width:90vw;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mzv-lb-trigger:focus-visible{outline:2px solid #0073aa;outline-offset:2px;border-radius:2px}
@media(prefers-reduced-motion:reduce){.mzv-lb-overlay,input.mzv-lb-toggle:checked~.mzv-lb-overlay,.mzv-lb-hover{transition:none}.mzv-lb-full,input.mzv-lb-toggle:checked~.mzv-lb-overlay .mzv-lb-full{transition:none;transform:scale(1)}}
@media print{.mzv-lb-overlay,.mzv-lb-hover,.mzv-lb-mobile-hint,.mzv-lb-toggle{display:none!important}}
CSS;
}

/**
 * Filter the_content to wrap eligible images with lightbox markup.
 */
add_filter( 'the_content', 'mzv_lb_wrap_images', 20 );

function mzv_lb_wrap_images( $content ) {
	if ( is_admin() || empty( $content ) ) {
		return $content;
	}

	// Only process singular content (posts/pages).
	if ( ! is_singular() ) {
		return $content;
	}

	// Quick bail if no images.
	if ( stripos( $content, '<img' ) === false ) {
		return $content;
	}

	$counter = 0;

	// Use DOMDocument for reliable HTML parsing.
	$charset  = get_bloginfo( 'charset' );
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

	if ( ! $images || $images->length === 0 ) {
		return $content;
	}

	$to_process = array();

	foreach ( $images as $img ) {
		// Skip images with empty or placeholder src.
		$src = $img->getAttribute( 'src' );
		if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) {
			continue;
		}

		// Skip images with no-lightbox class.
		$classes = $img->getAttribute( 'class' );
		if ( strpos( $classes, 'no-lightbox' ) !== false ) {
			continue;
		}

		// Skip if inside a link.
		$parent = $img->parentNode;
		while ( $parent && $parent->nodeName !== 'body' ) {
			if ( $parent->nodeName === 'a' ) {
				continue 2;
			}
			$parent = $parent->parentNode;
		}

		// Skip if inside .wprm-recipe-container.
		$ancestor = $img->parentNode;
		while ( $ancestor && $ancestor->nodeName !== 'body' ) {
			if ( $ancestor instanceof DOMElement ) {
				$ancestor_classes = $ancestor->getAttribute( 'class' );
				if ( strpos( $ancestor_classes, 'wprm-recipe-container' ) !== false ) {
					continue 2;
				}
			}
			$ancestor = $ancestor->parentNode;
		}

		$to_process[] = $img;
	}

	if ( empty( $to_process ) ) {
		return $content;
	}

	foreach ( $to_process as $img ) {
		$counter++;
		$id       = 'mzv-lb-' . $counter;
		$src      = $img->getAttribute( 'src' );
		$alt      = $img->getAttribute( 'alt' );
		$full_src = mzv_lb_get_full_size_url( $img );

		// Build the wrapper markup.
		$html     = mzv_lb_build_markup( $id, $img, $full_src, $alt, $doc );

		$img->parentNode->replaceChild( $html, $img );
	}

	// Extract the body content back out.
	$body   = $doc->getElementsByTagName( 'body' )->item( 0 );
	$output = '';
	foreach ( $body->childNodes as $child ) {
		$output .= $doc->saveHTML( $child );
	}

	return $output;
}

/**
 * Build lightbox DOM nodes for an image.
 */
function mzv_lb_build_markup( $id, $img, $full_src, $alt, $doc ) {
	// Container span (inline-block wrapper).
	$wrap = $doc->createElement( 'span' );
	$wrap->setAttribute( 'class', 'mzv-lb-wrap' );

	// Hidden checkbox.
	$input = $doc->createElement( 'input' );
	$input->setAttribute( 'type', 'checkbox' );
	$input->setAttribute( 'id', $id );
	$input->setAttribute( 'class', 'mzv-lb-toggle' );
	$input->setAttribute( 'aria-hidden', 'true' );

	// Trigger label wrapping the original image.
	$label = $doc->createElement( 'label' );
	$label->setAttribute( 'for', $id );
	$label->setAttribute( 'class', 'mzv-lb-trigger' );
	$label->setAttribute( 'aria-label', __( 'Open image in lightbox', 'mzv-lightbox' ) );
	$label->setAttribute( 'tabindex', '0' );

	// Clone the original image into the label.
	$img_clone = $img->cloneNode( true );
	$label->appendChild( $img_clone );

	// Hover overlay.
	$hover = $doc->createElement( 'span' );
	$hover->setAttribute( 'class', 'mzv-lb-hover' );
	$hover->setAttribute( 'aria-hidden', 'true' );
	$label->appendChild( $hover );

	// Mobile hint icon.
	$mobile = $doc->createElement( 'span' );
	$mobile->setAttribute( 'class', 'mzv-lb-mobile-hint' );
	$mobile->setAttribute( 'aria-hidden', 'true' );
	$label->appendChild( $mobile );

	// Overlay (dialog).
	$overlay = $doc->createElement( 'span' );
	$overlay->setAttribute( 'class', 'mzv-lb-overlay' );
	$overlay->setAttribute( 'role', 'dialog' );
	$overlay->setAttribute( 'aria-modal', 'true' );

	// Backdrop close label.
	$backdrop = $doc->createElement( 'label' );
	$backdrop->setAttribute( 'for', $id );
	$backdrop->setAttribute( 'class', 'mzv-lb-backdrop' );
	$backdrop->setAttribute( 'aria-label', __( 'Close image', 'mzv-lightbox' ) );
	$overlay->appendChild( $backdrop );

	// Full-size image.
	$full_img = $doc->createElement( 'img' );
	$full_img->setAttribute( 'src', $full_src );
	$full_img->setAttribute( 'alt', $alt );
	$full_img->setAttribute( 'class', 'mzv-lb-full' );
	$full_img->setAttribute( 'loading', 'lazy' );
	$full_img->setAttribute( 'decoding', 'async' );
	$overlay->appendChild( $full_img );

	// Close button.
	$close = $doc->createElement( 'label' );
	$close->setAttribute( 'for', $id );
	$close->setAttribute( 'class', 'mzv-lb-close' );
	$close->setAttribute( 'aria-label', __( 'Close image', 'mzv-lightbox' ) );
	$close->setAttribute( 'tabindex', '0' );
	$overlay->appendChild( $close );

	// Caption.
	if ( ! empty( $alt ) ) {
		$caption = $doc->createElement( 'span' );
		$caption->setAttribute( 'class', 'mzv-lb-caption' );
		$caption->textContent = $alt;
		$overlay->appendChild( $caption );
	}

	$wrap->appendChild( $input );
	$wrap->appendChild( $label );
	$wrap->appendChild( $overlay );

	return $wrap;
}

/**
 * Get the full-size URL for an image element.
 */
function mzv_lb_get_full_size_url( $img ) {
	$src     = $img->getAttribute( 'src' );
	$classes = $img->getAttribute( 'class' );

	// Try to get attachment ID from wp-image-{id} class.
	if ( preg_match( '/wp-image-(\d+)/', $classes, $matches ) ) {
		$attachment_id = (int) $matches[1];
		$full          = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( $full ) {
			return $full;
		}
	}

	// Fallback: strip the dimension suffix from the src URL.
	$full = preg_replace( '/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src );

	return $full;
}
