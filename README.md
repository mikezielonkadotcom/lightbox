# MZV Lightbox

Pure-CSS, zero-JS lightbox for WordPress. Fast, lightweight, no render-blocking.

## Features

- **Zero JavaScript** — uses the CSS checkbox hack for lightbox toggle
- **< 2KB gzipped CSS** — inlined via `wp_add_inline_style`, no extra HTTP requests
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
2. Upload to `/wp-content/plugins/mzv-lightbox/`
3. Activate in WordPress admin
4. Done — no configuration needed

## Excluding Images

Add the CSS class `no-lightbox` to any image you want to exclude.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL-2.0-or-later
