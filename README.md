# This Little Lightbox of Mine

Lightweight image lightbox for WordPress with CSS-Only and Enhanced modes. Fast, accessible, and built for food blogs.

## Features

- **CSS-Only mode** — uses the CSS checkbox hack for lightbox toggle
- **Enhanced mode** — adds gallery browsing, captions, swipe, keyboard navigation, and animations
- **Auto-wraps images** in `the_content` with smart exclusions
- **Skips**: WPRM recipe card images, images with class `no-lightbox`, images already wrapped in links
- **Full-size images** lazy-loaded only when lightbox opens
- **Hover overlay** with magnifier icon (desktop) / always-visible zoom hint (mobile)
- **Body scroll lock** via `html:has()` — no JS needed
- **Accessible**: `role="dialog"`, `aria-modal`, labeled close button, focus rings
- **`prefers-reduced-motion`** support
- **Print-safe**

## Installation

1. Download the [latest release](https://github.com/mikezielonkadotcom/lightbox/releases)
2. Upload to `/wp-content/plugins/little-lightbox/`
3. Activate in WordPress admin
4. Done — no configuration needed

## Excluding Images

Add the CSS class `no-lightbox` to any image you want to exclude.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL-2.0-or-later
