# 🔍 WPInspectorClaw — MZV Lightbox v1.0.0 Code Review

**Reviewed:** 2026-04-21  
**Files:** `mzv-lightbox.php`, `SPEC.md`  
**CSS budget:** 1.07KB gzipped ✅ (well under 2KB target)

---

## 🔴 HIGH (must fix before release)

### 1. Double-escaping via `esc_attr()` / `esc_url()` + `setAttribute()`

**Location:** `mzv_lb_build_markup()` — every `setAttribute()` call that pre-escapes its value.

**The bug:** `DOMDocument::setAttribute()` stores the raw value internally. `saveHTML()` handles HTML-encoding on output. If you pass `esc_attr($alt)` to `setAttribute()`, the `&` in `&amp;` gets re-escaped to `&amp;amp;` in the final HTML.

**Confirmed via test:**
```
Input alt:  "Mac & Cheese"
esc_attr(): "Mac &amp; Cheese"         ← correct for raw HTML output
setAttribute(esc_attr(val)): alt="Mac &amp;amp; Cheese"  ← BUG
setAttribute(raw val):       alt="Mac &amp; Cheese"      ← correct
```

**Affected lines:**
- `$label->setAttribute('aria-label', esc_attr__('Open image in lightbox', 'mzv-lightbox'))` — low risk (no special chars in translation), but still wrong
- `$full_img->setAttribute('src', esc_url($full_src))` — **active breakage**: URLs with query params (e.g. CDN URLs with `?w=300&h=200`) produce `src="...?w=300&amp;amp;h=200"` — broken image
- `$full_img->setAttribute('alt', esc_attr($alt))` — captions with `&` render as literal `&amp;`
- `$backdrop->setAttribute('aria-label', esc_attr__('Close image', 'mzv-lightbox'))` — low risk
- `$close->setAttribute('aria-label', esc_attr__('Close image', 'mzv-lightbox'))` — low risk

**Fix:** Remove the `esc_attr()` / `esc_url()` wrappers on all `setAttribute()` calls. Trust `saveHTML()` to escape. Validation of the src URL should use `wp_http_validate_url()` or `filter_var()` before setting, not `esc_url()`.

```php
// WRONG
$full_img->setAttribute( 'src', esc_url( $full_src ) );
$full_img->setAttribute( 'alt', esc_attr( $alt ) );

// CORRECT — DOMDocument handles escaping in saveHTML()
$full_img->setAttribute( 'src', $full_src );
$full_img->setAttribute( 'alt', $alt );

// Optional URL validation without HTML-encoding:
if ( ! filter_var( $full_src, FILTER_VALIDATE_URL ) && ! str_starts_with( $full_src, '/' ) ) {
    return; // skip unsafe src
}
```

---

### 2. Empty and placeholder `src` not guarded

**Location:** `mzv_lb_wrap_images()` — no check before adding to `$to_process`.

**The bug:** Images with `src=""` (or `src` omitted) get wrapped and produce `src=""` in the overlay `<img>`. Browsers interpret `src=""` as a same-page request — a wasted round-trip and potential 404. Worse, lazy-loaded images that use a Base64 GIF placeholder (`src="data:image/gif;base64,..."`) and store the real URL in `data-src` / `data-lazy-src` get their placeholder burned into the overlay's full-size `src`.

For the lazy-load case: if the image also has a `wp-image-N` class, the `mzv_lb_get_full_size_url()` function correctly retrieves the real URL via `wp_get_attachment_image_url()`. But if no `wp-image-N` class is present (third-party images, externally hosted), the data URI ends up as the full-size overlay image.

**Fix:** Skip images with empty or data-URI `src` in `mzv_lb_wrap_images()`:

```php
$src = $img->getAttribute( 'src' );
if ( empty( $src ) || str_starts_with( $src, 'data:' ) ) {
    continue;
}
```

---

### 3. Keyboard users cannot open or close the lightbox

**Location:** `mzv_lb_build_markup()` — trigger `<label>` and close `<label>` have no `tabindex`.

**The bug:** `<label>` elements are not natively keyboard-focusable. The trigger label (`.mzv-lb-trigger`) has no `tabindex="0"`, so Tab key never reaches it. The `.mzv-lb-wrap:focus-visible` CSS rule is **dead code** — the element it targets is not focusable. Same applies to the close label (`.mzv-lb-close`). Keyboard users have zero interaction path.

