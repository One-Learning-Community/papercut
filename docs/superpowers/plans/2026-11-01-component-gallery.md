# Component Gallery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Gallery page in the docs: an even grid of real Papercut renders of every built-in component, grouped by kind, with a lightbox that steps through named layout variants and links to each component's API docs.

**Architecture:** One small PHP source per image (`site/gallery/sources/*.php`, each returns a `Papercut\Document`) and a hand-written manifest (`site/gallery/gallery.json`). A render script turns each source into a tightly cropped PNG (multi-page sources become pages side by side) in the site's public folder. A Vue component reads the manifest at build time and draws the grid and lightbox. A core PHPUnit test keeps sources, manifest and images in step.

**Tech Stack:** PHP 8.4 with `packages/core` (its vendor has `bacon/bacon-qr-code` and `phiki/phiki`), GD, `pdftoppm`; VuePress 2 with Vue 3 (no new npm dependencies).

**Spec:** `docs/superpowers/specs/2026-11-01-component-gallery-design.md`

## Global Constraints

- Every image is a real Papercut render with the default theme; nothing is touched up or hand-drawn.
- Sample content is generic (a made-up studio, a made-up API), never tied to a customer or product.
- Only named layout variants get lightbox slides: code block `strip` and `caption`, card `flat`, section `large`, field `line`, badge `outline`. Callout and badge tones go together in one image.
- Header and footer, Page numbers and Table of contents images show small whole pages side by side; Page numbers shows two pages ("Page 1 of 2", "Page 2 of 2"); Table of contents shows the contents page beside a page it points to.
- No code on tiles or in the lightbox; no live rendering; PDF renders only.
- Three tiles per row on wide screens, two on tablets, one on phones; the page reads well in light and dark mode.
- No new npm or Composer dependencies.
- Commits end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` and no session link.

## Review Focus

1. A source that throws, or needs an optional package that isn't installed: the render script stops with the source's file name and the error, and writes no partial image. Test in Task 1.
2. A manifest entry whose image or source is missing, or a source file nobody lists: the core test fails naming it. Test in Task 1.
3. A tile with no variants: the lightbox shows no arrows, no dots and no "1 of 1"; arrow keys do nothing. Checked in Task 6.
4. Keyboard only: Enter on a tile opens the lightbox, Escape closes it and focus returns to that tile, Tab stays inside the lightbox while open. Checked in Task 6.
5. A 375px-wide phone: one tile per row, the lightbox image fits the screen, no sideways scrolling. Checked in Task 5 and Task 6.

---

## File Structure

| Path | Responsibility |
|---|---|
| `site/gallery/gallery.json` | Manifest: groups → tiles → variants (hand-written) |
| `site/gallery/sources/<tile>.php`, `<tile>.<variant>.php` | One Document per image |
| `site/gallery/Gallery.php` | Reads the manifest; lists entries `{id, source, image}`; no rendering |
| `site/gallery/render.php` | CLI: render every entry to PNG (or one, with an argument) |
| `site/gallery/Raster.php` | GD helpers: trim whitespace, place pages side by side |
| `site/src/.vuepress/public/gallery/*.png` | Committed images |
| `site/src/.vuepress/components/ComponentGallery.vue` | Grid, tiles, badges |
| `site/src/.vuepress/components/GalleryLightbox.vue` | Lightbox |
| `site/src/gallery.md` | The page |
| `packages/core/tests/Feature/GalleryTest.php` | Manifest, sources and images in step |

Manifest shape (exact keys):

```json
{
  "groups": [
    {
      "title": "Decorators",
      "tiles": [
        {
          "id": "card",
          "title": "Card",
          "api": "/guide/decorators.html#card",
          "alt": "A bordered card with a title strip",
          "pages": 1,
          "variants": [{ "id": "flat", "label": "variant=\"flat\"", "alt": "A flat card: a tinted panel with no border" }]
        }
      ]
    }
  ]
}
```

`pages` is how many PDF pages the image shows (1 for most; 2 for page numbers and contents, 1 or 2 for header and footer). An entry's id is `<tile>` or `<tile>.<variant>`; its source is `site/gallery/sources/<id>.php` and its image `site/src/.vuepress/public/gallery/<id>.png`.

---

### Task 1: Manifest, render pipeline and the test that keeps them in step

**Files:**
- Create: `site/gallery/gallery.json` (one group "Layout", one tile `rows-and-columns`, no variants)
- Create: `site/gallery/sources/rows-and-columns.php`
- Create: `site/gallery/Gallery.php`, `site/gallery/Raster.php`, `site/gallery/render.php`
- Create: `packages/core/tests/Feature/GalleryTest.php`
- Create: `site/src/.vuepress/public/gallery/rows-and-columns.png` (generated)
- Modify: `packages/core/phpstan.neon` (add `../../site/gallery/Gallery.php` to `scanFiles`, so the test's use of it type-checks)

**Interfaces:**
- Produces: `final class Papercut\Site\Gallery` (plain `require`d file, no autoload changes) with
  - `public static function load(string $manifestPath): self`
  - `/** @return list<array{id: string, source: string, image: string, pages: int}> */ public function entries(): array` (absolute paths; tiles then their variants, in manifest order)
- Produces: `final class Papercut\Site\Raster` with
  - `public static function trim(\GdImage $img, int $pad): \GdImage` (crop to the bounding box of non-white pixels, then pad with white)
  - `public static function sideBySide(array $pages, int $gap, int $border): \GdImage` (pages as `list<\GdImage>`, top-aligned, each with a 1px `#e4e4e7` border, on white)
