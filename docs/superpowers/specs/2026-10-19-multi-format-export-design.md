# Papercut: Multi-Format Export (PDF, HTML, DOCX) — Design Spec

**Date:** 2026-10-19
**Status:** Approved; DOCX on hold (amendment H1; 0.2.0 waits for DOCX)
**Target release:** 0.2.0 (`papercut/core`, `papercut/laravel`, new `papercut/docx`)
**Builds on:** [2026-10-18-papercut-design.md](2026-10-18-papercut-design.md) (v0.1) and [RULINGS.md](../RULINGS.md)

## 1. Summary

The same Papercut template produces three formats: PDF (the reference layout, unchanged), HTML for viewing in a browser, and Word (.docx). Exporters read the node tree and its resolved styles, which don't depend on layout. Only the PDF exporter runs the page-aware layout engine, painter and backend. HTML hands layout to the browser. DOCX hands vertical flow and pagination to Word, but takes its horizontal geometry (column widths) from our own layout calculation, so it matches the PDF side to side.

With this release the Blade prefix becomes `<x-doc::*>`, the facade becomes `Papercut`, and DOCX ships as an optional package.

## 2. Goals and success criteria

**Use cases**
- **HTML: web view and preview.** A developer who already has an invoice template can show the same document in a browser without writing it twice. "Close enough" is the bar, not pixel-identical. This is the use case the README leads with.
- **DOCX: a faithful copy in Word format.** Many recipients insist on .docx, and the file must import cleanly into Google Docs and Office 365. Looking like the PDF matters more than being easy to edit. Being editable is a welcome side effect of the chosen approach (§5.2), not a goal. A more editable mode is welcome as a contribution.

**0.2.0 succeeds when:**
1. `Papercut::view('invoice', $data)` can return `->pdf()`, `->html()` or `->docx()` from the same Blade view, with no template changes.
2. PDF output for every v0.1 fixture is byte-identical to v0.1.
3. The three examples (invoice, transcript, report) render in a browser looking close to the PDF, and open in Word, LibreOffice, Google Docs (import) and Office 365 web with header rows, page numbers and column widths intact.
4. The same text, in the same order, comes out of all three formats (the content parity test, §9).
5. Every approximation and every dropped feature is reported with its node path. Strict mode throws on dropped features.

**Non-goals for 0.2.0:**
- Pixel-matching HTML against the PDF.
- Matching the PDF's page breaks in DOCX.
- Email-safe HTML: table layout, Outlook quirks.
- Browser print-to-PDF as a third route to PDF.
- A positioned or "frame" DOCX mode.
- Raw HTML input (§12).
- Bookmarks, which move from v0.2 to v0.3.

## 3. Prior art (checked 2026-10-19)

- **No PHP or JS library renders one component tree to PDF, DOCX and HTML.**
  - Laravel DOCX packages (`dskripchenko/laravel-php-docx`, `ananikomlanmh/word-for-laravel`, `santwer/exporter`) use rendered HTML or Word templates as the shared model, not a typed tree.
  - In JS, `@react-pdf/renderer` targets PDF only. The React-to-docx reconciler (`revolunet/react-docx`) was last pushed in 2021.
- **One model, many writers.**
  - PHPWord (latest release 1.4.0, 2025-06-05; `master` still active) has Word2007, ODT, RTF and HTML writers. Its PDF writer goes through HTML and DomPDF/mPDF/TCPDF.
  - Pandoc writes DOCX as flowing content, separate from its PDF path.
  - Typst's HTML export is still experimental as of 0.15.
- **Lay out first, then export (Jasper, Telerik, DevExpress page-by-page).**
  - JasperReports' DOCX exporter is documented as "grid-based".
  - Telerik exports nested tables. When a user complained they are hard to edit, Telerik replied that changes belong in the report design.
  - DevExpress makes users choose between a continuous "editable" mode and a frame-based "faithful" mode.
  - Lesson: positioned output is brittle and fights Word's repagination.
