# papercut/core

Component-based, page-aware PDF layout engine for modern PHP.

> **Status: pre-release.** This package currently ships the foundation only: typed values
> (`Length`, `Color`, `Edges`, `TrackList`), backend-neutral paint ops, a font registry with
> the bundled Inter family, and a PDF backend built on `dskripchenko/php-pdf`.
> The component tree and layout engine land next.

## Requirements

PHP 8.4+, `ext-mbstring`, `ext-zlib`, `ext-dom`.

## License

MIT. Bundled Inter font: SIL Open Font License 1.1 (see `resources/fonts/Inter/LICENSE.txt`).
Color palette derived from Tailwind CSS (MIT).