- Produces: CLI `php site/gallery/render.php [id]`. Renders at 144 dpi (`pdftoppm -r 144`). A one-page entry: rasterise page 1, `trim(…, 24)`. A multi-page entry: rasterise pages 1..`pages` untrimmed, `sideBySide(…, 32, 1)`, then `trim(…, 24)`. Writes the PNG to a temporary name and renames it into place. On any error: print `gallery: <source path>: <message>` to stderr, exit 1.

- [ ] **Step 1: Write the failing test** `GalleryTest` (requires `site/gallery/Gallery.php`):
  - `test_every_entry_has_a_source_that_renders`: for each entry, `require` the source, `assertInstanceOf(Document::class, …)`, `assertStringStartsWith('%PDF', $doc->toPdf()->bytes())`.
  - `test_every_source_is_listed`: the set of `glob('site/gallery/sources/*.php')` equals the set of entry sources.
  - `test_every_image_exists`: `assertFileExists` for each entry image.
  - `test_ids_are_unique_and_api_links_point_into_the_site`: ids unique; each `api` matches `#^/(guide|laravel|reference)/[\w-]+\.html(\#[\w-]+)?$#`, and the `.md` file it names exists under `site/src`.
  - `test_render_script_reports_a_failing_source`: write a temporary source that throws `RuntimeException('boom')` and a temporary manifest naming it (pass the manifest path to `render.php` with `--manifest=<path>`); run the script; assert exit code 1, stderr contains the source path and `boom`, and no PNG was written.
- [ ] **Step 2: Run** `cd packages/core && vendor/bin/phpunit tests/Feature/GalleryTest.php`. Expected: FAIL, `Gallery.php` not found.
- [ ] **Step 3: Implement** `Gallery`, `Raster`, `render.php` (with the `--manifest=` option, default `site/gallery/gallery.json`) and the `rows-and-columns` source (a row of three tinted boxes with a column beside them, gap and justify on show; `Page::size(in(6), in(3))`, so the trim has little to remove).
- [ ] **Step 4: Run** `php site/gallery/render.php` then the test. Expected: PASS. Open the PNG and check it is crisp and tightly framed.
- [ ] **Step 5: Commit** `feat(site): gallery manifest, render script and consistency test`.

### Task 2: Text, Tables and Media tiles

**Files:**
- Modify: `site/gallery/gallery.json` (groups Text, Tables, Media in that order after Layout)
- Create sources: `paragraphs-and-headings`, `inline-formatting`, `lists`, `code-blocks`, `code-blocks.strip`, `code-blocks.caption`, `table`, `spanning-rows`, `image`, `svg`, `qr-code`, `styled-qr-code`
- Create: their PNGs

**Interfaces:**
- Consumes: Task 1's manifest shape, `render.php`.
- API links: `/guide/text.html#paragraphs-and-headings`, `#inline-formatting`, `#lists`, `#code-blocks`; `/guide/tables.html` (Table), `/guide/tables.html#spanning-rows`; `/guide/images-and-links.html#images`, `#svg`, `#qr-codes`, `#styled-qr-codes`.

- [ ] **Step 1: Add the manifest entries.** Run the test. Expected: FAIL, sources and images missing.
- [ ] **Step 2: Write the sources.** The image source may use `examples/assets/signature.svg` or a small PNG from `packages/core/tests/fixtures`; the code blocks use the same PHP snippet in all three looks.
- [ ] **Step 3: Run** `php site/gallery/render.php` then the test. Expected: PASS. Look at every new PNG.
- [ ] **Step 4: Commit** `feat(site): gallery tiles for text, tables and media`.

### Task 3: Pages tiles (pages side by side)

**Files:**
- Modify: `site/gallery/gallery.json` (group Pages: `header-and-footer` pages 2, `page-numbers` pages 2, `table-of-contents` pages 2, `pdf-template` pages 1)
- Create the four sources and PNGs.

**Interfaces:**
- Consumes: `Raster::sideBySide`, the `pages` key.
- API links: `/guide/pages.html#headers-footers-and-backgrounds`, `#headers-footers-and-backgrounds` (page numbers live there too; use it), `#tables-of-contents`, `#filling-in-an-existing-pdf`.

- [ ] **Step 1: Add a test** `test_a_multi_page_entry_has_that_many_pages`: for each entry with `pages > 1`, the source's PDF has at least `pages` pages (count `/Type /Page` objects, or use `PdfInspector` from `tests/Support`).
- [ ] **Step 2: Add the manifest entries; run.** Expected: FAIL.
- [ ] **Step 3: Write the sources** on small pages (A6 or `Page::size(in(4.25), in(5.5))`) so two sit side by side legibly. The template tile uses `examples/assets/certificate-template.pdf`.
- [ ] **Step 4: Render and run the test.** Expected: PASS. Check the pages read at tile size.
- [ ] **Step 5: Commit** `feat(site): gallery tiles for page features`.