The spec says "Visible focus rings on the trigger and close button" — this implies they should be reachable. Right now they're not.

**Fix:** Add `tabindex="0"` to both the trigger label and the close label:

```php
$label->setAttribute( 'tabindex', '0' );
$close->setAttribute( 'tabindex', '0' );
```

And update the focus-visible CSS to target the label directly:

```css
/* Was: .mzv-lb-wrap:focus-visible — unreachable */
.mzv-lb-trigger:focus-visible { outline: 2px solid #0073aa; outline-offset: 2px; border-radius: 2px; }
.mzv-lb-close:focus-visible   { outline: 2px solid #fff; outline-offset: 4px; border-radius: 2px; }
```

Note: keyboard Enter/Space on a `<label>` activates its associated input in most browsers, so the checkbox hack works once the label is focusable.

---

## 🟡 MEDIUM (should fix soon)

### 4. `position: fixed` overlay breaks inside CSS transform ancestors

**Location:** CSS `.mzv-lb-overlay { position: fixed; ... }`

CSS spec: `position: fixed` is relative to the nearest ancestor with a `transform`, `filter`, `will-change: transform`, or `perspective` property — not the viewport. If any ancestor of `.mzv-lb-wrap` applies one of these (common in Kadence theme sticky headers, scroll-animation blocks, AOS, lazy-load wrappers), the overlay will be clipped to that ancestor instead of covering the full viewport.

The `z-index: 2147483646` provides no protection here since stacking context is isolated by the transform parent.

**Mitigation options:**
1. Move the overlay DOM node to be a direct child of `<body>` via JS on open (breaks the pure-CSS approach)
2. Document this as a known limitation; test on Kadence specifically with sticky header and scroll-animation blocks
3. Check if Kadence's entry-content container applies transforms and, if so, add a CSS fallback

**Recommended action:** Test on the Kadence site before release. Flag as a known limitation if transform ancestors are not present in the target content area.

---

### 5. Spec deviation — `<figure>` should be the trigger, not `<img>`

**SPEC says:** "Entire `<figure>` is the trigger — larger tap target than the `<img>` alone"

**What the code does:** Replaces the `<img>` element with `<span class="mzv-lb-wrap">`. If the image is inside a `<figure>`, the figure becomes a mixed container: the span replaces the img, but the figcaption and figure wrapper remain outside the trigger tap target.

For standalone images this barely matters, but for Gutenberg Image blocks (which output `<figure class="wp-block-image"><img></figure>`), the tap target is only as large as the `<img>` itself — smaller than the spec intended.

**Fix:** When the `<img>` parent is a `<figure>`, wrap the entire `<figure>` instead. This requires detecting the parent in `$to_process` loop and calling `$figure->parentNode->replaceChild($wrap, $figure)`.

---

### 6. Exit animation contradicts the spec

**SPEC says:** "Exit animation — `:checked → :not(:checked)` transitions get janky. Instant close is snappier anyway." Exit animations are explicitly **out of scope**.

**What the code does:** Both `.mzv-lb-overlay` and `.mzv-lb-full` have `transition` properties that fire in both directions, so unchecking the checkbox plays a fade-out + scale-down animation. This is exactly what the spec said not to do.

**Fix:** Scope the transitions to apply only in the `:checked` state, not on the way out:

```css
/* Transitions only on open, instant close */
input.mzv-lb-toggle:checked ~ .mzv-lb-overlay {
    opacity: 1;
    pointer-events: auto;
    transition: opacity .2s ease-out;
}
input.mzv-lb-toggle:checked ~ .mzv-lb-overlay .mzv-lb-full {
    transform: scale(1);
    transition: transform .2s ease-out;
}
/* Base (closed) state — no transition */
.mzv-lb-overlay { opacity: 0; pointer-events: none; }
.mzv-lb-full { transform: scale(.96); }
```

---

### 7. Not delivered as a mu-plugin

**SPEC says:** "Delivered as a **mu-plugin** so it survives plugin activation changes and can't be deactivated accidentally." Install to `/wp-content/mu-plugins/lf-lightbox/`.

The file has a standard plugin header and lives as a regular plugin. It can be disabled from wp-admin. The spec was explicit about this being a mu-plugin for resilience.

