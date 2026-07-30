# This Little Lightbox of Mine

Lightweight image lightbox for WordPress with CSS-Only and Enhanced modes. Fast, accessible, and built for food blogs.

## Features

- **CSS-Only mode** — uses the CSS checkbox hack for lightbox toggle
- **Enhanced mode** — adds gallery browsing, captions, swipe, keyboard navigation, and animations
- **Auto-wraps images** in `the_content` with smart exclusions
- **Skips**: WPRM recipe card images, images with class `no-lightbox`, images already wrapped in links
- **Full-size images** lazy-loaded only when lightbox opens
- **Configurable trigger icon** with always-visible desktop corner mode and normal/jumbo/super sizing
- **Optional ad layering** for selected video-player and sticky-footer ad containers in Enhanced mode
- **Body scroll lock** via `html:has()` — no JS needed
- **Accessible**: `role="dialog"`, `aria-modal`, labeled close button, focus rings
- **`prefers-reduced-motion`** support
- **Print-safe**

## Installation

1. Download the [latest release](https://github.com/mikezielonkadotcom/little-lightbox/releases)
2. Upload to `/wp-content/plugins/little-lightbox/`
3. Activate in WordPress admin
4. Open Settings → This Little Lightbox of Mine and complete or skip the two-step welcome setup

The welcome setup is part of the plugin's single React-powered settings interface; it does not add a second WordPress menu item. It asks about optional Update Machine telemetry first, then offers the core lightbox mode, gallery, and animation choices. The telemetry choice and all plugin settings remain editable afterward. Network-active installations get one equivalent entry under Network Settings for the shared telemetry choice, while lightbox behavior remains site-specific.

Update checks include a reviewed, bounded feature snapshot plus two coarse activity buckets: how many UTC days in the last 30 days the server transformed at least one eligible image, and how recently that happened. The plugin does not track browser opens or impressions and does not send raw events, exact counts, dates, image details, captions, post data, selectors, or URLs. Turning sharing off deletes the locally retained activity state immediately; updates keep working. Data is sent over HTTPS under the [Update Machine privacy policy](https://updatemachine.com/privacy).

## Excluding Images

Add the CSS class `no-lightbox` to any image you want to exclude.

## Ad Layering

Enhanced mode can lift selected ad containers above the lightbox while it is open. The setting is off by default. Enable it under Settings → This Little Lightbox of Mine and adjust the comma-separated selector list for the site's video-player and sticky-footer ad wrappers.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL-2.0-or-later