### Task 4: Decorator tiles and variants

**Files:**
- Modify: `site/gallery/gallery.json` (group Decorators, in the spec's order)
- Create sources: `card`, `card.flat`, `callout`, `blockquote`, `section`, `section.large`, `field`, `field.line`, `choice-list`, `key-value-list`, `badge`, `badge.outline`, `stat`, `totals`, `signature`; and PNGs

**Interfaces:**
- API links: `/guide/decorators.html#<card|callout|blockquote|section|field|choice-list|key-value-list|badge|stat|totals|signature>`.
- A variant's source is its tile's source with `->variant('<id>')` added; the content stays the same, so the slides compare like with like.

- [ ] **Step 1: Add the manifest entries; run the test.** Expected: FAIL.
- [ ] **Step 2: Write the sources.** Callout: all four tones stacked. Badge: all five tones in a row (and `badge.outline` the same row outlined). Choice list: a checkbox list beside a radio list. Signature: a typed name on one line beside a blank one.
- [ ] **Step 3: Render and run the test.** Expected: PASS. Look at every PNG, light and dark tile background.
- [ ] **Step 4: Commit** `feat(site): gallery tiles for decorators and their variants`.

### Task 5: The page and the grid

**Files:**
- Create: `site/src/gallery.md` (front matter `sidebar: false`; a one-paragraph intro; then `<ComponentGallery />`, rendered at build time so the grid is in the static HTML)
- Create: `site/src/.vuepress/components/ComponentGallery.vue`
- Modify: `site/src/.vuepress/client.ts` (register `ComponentGallery`), `site/src/.vuepress/config.ts` (navbar: `{ text: 'Gallery', link: '/gallery.html' }` right after Examples)

**Interfaces:**
- Consumes: `site/gallery/gallery.json` via `import manifest from '../../../gallery/gallery.json'`; image URLs via `withBase('/gallery/<id>.png')` from `vuepress/client`.
- Produces: emits `open(tileId: string)` handled in the same component by showing `GalleryLightbox` (Task 6); until then, the tile's image is wrapped in `<a href="<full image url>">`, which stays as the no-JavaScript fallback.

- [ ] **Step 1: Build the grid**: a heading per group; tiles in a CSS grid (`repeat(3, 1fr)`, 2 under 960px, 1 under 600px); each tile has the image on a light desk background (`#f4f4f5`; in dark mode `#27272a`, image kept on white), the title, a variant badge (`"<n> variants"`, or `"1 variant"`) when `variants.length > 0`, and an `API →` link.
- [ ] **Step 2: Run** `cd site && npm run build`. Expected: success.
- [ ] **Step 3: Check in a browser** with `npx vuepress dev src`: light and dark mode, widths 1280, 800 and 375 (one tile per row, no sideways scroll), every API link opens the right heading.
- [ ] **Step 4: Commit** `feat(site): gallery page and grid`.

### Task 6: Lightbox

**Files:**
- Create: `site/src/.vuepress/components/GalleryLightbox.vue`
- Modify: `ComponentGallery.vue` (open on tile click or Enter, intercepting the fallback link)

**Interfaces:**
- Props: `tile` (a manifest tile), `startIndex: number`; emits `close`. Slides are `[default, ...variants]`.

- [ ] **Step 1: Build it**: a dimmed overlay with the large image fitted to the viewport (`max-width: 92vw; max-height: 80vh`); caption with the title, the variant label (none for the default slide), `"<i> of <n>"`, and the API link. Previous and next buttons, dots, arrow keys and horizontal swipe (pointer events, 40px threshold), shown and active only when there are variants. Escape, the close button and a click on the overlay close it; focus moves to the close button on open, Tab cycles inside, focus returns to the tile on close; `aria-modal="true"` with a label.
- [ ] **Step 2: Run** `npm run build`. Expected: success.
- [ ] **Step 3: Check in a browser**: card (2 slides), code blocks (3 slides), totals (no arrows, dots or counter; arrow keys do nothing); keyboard only through open, step, close; swipe on a touch-emulated 375px viewport; dark mode.
- [ ] **Step 4: Commit** `feat(site): gallery lightbox with variant slides`.

### Task 7: Wrap up

**Files:**
- Modify: `CHANGELOG.md` (Unreleased, Added: "A Gallery page in the docs: a render of every built-in component, with its layout variants and a link to its docs. `php site/gallery/render.php` rebuilds the images.")
- Modify: `CONTRIBUTING.md` (under Examples: how to rebuild gallery images after changing a component)

- [ ] **Step 1: Run** every suite (`vendor/bin/phpunit` in core, laravel, docx), `vendor/bin/phpstan analyse --memory-limit=1G` in core, and `npm run build`. Expected: all pass.
- [ ] **Step 2: Commit** `docs: gallery in the changelog and contributing guide`.
