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
4. Complete or skip the two-step welcome setup

The welcome setup asks about optional Update Machine telemetry first, then offers the core lightbox mode, gallery, and animation choices. The telemetry choice and all plugin settings remain editable afterward. Network-active installations manage the shared telemetry choice in Network Admin while lightbox behavior remains site-specific.

## Excluding Images

Add the CSS class `no-lightbox` to any image you want to exclude.

## Ad Layering

Enhanced mode can lift selected ad containers above the lightbox while it is open. The setting is off by default. Enable it under Settings → This Little Lightbox of Mine and adjust the comma-separated selector list for the site's video-player and sticky-footer ad wrappers.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL-2.0-or-later
