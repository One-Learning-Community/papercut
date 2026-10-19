# Changelog

All notable changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

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
- Plain-PHP usage guide whose code blocks are executed by the test suite.

#### papercut/laravel

- `<x-pdf::*>` Blade components that compile to the core tree through per-render node markers.
- `Pdf` facade: `view()` returning a `PendingPdf` (`download()`, `inline()`, `save()`, `toString()`) and `preview()` routes.
- Config file `papercut.php` (page size and margin, font families, theme, named styles, overflow, preview route).
- Blade error reporting with template file and line.
- Support for Laravel 12 and 13.

### Not included in 0.1

Right-to-left and bidirectional paragraph layout, HarfBuzz shaping (ruled out of scope for 0.1: the backend has no
glyph-level drawing API; its built-in shaping covers kerning and ligatures within runs), hyphenation, Knuth-Plass line breaking, multi-column text, SVG, AcroForms, signatures, PDF/A,
encryption, editing existing PDFs, table of contents and bookmarks (bookmarks are planned for 0.2).
