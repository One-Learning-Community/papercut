# Changelog

All notable changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

## [Unreleased] - 0.2.0

v0.1 was never published to Packagist, so there are no deprecation shims.

### Changed (breaking)

Migration from v0.1:

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

- The `Pdf` facade alias is now `Papercut`; `Papercut::pdfOptions()` replaces `Pdf::options()`.
- The Blade tag prefix is configurable through `papercut.prefix` (default `doc`).
- `PdfOptions` is not a drop-in rename of `RenderOptions`; the constructor changed shape:
  - the positional order is now theme, fonts, onWarning, strict, then backend, overflow, cacheDir, so use named arguments;
  - `fonts` takes a `FontSet`, not a `FontRegistry`;
  - fallback families moved to `FontSet::withFallbacks()`;
  - the bundled Inter is always registered.
- Save failures now read `Directory does not exist: …` or `Could not write output to "…": …` (previously `Could not write PDF to …`).
- In Laravel, all config errors (theme, styles, overflow, strict, fonts, including a missing or unreadable font file) now surface when the output is rendered, not at `view()`, `make()` or `Papercut::pdfOptions()`.
- `->html()` returns a `PendingHtml` that no longer extends `PendingOutput` and has no `toString()`, `save()`, `download()`, `inline()` or `output()`. Use `->html()->page()` (full page) or `->html()->fragment()` (embeddable fragment), each a `PendingOutput` named `document.html`. `PendingOutput` is now `final`. Returning `->html()` from a route still sends the full page with no `Content-Disposition`.
- `Output` and the export options expose methods, not properties: `$output->bytes()`, `mime()`, `extension()`; `$options->theme()`, `fonts()`, `onWarning()`, `strict()`, `overflow()`, `cacheDir()`.

### Added

