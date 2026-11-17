# Installation

## Requirements

- PHP 8.4 or newer
- `ext-mbstring`, `ext-zlib` and `ext-dom`
- `ext-gd`, but only if you need WebP, GIF, BMP or AVIF images (PNG and JPEG work without it)
- `ext-zip`, but only for Word export (`quoin/docx`)
- Laravel 12 or 13, if you're using the Blade package

The core package has two Composer dependencies: [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf), which writes the PDF files, and `quoin/math`, which builds [equations](./equations.md) and is released alongside it.

## Installing

Install with Composer:

```bash
composer require quoin/laravel   # Blade tags; pulls in quoin/core
composer require quoin/core      # the engine alone
composer require quoin/docx      # Word export; see Word export
```

Laravel discovers the service provider and the `Quoin` facade, so there's nothing to register.

`quoin/docx` pulls in `quoin/core`. In Laravel, `->docx()` works once it's installed. See [Word export](./word-export.md).

## Optional packages

These are suggested; none is required:

- `phiki/phiki` colors the syntax in code blocks.
- `bacon/bacon-qr-code` draws styled QR codes (dotted or rounded modules, eye shapes, colors, gradients).
- `rsvg-convert` (librsvg, a command-line program) draws SVG in Word export, without Imagick and more faithfully than most Imagick builds. Install it and [give it the path](/guide/word-export.md#svg-in-word), in Laravel with `QUOIN_RSVG_CONVERT` in `.env`.
- `ext-imagick` draws styled QR codes (with `bacon/bacon-qr-code`) in Word export, and SVG when `rsvg-convert` isn't set up; SVG needs an Imagick build that reads SVG. PDF and HTML don't use it.
- `ext-gd` also helps in Word export, for WebP and AVIF images, image `cover`, opacity and plain QR codes.

## Checking your fonts

The Inter font, the Cousine monospace font and the STIX Two Math font for [equations](./equations.md) are bundled (all under the SIL Open Font License), so your first document renders without setup. In Laravel, list every available font:

```bash
php artisan quoin:fonts
```

Each font file is reported as `ok`, `missing` or `invalid`. See [Fonts](./fonts.md) to add your own.
