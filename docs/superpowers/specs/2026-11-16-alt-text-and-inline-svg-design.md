# Alt text for images and SVGs, and inline SVG in HTML export

Ships in 0.14.1 with the table-cell height and math tokenizer fixes on branch `fix-0.14.1`.

## What shipped differs

- The getter is `altText(): string`, not `alt(): ?string`; `''` means decorative.
- A decorative Word picture gets an empty description (php-docx's default would be "image"), so images without alt
  text change from "image" to empty in Word.
- `inlineSvgs` is for trusted SVG, so there is no allowlist. On the inline path, comments, `<metadata>`, elements
  outside the SVG namespace (with their subtree; `sodipodi:namedview`, `inkscape:*`, `rdf:*`) and attributes in a
  namespace other than none, xlink and xml (`inkscape:label`) are removed. An SVG with a `<foreignObject>` (any
  letter case) stays an `<img>` with the note `SVG kept as an image (<foreignObject> can't be written inline)`.
  This replaces the narrower wording about `foreignObject` and about dropping unscoped rules.
- Comments are dropped on the inline path.
- The root default font family is written as a presentation attribute, so the SVG's own CSS can still override it.

## Why

Two gaps found while showing a document's HTML export in a browser next to its PDF and Word files:

1. **No alt text.** A raster image is always written as `<img alt="">` and an SVG gets its alt only from a root
   `<title>` element. `<x-doc::image>` and `<x-doc::svg>` have no `alt`, and `Image`/`Svg` have no `->alt()`. A
   content figure (a number line, a chart, a diagram from pasted HTML) comes out as decorative. Pasted HTML that
   already has `<img alt="…">` loses that alt unless the image is missing (then the alt is shown as stand-in text).
   Word pictures get a description only from an SVG `<title>`.
2. **SVG text in HTML can't use the page's fonts.** An SVG is written as `<img src="data:image/svg+xml;base64,…">`.
   An SVG shown through `<img>` cannot see the page's `@font-face` rules, so its `<text>` falls back to the browser's
   default font (serif in Chrome). Labels drawn in Inter in the PDF show in Times in the HTML. Today this is reported
   as approximated (`SVG text in the browser's own fonts`) but there is no way to fix it.

## Goals

- Alt text can be set on images and SVGs from PHP, Blade and pasted HTML, and is used in HTML and Word output.
- An HTML export option that writes SVGs as inline `<svg>` markup, so SVG text uses the page's fonts.
- Nothing changes for anyone who doesn't use the new settings, except that pasted `<img alt>` now carries through.

## Non-goals

- PDF alt text. Tagged PDF is built on the unreleased `tagged-pdf` branch (waiting on php-pdf #53). The alt is
  stored on the node so that branch can use it later as the figure tag's `/Alt`; nothing is written to the PDF now.
- A security sanitizer for inline SVG (see "Trust" below).
- Warnings for images without alt text. Documents full of decorative pictures would get noisy.
- SVG from pasted HTML. The HTML converter does not produce `Svg` nodes today (`<img src="….svg">` is treated as
  missing, inline `<svg>` is not read), and this work does not add that. Pasted `<img alt>` therefore reaches `Image`
  nodes only. (Builder's call, made while writing this: no one asked for pasted SVG.)
- Embedding fonts inside an `<img>` SVG, or converting SVG text to outlines. Both were considered; inlining is
  simpler and keeps the text real.

## Part 1: alt text

### Setting it

- `Image::alt(string $text): static` and `Svg::alt(string $text): static`, with an `altText(): string` getter.
  An empty string means not set or decorative.
- Blade: an `alt` attribute on `<x-doc::image>` and `<x-doc::svg>`.
- Pasted HTML (`Markup::html()` / the HTML converter): `<img alt="…">` sets the alt of the `Image` the `<img>` becomes.
  The existing behaviour for a missing image (its alt shown as italic stand-in text, reported as approximated) is
  unchanged.

### Which alt wins

1. The alt set on the node (PHP `->alt()`, Blade `alt`, or pasted `<img alt>`, which all land in the same place).
2. For an SVG, its root `<title>` (current behaviour).
3. Nothing: the image is decorative.

`alt('')` and no alt both mean decorative. An explicit `alt('')` on an SVG overrides its `<title>` (the author said
it's decorative). Whitespace is collapsed and trimmed as the SVG `<title>` is today.

### Where it's used

- **HTML export**: the `alt` attribute of the `<img>` (raster images and non-inline SVG); `alt=""` when decorative,
  as now. For an inline SVG (Part 2): `role="img"` and `aria-label="…"` when there is an alt, `aria-hidden="true"`
  when decorative.
- **Word export**: the picture's description (`DocxImage` alt text, written as `wp:docPr descr`). This covers raster
  images (today they pass no alt at all) and SVGs (today only the `<title>`). Floating pictures already pass
  `altText` through and keep doing so.
- **PDF**: not written (see Non-goals).

## Part 2: inline SVG in HTML export

### The option

A new `HtmlOptions` setting, `inlineSvgs`, `false` by default, set the way the other HTML options are (constructor
argument plus a `withInlineSvgs(bool)` wither, matching `withFullPage`). In Laravel it is reachable wherever the
other HTML options are configured.

When `true`, every `Svg` node is written as its sanitized `<svg>` markup in the page instead of an `<img>`, with the
same box styles the `<img>` gets today. Its `<text>` then resolves against the page's `@font-face` rules.

When `false`, output is as today. The existing approximated note for an SVG with text is kept, and its wording
points at the option, e.g. `SVG text in the browser's own fonts (set inlineSvgs to use the page's fonts)`.

QR codes are unchanged: the plain one is already inline; the designed one stays an `<img>`.

### Fonts: match the PDF

The point of the option is that SVG labels look as they do in the PDF. In the PDF, `SvgFonts::resolve()` maps each
`<text>` to a registered family; a family the document did not register, or no family, falls back to the document's
family. Inline SVG must give the same result in the browser:

- Each `<text>` (and anything it inherits from) in an inline SVG gets the family the PDF would use, written so the
  browser resolves it the same way: the resolved registered family name, with the page's usual fallbacks. An
  unregistered name such as `Arial` is replaced by the family the PDF used. (Builder's call: implement by rewriting
  or adding `font-family` on the SVG's text elements, or a scoped rule; whichever is simpler and testable.)
- The families used by inline SVG text are added to the export's font set, so the `@font-face` rules include them
  in the font modes that write `@font-face` rules.
- With the font mode that writes no `@font-face` rules, SVG text gets the same family stack as the page's text, which
  is what "the page's fonts" means in that mode. No note.
- The existing "SVG text in the browser's own fonts" note fires only when the SVG is an `<img>` (option off).

### Trust

Inline SVG runs in the page. The docs say plainly: turn `inlineSvgs` on only when you trust where every SVG in the
document comes from. Papercut does no extra security cleaning beyond what every SVG
already gets (scripts, `on…` attributes, processing instructions and `xml:base` removed). It does not remove links,
`foreignObject`, animation elements or external image references.

### Correctness on a shared page (done regardless of trust)

Two SVGs on one page share one DOM, which breaks drawings even when both are trusted:

- **IDs.** Every `id` in an inline SVG is prefixed with a per-SVG prefix unique within the export (e.g. `pc-3a9f2c-3-`: a short hash of the SVG markup and the export counter),
  and every reference to it is rewritten: `href`/`xlink:href="#id"` and `url(#id)` in attributes and in `style`
  attributes and `<style>` text.
- **`<style>` elements.** A `<style>` inside an inline SVG would style the whole page. Each selector in it is scoped
  to that SVG (the root `<svg>` gets a class or id, and selectors are prefixed with it). `@`-rules other than
  `@media` and `@font-face` may be dropped and reported as approximated if scoping them is not reasonable.

### Fidelity notes

- No new note when inlining succeeds.
- If scoping drops a rule, report it as approximated, once per SVG.

## Docs

- `site/src/guide/html-export.md`: the `inlineSvgs` option, the trust warning, and what changes (fonts).
- `site/src/guide/images-and-links.md`: `alt` on images and SVGs, pasted `<img alt>`, the SVG `<title>` fallback, and
  where alt text ends up in each format.
- `site/src/guide/word-export.md`: pictures get their alt as the description.
- `CHANGELOG.md` [Unreleased].

## Tests

- Unit tests for each alt source and the precedence, in HTML and Word output.
- HTML converter: `<img alt>` carried through; missing-image stand-in text unchanged.
- Blade: `alt` attribute on both components.
- Inline SVG: markup is inline, box styles present, `role`/`aria-label`/`aria-hidden`, IDs prefixed and references
  rewritten (including `url(#…)` in `style` and `<style>`), two SVGs with the same ids on one page don't collide,
  `<style>` selectors scoped, default stays `<img>`.
- Inline SVG fonts: a registered family used only in SVG text gets an `@font-face` rule; an unregistered family is
  replaced by the PDF's family.
- Check in Chrome that inline SVG text renders in the same font as the PDF's SVG text (rendered font, not just
  markup).

## Left to the builder

- The prefix format and how the per-export counter is kept.
- How `<style>` text is parsed for scoping (a small, robust approach is fine; report what can't be scoped).
- Exact wording of notes and docs, following the house style (plain, concrete English).
