=== MZV Lightbox ===
Contributors: mikezielonka
Tags: lightbox, images, css, performance, no-javascript
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pure-CSS, zero-JS lightbox for WordPress. Fast, lightweight, no render-blocking.

== Description ==

MZV Lightbox adds a beautiful, performant lightbox to images in your post content — with zero JavaScript.

**How it works:**

* Uses the CSS checkbox hack to toggle lightbox visibility
* Automatically wraps images in your post content
* Skips images inside WPRM recipe cards, images with the `no-lightbox` class, and images already wrapped in links
* Full-size images are lazy-loaded only when opened
* CSS is inlined — zero extra HTTP requests

**Performance:**

* CSS < 2KB gzipped
* 0 bytes of JavaScript
* 0 extra HTTP requests
* No render-blocking resources

**Features:**

* Hover overlay with magnifier icon on desktop
* Always-visible zoom hint on mobile
* Body scroll lock while lightbox is open
* Caption from alt text
* `prefers-reduced-motion` support
* Print-safe (lightbox markup hidden in print)
* High z-index to sit above admin bars and popups

== Installation ==

1. Upload the `mzv-lightbox` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. That's it — images in your post content will automatically get the lightbox

== Frequently Asked Questions ==

= How do I exclude an image from the lightbox? =

Add the CSS class `no-lightbox` to the image in the block editor.

= Does it work with WPRM (WP Recipe Maker)? =

Yes. Images inside WPRM recipe cards are automatically excluded.

= Is there any JavaScript? =

No. The entire lightbox is pure CSS using the checkbox hack technique.

= Can I close the lightbox by pressing ESC? =

Not in v1 (pure CSS can't listen for keyboard events). This may be added as an optional JS enhancement in a future version.

== Changelog ==

= 1.0.0 =
* Initial release
* Pure CSS checkbox-hack lightbox
* Auto-wraps entry content images
* Skips WPRM recipe cards, linked images, and `.no-lightbox` images
* Inline CSS via `wp_add_inline_style`
* Lazy-loaded full-size images
* Accessible overlay with role="dialog" and aria-modal
* prefers-reduced-motion and print support
