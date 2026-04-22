# MZV Lightbox — v2.0 Engineering Spec

**Plugin slug:** `mzv-lightbox`  
**Distribution:** Mike Zielonka Ventures Update Machine v2 (self-hosted, no license key)  
**Author:** Mike Zielonka Ventures  
**Status:** Ready for Build — April 2026

---

## Table of Contents

1. [Overview & Goals](#1-overview--goals)
2. [Architecture](#2-architecture)
3. [Settings Schema](#3-settings-schema)
4. [Feature Specs](#4-feature-specs)
5. [Frontend Behavior](#5-frontend-behavior)
6. [Admin UI](#6-admin-ui)
7. [WPRM Integration](#7-wprm-integration)
8. [Update Machine v2 Integration](#8-update-machine-v2-integration)
9. [Performance Budget & Constraints](#9-performance-budget--constraints)
10. [Accessibility Requirements](#10-accessibility-requirements)
11. [Security Checklist](#11-security-checklist)
12. [Migration from v1](#12-migration-from-v1)
13. [Test Matrix](#13-test-matrix)
14. [Open Questions](#14-open-questions)

---

## 1. Overview & Goals

### What We're Building

MZV Lightbox v2.0 is a WordPress plugin that wraps `.entry-content` images in a configurable lightbox with two operating modes:

- **CSS-Only Mode ("Lite"):** Pure-CSS checkbox-hack lightbox — zero JavaScript, inline styles, minimal footprint. Equivalent to v1's approach. Open/close only, no gallery, no captions, no animations beyond instant show/hide.
- **Enhanced Mode ("Full"):** JavaScript-driven modal with gallery navigation, caption sourcing, swipe/keyboard nav, animation control, and deep WPRM integration. This is the default.

Site owners choose their mode in Settings. Both share the same PHP content filter for image wrapping, but the DOM output and frontend assets differ by mode.

### Why v2?

v1.0 proved the concept but had no settings and no upgrade path. v2 adds:
- A settings page and proper options system
- An Enhanced mode with JS-powered features (gallery, captions, animations, swipe/keyboard)
- A CSS-Only mode for sites that want zero JS overhead
- WPRM integration (conflict detection, jump-to-recipe)
- Self-hosted updates via UM v2

### Goals

| Goal | How |
|------|-----|
| Two modes: CSS-Only and Enhanced | `lightbox_mode` setting: `css` or `enhanced` |
| Gallery browsing with prev/next | JS modal (Enhanced mode only), grouped by content area |
| Caption sources (alt/title/desc/none) | PHP injects data attrs; JS reads them (Enhanced only) |
| WPRM recipe jump link | Conditional PHP injection + JS scroll (Enhanced only) |
| Visibility controls (min width, excluded classes) | PHP-side exclusion at wrap-time (both modes) |
| WPRM lightbox conflict detection | PHP admin notice system (both modes) |
| Configurable animations + reduced-motion | JS-driven CSS custom properties (Enhanced only) |
| Self-hosted updates via UM v2 | Bundled `um-updater.php` |
| Settings page | WP Settings API, single option array |
| Backward compatibility | v1 CSS classes preserved; no data loss on upgrade |

### Non-Goals (v2)

- Pinch-zoom inside lightbox (browser native handles it)
- Video lightbox
- Caption editing UI
- Per-image overrides (global settings only)
- i18n beyond English (strings ready for translation, translator notes included)
- Gallery/captions/animations in CSS-Only mode (these are Enhanced-only features)

---

## 2. Architecture

### 2.1 Two-Mode Architecture

**Decision: Support both a CSS-Only mode and a JS-Enhanced mode, selectable in settings.**

#### CSS-Only Mode (`lightbox_mode = 'css'`)
- Uses the v1 checkbox-hack approach: each image gets `<input type="checkbox">` + `<label>` + `.mzv-lb-overlay` DOM.
- CSS delivered inline via `wp_add_inline_style` (zero extra HTTP requests, zero JS).
- Features available: open/close lightbox, full-size image display, magnifier hover overlay, mobile zoom hint, body scroll lock (`html:has()`), `prefers-reduced-motion`, `.no-lightbox` escape hatch, visibility controls (min width, excluded classes, recipe card toggle).
- Features NOT available: gallery nav, captions, animations (enter/exit), swipe, keyboard nav, jump-to-recipe, focus trap.
- No JS file enqueued at all.

#### Enhanced Mode (`lightbox_mode = 'enhanced'`, default)
- JS-driven modal: single shared `<div#mzv-lb-modal>` reused for all images.
- Checkbox/input/label DOM removed — JS handles everything.
- All v2 features available: gallery, captions, animations, swipe, keyboard, WPRM jump link, focus trap, etc.
- CSS delivered as linked file + JS file enqueued.

#### Shared PHP Layer
- `MZV_LB_Content::wrap_images()` handles image wrapping for both modes.
- When `mode = 'css'`: outputs checkbox-hack DOM (input + label + overlay) per image.
- When `mode = 'enhanced'`: outputs lightweight `<span class="mzv-lb-wrap">` with data attributes.
- Exclusion logic (`should_skip_img()`) is identical in both modes.

Rationale: Some sites want the absolute lightest possible footprint — zero JS, inline CSS, just works. Others want the bells and whistles. Both are valid.

### 2.2 File Structure

```
mzv-lightbox/
├── mzv-lightbox.php          # Main plugin file: header, constants, bootstrap
├── includes/
│   ├── class-settings.php    # WP Settings API registration + defaults
│   ├── class-content.php     # the_content filter: DOM parsing + wrap logic (both modes)
│   ├── class-css-mode.php    # CSS-Only mode: inline styles, checkbox-hack DOM generation
│   ├── class-admin.php       # Admin notices (WPRM conflict), settings page render
│   └── um-updater.php        # UM v2 client (copied from update-machine-v2 repo)
├── assets/
│   ├── mzv-lightbox.js       # Vanilla JS: modal, gallery, swipe, keyboard, animation (Enhanced only)
│   └── mzv-lightbox.css      # Styles: trigger, modal, animations, responsive (Enhanced only)
└── readme.txt                # WordPress.org-compatible readme
```

**No build step required.** Vanilla JS (ES2017+), no bundler, no npm. Files are enqueued directly.

### 2.3 Class/Module Breakdown

```
mzv-lightbox.php
  ├── define MZV_LB_VERSION, MZV_LB_FILE, MZV_LB_DIR, MZV_LB_URL
  ├── require_once all includes/
  └── init hook → instantiate Settings, Content, Admin

class MZV_LB_Settings
  ├── register_settings()          → WP Settings API
  ├── get_options(): array         → merged with defaults
  ├── get_option(key): mixed       → single option
  └── defaults(): array            → canonical defaults

class MZV_LB_Content
  ├── __construct(Settings $s)
  ├── hooks()                      → add_filter the_content
  ├── wrap_images(content): string → DOMDocument parse + wrap
  ├── should_skip_img(img, opts)   → exclusion logic
  ├── get_full_size_url(img)       → attachment lookup + fallback
  ├── get_caption_value(img)       → alt/title/description per setting
  ├── build_markup(img, meta)      → returns wrapped DOM fragment
  └── inject_wprm_jump(img)       → conditionally adds data attr

class MZV_LB_Admin
  ├── hooks()
  ├── settings_page()              → render HTML
  ├── check_wprm_conflict()        → check WPRM lightbox option
  ├── render_conflict_notice()     → admin_notices hook
  └── dismiss_conflict_notice()    → AJAX handler

UM\PluginUpdater\register()        → UM v2 client (unchanged from msn-feed-exclusion)
```

### 2.4 Data Flow (ASCII)

```
Page Request
    │
    ▼
MZV_LB_Content::wrap_images()
    │  Reads: mzv_lightbox_options (PHP)
    │  Checks: lightbox_mode
    │
    ├── CSS-Only Mode:
    │     For each eligible <img>:
    │       - Generate unique ID (mzv-lb-{n})
    │       - Wrap in: <input type="checkbox"> + <label> + .mzv-lb-overlay DOM
    │       - All lightbox UI is pure HTML/CSS (checkbox hack)
    │     Inline CSS injected via wp_add_inline_style
    │     ▼
    │     HTML delivered → Done. No JS.
    │
    └── Enhanced Mode:
          For each eligible <img>:
            - Inject data-mzv-lb-* attributes onto the <img>
            - Wrap in .mzv-lb-wrap <span> (trigger only, no overlay DOM)
          ▼
        HTML delivered to browser
          │
          ▼
        mzv-lightbox.js boots (DOMContentLoaded)
    │
    ├── Reads window.mzvLbConfig (PHP-injected via wp_localize_script)
    │     caption_source, gallery_enabled, animation_ms, etc.
    │
    ├── Queries all .mzv-lb-wrap elements
    │     Builds gallery groups: content[] and recipe_card[] 
    │
    ├── Creates single #mzv-lb-modal <div> (appended to <body>)
    │     Contains: backdrop, img, caption, close btn,
    │               prev/next arrows, counter, jump-to-recipe link
    │
    └── Attaches click handlers to each .mzv-lb-wrap
          On click → open modal with image data

User opens lightbox
    │
    ├── Modal fades in (or instant if reduced-motion/disabled)
    ├── Full-size img src set (lazy loads)
    ├── Caption populated from data-mzv-lb-caption attr
    ├── Jump-to-recipe link shown/hidden per data attr
    ├── Gallery counter updated (e.g., "3 of 12")
    ├── Focus trapped inside modal
    └── Body scroll locked (overflow:hidden on <html>)

User navigates gallery (prev/next/keys/swipe)
    │
    └── Modal content swaps (img src, caption, counter)
        No close/reopen — smooth in-place update

User closes lightbox
    │
    ├── Fade-out animation (or instant)
    ├── If pending jump-to-recipe scroll: execute after modal hidden
    ├── Body scroll restored
    └── Focus returns to triggering element
```

---

## 3. Settings Schema

All settings stored in a single `mzv_lightbox_options` option (serialized array).

```php
// Defaults (canonical source: MZV_LB_Settings::defaults())
[
    // Mode Selection
    'lightbox_mode'           => 'enhanced',   // string: 'css'|'enhanced'

    // Feature: Caption Source (Enhanced only)
    'caption_source'          => 'alt',        // string: 'alt'|'title'|'description'|'none'

    // Feature: WPRM Jump Link (Enhanced only)
    'wprm_jump_enabled'       => true,         // bool

    // Feature: Visibility Controls (both modes)
    'min_image_width'         => 0,            // int (px), 0 = no minimum
    'excluded_classes'        => '',           // string: comma-separated CSS class names
    'recipe_card_lightbox'    => true,         // bool: enable lightbox on WPRM recipe card images

    // Feature: Gallery Mode (Enhanced only)
    'gallery_enabled'         => true,         // bool

    // Feature: Animation (Enhanced only)
    'animations_enabled'      => true,         // bool
    'animation_duration_ms'   => 200,          // int (ms), 50–1000

    // Feature: WPRM Conflict Notice (both modes)
    'wprm_conflict_dismissed' => false,        // bool: user dismissed the notice
]
```

**Notes:**
- `excluded_classes` is stored as a raw comma-separated string; parsed to array at runtime.
- `.no-lightbox` is always excluded — it is hardcoded in PHP, not user-configurable.
- `animation_duration_ms` is clamped to 50–1000ms on save (sanitize callback).
- `min_image_width` is checked against the rendered `width` attribute (or natural width when unavailable).
- When `lightbox_mode = 'css'`, Enhanced-only settings (caption, gallery, animation, WPRM jump) are stored but ignored at runtime. The admin UI shows them grayed out with a note: "Available in Enhanced mode."

---

## 4. Feature Specs

### 4.1 Caption Source Options

**Purpose:** Display contextually meaningful text below the lightbox image.

**PHP behavior (`MZV_LB_Content`):**
- For each wrapped image, resolve the caption value at wrap-time (PHP):
  - `alt` → `$img->getAttribute('alt')`
  - `title` → `$img->getAttribute('title')`
  - `description` → `wp_get_attachment_caption($attachment_id)` (requires attachment ID from `wp-image-{id}` class); empty string if no ID
  - `none` → always empty string
- Inject resolved value as `data-mzv-lb-caption="..."` on the `<img>` element.
- If resolved value is empty string: do not render caption in modal (JS hides `.mzv-lb-caption`).

**JS behavior:**
- On open: read `img.dataset.mzvLbCaption`.
- Set `.mzv-lb-caption` text content; toggle `.is-empty` class when blank.
- CSS: `.mzv-lb-caption.is-empty { display: none }`.

**Acceptance criteria:**
- [ ] Caption displays from correct source per setting.
- [ ] Empty/missing caption: caption element not shown.
- [ ] Changing setting updates all future lightbox opens (no stale cache from inline data).
- [ ] `description` source gracefully degrades to no caption if image has no attachment ID.

---

### 4.2 WPRM "Jump to Recipe" Link

**Purpose:** One-tap shortcut from the hero image lightbox to the recipe card.

**PHP behavior (`MZV_LB_Content`):**
- Guard: only inject if `wprm_jump_enabled === true`, WPRM is active (`function_exists('WPRM') || class_exists('WP_Recipe_Maker')`), and the current post has a WPRM recipe.
- Detect recipe on post: `WPRM_Recipe_Manager::get_recipe_ids_from_post($post_id)` → if non-empty array, a recipe exists. (Static method on `WPRM_Recipe_Manager` class, accepts optional post ID; see OQ-2 resolution.)
- Inject `data-mzv-lb-has-jump="1"` on the `<img>` element.
- PHP also outputs the recipe card anchor selector via `window.mzvLbConfig.recipeCardSelector = '.wprm-recipe-container'` (JS localized data).

**JS behavior:**
- On open: if `img.dataset.mzvLbHasJump === '1'`, show `.mzv-lb-jump-link` button.
- On click: 
  1. Close the lightbox with standard animation.
  2. After close transition ends: `document.querySelector(config.recipeCardSelector)?.scrollIntoView({ behavior: 'smooth', block: 'start' })`.
- If `prefers-reduced-motion`: skip smooth scroll, use instant scroll.

**Acceptance criteria:**
- [ ] Jump link visible only when WPRM active + post has recipe + setting enabled.
- [ ] Jump link not visible on posts without a recipe.
- [ ] Jump link not visible when `wprm_jump_enabled = false`.
- [ ] Clicking jump link: closes lightbox, then scrolls to recipe card.
- [ ] Scroll happens after modal is fully hidden (not during animation).

---

### 4.3 Visibility Controls

**Purpose:** Fine-grained exclusion of images that shouldn't get lightbox treatment.

#### 4.3.1 Minimum Image Width

- PHP: before wrapping, check `$img->getAttribute('width')`.
- If the `width` attribute is set AND is numeric AND is less than `min_image_width`, skip this image.
- If `width` attribute is missing/non-numeric, do NOT skip (be permissive — can't know size without loading).
- Default: `0` (all images eligible).

#### 4.3.2 Excluded CSS Classes

- Settings value: comma-separated string, e.g. `"alignright, sponsor-logo, ad-image"`.
- PHP: parse to array, trim whitespace, remove empty entries.
- For each `<img>`, check `$img->getAttribute('class')` against the list.
- Also walk up the DOM tree (to parent elements) — if any ancestor element has one of the excluded classes, skip the image.
- `.no-lightbox` is always included in this check regardless of settings.
- Depth limit for ancestor walk: stop at `<body>` (same as existing WPRM check).

#### 4.3.3 Recipe Card Images Toggle

- `recipe_card_lightbox` (bool): when `true`, WPRM recipe card images ARE wrapped (get lightbox).
- When `false` (default behavior matching v1): WPRM recipe card images are excluded — identical to v1 `.wprm-recipe-container` ancestor check.
- Recipe card images go into a **separate gallery group** (`group: 'recipe'`) even when enabled, so they don't mix with content images in gallery nav.

**Acceptance criteria:**
- [ ] Images with `width` attribute below threshold skipped; images without `width` attr not skipped.
- [ ] Images (or their ancestors) with excluded classes get no lightbox.
- [ ] `.no-lightbox` always excluded, even if not in the excluded_classes setting.
- [ ] `recipe_card_lightbox = false`: WPRM recipe images skipped (v1 behavior).
- [ ] `recipe_card_lightbox = true`: WPRM recipe images wrapped but in separate gallery group.

---

### 4.4 WPRM Lightbox Conflict Detection

**Purpose:** Warn admins that WPRM's built-in lightbox will double-wrap images.

**Detection logic (`MZV_LB_Admin`):**
```php
function is_wprm_lightbox_active(): bool {
    if ( ! function_exists( 'WPRM' ) ) return false;
    $wprm_settings = get_option( 'wprm_settings', [] );
    // WPRM doesn't have its own lightbox — it has "clickable images" that wrap
    // recipe/instruction images in <a> links to full-size URLs, designed for
    // use with a third-party lightbox plugin.
    // Setting keys: 'recipe_image_clickable' and 'instruction_image_clickable'
    // Found under WPRM > Settings > Lightbox page.
    return WPRM_Settings::get( 'recipe_image_clickable' )
        || WPRM_Settings::get( 'instruction_image_clickable' );
}
```
> ✅ **OQ-1 RESOLVED:** WPRM's "lightbox" feature is actually "clickable images" — it wraps images in `<a>` tags linking to full-size URLs. The setting keys are `recipe_image_clickable` and `instruction_image_clickable`, accessed via `WPRM_Settings::get()`. When enabled AND our plugin is active, recipe card images inside `<a>` tags are skipped by `should_skip_img()` (existing ancestor `<a>` check), which is correct. The admin notice advises disabling WPRM's clickable images.

**Admin notice:**
- Hook: `admin_notices`.
- Show when: `is_wprm_lightbox_active() && ! get_option_value('wprm_conflict_dismissed')`.
- Shown on: all admin pages (not just settings), so it's not missed.
- Message: *"WP Recipe Maker's clickable images feature is enabled. This wraps recipe images in links, which prevents MZV Lightbox from handling them. To let MZV Lightbox manage recipe images, disable clickable images in WPRM → Settings → Lightbox."*
- Dismiss button: sends AJAX request to `wp_ajax_mzv_lb_dismiss_conflict`.
- AJAX handler: sets `mzv_lightbox_options['wprm_conflict_dismissed'] = true`, then updates option.
- Nonce: `mzv_lb_dismiss_nonce` checked in the handler.

**On activation:**
- Hook: `register_activation_hook`.
- If conflict detected: set a transient `mzv_lb_activation_notice` (expires 1 week).
- On next admin page load: display notice and clear transient.

**Acceptance criteria:**
- [ ] Notice shown when WPRM lightbox is active and not dismissed.
- [ ] Notice not shown when WPRM is inactive.
- [ ] Dismiss persists across page reloads.
- [ ] Notice reappears if `wprm_conflict_dismissed` is reset (e.g., by a fresh option update).
- [ ] AJAX dismiss is nonce-protected and capability-checked (`manage_options`).

---

### 4.5 Gallery Browsing Mode

**Purpose:** Let users swipe/click through all content images without closing the lightbox.

**Gallery group construction (JS):**
- On page load, JS queries all `.mzv-lb-wrap` elements.
- Each element has `data-mzv-lb-group` attribute (injected by PHP):
  - Content images: `group="content"`
  - Recipe card images (if enabled): `group="recipe"`
- Build two arrays: `gallery.content[]` and `gallery.recipe[]`.
- When a lightbox opens, the active gallery is the group of the clicked image.

**PHP injection:**
- PHP sets `data-mzv-lb-group="content"` or `data-mzv-lb-group="recipe"` on each `.mzv-lb-wrap` span.

**Modal controls:**
```
[ ← ]  [image]  [ → ]
         [3 of 12]
```
- Previous/next arrows: `<button class="mzv-lb-prev">` and `<button class="mzv-lb-next">`.
- Single image or gallery of 1: arrows are hidden, counter hidden.
- Counter: `<span class="mzv-lb-counter">3 of 12</span>`.
- Wraps: last image → next → first image. (Circular navigation.)

**Keyboard navigation:**
- `ArrowRight` / `ArrowLeft` → next/prev.
- `Escape` → close.
- Event listener attached to `document` when modal is open; removed on close.

**Swipe support (touch events):**
- Track `touchstart` → `touchend` on the modal image area.
- Horizontal swipe > 50px: navigate (left swipe = next, right swipe = prev).
- Vertical swipe > horizontal: ignore (allow native scroll).
- Passive event listeners where possible.

**Gallery transition:**
- On navigate: update `src`, `alt`, caption, counter in-place.
- Optionally: brief opacity transition (50ms) for the image swap — uses same animation duration setting.

**When `gallery_enabled = false`:**
- Arrows and counter not rendered.
- Gallery arrays still built but not used.
- Each image opens independently (same as v1 behavior).

**Acceptance criteria:**
- [ ] Prev/next arrows navigate through all content images on the page in DOM order.
- [ ] Circular wrap: last → next → first.
- [ ] Recipe card images (when enabled) in separate group, not mixed with content images.
- [ ] Counter shows correct position ("1 of N").
- [ ] `ArrowLeft`/`ArrowRight` keys navigate when modal is open.
- [ ] `Escape` closes modal.
- [ ] Swipe left → next, swipe right → prev on touch devices.
- [ ] Single image: arrows and counter hidden.
- [ ] `gallery_enabled = false`: no arrows, no counter, each image independent.
- [ ] Gallery keys do NOT fire when modal is closed.

---

### 4.6 Update Machine v2 Integration

See [Section 8](#8-update-machine-v2-integration).

---

### 4.7 Animation Settings

**Purpose:** Give site owners control over open/close transitions.

**PHP:** Injects `window.mzvLbConfig.animationsEnabled` and `window.mzvLbConfig.animationDurationMs` via `wp_localize_script`.

**JS:**
- On open: if `animationsEnabled && !prefersReducedMotion`:
  - Set CSS custom property `--mzv-lb-duration: {N}ms` on `#mzv-lb-modal`.
  - Add `.is-opening` class → triggers CSS transition: `opacity 0→1, scale 0.96→1`.
  - Remove `.is-opening` after transition ends.
- On close: if `animationsEnabled && !prefersReducedMotion`:
  - Add `.is-closing` class → triggers CSS transition: `opacity 1→0, scale 1→0.96`.
  - After `transitionend` (or `animationDurationMs` timeout as fallback): set `display:none`, restore focus, clean up.
  - This fixes the v1 bug where close was instant.
- If `animationsEnabled = false` OR `prefersReducedMotion`:
  - Instant open/close. No CSS transitions applied.

**CSS:**
```css
#mzv-lb-modal {
    --mzv-lb-duration: 200ms;
    transition: opacity var(--mzv-lb-duration) ease-out;
}
#mzv-lb-modal .mzv-lb-full {
    transition: transform var(--mzv-lb-duration) ease-out;
}
/* .is-opening sets initial states; .is-visible is the steady state */
/* .is-closing reverses the transition */
@media (prefers-reduced-motion: reduce) {
    #mzv-lb-modal, #mzv-lb-modal .mzv-lb-full {
        transition: none !important;
    }
}
```

**Acceptance criteria:**
- [ ] With animations enabled: modal fades in + scales on open.
- [ ] With animations enabled: modal fades out + scales down on close (NEW vs. v1).
- [ ] Animation duration respects settings value.
- [ ] With animations disabled: instant open/close.
- [ ] `prefers-reduced-motion: reduce`: instant regardless of setting.
- [ ] No stuck/invisible modal if `transitionend` never fires (timeout fallback).

---

### 4.8 Maintained from v1

All v1 behaviors are preserved:

| Behavior | How maintained |
|----------|---------------|
| Auto-wraps `.entry-content img` | `MZV_LB_Content::wrap_images()` |
| Skips images inside `<a>` tags | Ancestor walk in `should_skip_img()` |
| Body scroll lock | `html.mzv-lb-open { overflow: hidden }` via JS class on `<html>` |
| Full-size image with lazy load | `loading="lazy"` set on `#mzv-lb-modal img`; src not set until open |
| Dark overlay with close button | `#mzv-lb-modal` (JS-driven) |
| Magnifier hover overlay | `.mzv-lb-hover` CSS, same as v1 |
| Mobile zoom hint | `.mzv-lb-mobile-hint` CSS, same as v1 |
| Print stylesheet | `@media print { #mzv-lb-modal { display:none } }` |
| `role="dialog"`, `aria-modal` | On `#mzv-lb-modal` |
| Focus trap | JS: trap Tab within modal when open |
| `aria-label` | On close btn, prev/next, trigger label |
| `prefers-reduced-motion` | JS check + CSS media query |
| Inline SVG icons | `background-image: url("data:image/svg+xml,...")` |
| `.no-lightbox` escape hatch | Hardcoded in `should_skip_img()` |

---

## 5. Frontend Behavior

### 5.1 DOM Structure (v2)

**Per-image trigger (PHP-generated, in `.entry-content`):**
```html
<span class="mzv-lb-wrap"
      data-mzv-lb-src="https://example.com/image-full.jpg"
      data-mzv-lb-caption="A delicious bowl of pasta"
      data-mzv-lb-group="content"
      data-mzv-lb-has-jump="0">
    <img src="..." alt="..." class="wp-image-123 ..." />
    <span class="mzv-lb-hover" aria-hidden="true"></span>
    <span class="mzv-lb-mobile-hint" aria-hidden="true"></span>
</span>
```

**Single shared modal (JS-generated, appended to `<body>`):**
```html
<div id="mzv-lb-modal"
     role="dialog"
     aria-modal="true"
     aria-label="Image lightbox"
     aria-hidden="true">
    <div class="mzv-lb-backdrop"></div>
    <button class="mzv-lb-prev" aria-label="Previous image"><!-- SVG --></button>
    <button class="mzv-lb-next" aria-label="Next image"><!-- SVG --></button>
    <div class="mzv-lb-content">
        <img class="mzv-lb-full" src="" alt="" loading="lazy" decoding="async" />
        <span class="mzv-lb-caption"></span>
        <a class="mzv-lb-jump-link" href="#" role="button">Jump to Recipe ↓</a>
    </div>
    <button class="mzv-lb-close" aria-label="Close image"><!-- SVG --></button>
    <span class="mzv-lb-counter" aria-live="polite"></span>
</div>
```

**Key design decisions:**
- The checkbox/input/label pattern from v1 is fully removed.
- A single modal is reused for all images (avoids N × overlay DOM nodes).
- `aria-hidden="true"` on modal when closed; removed when open.
- `aria-live="polite"` on counter so screen readers announce gallery position.

### 5.2 JS Module Structure (`mzv-lightbox.js`)

```js
// IIFE or ES module (no build step needed — single file)
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────
    let config = window.mzvLbConfig || {};
    let modal, activeGroup, activeIndex, lastFocused, pendingJump;
    const groups = { content: [], recipe: [] };

    // ── Init ─────────────────────────────────────────────────────────────
    function init() { ... }        // Build groups, create modal, attach handlers

    // ── Modal DOM ────────────────────────────────────────────────────────
    function createModal() { ... } // Once: build #mzv-lb-modal and append to body

    // ── Open / Close ─────────────────────────────────────────────────────
    function openModal(wrapEl) { ... }   // Set img src, caption, counter; show modal
    function closeModal() { ... }        // Animate out; restore focus; clear pending jump
    function afterClose() { ... }        // Called after animation: execute pendingJump scroll

    // ── Navigation ───────────────────────────────────────────────────────
    function navigate(direction) { ... } // direction: +1 | -1

    // ── Animation ────────────────────────────────────────────────────────
    function prefersReducedMotion() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    function shouldAnimate() { return config.animationsEnabled && !prefersReducedMotion(); }

    // ── Swipe ────────────────────────────────────────────────────────────
    let touchStartX, touchStartY;
    function onTouchStart(e) { ... }
    function onTouchEnd(e) { ... }

    // ── Focus Trap ───────────────────────────────────────────────────────
    function trapFocus(e) { ... }  // Tab / Shift+Tab within modal

    // ── Keyboard ─────────────────────────────────────────────────────────
    function onKeyDown(e) { ... }  // ArrowLeft, ArrowRight, Escape

    document.addEventListener('DOMContentLoaded', init);
})();
```

### 5.3 PHP → JS Data Contract (`wp_localize_script`)

```php
wp_localize_script( 'mzv-lightbox', 'mzvLbConfig', [
    'captionSource'         => $opts['caption_source'],          // string
    'galleryEnabled'        => (bool) $opts['gallery_enabled'],  // bool
    'animationsEnabled'     => (bool) $opts['animations_enabled'],
    'animationDurationMs'   => (int)  $opts['animation_duration_ms'],
    'wprm_jump_enabled'     => (bool) $opts['wprm_jump_enabled'],
    'recipeCardSelector'    => '.wprm-recipe-container',
    'i18n' => [
        'close'       => __( 'Close image', 'mzv-lightbox' ),
        'prev'        => __( 'Previous image', 'mzv-lightbox' ),
        'next'        => __( 'Next image', 'mzv-lightbox' ),
        'counter'     => __( '%1$d of %2$d', 'mzv-lightbox' ),  // sprintf format
        'jumpToRecipe'=> __( 'Jump to Recipe ↓', 'mzv-lightbox' ),
        'openImage'   => __( 'Open image in lightbox', 'mzv-lightbox' ),
    ],
] );
```

### 5.4 Enqueue Strategy

```php
// Only enqueue on singular posts/pages (same guard as v1 wrap logic)
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular() ) return;

    $opts = MZV_LB_Settings::get_options();

    if ( $opts['lightbox_mode'] === 'css' ) {
        // CSS-Only mode: inline styles only, zero HTTP requests, zero JS
        wp_register_style( 'mzv-lightbox-base', false ); // dummy handle
        wp_enqueue_style( 'mzv-lightbox-base' );
        wp_add_inline_style( 'mzv-lightbox-base', MZV_LB_CSS_Mode::get_inline_css() );
        // No JS enqueued at all.
        return;
    }

    // Enhanced mode: linked CSS + JS files
    wp_enqueue_script(
        'mzv-lightbox',
        MZV_LB_URL . 'assets/mzv-lightbox.js',
        [],
        MZV_LB_VERSION,
        true   // footer
    );
    wp_enqueue_style(
        'mzv-lightbox',
        MZV_LB_URL . 'assets/mzv-lightbox.css',
        [],
        MZV_LB_VERSION
    );
    wp_localize_script( 'mzv-lightbox', 'mzvLbConfig', [...] );
} );
```

---

## 6. Admin UI

### 6.1 Settings Page

- **Location:** Settings → MZV Lightbox (`options-general.php?page=mzv-lightbox`)
- **Capability:** `manage_options`
- **Method:** `WP_Settings_API` (register_setting + add_settings_section + add_settings_field)
- **Option group:** `mzv_lightbox_options`
- **Single option key:** `mzv_lightbox_options` (array)

### 6.2 Page Layout

```
╔══════════════════════════════════════════════════════════╗
║  MZV Lightbox Settings                                   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: Lightbox Mode                                   ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Mode           ● Enhanced (default)                 │   ║
║  │                  Full JS lightbox with gallery,     │   ║
║  │                  captions, animations & keyboard    │   ║
║  │                ○ CSS-Only                           │   ║
║  │                  Zero JavaScript. Pure CSS lightbox  │   ║
║  │                  with open/close only. Minimal       │   ║
║  │                  footprint.                         │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: Caption                                        ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Caption Source  ○ Alt text (default)              │   ║
║  │                 ○ Title attribute                 │   ║
║  │                 ○ Description (attachment)        │   ║
║  │                 ○ None (no caption)               │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: Visibility                                     ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Min Image Width   [    0    ] px                  │   ║
║  │                   (0 = all images)                │   ║
║  │                                                   │   ║
║  │ Excluded Classes  [alignright, sponsor-logo     ] │   ║
║  │                   Comma-separated. .no-lightbox   │   ║
║  │                   is always excluded.             │   ║
║  │                                                   │   ║
║  │ Recipe Card       ☑ Enable lightbox on WPRM       │   ║
║  │ Images            recipe card images              │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: Gallery                                        ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Gallery Browsing  ☑ Enable prev/next navigation   │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: Animations                                     ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Animations        ☑ Enable open/close animations  │   ║
║  │                                                   │   ║
║  │ Duration          [  200  ] ms  (50–1000)         │   ║
║  │                   (shown only when enabled)       │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  SECTION: WPRM Integration                               ║
║  ┌──────────────────────────────────────────────────┐   ║
║  │ Jump to Recipe    ☑ Show "Jump to Recipe" link    │   ║
║  │                   in lightbox (requires WPRM)     │   ║
║  │                                                   │   ║
║  │  [i] WPRM detected. Lightbox conflict: ACTIVE     │   ║
║  │      [Dismiss Warning]                            │   ║
║  └──────────────────────────────────────────────────┘   ║
╠══════════════════════════════════════════════════════════╣
║  [ Save Changes ]                                        ║
╚══════════════════════════════════════════════════════════╝
```

### 6.3 Field Details

| Field | Type | Key | Mode | Notes |
|-------|------|-----|------|-------|
| Lightbox Mode | Radio | `lightbox_mode` | both | 2 options: Enhanced / CSS-Only |
| Caption Source | Radio | `caption_source` | Enhanced | 4 options; grayed when CSS mode |
| Min Image Width | Number input | `min_image_width` | both | min=0, step=1 |
| Excluded Classes | Text input | `excluded_classes` | both | placeholder shows example |
| Recipe Card Images | Checkbox | `recipe_card_lightbox` | both | |
| Gallery Browsing | Checkbox | `gallery_enabled` | Enhanced | grayed when CSS mode |
| Animations | Checkbox | `animations_enabled` | Enhanced | grayed when CSS mode |
| Duration (ms) | Number input | `animation_duration_ms` | Enhanced | min=50, max=1000; JS-hide when animations off |
| Jump to Recipe | Checkbox | `wprm_jump_enabled` | Enhanced | grayed when CSS mode; note when WPRM inactive |
| WPRM conflict status | Info/notice | — | both | inline in WPRM section, not a settings field |

### 6.4 Sanitize Callbacks

```php
function sanitize( array $input ): array {
    $clean = [];
    $clean['lightbox_mode']           = in_array($input['lightbox_mode'] ?? '', ['css','enhanced'])
                                          ? $input['lightbox_mode'] : 'enhanced';
    $clean['caption_source']          = in_array($input['caption_source'], ['alt','title','description','none']) 
                                          ? $input['caption_source'] : 'alt';
    $clean['wprm_jump_enabled']       = ! empty( $input['wprm_jump_enabled'] );
    $clean['min_image_width']         = max( 0, (int) ($input['min_image_width'] ?? 0) );
    $clean['excluded_classes']        = sanitize_text_field( $input['excluded_classes'] ?? '' );
    $clean['recipe_card_lightbox']    = ! empty( $input['recipe_card_lightbox'] );
    $clean['gallery_enabled']         = ! empty( $input['gallery_enabled'] );
    $clean['animations_enabled']      = ! empty( $input['animations_enabled'] );
    $clean['animation_duration_ms']   = min( 1000, max( 50, (int) ($input['animation_duration_ms'] ?? 200) ) );
    // Preserve dismissed state across saves (not a settings field)
    $clean['wprm_conflict_dismissed'] = ! empty( get_option('mzv_lightbox_options')['wprm_conflict_dismissed'] );
    return $clean;
}
```

---

## 7. WPRM Integration

### 7.1 WPRM Active Detection

```php
// Safe guard — don't assume class/function names without checking
define('MZV_LB_WPRM_ACTIVE', function_exists('WPRM') || class_exists('WP_Recipe_Maker'));
```

### 7.2 Detecting Posts with Recipes

```php
// Using WPRM's public static API (confirmed in official docs)
function mzv_lb_post_has_recipe( int $post_id ): bool {
    if ( ! MZV_LB_WPRM_ACTIVE ) return false;
    if ( class_exists( 'WPRM_Recipe_Manager' ) 
         && method_exists( 'WPRM_Recipe_Manager', 'get_recipe_ids_from_post' ) ) {
        return ! empty( WPRM_Recipe_Manager::get_recipe_ids_from_post( $post_id ) );
    }
    // Fallback: check for WPRM shortcode or block in content
    $post = get_post( $post_id );
    if ( ! $post ) return false;
    return strpos( $post->post_content, 'wprm-recipe-id' ) !== false
        || strpos( $post->post_content, '[wprm-recipe' ) !== false;
}
```

### 7.3 Recipe Card Exclusion Logic

```
recipe_card_lightbox = false (default)
  → Images inside .wprm-recipe-container: skip entirely (v1 behavior)

recipe_card_lightbox = true
  → Images inside .wprm-recipe-container: wrap, but set data-mzv-lb-group="recipe"
  → These form a separate gallery group (not mixed with content images)
```

### 7.4 Conflict Detection

```php
function mzv_lb_wprm_lightbox_active(): bool {
    if ( ! MZV_LB_WPRM_ACTIVE ) return false;
    if ( ! class_exists( 'WPRM_Settings' ) ) return false;
    // WPRM's "Lightbox" page controls clickable images, not a built-in lightbox.
    // These settings wrap images in <a> tags linking to full-size URLs.
    return WPRM_Settings::get( 'recipe_image_clickable' )
        || WPRM_Settings::get( 'instruction_image_clickable' );
}
```

WPRM's "Lightbox" settings page actually controls "clickable images" — wrapping recipe/instruction images in `<a>` links to full-size URLs, designed for use with a third-party lightbox plugin. The setting keys are `recipe_image_clickable` and `instruction_image_clickable`, accessed via `WPRM_Settings::get()`.

---

## 8. Update Machine v2 Integration

### 8.1 Client File

Copy `um-updater.php` from `dontpressthis/update-machine-v2` (same file used in `msn-feed-exclusion` plugin) into `includes/um-updater.php`. **Do not modify the file** — it guards against double-loading with `UM_PLUGIN_UPDATER_LOADED`.

### 8.2 Bootstrap in Main Plugin File

```php
// In mzv-lightbox.php, after constants are defined:
require_once MZV_LB_DIR . 'includes/um-updater.php';

add_action( 'init', function() {
    if ( is_admin() ) {
        \UM\PluginUpdater\register([
            'file'       => MZV_LB_FILE,
            'slug'       => 'mzv-lightbox',
            'update_url' => 'https://updates.mikezielonka.com/plugins/mzv-lightbox/info.json',
        ]);
    }
} );
```

### 8.3 Update Server Manifest Format

The update server must return JSON matching the UM v2 expected schema:

```json
{
    "version": "2.0.1",
    "download_url": "https://updates.mikezielonka.com/releases/mzv-lightbox-2.0.1.zip",
    "requires": "6.0",
    "tested": "6.8",
    "requires_php": "7.4",
    "last_updated": "2026-04-21",
    "sections": {
        "description": "<p>Pure-JS lightbox for WordPress...</p>",
        "changelog": "<h4>2.0.1</h4><ul><li>Bug fix</li></ul>"
    }
}
```

### 8.4 Behavior

- No license key — all installs receive updates unconditionally.
- Client caches the remote manifest for 6 hours (`set_site_transient`).
- Update appears in Plugins → Updates like any other plugin.
- "View details" link in the updates table uses data from the same manifest.

---

## 9. Performance Budget & Constraints

| Asset | v1 Budget | v2 Target | Notes |
|-------|-----------|-----------|-------|
| CSS | < 2KB gz | < 5KB gz | More rules for modal, arrows, counter, animations |
| JS | 0 bytes | < 8KB gz | Vanilla, no jQuery, no framework |
| PHP | negligible | negligible | DOM parsing unchanged from v1 |
| Extra HTTP requests | 0 | 0 | CSS/JS enqueued as files; icons still inline SVG |
| Full-size images | lazy | lazy | `loading="lazy"` on `#mzv-lb-modal img`; src only set on open |

**JS loading constraints:**
- Loaded in footer (`true` for `$in_footer` in `wp_enqueue_script`).
- Loaded only on `is_singular()` pages (same guard as v1).
- No jQuery dependency.
- No external CDN dependencies.

**CSS constraints:**
- Delivered as a linked stylesheet (one HTTP request vs. v1's inline approach).
- Trade: v1 had zero extra HTTP requests; v2 accepts one CSS request for cleaner plugin structure.
- If zero-request CSS is desired, the same inline trick can be applied — but the JS file will always be an HTTP request anyway, so the trade-off is moot.

**No WP_Query on frontend:**
- Recipe detection (`mzv_lb_post_has_recipe`) is called at most once per page render, caching the result.

---

## 10. Accessibility Requirements

### Required (must ship)

| Requirement | Implementation |
|-------------|----------------|
| Modal role | `role="dialog"` on `#mzv-lb-modal` |
| Modal labeled | `aria-label="Image lightbox"` |
| Modal hidden when closed | `aria-hidden="true"` set/removed by JS |
| Close button label | `aria-label="Close image"` |
| Prev/next labels | `aria-label="Previous image"` / `aria-label="Next image"` |
| Focus trap | JS: Tab/Shift+Tab cycles through modal's focusable elements only |
| Focus restore | On close: `lastFocused.focus()` (the triggering `.mzv-lb-wrap` label) |
| Keyboard close | `Escape` key |
| Keyboard navigate | `ArrowLeft` / `ArrowRight` |
| Counter announced | `aria-live="polite"` on `.mzv-lb-counter` |
| Trigger label | `aria-label="Open image in lightbox"` on `.mzv-lb-wrap` |
| Focus ring | Visible outline on all interactive elements |
| Reduced motion | Instant transitions when `prefers-reduced-motion: reduce` |

### Nice-to-have (post-v2)

- `aria-roledescription="carousel"` on gallery container.
- Announce image alt text via `aria-describedby` when navigating.
- High-contrast mode testing.

### Known gap

Tab order within the modal puts the backdrop (`<div class="mzv-lb-backdrop">`) first in DOM. The backdrop is not focusable (no `tabindex`), so Tab should skip it. Verify in testing.

---

## 11. Security Checklist

| Risk | Mitigation |
|------|-----------|
| XSS in caption | `$img->getAttribute('alt')` output stored in `data-*` attr via DOM API (no `innerHTML` in PHP); JS uses `textContent`, never `innerHTML` for caption |
| XSS in excluded_classes setting | `sanitize_text_field()` on save |
| CSRF on dismiss AJAX | `check_ajax_referer('mzv_lb_dismiss_nonce')` |
| Unauthorized dismiss | `current_user_can('manage_options')` check in AJAX handler |
| Settings save | `register_setting()` with sanitize callback; nonce via Settings API |
| Full-size URL injection | URL comes from `wp_get_attachment_image_url()` (trusted) or regex strip from existing `src` (already in content) — not from user input |
| DOMDocument parsing | `libxml_use_internal_errors(true)` to suppress warnings; no exec, no eval |
| UM updater | Only fetches from hardcoded update URL; `esc_url_raw()` applied; result decoded as JSON only |

---

## 12. Migration from v1

### 12.1 What Changes

| Area | v1 | v2 |
|------|----|----|
| Lightbox mechanism | CSS checkbox hack | JS modal |
| DOM added per image | `<input>` + `<label>` + `.mzv-lb-overlay` | `<span class="mzv-lb-wrap">` only; single shared `#mzv-lb-modal` |
| CSS class names | `mzv-lb-*` (kept) | `mzv-lb-*` (preserved for compatibility) |
| JS | None | `mzv-lightbox.js` |
| Settings | None | `mzv_lightbox_options` option |
| Files | 1 PHP file | `mzv-lightbox.php` + `includes/` + `assets/` |
| Installation | Plugin or mu-plugin | Plugin (not mu-plugin) |
| Update mechanism | Manual | Update Machine v2 |

### 12.2 Backward Compatibility

- **CSS class names preserved:** `.mzv-lb-wrap`, `.mzv-lb-hover`, `.mzv-lb-mobile-hint`, `.mzv-lb-full`, `.mzv-lb-caption`, `.mzv-lb-close`. Any custom CSS targeting these classes continues to work.
- **`.no-lightbox` escape hatch:** works identically.
- **`data-*` attributes added:** no conflict with existing attributes.
- **The checkbox/input markup is removed.** Any CSS or JS that targeted `input.mzv-lb-toggle` or `label[for="mzv-lb-*"]` will break. These were internal implementation details not intended for customization.

### 12.3 Upgrade Path

1. Deactivate v1 plugin (or remove from mu-plugins).
2. Install and activate v2 plugin.
3. v2 auto-detects no existing `mzv_lightbox_options` → uses defaults (caption: alt, gallery: on, animations: on, recipe card: excluded).
4. No database migration needed — v1 stored no options.
5. Existing content is unchanged; v2 re-wraps on each page request (same as v1).

### 12.4 Failure Mode: Both Active Simultaneously

If v1 (mu-plugin) and v2 (plugin) are both active:
- v1 wraps images first (mu-plugins load before regular plugins).
- v2 also runs `the_content` filter.
- v2's DOMDocument parser will encounter `<span class="mzv-lb-wrap">` elements (already wrapped by v1) containing `<img>` tags.
- v2's `should_skip_img()` should detect the parent `.mzv-lb-wrap` class and skip those images.

**Mitigation:** Add `.mzv-lb-wrap` to the list of ancestor classes that trigger a skip. This prevents double-wrapping even if both versions are active.

---

## 13. Test Matrix

### PHP / Content Wrapping

| Test | Expected |
|------|----------|
| Standard `.entry-content img` | Wrapped with `.mzv-lb-wrap` |
| Image inside `<a>` | Not wrapped |
| Image with `class="no-lightbox"` | Not wrapped |
| Image inside `.wprm-recipe-container` (recipe_card_lightbox=false) | Not wrapped |
| Image inside `.wprm-recipe-container` (recipe_card_lightbox=true) | Wrapped, `data-mzv-lb-group="recipe"` |
| Image with `width="30"` and min_image_width=100 | Not wrapped |
| Image with `width="200"` and min_image_width=100 | Wrapped |
| Image without `width` attr and min_image_width=100 | Wrapped (permissive) |
| Image with excluded class `sponsor-logo` | Not wrapped |
| Image whose parent has excluded class | Not wrapped |
| Non-singular page (archive/home) | No wrapping at all |
| Caption source = alt: image with alt | `data-mzv-lb-caption` = alt value |
| Caption source = alt: image without alt | `data-mzv-lb-caption` = "" |
| Caption source = description: image with attachment ID | Caption from `wp_get_attachment_caption()` |
| Caption source = none | `data-mzv-lb-caption` = "" |
| Post with WPRM recipe + wprm_jump_enabled | `data-mzv-lb-has-jump="1"` on content images |
| Post without recipe + wprm_jump_enabled | `data-mzv-lb-has-jump="0"` |

### JS / Modal Behavior

| Test | Expected |
|------|----------|
| Click image → modal opens | Modal visible, src loaded, caption shown |
| Click backdrop → modal closes | Modal hidden |
| Click X → modal closes | Modal hidden |
| Escape key → modal closes | Modal hidden |
| ArrowRight → next image | Counter increments, img src changes |
| ArrowLeft → prev image | Counter decrements, img src changes |
| Last image + ArrowRight | Wraps to first image |
| Swipe left | Navigates to next image |
| Swipe right | Navigates to prev image |
| Swipe vertical (>45°) | No navigation |
| Single image | Arrows hidden, counter hidden |
| gallery_enabled = false | Arrows hidden, no keyboard nav |
| animations_enabled = true | Fade-in on open, fade-out on close |
| animations_enabled = false | Instant open/close |
| prefers-reduced-motion | Instant regardless of setting |
| Body scroll lock | `overflow:hidden` on `<html>` when open |
| Focus trap | Tab stays within modal |
| Focus restore | Focus returns to trigger on close |
| Jump to recipe click | Modal closes, then page scrolls to recipe |

### CSS-Only Mode

| Test | Expected |
|------|----------|
| Click image → lightbox opens (CSS mode) | Checkbox toggles, overlay visible, full-size image shown |
| Click overlay/close → closes (CSS mode) | Checkbox unchecked, overlay hidden |
| Body scroll lock (CSS mode) | `html:has(:checked)` prevents body scroll |
| Magnifier hover overlay (CSS mode) | Hover on desktop shows magnifier; mobile shows zoom hint |
| `.no-lightbox` image (CSS mode) | Not wrapped, no lightbox |
| Excluded class image (CSS mode) | Not wrapped, no lightbox |
| Min width exclusion (CSS mode) | Small images not wrapped |
| Recipe card image excluded (CSS mode, recipe_card_lightbox=false) | Not wrapped |
| No JS loaded (CSS mode) | Page source has zero `mzv-lightbox.js` script tags |
| No linked CSS file (CSS mode) | CSS is inline only, zero extra HTTP requests |
| `prefers-reduced-motion` (CSS mode) | Instant show/hide, no transitions |
| Print stylesheet (CSS mode) | Lightbox overlays hidden in print |

### Admin / Settings

| Test | Expected |
|------|----------|
| Save valid settings | `mzv_lightbox_options` updated correctly |
| lightbox_mode = "css" | CSS-Only mode active; no JS enqueued; inline CSS used |
| lightbox_mode = "enhanced" | Enhanced mode active; JS + CSS files enqueued |
| lightbox_mode = "invalid" | Falls back to "enhanced" |
| CSS mode + Enhanced-only settings | Settings saved but grayed out in UI |
| animation_duration_ms = 5 (below min) | Clamped to 50 |
| animation_duration_ms = 9999 (above max) | Clamped to 1000 |
| caption_source = "invalid" | Falls back to "alt" |
| WPRM lightbox active | Conflict notice shown |
| Dismiss notice | Notice hidden, `wprm_conflict_dismissed = true` |
| WPRM not active | No conflict notice |
| Unauthorized user visits settings | WordPress capability check blocks access |

---

## 14. Open Questions — ALL RESOLVED

All questions resolved by Robin on 2026-04-21. No blockers remain.

**OQ-1: WPRM lightbox option key** ✅ RESOLVED  
WPRM doesn't have a built-in lightbox. Its "Lightbox" settings page controls "clickable images" — wrapping recipe/instruction images in `<a>` links to full-size URLs for use with third-party lightbox plugins. Setting keys: `recipe_image_clickable` and `instruction_image_clickable`, accessed via `WPRM_Settings::get()`. Our `should_skip_img()` already handles this correctly (skips images inside `<a>` tags). Admin notice updated to reference the correct feature name and settings path.  

**OQ-2: `wprm_get_recipe_ids_from_post` availability** ✅ RESOLVED  
Confirmed via official WPRM developer docs (Bootstrapped Ventures KB). It's a static method: `WPRM_Recipe_Manager::get_recipe_ids_from_post($post_id)`. Accepts optional post ID parameter (uses global `$post` if omitted). Returns array of recipe IDs. Fallback via shortcode string check retained for safety.  

**OQ-3: Recipe card anchor selector** ✅ RESOLVED  
Selector `.wprm-recipe-container` is correct — confirmed across WPRM's own demo site, documentation, and third-party code examples. For multiple recipes on one page, `document.querySelector()` returns the first match, which is the right UX (scroll to first recipe).  

**OQ-4: CSS delivery — inline vs. file** ✅ RESOLVED  
Split by mode:
- **CSS-Only mode:** Inline CSS via `wp_add_inline_style` (zero HTTP requests, matching v1 approach).
- **Enhanced mode:** Linked CSS file + linked JS file (two HTTP requests total, clean plugin structure). Perfmatters or similar optimization plugins can combine/defer these.  

**OQ-5: Gallery image ordering** ✅ RESOLVED  
DOM order is correct. Gallery navigation follows the order images appear in the rendered content. This matches reader expectation ("next" = the image below the current one on the page).  

**OQ-6: No-JS fallback** ✅ RESOLVED  
Moot — the new two-mode architecture solves this. Sites wanting zero-JS get the CSS-Only mode (full lightbox via checkbox hack). Enhanced mode requires JS; without it, images remain as plain images (acceptable graceful degradation for an enhancement feature).
