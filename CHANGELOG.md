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

- `Papercut::make($document)` and `PendingDocument` (`document()`, `pdf()`); `PendingOutput` takes its `Content-Type` and default filename from the `Output`.
- Core: `Exporter`/`Output` API, `PdfOptions`, `FontSet`, fidelity reporting (`Fidelity`, `FidelityReport`), strict mode (`UnsupportedFeature`) and `MissingExporter`.
- Config key `papercut.strict` (default `false`).
- HTML export: `Document::toHtml()` in core and `Papercut::view(...)->html()` in Laravel (`PendingHtml`: `(string)` and `{{ }}` give the fragment for embedding, `page()` and `fragment()` each return a `PendingOutput`, and a route response is the full page).
- Config key `papercut.html.fonts` (`none` default, `embed` or `link`), and `papercut.html.image_url` / `papercut.html.font_url`: invokable class names implementing `Papercut\Laravel\Html\ImageUrlResolver` / `FontUrlResolver`, built by the container (class names survive `config:cache`; closures are rejected). `link` needs `font_url`. Invalid `html.*` config is reported at the first `->html()` export.
- Preview routes accept `?format=pdf|html`; any other value is a 404.
- `Papercut::exportOptions()` (shared theme, fonts, warnings, strict) and `Papercut::htmlOptions()`; `pdfOptions()` builds on the shared options.
- `HtmlOptions` (`fullPage`, `fontMode`, `imageUrl`, `fontUrl`), `FontMode` (`None`, `Embed`, `Link`) and the `imageUrl` / `fontUrl` callbacks: images embed as data URIs unless `imageUrl` returns a safe URL; fonts embed or link per `FontMode`.
- `onWarning` callbacks are arity-aware: a one-parameter callback gets the message only, a callback with a second parameter also gets the `FidelityReport`.
- A table cell outside a table (not in a row) is laid out as a column and warns `A table cell outside a table is laid out as a column`, in both PDF and HTML.
- `Papercut\Tree\Visit`: `TreeVisitor` callbacks now receive a `Visit` (path, index, parent, header and region context) instead of loose arguments.

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
