# Versions and compatibility

The packages follow [Semantic Versioning](https://semver.org/).

## Before 1.0

The current version is 0.15. Until 1.0, a minor version (0.15 to 0.16) may change the API, and the [changelog](https://github.com/One-Learning-Community/quoin/blob/main/CHANGELOG.md) lists every change. A patch version (0.15.0 to 0.15.1) only fixes bugs. Require a minor version with a caret, such as `^0.15`, and Composer won't move you past it.

## From 1.0

A breaking change to the public API waits for the next major version (2.0). Minor versions add features, and patch versions fix bugs.

## The four packages move together

`quoin/math`, `quoin/core`, `quoin/laravel` and `quoin/docx` share one version number and are released together. Word export and the Blade package use parts of the core that aren't public, so install the same minor version of each. Before 1.0, Composer enforces this: core requires the matching quoin/math, and the other packages require the matching core (`^0.15`).

## What the promise covers

- The PHP API in this guide and the [reference](/reference/blade-tags.md): the elements (`Text`, `View`, `Table`, `ColumnFlow` and the rest), their setters, styles and themes, fonts, the exporters and their options, and the exceptions they throw.
- Blade tags, their attributes and the style attributes.
- Theme style names, including the decorators' named styles.
- The Laravel config keys, the `Quoin` facade, the preview routes and the Artisan commands.
- Your own decorators: the `Quoin\Decorator\Decorator` interface and the `HasStyle` and `HasVariants` traits, as shown in [Your own decorator](/guide/decorators.md#your-own-decorator).
- Making a `PhpPdfBackend` with its constructor options and passing it to `PdfOptions(backend:)`, as in [Fonts](/guide/fonts.md).

## What it doesn't cover

- **Anything marked `@internal`.** These can change in any release, including a patch. Editors such as PhpStorm warn when you use them.
- **The engine.** The layout engine (`Quoin\Layout`), the tree walk (`Quoin\Tree`), the HTML writer and the text layout classes are internal. The HTML export's font sources (`FontSource`, `GoogleFonts`, `LocalFonts` and the types they use) are public.
- **Custom exporters.** You export through `toPdf()`, `toHtml()` and `export(new DocxExporter())`, which are covered. Writing your own `Exporter` isn't.
- **The packages' own classes behind the public API.** The Blade tags, Artisan commands and preview routes are covered, but the PHP classes that implement them aren't. Nor are the Word export's internals.
- **Custom backends.** The `Quoin\Backend` interfaces and the `Quoin\Paint` display list are internal. The built-in PDF writer is the only backend, which leaves room to add things like tagged PDF without a breaking change.
- **Exact output.** The PDF, HTML and Word files a document produces can differ between versions. A bug fix can move a line or a page break, and the bytes of the files change too. Content isn't dropped without a warning, and a fix that changes layout is listed in the changelog.
- **Word layout.** Word, LibreOffice and Google Docs lay out a Word file themselves, so page breaks in a Word file can differ from the PDF.

## Requirements

The packages need PHP 8.4 or newer, and Laravel 12 or 13 for the Blade package. Support for a PHP or Laravel version is dropped only in a minor or major version, never in a patch, and the changelog says so.
