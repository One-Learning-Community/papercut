# Component gallery for the docs

Date: 2026-11-01. Status: approved 2026-11-01.

## Why

People want to find Papercut's built-in components by sight, not by reading reference tables. The page is also a showcase: a newcomer scrolling it should come away thinking "I can get a sharp PDF out of this with very little effort". Every image is a real Papercut render with the default theme. Nothing is touched up or hand-drawn.

## What it is

A new docs page, **Gallery**, with its own entry in the top navigation beside Examples.

- **Layout:** an even grid of same-size tiles, grouped by kind under a small heading per group. Three tiles per row on a wide screen, two on a tablet, one on a phone.
- **A tile:** the component's image on a light "desk" background, its name, and a link to that component's API details (the guide or reference section that documents it). When the component has named layout variants, a small badge in the corner says how many, such as "2 variants".
- **Lightbox:** clicking a tile opens a large view of the image. For a component with variants, the lightbox steps through the default look and then each variant, with arrow buttons, swipes on a touch screen and the arrow keys. A caption shows the component name, the variant (such as `variant="flat"`), a position such as "2 of 2", and the same API link. Escape, clicking outside or a close button closes it.
- **Scope:** everything a user can build with, not only decorators. The groups and tiles, in the order shown:

| Group | Tiles |
|---|---|
| Layout | Rows and columns |
| Text | Paragraphs and headings, Inline formatting, Lists, Code blocks (variants: `strip`, `caption`) |
| Tables | Table, Spanning rows |
| Media | Image, SVG, QR code, Styled QR code |
| Pages | Header and footer, Page numbers, Table of contents, Filling in a PDF template |
| Decorators | Card (`flat`), Callout, Blockquote, Section (`large`), Field (`line`), Choice list, Key-value list, Badge (`outline`), Stat, Totals, Signature |

**Tiles that span pages.** Header and footer, Page numbers and Table of contents only make sense across whole pages, so their image shows small whole pages side by side in one picture, in the way the Examples page shows a document's pages. Page numbers shows two pages ("Page 1 of 2", "Page 2 of 2"), and Table of contents shows the contents page beside a page it points to. Filling in a PDF template shows the one filled page. These are still one image per tile.

Only named layout variants get lightbox slides. Colour tones are not variants for this page: the Callout tile shows its four tones together in one image, and so does the Badge tile. Other options that change a component's look (radio versus checkbox, a section with a rule) can go in that component's one image, not as slides.

## Goals

- A newcomer sees in one scroll what Papercut can make, and every picture looks finished.
- Each image is reproducible from a small PHP source in the repository, so it never drifts from what the code actually draws.
- A reader can go from any picture to the docs that explain it in one click.

## Non-goals

- No live rendering in the browser and no code playground.
- No code snippet on the tile or in the lightbox. The API link covers that.
- No Word or HTML versions of the gallery images. PDF renders only.
- No colour-tone slides.

## Anti-goals

- Anything that looks unfinished or cramped. A weak image does more harm than no tile.
- Content tied to a particular customer or product. Papercut is a public package, so sample text is generic (invoices for a made-up studio, a made-up API, and so on), as in the existing examples.
- Images that only look right in light mode. The page must read well in the site's dark mode too.

## How it gets made

These parts are decided:

- **Sources:** one small PHP file per image, each returning a `Papercut\Document`, kept with the site in `site/gallery/`. A variant gets its own file, named `<component>.<variant>.php` (for example `card.flat.php`). Each source sizes its page to fit the component snugly, so the image has no large empty margins.
- **Render script:** `php site/gallery/render.php` renders every source to PDF, rasterises it with `pdftoppm` (already used for the snapshots and examples), places the pages of a multi-page source side by side with PHP's GD extension (which the snapshot tests already require), and writes PNGs to `site/src/.vuepress/public/gallery/`. The images are rendered at about twice the size they're shown at, so they stay sharp on high-density screens and in the lightbox. The PNGs are committed, like the example page images.
- **Manifest:** a committed `site/gallery/gallery.json` lists the groups, the tiles in order, each tile's title, API link, image and alt text, and each variant's label and image. The page reads it at build time. It is written by hand; the render script reads it to know what to render.
- **Page:** `site/src/gallery.md` mounts a Vue component (registered in `site/src/.vuepress/client.ts`, as the Tailwind translator is) that draws the grid and the lightbox from the manifest. No new npm dependencies: the lightbox is a small hand-written component.
- **Test:** a PHPUnit test in `packages/core/tests/Feature/` (like `ExamplesTest`) checks that every source in the manifest exists and renders a PDF, that every source file is listed, and that every image the manifest names exists. It doesn't compare the PNGs themselves.

## Calls made in drafting

These weren't discussed. They are reasonable defaults, open to change:

- The page is called Gallery and sits in the top navigation beside Examples.
- Groups and their order follow the guide: Layout, Text, Tables, Media, Pages, Decorators. The tile list above is a first cut.
- Callout and badge tones appear together in one image.
- Images come from PHP sources by a render script, committed like the example images, with a test that keeps sources and manifest in step.
- No code on the tile or in the lightbox, since the API link covers it.

## Left to the builder

- The exact sample content of each image, within the anti-goals above.
- How the page size is fitted to each component, and the PNG resolution, as long as the images are crisp and tightly framed.
- The tile and lightbox styling, matched to the site's existing look (the Examples page cards and the brand colours in `site/src/.vuepress/styles/index.scss`).
- Accessibility details: focus moves into the lightbox and back to the tile, a keyboard can reach everything, images have alt text, and each tile links to its full-size image so it still works without JavaScript.
- Which docs section each API link points to, picking the most specific heading that documents the component.
