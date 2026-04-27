=== This Little Lightbox of Mine ===
Contributors: mikezielonka
Tags: lightbox, images, gallery, photography, food blog
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight lightbox for WordPress with CSS-Only and Enhanced modes. Gallery navigation, captions, swipe, keyboard, and WPRM integration.

== Description ==

This Little Lightbox of Mine automatically wraps `.entry-content` images in a configurable lightbox with two operating modes:

* **Enhanced Mode (default):** JS-driven modal with gallery navigation, captions, swipe/keyboard support, animations, and WPRM "Jump to Recipe" integration.
* **CSS-Only Mode:** Pure-CSS checkbox-hack lightbox — zero JavaScript, inline styles, minimal footprint.

= Features =

* Gallery browsing with prev/next navigation (Enhanced)
* Configurable caption source: alt text, title, attachment description, or none (Enhanced)
* Touch swipe and keyboard navigation (Enhanced)
* Configurable open/close animations with reduced-motion support (Enhanced)
* WPRM "Jump to Recipe" link in lightbox (Enhanced)
* Minimum image width filter
* Excluded CSS classes filter
* Recipe card image toggle (separate gallery group)
* WPRM lightbox conflict detection with admin notice
* Self-hosted updates via Update Machine v2
* `.no-lightbox` escape hatch class
* Body scroll lock, focus trap, full accessibility support

== Changelog ==

= 2.2.0 =
* Fix: Jump to Recipe now closes the Enhanced lightbox before smoothly scrolling to the WPRM recipe container.
* Changed: Jump to Recipe is only available in Enhanced mode because CSS-Only mode cannot close itself with JavaScript.

= 2.1.0 =
* Changed: Rebranded plugin to This Little Lightbox of Mine with little-lightbox text domain and asset filenames.
* New: Fire a GA4 lightbox_open event when Enhanced mode opens an image and gtag is available.
* Cleanup: Removed repository-only screenshots and planning/review docs from the plugin source.

= 2.0.0 =
* New: Two-mode architecture — CSS-Only and Enhanced modes
* New: Settings page (Settings → This Little Lightbox of Mine)
* New: Gallery navigation with prev/next arrows and counter
* New: Caption source selection (alt, title, description, none)
* New: Animation controls (enable/disable, duration)
* New: Swipe and keyboard navigation
* New: WPRM "Jump to Recipe" link integration
* New: Visibility controls (min width, excluded classes, recipe card toggle)
* New: WPRM conflict detection with dismissible admin notice
* New: Self-hosted updates via Update Machine v2
* New: Focus trap and full ARIA accessibility
* Changed: Single shared modal replaces per-image checkbox-hack DOM (Enhanced mode)
* Changed: Plugin split into class-based architecture

= 1.0.1 =
* Initial release with pure-CSS lightbox
