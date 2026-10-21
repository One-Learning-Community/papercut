# Installation

## Requirements

- PHP 8.4 or newer
- `ext-mbstring`, `ext-zlib` and `ext-dom`
- `ext-gd`, but only if you need WebP, GIF, BMP or AVIF images (PNG and JPEG work without it)
- Laravel 12 or 13, if you're using the Blade package

The core package has a single Composer dependency, [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf), which writes the PDF files.

## Installing

Install with Composer:

```bash
composer require papercut/laravel   # Blade tags; pulls in papercut/core
composer require papercut/core      # the engine alone
```

Laravel discovers the service provider and the `Papercut` facade automatically, so there's nothing to register.

## Checking your fonts

Papercut ships with the Inter font, so your first document will render without any setup. In Laravel, you can list every font Papercut can see:

```bash
php artisan papercut:fonts
```

Each font file is reported as `ok`, `missing` or `invalid`. See [Fonts](./fonts.md) to add your own.