- **Flowing exporters (Pandoc, PHPWord, docx.js, Aspose) leave pagination to Word through native features:**
  - `w:tblHeader` (repeating header rows)
  - `w:cantSplit` (rows that don't split)
  - `keepNext` / `keepLines`
  - PAGE / NUMPAGES fields
  - section headers and footers

  Papercut's DOCX mapping relies on exactly these features.
- **Testing practice:** Pandoc compares unzipped DOCX XML against golden copies. OOXML schema validators can run from npx without .NET.

Papercut is the first to put a typed component tree in front of all three formats. The DOCX design (§5.2) takes the table approach from the layout-first exporters but builds it from the tree, not from positioned boxes.

## 4. Decisions

| # | Decision | Alternatives rejected |
|---|---|---|
| D1 | Exporters read the node tree plus resolved styles. Word and HTML are **not** new `Backend`s. | A DOCX backend after layout (text boxes nobody can edit) |
| D2 | HTML is for viewing in a browser; "close enough" | standalone archive file, email HTML, print-to-PDF |
| D3 | DOCX is a faithful copy, built as **B2**: tables sized by our layout, Word paginates | B1 positioned frames (imports badly into Google Docs, breaks when Word's text metrics differ); a per-document mode switch |
| D4 | DOCX writer: `dskripchenko/php-docx`, behind `Papercut\Docx\*` only | PHPWord (slow releases, no deterministic output, no font embedding); our own OOXML writer |
| D5 | Shared `Expander` + `StyleResolver` + `TreeWalker`; nodes unchanged | a separate `ResolvedNode` tree; a format-neutral flow model (Pandoc style) |
| D6 | Packages: core keeps PDF and adds HTML; DOCX is a separate `papercut/docx` | everything in core; four-way split |
| D7 | Facade `Papercut`; tag prefix `doc` (configurable) | `Doc` facade; per-format facades; `x-papercut`, `x-paper`, `x-pc` |
| D8 | Order: seams → HTML → DOCX, with the upstream PR running alongside | DOCX first |

## 5. Architecture

### 5.1 Pipeline

```
Node tree ─► Expander + StyleResolver (shared, no layout) ─► TreeWalker
   ├─ PdfExporter:  LayoutEngine ─► Painter ─► Backend (php-pdf)       [core]
   ├─ HtmlExporter: walk ─► HTML + CSS                                  [core]
   └─ DocxExporter: walk (+ WidthResolver) ─► php-docx ─► .docx bytes   [papercut/docx]
```

### 5.2 Core units (new or changed)

| Unit | Responsibility | Depends on |
|---|---|---|
| `Expander` | Turns `Component`s into `Node`s, with the existing depth guard. Moved out of `LayoutEngine::expand`. | Node |
| `StyleResolver` | `(Node, parent ComputedStyle) → ComputedStyle`: theme presets, named styles, the node's own style, inheritance. Moved out of `LayoutContext::resolveStyle`, keeping its per-node cache. `LayoutEngine` uses it unchanged. | Node, Style |
| `TreeWalker` | Depth-first walk that yields `(Node, ComputedStyle, path)` for page body, header, footer and background. Builds node paths (`page > row[2] > column[0]`) the same way layout does, and attaches them to `InvalidValue` / `UnknownAttribute`. `TreeVisitor::enter`/`leave` receive the node, its `ComputedStyle`, and a `Visit` carrying the path, sibling index, parent node, header flag and region. Exporters keep their own parent-style stack. | Expander, StyleResolver |
| `WidthResolver` | The layout engine's horizontal calculation exposed on its own: given a node and an available width, it returns the resolved widths of flex children (basis, grow, shrink, gap) and table columns (fixed, fr, auto via content min/max). No pagination. Uses `FontRegistry` for content-dependent widths. | Layout, Text |
| `Exporter` | `export(Document $doc, ExportOptions $opts): Output` | — |
| `Output` | `bytes(): string`, `mime(): string`, `extension(): string`, `save(string $path): void`, `stream(): int` (all state private; public accessors are methods) | — |
| `Fidelity` | Collects `FidelityReport(level, format, feature, path)`. Levels: `Approximated`, `Dropped`. Forwards to `onWarning`. In strict mode, throws `UnsupportedFeature` on the first `Dropped`. | — |
| `FontSet` | Format-neutral font configuration: family → file per weight and italic, plus fallback families. PDF loads it into `FontRegistry`; HTML emits `@font-face`; DOCX writes family names. | Value |
| `PdfExporter` | Wraps the existing pipeline (`Paginator` → `Painter` → `Backend`). | Layout, Paint, Backend |
| `HtmlExporter` | §6.1 | TreeWalker, Fidelity |

**Fidelity levels**
- **Supported:** emitted as specified.
- **Approximated (≈):** emitted with a documented substitute. Reported, never thrown.
- **Dropped (✗):** not emitted. Reported, and thrown in strict mode.
- **n/a:** the feature has no meaning in the format (for example page numbers on screen). Left out silently, never reported, never thrown.

**Options**
- Options are readonly classes with private state and accessor methods: `theme()`, `fonts()`, `onWarning()`, `strict()`; `PdfOptions` adds `backend()`, `overflow()`, `cacheDir()`; `HtmlOptions` adds `fullPage()`, `fontMode()`, `imageUrl()`, `fontUrl()`, `fontSources()`. Each subclass has `from(ExportOptions)`.
- `ExportOptions` (shared): `theme`, `fonts: FontSet`, `onWarning: ?Closure`, `strict: bool = false`.
- `PdfOptions extends ExportOptions`: `backend`, `cacheDir`, `overflow: OverflowMode`. This replaces `RenderOptions`.
- `HtmlOptions extends ExportOptions`:
  - `fullPage: bool = false`
  - `fontMode: FontMode = none` (`none`|`embed`|`link`|`sources`). `embed`, `link` and `sources` are opt-in. The Laravel config key `html.fonts` accepts `none`, `embed`, `link` or `sources`; `link` needs the `fontUrl` callback; in Laravel, `link` requires `html.font_url` (§8.2).
  - `fontUrl: ?Closure(FontFace): ?string`: used by `link`; a null return skips that face.
  - `fontSources: list<FontSource|Closure(FontRequest): ?FontAnswer>`: used by `sources`; an ordered stack, and each used family goes to the first source that accepts it. Details in `2026-10-19-html-font-sources-design.md`.
  - `imageUrl: ?Closure(Image): ?string` (null, or a null return, means embed as a data URI)
- `DocxOptions extends ExportOptions`: no extra settings in 0.2.0.

**Removing layout and PDF assumptions from the tree**
- `Image::handle(ImageHandle)` is removed. `Image` sources are `file` and `bytes` only. `ImageHandle` stays inside the backend, where it caches decoded images.
- `OverflowMode` moves from the shared options to `PdfOptions`.
- The other page and layout concepts (`PageNumber` placeholders, fixed `height`, `Spacer::flex`, `auto` columns, the background slot) stay in the tree unchanged. Each format handles them through the capability table (§7).

**Document API**
- `Document::render()`, `toString()`, `save()` and `stream()` are replaced by:
  - `export(Exporter $e, ?ExportOptions $o = null): Output`
  - `toPdf(?PdfOptions $o = null): Output`
  - `toHtml(?HtmlOptions $o = null): Output`
- DOCX is `$doc->export(new \Papercut\Docx\DocxExporter())`, because core does not depend on the DOCX package.

### 5.3 DOCX mapping (approach B2)

- **Document:** core properties (title, author, subject, keywords, created/modified from `createdAt` or a fixed date, so output is deterministic) and the default language.
- **Page → one Word section:**
  - `pgSz` and orientation from the page; `pgMar` from its margins.
  - The header/footer distance is approximated from `headerGap`/`footerGap`. Word measures from the page edge, while Papercut puts the header inside the top margin.
  - The header and footer slots become the section's header and footer parts.
- **Column and the page body:** content flows top to bottom as paragraphs and tables. `gap` becomes paragraph spacing (space after) between children. If the column has padding, a border or a background, its content is wrapped in a single-cell table carrying those properties.
- **Row:**
  - Becomes a one-row, borderless, **fixed-layout** table.
  - `gridCol` widths come from `WidthResolver` at the width actually available (the page content width minus enclosing padding).
  - `gap` becomes empty spacer columns.
  - `align-items` becomes cell vertical alignment (`top`/`center`/`bottom`). `stretch` is the default behavior of table rows.
  - `wrap` uses `WidthResolver`'s line assignment: one table row per flex line.
- **Spacer:** in a row, an empty cell with its resolved width. In a column, an empty paragraph with an exact line height of the spacer's size. A flexible spacer in a column has no size of its own, so it is approximated as zero.
- **Text:** a paragraph.
  - Supported directly: alignment (including justify), line spacing (exact or multiple), letter spacing, font size, weight (≥ 600 is bold, otherwise not; ≈ for in-between weights), italic, color, underline and strike.
  - Spans become runs; `<br>` becomes a line break; `href` becomes a hyperlink.
  - Presets h1–h4 and p become Word paragraph styles (`Heading1`–`Heading4`, `Normal`) with Papercut's resolved values, so a document outline appears in Word and Google Docs.
- **Table:**
  - `gridCol` widths come from `WidthResolver` (the same column calculation as PDF).
  - Header rows get `w:tblHeader`; every row gets `w:cantSplit`.
  - `colspan` becomes `gridSpan`. Cell padding, borders and background map directly.
- **Pagination styles:**
  - `keep-with-next` → `keepNext` on the node's last paragraph.
  - `keep-together` → `keepLines` and `keepNext` on every paragraph but the last; for tables, `cantSplit` on every row.
  - `break-before` → `pageBreakBefore`; `break-after` → a page break after the node.
  - `page-break` → `w:br w:type="page"`.
  - `min-lines` ≥ 2 → widow control on (≈).
- **PageNumber:** the format string is split on `{page}` and `{total}` into text runs and PAGE / NUMPAGES fields.
- **Image:**
  - The size comes from our image layouter's `contain`/`fill` calculation at the available width.
  - `cover` is approximated as `contain`.
  - Block `link` applies its hyperlink to the text runs inside it (≈). Images inside a link are not linked.
- **Colors:** an alpha below 1 is blended onto white (≈).

**Upstream dependency (amendment H1, 2026-10-19):** php-docx 1.15 lacks several features this mapping relies on. They are being contributed upstream, and `papercut/docx` starts once a release has them. The gaps, confirmed against 1.15 on 2026-10-19, are listed in [php-docx-1.15-gaps.md](../php-docx-1.15-gaps.md):
- per-page sections and custom page sizes
- multiple line spacing
- `Normal`/`pStyle` styles and `w:lang`
- `keepNext`, `keepLines`, `cantSplit`, widow control and `pageBreakBefore`
- exact row heights
- fixed ZIP timestamps

### 5.4 Error handling

- New exceptions (all extend `PapercutException`):
  - `UnsupportedFeature`: strict mode. The message names the format, feature and node path, for example `Strict mode: docx cannot render radius at page > row[2] > column[0].`
  - `MissingExporter`: the format's package is not installed, for example `DOCX export needs papercut/docx: run "composer require papercut/docx".`
- Validation errors (`InvalidValue`, `UnknownAttribute`, Laravel `InvalidStructure`) are raised the same way in every format, because they come from the shared `StyleResolver` and `TreeWalker`.

## 6. Exporters

### 6.1 HTML

- **Structure:**
  - A `<div class="papercut">` root contains one `<section class="pc-page">` per `page`.
  - Each page box has the page width as `max-width`, the margins as padding, and a "paper" look on screen (shadow, gap between pages).
  - Header, body and footer render in order inside each page box. The background slot is an absolutely positioned layer behind the content.
- **Elements:**
  - `view` / `row` / `column` → `<div>` with flexbox.
  - `text` → `<p>`; presets h1–h4 → `<h1>`–`<h4>`.
  - Spans → `<span>`, `<b>`, `<i>`, `<u>`, `<s>`, `<a>`, `<br>`.
  - `table`, `thead`, `tr`, `th`, `td` → the same HTML elements with `colgroup` widths: lengths as-is, `fr` as proportional `%` of the remaining width, `auto` as auto.
  - `image` → `<img>` with `object-fit`.
  - Block `link` → `<a>` around the block.
  - `spacer` → `<div>` with a fixed size or `flex-grow`.
- **Styles:**
  - Computed styles are written **inline** on each element, so the output doesn't depend on the host page's cascade.
  - A small fixed stylesheet scoped to `.papercut` provides the page box and a minimal reset (margins, box-sizing, list and heading defaults), so host CSS does not leak in.
  - Units are emitted in `pt`.
- **Print CSS:** `@page` size and margins from the first page; `break-before: page` for page breaks and `break-before`/`break-after`; `break-inside: avoid` for `keep-together` and table rows. The page box's screen styling is removed in print.
- **Page-only features:** `page-number`, keep rules and `min-lines` are n/a on screen. Page numbers are left out of the output, not rendered as "1".
- **Fonts (`fontMode`):** `none` (default) emits family names only, falling back to `system-ui, sans-serif`. `embed` is opt-in and inlines each used `FontSet` file as a base64 `@font-face`. `link` is opt-in and uses the URL the `fontUrl` callback returns for each face (a null return skips that face). `sources` is opt-in and takes an ordered stack of font sources (`fontSources`): each used family goes to the first source that accepts it, and a source answers with a stylesheet URL (such as Google Fonts) or with `@font-face` faces (local files). A family no source accepts is reported as `font not linked`. See `2026-10-19-html-font-sources-design.md`.
- **Images:** data URIs by default; an `imageUrl` callback can return a URL instead. Laravel embeds by default too.
- **Security:**
  - All text and attribute values are escaped.
  - `href`s go through the same scheme allowlist as PDF (http, https, mailto, tel, `#fragment`).
  - Style values come only from typed `ComputedStyle` values, never from raw strings, so nothing can inject CSS.
- **Fragment and full page:** with `fullPage: false` the output is the root `<div>` plus a `<style>` block. With `fullPage: true` it is a complete HTML5 document with doctype, `<html lang>`, `<meta charset>`, viewport and `<title>` from the document metadata.

### 6.2 DOCX

See §5.3 for the mapping. The package is `papercut/docx` (§8). `DocxExporter` builds a `Dskripchenko\PhpDocx` document through its builder API, sets fixed core property dates and ZIP entry timestamps so output is deterministic, and returns `Output` with MIME `application/vnd.openxmlformats-officedocument.wordprocessingml.document`.

## 7. Capability table

Legend: ✓ supported · ≈ approximated (reported) · ✗ dropped (reported; strict throws) · n/a (silent). PDF supports every row (it is the reference).

| Feature | HTML (web view) | DOCX |
|---|---|---|
| Page size, orientation, margins | ≈ page box of that width, margins as padding | ✓ one section per `page` |
| Header and footer slots | ≈ rendered once at the top and bottom of each page box | ✓ section header and footer |
| Header and footer gaps | ✓ spacing in the page box | ≈ Word's header and footer distance |
| Background slot | ≈ absolute layer behind the page box | ✗ in 0.2.0 |
| `page-number` `{page}` / `{total}` | n/a (left out) | ✓ PAGE / NUMPAGES fields |
| `page-break`, `break-before` / `-after` | n/a on screen; print CSS | ✓ |
| `keep-together`, `keep-with-next` | n/a on screen; print CSS | ✓ `keepLines` / `keepNext` / `cantSplit` |
| `min-lines` | n/a | ≈ widow control on or off |
| Row / column / view: direction, gap, justify, align, grow, shrink, basis | ✓ flexbox | ≈ fixed-layout tables, widths from `WidthResolver` |
| `wrap` | ✓ | ≈ one table row per flex line |
| Flexible `spacer` in a column | ✓ | ≈ zero height |
| Fixed `height` | ✓ | ≈ exact row height (Word may cut off text) |
| Width, min/max width | ✓ | ✓ via `WidthResolver` |
| Padding, border width/color, background on a box | ✓ | ≈ single-cell table wrapper |
| Border style solid / dashed | ✓ | ✓ |
| `radius` | ✓ | ✗ |
| `opacity` | ✓ | ✗ |
| Color with alpha | ✓ | ≈ blended onto white |
| Font family, size, italic, color, underline, strike | ✓ | ✓ |
| Font weight | ✓ | ≈ bold for ≥ 600, otherwise regular |
| Line height, letter spacing, text align incl. justify | ✓ | ✓ |
| Fonts | ✓ `@font-face` (embed/link/none) or font sources (sources) | ≈ family name only (the reader's machine supplies the font) |
| Inline markup `<b> <i> <u> <s> <a> <br> <span>` | ✓ | ✓ |
| Headings h1–h4, p | ✓ `<h1>`–`<h4>`, `<p>` | ✓ paragraph styles |
| Block `link` | ✓ | ≈ hyperlink on the text runs inside it |
| Image `contain`, `fill` | ✓ | ✓ |
| Image `cover` | ✓ | ≈ as `contain` |
| Table column widths (lengths, fr, auto) | ≈ fr as %; auto by the browser | ✓ via `WidthResolver` |
| `colspan` | ✓ | ✓ `gridSpan` |
| Repeating header rows | n/a on screen (`thead` repeats in print) | ✓ `tblHeader` |
| Rows don't split | n/a (`break-inside: avoid` in print) | ✓ `cantSplit` |
| Metadata, `lang` | ✓ | ✓ |
| `OverflowMode` | n/a | n/a |

## 8. Packaging, naming and API

### 8.1 Packages

| Package | Namespace | Requires |
|---|---|---|
| `papercut/core` | `Papercut\` | `php ^8.4`, `ext-dom`, `ext-mbstring`, `ext-zlib`, `dskripchenko/php-pdf ^1.24` (unchanged) |
| `papercut/docx` (new) | `Papercut\Docx\` | `papercut/core ^0.2`, `dskripchenko/php-docx ^1.15`, `ext-zip` |
| `papercut/laravel` | `Papercut\Laravel\` | `papercut/core ^0.2`; `suggest: papercut/docx` |

- **Dependency rules (enforced by PHPStan):**
  - Only `Papercut\Backend\PhpPdf\*` may import `Dskripchenko\PhpPdf\*` (unchanged).
  - Only `Papercut\Docx\*` may import `Dskripchenko\PhpDocx\*`.
  - Core never imports `Papercut\Docx\*`.
- **Repository:** the monorepo adds `packages/docx` and a `docx.yml` workflow. The split and release scripts gain the new package. All three packages are released together as 0.2.0.

### 8.2 Laravel

```php
Papercut::view('invoices.show', $data)->pdf()->download('invoice.pdf');
Papercut::view('invoices.show', $data)->docx()->download('invoice.docx');
return Papercut::view('invoices.show', $data)->html();   // full-page response
{!! Papercut::view('invoices.show', $data)->html() !!}   // fragment in your own layout
```

- `PdfManager` → `PapercutManager`; facade `Pdf` → `Papercut`.
- `view()` and `make(Document)` return a `PendingDocument`. It resolves the Blade markers once and holds the node tree.
- `->pdf()` and `->docx()` return a `PendingOutput`: `toString()`, `save()`, `download()`, `inline()`, and `Responsable` (inline by default). `->html()` returns a `PendingHtml` (next bullet).
- **`PendingHtml` (amendment H2):** `->html()` returns a `PendingHtml` that is `Htmlable`, `Stringable` and `Responsable`. It does not extend `PendingOutput`.
  - `toHtml()` and `(string)` give the fragment, so `{{ $doc->html() }}` prints it unescaped.
  - `toResponse()` keeps the full-page response: 200, `text/html; charset=UTF-8`, no `Content-Disposition`.
  - `page()` and `fragment()` each return a `PendingOutput` (`toString()`, `save()`, `download()`, `inline()`), with the default name `document.html`.
  - `PendingHtml` has no `toString()`, `save()` or `download()` of its own, so a string never means two things.
- `->docx()` without `papercut/docx` installed throws `MissingExporter`.
- **Tags:** `<x-doc::*>`. The prefix is the config key `papercut.prefix` (default `doc`); the service provider registers the component namespace from it. App components in `components/doc/` (`<x-doc.foo>`) do not conflict.
- **Config additions:** `prefix`, `strict` (default `false`; the docs suggest `true` in tests), `html.fonts` (`none|embed|link|sources`), `html.image_url` and `html.font_url` (amendment H3), and `html.font_sources`, `html.google_fonts` and `html.local_fonts` (see `2026-10-19-html-font-sources-design.md`). Laravel's `onWarning` logs `Dropped` as a warning and `Approximated` as debug.
- **HTML callbacks (amendment H3):** `html.image_url` and `html.font_url` name invokable classes, resolved from the container, that implement `Papercut\Laravel\Html\ImageUrlResolver` and `Papercut\Laravel\Html\FontUrlResolver`. Class names survive `config:cache`; a closure does not, and is rejected with an error naming the key. `html.fonts = link` requires `html.font_url`. Invalid `html.*` config is reported when HTML options are first built (the first `->html()` export), not at boot. There is no code-level options hook in 0.2.0.
- **Preview route:** `/_papercut/{view}?format=pdf|html|docx` (default `pdf`).

### 8.3 Migration from v0.1

v0.1 was never published to Packagist, so there are no deprecation shims. The changelog carries this table:

| v0.1 | 0.2 |
|---|---|
| `Pdf::view(...)` | `Papercut::view(...)->pdf()` |
| `<x-pdf::…>` | `<x-doc::…>` |
| `PendingPdf` | `PendingOutput` |
| `PdfManager` | `PapercutManager` |
| `Document::render()` / `toString()` | `toPdf()->bytes()` |
| `Document::save($p)` / `stream()` | `toPdf()->save($p)` / `toPdf()->stream()` |
| `RenderOptions` | `PdfOptions` |
| `Image::handle()` | removed; use `Image::file()` / `Image::bytes()` |

## 9. Testing

**Shared across formats**
- **Fixture corpus:** the three examples plus one fixture per capability-table row, run through every exporter.
- **Content parity:**
  - Text is extracted from PDF (`pdftotext`), HTML (DOM `textContent`) and DOCX (`w:t` in body order).
  - After normalizing whitespace, the three must be equal.
  - Items marked n/a in a format (for example page numbers in HTML) are left out of that format's comparison.
- **Fidelity:**
  - Every ≈ and ✗ row has a test asserting level, format, feature and node path.
  - Every ✗ row throws `UnsupportedFeature` in strict mode.
  - n/a rows produce no report.

**HTML**
- **Golden files:** output is parsed with `Dom\HTMLDocument` (PHP 8.4), written back out in canonical form, and compared with approved files.
- **XPath assertions** per capability row.
- **Validity:** no HTML5 parse errors. Full-page output has doctype, `lang`, charset and `<title>`.
- **Security:** escaping `<script>` in text and attributes, rejecting `javascript:` hrefs, and building data URIs only from file or byte sources.
- **Visual (local, never blocks CI):** `examples/html/render.php` writes the example documents as HTML for a person to open in a browser and review. No pixel comparison against the PDF. CI screenshots of the examples arrive with Phase 2's `docx.yml`.

**DOCX**
- **Golden XML:** unzip the output, canonicalize each part (C14N), and compare `document.xml`, `styles.xml`, header and footer parts, `_rels`, and `[Content_Types].xml` with approved copies.
- **XPath assertions:**
  - `w:tblHeader` on header rows; `w:cantSplit` on rows; PAGE and NUMPAGES fields in the footer.
  - `pgSz` and `pgMar` per section.
  - **`gridCol` widths equal `WidthResolver` output within 1 twip.** This proves the "matches horizontally" promise.
    Exceptions, which Word needs to avoid wrapping short labels: `auto` table columns and row items get 15% + 4pt of slack, and content-width boxes get 3% + 2pt. Those widths are a few points wider than `WidthResolver`'s.
- **Schema validation:** a pinned OOXML validator run through npx in `docx.yml`. A schema error fails the job.
- **Determinism:** the same input gives byte-identical `.docx` files.
- **LibreOffice:** `soffice --headless --convert-to pdf` feeds the content parity test. `pdftoppm` PNGs are uploaded as artifacts for review. Page counts are not asserted (LibreOffice paginates differently from Word).
- **Manual release checklist:** Word (macOS or Windows), Google Docs import, Office 365 web. Check header rows repeat, page numbers update, and column widths match the PDF.

**Unchanged and other suites**
- **Phase 0 gate:** every v0.1 fixture's PDF is byte-identical; visual snapshots unchanged.
- **Laravel (Testbench):**
  - The `x-doc` prefix and a custom prefix.
  - `Htmlable` fragment versus `Responsable` full page.
  - `MissingExporter` without the docx package.
  - Strict mode from config.
  - Preview `?format=`.
  - Error file and line reporting under the new prefix.
- **Benchmarks:** HTML and DOCX timings are added to the bench and recorded. Each gets a 20% regression gate once its baseline is committed. The PDF gate is unchanged.
- **CI matrix:** PHP 8.4 and 8.5, plus Laravel 12 and 13 for the Laravel package (unchanged). `docx.yml` needs `ext-zip`, Node (for the validator) and LibreOffice.

## 10. Order of work

| Phase | Delivers | Exit criteria |
|---|---|---|
| **0. Seams and renames** | §5.2 core units, `Image::handle` removed, options split, `Document` API, `WidthResolver`, Laravel renames (`x-doc`, `Papercut`, `PendingDocument`/`PendingOutput`, `prefix` config) | PDF byte-identical to v0.1; full suite green after renames; `WidthResolver` tested on its own against layout fragment widths |
| **1. HTML** | `HtmlExporter`, `HtmlOptions`, Laravel `->html()`, preview `?format=html` | Examples render close to the PDF (reviewed in a browser via `examples/html/render.php`); every HTML capability row has a test; content parity PDF ↔ HTML |
| **1b. Upstream (runs alongside phase 1)** | php-docx PRs for the gaps listed in §5.3 | A php-docx release that has them |
| **1c. PDF/HTML hardening** | Carry-overs that don't need DOCX, plus H2 and H3: [plan](plans/2026-10-19-pdf-html-hardening.md) | Plan exit check passes |
| **2. DOCX** (on hold: blocked on upstream php-docx, H1) | `papercut/docx`, `DocxExporter`, Laravel `->docx()` and `MissingExporter`, `docx.yml` | Schema-valid; golden and XPath tests green; content parity across all three; manual checklist passed |
| **3. Docs and release** (0.2.0 waits for DOCX, H1) | README "one template, three formats" (the invoice in all three), capability table in the docs, migration table, version bumps, 0.2.0 tags | Docs reviewed; tags pushed by the maintainer |

**Why the order:**
- HTML first is cheap, needs no new dependency, and proves the tree is independent of layout.
- `WidthResolver` is in phase 0, not phase 2, because HTML never uses it. Otherwise the riskiest DOCX seam would go untested until the end.

## 11. Risks

- **php-docx is young and has one maintainer** (created 2026-05, 1 star), the same maintainer as our PDF backend.
  - Mitigation: the import rule (§8.1), a pinned version, and DOCX golden and contract tests that any replacement writer must pass.
  - The upstream relationship is also a channel for fixes.
  - The gaps were confirmed against 1.15 on 2026-10-19 ([gaps](../php-docx-1.15-gaps.md)); DOCX waits for a release that closes them.
- **Word's text metrics differ from ours,** so fixed-height rows can cut off text and page breaks drift. Fixed height is documented as ≈. Page-break drift is a documented non-goal.
- **Font substitution in DOCX** changes how wide text runs within our fixed column widths. Font embedding is a follow-up to contribute upstream (OOXML obfuscated embedded TrueType fonts; Word honors them, Google Docs ignores them).
- **Host CSS leaking into HTML fragments.** Mitigation: inline styles plus the `.papercut` reset. Documented as "close enough".
- **`WidthResolver` extraction disturbing layout.** Mitigation: phase 0 exit gate on byte-identical PDF output.
- **CI weight:** LibreOffice and Node in `docx.yml`. It is a separate workflow, so it doesn't slow down core CI.

## 12. Future work (the design leaves room)

- *Done:* font sources (Google Fonts and others in HTML export), see `2026-10-19-html-font-sources-design.md`.
- **Raw HTML input** (`<x-doc::html>`): convert HTML into Papercut nodes once, when the tree is built, using php-pdf's `HtmlParser` and CSS cascade as the front end plus a translator from its elements (`Paragraph`, `Run`, `Table`, `ListNode`) to `Text`/`Span`/`Table`. The result works in every format. It reverses v0.1's "no HTML parser" stance, so it needs its own spec. It would likely also bring a list node.
- **Font embedding in DOCX** (upstream).
- **An editable DOCX mode** (fewer layout tables, more native paragraphs), as a contribution.
- **Page background in DOCX** as an anchored shape behind the text.
- **Bookmarks** (moved from v0.2 to v0.3), mapping naturally to PDF outlines, DOCX heading styles and HTML anchors.
