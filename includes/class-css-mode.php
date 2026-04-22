<?php
/**
 * MZV Lightbox CSS-Only Mode — inline styles + checkbox-hack DOM generation.
 *
 * @package MZV_Lightbox
 */

defined( 'ABSPATH' ) || exit;

class MZV_LB_CSS_Mode {

	/**
	 * Return the complete inline CSS for CSS-Only mode.
	 */
	public static function get_inline_css(): string {
		// Magnifier-plus SVG (white, 20×20).
		$magnifier_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='none' stroke='white' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='8.5' cy='8.5' r='5.5'/%3E%3Cline x1='13' y1='13' x2='18' y2='18'/%3E%3Cline x1='6' y1='8.5' x2='11' y2='8.5'/%3E%3Cline x1='8.5' y1='6' x2='8.5' y2='11'/%3E%3C/svg%3E";

		// X close SVG (white, 24×24).
		$close_svg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round'%3E%3Cline x1='6' y1='6' x2='18' y2='18'/%3E%3Cline x1='18' y1='6' x2='6' y2='18'/%3E%3C/svg%3E";

		return <<<CSS
/* MZV Lightbox v2.0.0 — CSS-Only Mode */
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
	 * Build CSS-Only mode DOM for a single image.
	 *
	 * @param string      $id       Unique lightbox ID (e.g. mzv-lb-1).
	 * @param DOMElement  $img      The original <img> element.
	 * @param string      $full_src Full-size image URL.
	 * @param string      $alt      Alt text.
	 * @param DOMDocument $doc      The document.
	 * @return DOMElement The wrapper fragment.
	 */
	public static function build_markup( string $id, DOMElement $img, string $full_src, string $alt, DOMDocument $doc ): DOMElement {
		// Container span.
		$wrap = $doc->createElement( 'span' );
		$wrap->setAttribute( 'class', 'mzv-lb-wrap' );

		// Hidden checkbox.
		$input = $doc->createElement( 'input' );
		$input->setAttribute( 'type', 'checkbox' );
		$input->setAttribute( 'id', $id );
		$input->setAttribute( 'class', 'mzv-lb-toggle' );
		$input->setAttribute( 'aria-hidden', 'true' );

		// Trigger label.
		$label = $doc->createElement( 'label' );
		$label->setAttribute( 'for', $id );
		$label->setAttribute( 'class', 'mzv-lb-trigger' );
		$label->setAttribute( 'aria-label', __( 'Open image in lightbox', 'mzv-lightbox' ) );
		$label->setAttribute( 'tabindex', '0' );

		$img_clone = $img->cloneNode( true );
		$label->appendChild( $img_clone );

		// Hover overlay.
		$hover = $doc->createElement( 'span' );
		$hover->setAttribute( 'class', 'mzv-lb-hover' );
		$hover->setAttribute( 'aria-hidden', 'true' );
		$label->appendChild( $hover );

		// Mobile hint.
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
}
