=== This Little Lightbox of Mine ===
Contributors: mikezielonka
Tags: lightbox, images, gallery, photography, food blog
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.7.2
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
* Desktop trigger icon can stay visible in the image corner
* Trigger icon size controls: normal, jumbo (2x), and super size (3x)
* WPRM lightbox conflict detection with admin notice
* Optional ad layering for selected video-player and sticky-footer ad containers (Enhanced)
* Self-hosted updates via Update Machine v2
* `.no-lightbox` escape hatch class
* Body scroll lock, focus trap, full accessibility support

== Telemetry & privacy ==

The updater sends a small telemetry payload to the update server:

| Field | Sent on hourly update check | Sent on activation (registration) |
|---|---|---|
| `site_url` | Yes | Yes (also part of the HMAC signature) |
| `site_name` | Yes | Yes |
| `plugin_version` | Yes | Yes |
| `plugin_slug` | No (implied by URL) | Yes |
| `sdk_version` | Yes | Yes (also sent on challenge init) |
| `php_version` | Yes | No |
| `wp_version` | Yes | No |
| `environment_type` | Yes | No |
| `is_multisite` | Yes | No |
| `activation_scope` | Yes | No |
| `features` | Optional bounded feature-state snapshot | No |
| `usage` | Optional, plugin-provided | No |

That is the complete list. No admin email (removed in um-updater v4.1.0 because the site key already identifies the install), no locale, and no user data. `usage` is absent unless this plugin explicitly opts in with a flat usage snapshot. Zero-config challenge registration sends only `site_url`, `plugin_slug`, `plugin_version`, and `sdk_version`; it does not send the site name.

Optional usage snapshots are for plugin feature flags/counters, not user data. The SDK keeps at most 20 keys, allows only short scalar values, caps the serialized object at 2KB, and drops invalid usage data instead of sending it.

Little Lightbox reports a typed snapshot of its selected mode, caption source, gallery and animation settings, recipe-card and Jump to Recipe integrations, ad-layering toggle, and trigger icon size. It also reports only coarse ranges for how many UTC days eligible server-rendered images were transformed in the last 30 days and how recently that happened. This is not a browser-open or impression count. It sends no raw events, exact counts, dates, captions, image details, posts, selectors, URLs, keys, or other free-form values.

The welcome setup asks about telemetry first. Sharing is enabled by default, can be unchecked without blocking setup, and remains editable from the plugin settings screen. Update checks and licensing keep working when sharing is disabled, optional callbacks do not run, and the update-check request body is empty. Network-active installations store one network preference managed from Network Admin; site-active installations store a separate preference per site. See the [Update Machine privacy policy](https://updatemachine.com/privacy).

== Changelog ==

= 2.7.2 =
* Added privacy-bounded eligible-render activity ranges using the Update Machine SDK v4.7.0 candidate. No browser tracking or additional request is added.
* Updated the exact telemetry disclosure and schema registry version for the three-plugin canary.

= 2.7.1 =
* Updated the bundled Update Machine SDK to v4.6.1 with safer registration recovery, bounded domain-lock self-healing, and final-plugin cleanup.

= 2.7.0 =
* Rebuilt the existing plugin settings screen with WordPress React components and kept welcome setup inside the same menu entry.
* Added a resumable two-step welcome setup with telemetry as the first question and permanent access from plugin settings.
* Added explicit default-on telemetry sharing with a clear opt-out, exact bounded-data disclosure, and privacy-policy link.
* Added network-scoped onboarding and telemetry controls for network-active multisite installations.
* Fixed activation on WordPress 6.0 and older WP-CLI paths that pass a nullable network flag.
* Updated the bundled Update Machine updater SDK to the tagged v4.6.0 consent-mode release while retaining mixed-load compatibility with v4.5.0.

= 2.6.5 =
* Fixed zero-config registration on REST and cron requests so free installs can receive a site key and submit authorized telemetry.

= 2.6.4 =
* Added privacy-bounded feature-state telemetry for the reviewed Little Lightbox settings schema.
* Updated the bundled Update Machine updater SDK to v4.5.0.

= 2.6.3 =
* Security: Updated the bundled Update Machine updater SDK to v4.4.3 with stricter TLS and ZIP integrity enforcement.

= 2.6.2 =
* Dev: Updated the bundled Update Machine updater SDK to v4.4.2, including registration recovery, keyed download fixes, and expanded telemetry disclosure.

= 2.6.1 =
* Dev: Updated the bundled Update Machine updater SDK to v4.4.1.

= 2.6.0 =
* Dev: Updated the bundled Update Machine updater SDK to v4.4.0.
* Dev: Added telemetry opt-out cleanup on uninstall and package validation coverage for `uninstall.php`.

= 2.5.0 =
* New: Added a desktop trigger icon setting that keeps the magnifier visible in the image corner instead of only showing it on hover.
* New: Added trigger icon size options: normal, jumbo (2x), and super size (3x).

= 2.4.0 =
* New: Added an Enhanced-mode setting to let selected ad containers render above the lightbox while it is open.
* New: Added configurable ad-layer selectors for video-player and sticky-footer ad wrappers. The setting is off by default.
* Dev: Added CI package validation and reusable release ZIP build scripts.

= 2.3.0 =
* Fix: Close, prev, and next buttons now reset native button appearance (`appearance: none`), preventing platform/theme-default button chrome from painting over the SVG icons (manifested as a grey square in place of the close X on some browsers/themes).
* Changed: Renamed all frontend CSS classes, IDs, data attributes, and JS config object from `mzv-lb-*` / `mzvLbConfig` to `llb-*` / `llbConfig`. **Breaking** for any custom CSS or JS that targeted the old prefix.
* Changed: Bumped specificity on close/prev/next button rules with `#llb-modal` scope so theme button rules can't override them.
* Note: Existing settings, post meta, and database keys are unchanged — no data migration required.

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