**Fix:** Move to `/wp-content/mu-plugins/mzv-lightbox/mzv-lightbox.php`. The standard plugin header is harmless in an mu-plugin directory and is good practice for identification.

---

### 8. Dead code — `$fragment` never used

**Location:** `mzv_lb_wrap_images()`, line ~95:

```php
$fragment = $doc->createDocumentFragment();
$html     = mzv_lb_build_markup( $id, $img, $full_src, $alt, $doc );
```

`$fragment` is assigned and immediately abandoned. `mzv_lb_build_markup()` creates and returns a `<span>` directly; the fragment is never used. No functional impact, but it signals incomplete refactoring.

**Fix:** Delete the `$fragment` line.

---

## 🟢 LOW (nice to have)

### 9. `.mzv-lb-trigger` CSS class is unused

The trigger label gets class `mzv-lb-trigger` in PHP, but no CSS rule targets it. The hover effect is driven by `.mzv-lb-wrap:hover .mzv-lb-hover` (which works because the hover span is inside `.mzv-lb-wrap`). This is not a bug, but the class is vestigial unless focus styles get moved to `.mzv-lb-trigger:focus-visible` as suggested in finding #3.

### 10. SVG images will receive the lightbox

No filter excludes `src="*.svg"`. SVG lightboxes work (SVGs render fine in `object-fit: contain`), but animated or script-containing SVGs in a lightbox could behave unexpectedly. Recommend documenting this or adding an SVG src check if your content contains SVG images.

### 11. Gallery images without links will get lightboxed

Gutenberg Gallery blocks can be configured with "Link to: None" — in this case, gallery thumbnails have no parent `<a>`, so the existing-link check doesn't protect them. Each gallery image gets its own independent lightbox. Per spec, gallery grouping is out of scope and each image is independent — so this is technically correct behavior, but the UX of opening a lightbox from within a gallery grid looks odd. Worth noting for QA.

---

## ✅ PASSED

- **CSS budget:** 1.07KB gzipped — well within 2KB target
- **Gutenberg block comment preservation:** `<!-- wp:image -->` markers survive `DOMDocument` round-trip
- **`no-lightbox` escape hatch:** works correctly
- **Skip images inside `<a>`:** ancestor walk correctly catches all link depths
- **Skip WPRM recipe container:** ancestor walk correctly catches `.wprm-recipe-container`
- **Full-size URL resolution:** `wp-image-N` class → `wp_get_attachment_image_url()` with dimension-strip fallback is correct
- **`libxml_use_internal_errors` save/restore:** properly saves and restores the previous error state
- **`$to_process` collect-then-process pattern:** avoids modifying a live `DOMNodeList` during iteration — correct
- **Fast bail conditions:** `is_singular()` + `stripos($content, '<img')` avoid unnecessary DOM parsing
- **Scroll lock:** `html:has(input.mzv-lb-toggle:checked){overflow:hidden}` — clean, modern, no JS
- **`prefers-reduced-motion`:** transitions correctly disabled
- **Print stylesheet:** overlay/hints hidden from print
- **`loading="lazy"` + `decoding="async"`** on overlay full-size image — full-res deferred until open ✓
- **z-index 2147483646** — as specified ✓
- **Inline SVG data URIs** — zero extra HTTP requests ✓
- **`is_admin()` guard** in both functions ✓
- **Plugin header:** complete, correct text domain, proper version info ✓
- **Function prefix:** all functions consistently prefixed `mzv_lb_` ✓
- **`wp_register_style( 'mzv-lightbox', false )` + phpcs:ignore** — correct pattern for inline-only style ✓
- **Zero JavaScript** — confirmed ✓

---

## Summary

| Severity | Count | Highlights |
|----------|-------|------------|
| 🔴 HIGH  | 3     | Double-escape breaks alt text & URLs with `&`; empty src guard missing; keyboard inaccessible |
| 🟡 MEDIUM | 4    | Fixed overlay in transform context; figure-vs-img trigger; exit animation contradicts spec; not a mu-plugin |
| 🟢 LOW   | 3     | Dead class, SVGs, gallery edge case |

**The double-escaping bug (#1) is the most likely to surface immediately on real content** — any image with an `&` in its alt text or any CDN/attachment URL with query parameters will produce broken output. Fix that one before anything else goes near production.
