# Papercut

[![core](https://github.com/One-Learning-Community/papercut/actions/workflows/core.yml/badge.svg)](https://github.com/One-Learning-Community/papercut/actions/workflows/core.yml)
[![laravel](https://github.com/One-Learning-Community/papercut/actions/workflows/laravel.yml/badge.svg)](https://github.com/One-Learning-Community/papercut/actions/workflows/laravel.yml)

Blade components that compile to a real layout engine, not HTML.

Papercut is a PDF library for modern PHP. You write `<x-doc::*>` tags in Blade; they become a tree of
typed, immutable components with flexbox layout and page-aware pagination, and a pure-PHP engine turns
that tree into a PDF. There is no browser, no HTML parser and no CSS cascade. The tag vocabulary is small,
strict and ours, and a typo in an attribute is an error that names the tag, not a silently wrong page.

> **Status: v0.1, pre-release.** Not yet published to Packagist. See [Status](#status) below.

## A Blade document

```blade
<x-doc::document title="Transcript">
  <x-doc::page size="a4" margin="20mm">
    <x-doc::column gap="8pt">
      <x-doc::h1>{{ $student }}</x-doc::h1>
      <x-doc::table columns="3fr 25mm 20mm">
        <x-doc::thead><x-doc::th>Course</x-doc::th><x-doc::th>Credits</x-doc::th><x-doc::th>Grade</x-doc::th></x-doc::thead>
        @foreach ($courses as $c)
          <x-doc::tr><x-doc::td>{{ $c->title }}</x-doc::td><x-doc::td>{{ $c->credits }}</x-doc::td><x-doc::td align="right">{{ $c->grade }}</x-doc::td></x-doc::tr>
        @endforeach
      </x-doc::table>
    </x-doc::column>
    <x-slot:footer><x-doc::page-number format="Page {page} of {total}" align="center" /></x-slot:footer>
  </x-doc::page>
</x-doc::document>
```

```php
return Papercut::view('pdf.transcript', compact('student', 'courses'))->pdf()->download('transcript.pdf');
```

## One template, PDF and HTML

```php
$invoice = Papercut::view('pdf.invoice', compact('invoice'));

return $invoice->pdf()->download('invoice.pdf');   // PDF
return $invoice->html();                           // full HTML page
// {!! $invoice->html() !!}                        // fragment inside another Blade view
```

## Features

- Flexbox-style layout: rows, columns, `gap`, `grow`, `justify`, `align`, spacers.
- Page-aware tables: rows split across pages, never through a row unless a row is taller than a page.
- Repeated table headers on every page a table crosses.
- Keep-together and keep-with-next, so a heading is not stranded at the bottom of a page.
- Page numbers with a page count: `Page {page} of {total}`.
- Real fonts: every document embeds fonts (Inter is bundled; register your own families), with subsetting and fallback families. The 14 standard PDF fonts are not used.
- Composition: your own Blade components can contain `<x-doc::*>` tags (see `examples/laravel/views/components`).
- Strict, readable errors for unknown attributes, bad values and invalid structure.

## How it works

1. Each `<x-doc::*>` component builds a node of the core engine (`Papercut\Row`, `Papercut\Table`, ...) and,
   instead of emitting HTML, returns a short opaque marker string that stands in for that node in a
   per-render registry.
2. Blade composes the markers into the template output as it normally would, so loops, conditionals and
   your own components work unchanged. The package then resolves the markers back into one node tree.
3. The core engine measures and places that tree page by page (flexbox-style distribution, table and
   text fragmentation) into a backend-neutral display list.
4. A thin backend adapter turns the display list into PDF objects via
   [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf).

## Not using Laravel?

`papercut/core` is framework-agnostic. Build the same documents with a fluent, immutable PHP API; see
`packages/core` and the runnable scripts in [`examples/core`](examples/core).

## Examples

| | Blade | Plain PHP |
|---|---|---|
| Invoice | [`examples/laravel/views/invoice.blade.php`](examples/laravel/views/invoice.blade.php) | [`examples/core/invoice.php`](examples/core/invoice.php) |
| Transcript | [`examples/laravel/views/transcript.blade.php`](examples/laravel/views/transcript.blade.php) | [`examples/core/transcript.php`](examples/core/transcript.php) |
| Report | [`examples/laravel/views/report.blade.php`](examples/laravel/views/report.blade.php) | [`examples/core/report.php`](examples/core/report.php) |

Render the Blade examples with `php examples/laravel/render.php` (after `cd packages/laravel && composer install`).

## Laravel docs

Install, configuration, tag and attribute reference: [`packages/laravel/README.md`](packages/laravel/README.md).

## Status

v0.1, pre-release, not yet published to Packagist. No performance or compatibility benchmarks have been
published; do not assume any. Requires PHP 8.4+ and Laravel 12 or 13 (CI tests against both).

Not in v0.1: HarfBuzz shaping (the backend exposes no glyph-level drawing API; its built-in shaping covers kerning and ligatures within runs), right-to-left and bidirectional paragraph layout, hyphenation,
Knuth-Plass line breaking, multi-column text flow, SVG, AcroForms, digital signatures, PDF/A, encryption,
editing or merging existing PDFs, table of contents and bookmarks. Bookmarks are first in line for v0.2.

## Licenses

- Papercut: MIT (`LICENSE`). Third-party notices: [`NOTICE.md`](NOTICE.md).
- Bundled Inter font: SIL Open Font License 1.1 (`packages/core/resources/fonts/Inter/LICENSE.txt`).
- Color palette derived from Tailwind CSS: MIT.
- PDF backend [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf): MIT.
