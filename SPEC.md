# Little Figgy Lightbox — v1 Spec

A pure-CSS, zero-JS lightbox built specifically for Little Figgy. Fast, lightweight, no render-blocking, no dependencies.

## Goals

- **Fast.** Under 2KB gzipped CSS. Zero JavaScript. Zero extra HTTP requests.
- **Good UX.** Hover-to-indicate on desktop, always-visible tap target on mobile, smooth fade-in, tap-anywhere-to-close.
- **Plays nice with Kadence + Perfmatters.** No script to delay, no asset to prioritize.
- **Doesn't break recipes.** WPRM recipe card images are explicitly excluded.
- **No URL pollution.** Opening an image doesn't change the URL or clutter browser history.

## The core technique

**Checkbox hack** — not `:target`.

Each image gets wrapped in a `<label>` tied to a hidden checkbox. The overlay's visibility is controlled by `input:checked ~ .lightbox { ... }`. A second label inside the overlay toggles the checkbox back off to close.

Why not `:target`:
- Adds `#image-id` to the URL (gets indexed, appears in share links)
- Back button closes the modal but also scrolls the page
- Jarring on long recipe posts

Checkbox hack trades a tiny bit of extra DOM for cleaner behavior.

## Visual spec

### Trigger (the inline image)

- Cursor: `zoom-in` on hover
- Dark gradient overlay fades in over the image on hover (~200ms ease-out)
- Centered magnifier-plus SVG icon fades in with it
- **Mobile**: small zoom icon always visible in the bottom-right corner at ~60% opacity with 8px padding, so users know it's tappable
- Entire `<figure>` is the trigger — larger tap target than the `<img>` alone

### Overlay

- `position: fixed; inset: 0;`
- Background: black @ 92% opacity with `backdrop-filter: blur(4px)`
- Image centered: `max-width: 95vw; max-height: 90vh; object-fit: contain`
- Entrance animation: opacity 0→1, scale 0.96→1, 200ms ease-out
- Close X top-right (32px tap target, white with drop-shadow)
- Tap anywhere on the backdrop also closes
- Caption shown at the bottom as a thin readable pill (pulled from alt text)

### Icons

- Inline SVG encoded as `background-image: url("data:image/svg+xml,...")` — zero extra requests
- Magnifier-plus glyph for the trigger
- X glyph for close
- Both white with a subtle drop-shadow for contrast over bright food photos

## Body scroll lock

Pure CSS via `:has()`:

```css
html:has(input.lf-lb-toggle:checked) {
  overflow: hidden;
}
```

Supported in all modern browsers. No JS needed.

## PHP integration

A small mu-plugin filters `the_content`:

1. Match `<img>` tags inside `.entry-content` (post content only, not sidebars/widgets).
2. **Skip if:**
   - Parent contains `.wprm-recipe-container` (WPRM recipe card images)
   - Image has class `no-lightbox` (escape hatch)
   - Image is inside an `<a>` that already links somewhere (respect existing links)
3. Grab the full-size image URL from the attachment ID. Fallback: strip `-{w}x{h}` from the src.
4. Wrap in the label/checkbox/overlay markup.
5. Delivered as a **mu-plugin** so it survives plugin activation changes and can't be deactivated accidentally.

## Out of scope for v1

Deliberately cut to keep the build tight:

- **Prev/next navigation** — doable in CSS but every checkbox needs to know about every sibling. DOM explodes. Not worth it for recipe posts where one hero shot is the norm.
- **Pinch-zoom inside the lightbox** — browser's native pinch works fine at full res. Don't reinvent it.
- **ESC to close** — pure CSS can't listen for keydown. If users ask for it, we add ~6 lines of vanilla JS later.
- **Exit animation** — `:checked → :not(:checked)` transitions get janky. Instant close is snappier anyway.
- **Gallery grouping** — each image operates independently.

## Nice-to-haves included in v1

- **`prefers-reduced-motion`** — drops the scale animation, keeps opacity only.
- **Print stylesheet** — `@media print { .lf-lightbox { display: none !important; } }` so print layouts don't break.
- **`loading="lazy"` + `decoding="async"`** on the full-size image so browsers don't fetch it until the checkbox flips.
- **High z-index** (`2147483646`) so it sits above the Kadence admin bar and any popups.

## Accessibility — pragmatic, not complete

Honest take: pure-CSS modals can't fully satisfy WCAG focus-trap requirements. We do what we can:

- `role="dialog"` and `aria-modal="true"` on the overlay
- `aria-label="Close image"` on the close button
- Alt text carries into the caption (keeps it meaningful for screen readers)
- Visible focus rings on the trigger and close button
- High-contrast close button

If this becomes a real issue, the upgrade path is ~20 lines of JS for focus trap + ESC.

## Performance budget

| Asset | Budget |
|-------|--------|
| CSS | < 2KB gzipped |
| JS | 0 bytes |
| Extra HTTP requests | 0 |
| Icons | Inline SVG (data URI) |
| Full-size images | Lazy-loaded, fetched only on open |

## Files delivered

1. `lf-lightbox.php` — mu-plugin, filters `the_content` and wraps images
2. `lf-lightbox.css` — the styles (inlined by the mu-plugin via `wp_add_inline_style` so it doesn't become another HTTP request)

Installed to `/wp-content/mu-plugins/lf-lightbox/`.

## Open decisions

These need a call before I build:

1. **Auto-wrap vs. opt-in.** Recommendation: auto-wrap every `.entry-content img` with a `.no-lightbox` escape hatch. More magic, less ongoing work.
2. **Caption source.** Recommendation: use alt text. Usually well-written on your posts and doubles as a11y.
3. **Galleries.** Recommendation: each image independent. Grouping is out of scope.

## Rollout plan

1. Build on staging (clone via `wp-clone-local` if we don't have a working copy).
2. QA pass on desktop + mobile: hero images, inline images, images inside WPRM cards (should be untouched), images with existing links (should be untouched).
3. Check Perfmatters didn't break anything (it won't — there's no JS to delay).
4. Push to production as an mu-plugin.
5. Smoke-test on a live post.

## Definition of done

- Opens on click/tap from any non-excluded `.entry-content` image
- Closes on X, backdrop tap, or backdrop click
- WPRM recipe card images do NOT get the lightbox
- Images wrapped in an existing link do NOT get the lightbox
- Images with class `no-lightbox` do NOT get the lightbox
- Body doesn't scroll while open
- Full-res image loads only when opened
- Works on iOS Safari, Android Chrome, desktop Chrome/Firefox/Safari/Edge
- CSS payload under 2KB gzipped
- Zero JavaScript added
- No console errors, no layout shift