- QR codes: `QrCode::make($value)` and `<x-doc::qr-code value="…">` draw a QR code as vector squares in the text color, square, 25mm by default, with error-correction level and quiet zone. Encoded with php-pdf's encoder (up to 213 bytes at Medium) behind a `QrEncoder` interface, or from your own grid with `QrCode::fromModules()`. HTML exports draw an inline SVG.
- Encryption: `PdfOptions(encryption: Encryption::password('secret'))` and Laravel's `->encrypt()` write AES-256 PDFs that open only with the password. `Encryption` sets print, copy, modify, annotate and assemble permissions; a missing owner password is generated at random so the permissions hold, and screen-reader access is always allowed.
- Bookmarks: headings `h1`–`h4` fill the PDF's bookmarks panel, nested by level. `bookmark($title, $level)` (Blade `bookmark`, `bookmark-level`) adds or retitles one on any component, `bookmark(false)` removes a heading's, and `PdfOptions(bookmarks: false)` / `papercut.bookmarks` turns them off. Headers, footers and backgrounds never add bookmarks.
- `php artisan papercut:fonts:install <family>` downloads a family from Google Fonts into the app for use in PDFs, and prints its `fonts` config entry.
- `PendingOutput::store($path, $disk = null, $options = [])` writes a PDF or HTML export to a Laravel filesystem disk.
- `Papercut::make($document)` and `PendingDocument` (`document()`, `pdf()`); `PendingOutput` takes its `Content-Type` and default filename from the `Output`.
- Core: `Exporter`/`Output` API, `PdfOptions`, `FontSet`, fidelity reporting (`Fidelity`, `FidelityReport`), strict mode (`UnsupportedFeature`) and `MissingExporter`.
- Config key `papercut.strict` (default `false`).
- HTML export: `Document::toHtml()` in core and `Papercut::view(...)->html()` in Laravel (`PendingHtml`: `(string)` and `{{ }}` give the fragment for embedding, `page()` and `fragment()` each return a `PendingOutput`, and a route response is the full page).
- Config key `papercut.html.fonts` (`none` default, `embed` or `link`), and `papercut.html.image_url` / `papercut.html.font_url`: invokable class names implementing `Papercut\Laravel\Html\ImageUrlResolver` / `FontUrlResolver`, built by the container (class names survive `config:cache`; closures are rejected). `link` needs `font_url`. Invalid `html.*` config is reported at the first `->html()` export.
- Laravel: `papercut.html.fonts = sources`, with `html.font_sources` (default `[GoogleFonts::class]`), `html.google_fonts` (`host`, `display`, `families`) and `html.local_fonts` (`mode`, `families`). The service provider binds `GoogleFonts` and `LocalFonts` from config. Checked lazily at the first `->html()` export, and only when used.
- Core: lists. `ListNode::ordered()` / `unordered()` with `ListItem`, `start()`, `marker()` and `ListItem::withValue()`; `ListMarker` (decimal, lower/upper alpha and roman, disc, circle, square, none). Markers follow nesting depth unless set, sit in a right-aligned gutter on the first line's baseline, and items split across pages. PDF and HTML (`<ol>`/`<ul>`/`<li>`).
- Core: `Markup::html()`, `Markup::text()` and `Markup::flatten()` with `MarkupOptions` (`styles`: `StyleMode::Theme` or `Original`, `images` resolver closure, `blankParagraphs`). Converts an HTML fragment or plain text to nodes. Unsupported content is reported through the fidelity report at `approximated` or `dropped`, so strict mode fails only on the dropped cases. Nesting deeper than 256 levels is flattened to text with one report. Bytes the image resolver returns that are not a recognised image format count as a missing image. `img` `width`/`height` attributes set a width only (the image keeps the aspect of its bytes and still shrinks to fit a page). Block-level tags without a mapping (`blockquote`, `pre`, `section`, tables, `dl`, `hr`, …) keep their content as separate paragraphs (`pre` keeps its line breaks); unknown tags are reported once per tag name. List `start`/`value` attributes are clamped to the 32-bit range, and roman markers fall back to decimal outside 1–3999.
- Laravel: `<x-doc::ol>`, `<x-doc::ul>` and `<x-doc::li>`.
- Laravel: `<x-doc::markup :html|:text>` (inline inside a text tag; numbers are taken as strings) and config keys `papercut.markup.images` (a class implementing `Papercut\Laravel\Markup\ImageResolver`), `markup.styles` and `markup.blank_paragraphs`.
- Laravel: `<x-doc::node :node="$node" />` embeds a PHP-built `Node` or `Component`, as a block or inline inside a text tag. An inline tag cannot span an embedded node.
- Preview routes accept `?format=pdf|html`; any other value is a 404.
- `Papercut::exportOptions()` (shared theme, fonts, warnings, strict) and `Papercut::htmlOptions()`; `pdfOptions()` builds on the shared options.
- `HtmlOptions` (`fullPage`, `fontMode`, `imageUrl`, `fontUrl`), `FontMode` (`None`, `Embed`, `Link`) and the `imageUrl` / `fontUrl` callbacks: images embed as data URIs unless `imageUrl` returns a safe URL; fonts embed or link per `FontMode`.
- `onWarning` callbacks are arity-aware: a one-parameter callback gets the message only, a callback with a second parameter also gets the `FidelityReport`.
- A table cell outside a table (not in a row) is laid out as a column and warns `A table cell outside a table is laid out as a column`, in both PDF and HTML.
- HTML font sources: `FontMode::Sources` with `HtmlOptions::fontSources`, an ordered stack of `FontSource` objects or closures (`GoogleFonts` for Google Fonts and Bunny Fonts, `LocalFonts::embed()` / `link()` for registered files). Full pages get `<link>` tags in `<head>`; fragments get `@import` rules first in `<style>`. Unsafe answers fall through to the next source with a warning, and a family nobody answers for is reported as `font not linked`.
- `Papercut\Tree\Visit`: `TreeVisitor` callbacks now receive a `Visit` (path, index, parent, header and region context) instead of loose arguments.
- Images: an image taller than an empty page shrinks to fit it, keeping its aspect ratio, as it already did for width (previously `LayoutOverflow`). An explicit `height` is still respected.
- Images: WebP, GIF, BMP and AVIF are accepted when the GD extension is loaded (`ext-gd`, now in `suggest`). They are converted to PNG for PDF, which is lossless and keeps transparency; HTML export embeds the original bytes. PNG and JPEG never need GD. Without GD the error names the extension to install, and the unsupported-format message lists every accepted format.
- Paper sizes: `Page::named()` adds `a0`, `a1`, `a2`, `b4`, `b5`, `tabloid` and `executive` (`Page::sizeNames()` lists them all), and an unknown name lists the valid ones. In Laravel, `<x-doc::page size>` and `papercut.paper` also accept a width and height, such as `"8.5in 11in"`.
- Core: decorator components in `Papercut\Decorator`: `Card`, `Callout`, `Blockquote`, `Section`, `Field`, `ChoiceList`, `KeyValueList` and `Badge`. They render from existing nodes, so PDF and HTML export both work, and their look comes from built-in named styles (`card`, `callout.warning`, `choice.marker`, `badge.tip`, …) that any `Theme` resolves and `withStyle()` can override.
- Core: `Theme` accepts space-separated style names (`apply('callout callout.warning')`), merged left to right; an unknown name in the list is reported with a suggestion.
- Core: `View::htmlTag()` sets the element HTML export writes (`div`, `section`, `aside`, `blockquote`, `header`, `footer`, `figure`); PDF ignores it.
- Core: highlighted text runs. Spans honour `background`, and `<mark>` in `Text::markup()` paints `yellow-200` behind the run (one rectangle per line fragment) in PDF and HTML.
- Core: `Markup::html()` converts `<blockquote>` to `Blockquote` (a trailing `<cite>` or `<footer>` becomes the attribution) and `<mark>` to a highlight; `<span style="background-color">` keeps its colour in `StyleMode::Original`.
- Laravel: `<x-doc::card>`, `<x-doc::callout>`, `<x-doc::blockquote>`, `<x-doc::section>`, `<x-doc::field>`, `<x-doc::choices>` / `<x-doc::choice>`, `<x-doc::key-value>` / `<x-doc::kv>` and `<x-doc::badge>`, plus a `responses` example and a "Decorators" section in the README.
- Upgrade note: decorator style names (`card`, `card.title`, `callout.*`, `section.*`, `field.*`, `choice.*`, `key-value.*`, `badge.*`, `blockquote.*`) are used by the decorators, and a theme or config style with the same name replaces the built-in one. If you already define a style called `card`, rename it or expect it to restyle `<x-doc::card>`.
- Core: `Theme::withStyle()` rejects a name containing whitespace, since it could never be resolved; `Callout::tone()` rejects internal whitespace; blank titles, numbers, labels and cites count as unset; a fully transparent span background paints nothing.
- Core: `em` and `lh` lengths in styles (`padding="0.5em"`, `min-height="4lh"`, `Length::em()`, `Length::lh()`): `em` is the element's font size and `lh` its line height, resolved to points when styles are computed. A relative `font-size` is a share of the parent's. Page margins, table tracks and editor-HTML CSS stay absolute.
- Decorators follow the theme's size: `Field::lines(n)` reserves n lines of the field's own text inside its padding, choice markers are `0.75em` and centred on the first line of their label, and the field label, badge and blockquote cite use `0.9em` instead of 9pt.
- Core: a `responses` example (`examples/core/documents/responses.php`), the PHP twin of the Blade one, built from all eight decorators.
- A documentation site built with VuePress in `site/`, deployed to GitHub Pages by `.github/workflows/docs.yml`.

