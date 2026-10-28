# Third-party notices

Papercut is MIT licensed (see `LICENSE`). It includes or depends on the following third-party works.

## Inter typeface

Bundled in `packages/core/resources/fonts/Inter/` (Regular, Bold, Italic, BoldItalic), and embedded (subset) in
generated PDFs.

- License: SIL Open Font License 1.1
- Full text: `packages/core/resources/fonts/Inter/LICENSE.txt`
- Project: https://rsms.me/inter/

## Tailwind CSS color palette

The named colors (`slate-500`, `indigo-600`, ...) in `packages/core/resources/palette.php` are generated from the
Tailwind CSS v3.4.17 default palette by `packages/core/tools/generate-palette.php`.

- License: MIT, Copyright (c) Tailwind Labs, Inc.
- Full text: `packages/core/resources/TAILWIND-LICENSE.txt`

## dskripchenko/php-pdf

`papercut/core` depends on [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) (`^1.26`) for PDF
object writing, font subsetting and text shaping. It is used only through `Papercut\Backend\PhpPdf\*`.

- License: MIT (distributed via Composer, not bundled in this repository)

## dskripchenko/php-docx

`papercut/docx` depends on [`dskripchenko/php-docx`](https://github.com/dskripchenko/php-docx) (`^1.17`) to write Word files. It is used only through
`Papercut\Docx\*`.

- License: MIT (distributed via Composer, not bundled in this repository)