### Fixed

- `min-height` and `max-height` now apply to boxes and text that size to their content in the PDF (the HTML export already had them). A `min-height` that does not fit in the space left on a page moves the box to the next page (on an empty page it is cut to the page). A box with `max-height` (and text with a `height`) is kept whole and placed or moved, like a fixed-height box; only a cap taller than an empty page lets it split, so no content is cut off. Text also honours an explicit `height`.
- Page margins that meet or cross now throw `InvalidValue` (kind `page margins`) naming the page and the margins, in both PDF and HTML. Left and right margins wider than the page used to render one letter per line, and the HTML export rendered silently; top and bottom margins taller than the page blamed the header and footer. Checked at export, after the size and orientation are final.
- Visual change: `Field::lines(n)` now counts lines inside the padding, so a field with `lines` is taller by its padding and border (`lines="2"` goes from 28pt to 40pt at the default size).

## [0.1.0] - unreleased

### Added

#### papercut/core

- Immutable component tree: `Document`, `Page`, `Row`, `Column`, `Spacer`, `Text` (with inline markup and spans),
  `Image`, `Link`, `Table`, `PageNumber`, `PageBreak`.
- Typed values: `Length` (`pt px mm cm in`), `Percent`, `Fr`, `Color`, `Edges`, `TrackList`.
- Flexbox-style layout: `gap`, `grow`, `shrink`, `basis`, `justify`, `align`, min/max sizes.
- Page-aware pagination: tables that split between rows with repeating headers, keep-together, keep-with-next,
  forced breaks, orphan/widow control, headers, footers, backgrounds, `{page}` / `{total}` page numbers.
- Fonts: bundled Inter family, custom font registration, fallback families, subsetting.
- Themes and named styles; Tailwind color names.
- PDF backend adapter over `dskripchenko/php-pdf`; deterministic output.
- Strict errors with suggestions: `UnknownAttribute`, `InvalidValue`, `FontNotFound`, `ImageUnreadable`,
  `LayoutOverflow`; `OverflowMode::Throw` and `Clip`.
- On-disk font kerning cache (`RenderOptions(cacheDir: ...)`, `false` disables); default `sys_get_temp_dir()/papercut-cache-<uid>`, 30-day prune.
- Plain-PHP usage guide whose code blocks are executed by the test suite.

#### papercut/laravel

- `<x-pdf::*>` Blade components that compile to the core tree through per-render node markers.
- `Pdf` facade: `view()` returning a `PendingPdf` (`download()`, `inline()`, `save()`, `toString()`) and `preview()` routes.
- Config file `papercut.php` (page size and margin, font families, theme, named styles, overflow, preview route).
- Config key `cache_dir` for the kerning cache (`null` default location, `false` disabled, or a path).
- Blade error reporting with template file and line.
- Support for Laravel 12 and 13.

### Not included in 0.1

Right-to-left and bidirectional paragraph layout, HarfBuzz shaping (ruled out of scope for 0.1: the backend has no
glyph-level drawing API; its built-in shaping covers kerning and ligatures within runs), hyphenation, Knuth-Plass line breaking, multi-column text, SVG, AcroForms, signatures, PDF/A,
encryption, editing existing PDFs, table of contents and bookmarks (bookmarks are planned for 0.2).
