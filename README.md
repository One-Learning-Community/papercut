<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/papercut-logo-dark.svg">
    <img alt="Papercut" src="art/papercut-logo.svg" width="320">
  </picture>
</h1>

[![core](https://github.com/One-Learning-Community/papercut/actions/workflows/core.yml/badge.svg)](https://github.com/One-Learning-Community/papercut/actions/workflows/core.yml)
[![laravel](https://github.com/One-Learning-Community/papercut/actions/workflows/laravel.yml/badge.svg)](https://github.com/One-Learning-Community/papercut/actions/workflows/laravel.yml)

**Page-aware PDF, Word, and HTML for modern PHP.**

Write documents as Blade views, or build them with a fluent PHP API. Papercut lays them out page by page: table headers repeat, rows aren't cut in half, headings stay with what follows them, and `Page {page} of {total}` is one tag. It's pure PHP, with PDFs written by Denis Skripchenko's [php-pdf](https://github.com/dskripchenko/php-pdf) (see [Built on](#built-on)), so there's no headless Chrome, wkhtmltopdf or Node on your server. The same template also exports to HTML, and to Word.

Templates use a small set of `<x-doc::*>` tags instead of HTML and CSS. That's what keeps pagination predictable, and it makes mistakes loud: a typo in an attribute is an error that names the tag, not a silently wrong page. HTML you already have, such as rich text from an editor, comes in through `<x-doc::markup>`.

**[Documentation](https://one-learning-community.github.io/papercut/)** · [Guide](https://one-learning-community.github.io/papercut/guide/) · [Laravel](https://one-learning-community.github.io/papercut/laravel/) · [Recipes](https://one-learning-community.github.io/papercut/laravel/recipes.html) · [Reference](https://one-learning-community.github.io/papercut/reference/blade-tags.html) · [Examples](https://one-learning-community.github.io/papercut/examples.html)

> **Status: 0.11, before 1.0.** Until 1.0 a minor version may change the API; patch versions won't. See [Status](#status) below.

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

## One template, PDF, HTML and Word

```php
$invoice = Papercut::view('pdf.invoice', compact('invoice'));

return $invoice->pdf()->download('invoice.pdf');   // PDF
return $invoice->html();                           // full HTML page
// {!! $invoice->html() !!}                        // fragment inside another Blade view
return $invoice->docx()->download('invoice.docx'); // Word, with papercut/docx installed
```

## In a Laravel app

```php
// Attach to a mailable or notification; it renders when attached.
public function attachments(): array
{
    return [Papercut::view('pdf.invoice', ['invoice' => $this->invoice])->pdf()->attachment('invoice.pdf')];
}

// Store on any disk, for example from a queued job.
Papercut::view('pdf.report', ['report' => $report])->pdf()->store("reports/{$report->id}.pdf", 's3');

// Download from a Livewire or Filament action.
return Papercut::view('pdf.invoice', ['invoice' => $invoice])->pdf()->streamDownload('invoice.pdf');
```

Preview routes render each document with sample data at a fixed URL while you work on it. The [recipes](https://one-learning-community.github.io/papercut/laravel/recipes.html) cover queues, S3, mail, Livewire, Filament and SVG charts.

## Features

- Flexbox-style layout: rows, columns, `gap`, `grow`, `justify`, `align`, spacers.
- Page-aware tables: rows split across pages, never through a row unless a row is taller than a page; headers repeat on every page a table crosses; cells can span rows and columns.
- Text in columns, like Word's: content fills each column and then the next page, the last page ends level, and Word export keeps real, editable section columns.
- Keep-together and keep-with-next, so a heading isn't stranded at the bottom of a page.
- Page numbers with a page count (`Page {page} of {total}`), bookmarks built from headings, and a table of contents with page numbers, dot leaders and links.
- Real fonts: every document embeds its fonts (Inter is bundled; register your own families; in Laravel, one command installs a Google Fonts family), with subsetting and fallback families. The 14 standard PDF fonts are not used.
- Lists, links, images (PNG and JPEG; WebP, GIF, BMP and AVIF with `ext-gd`), SVG drawn as vectors, and QR codes, plain or styled.
- Equations, in a block or inside a line of text, in the PDF, HTML (MathML) and Word (live Word equations), built with the `papercut/math` package.
- Code blocks, with syntax highlighting through the optional `phiki/phiki` package.
- Rich text from your data: `<x-doc::markup>` turns an HTML fragment or plain text (say, from a rich text editor) into Papercut nodes, and reports anything it couldn't convert.
- PDF templates and placed content: draw a page of an existing PDF behind yours and place text at a point on it, for certificates and letterheads.
- AES-256 encryption with permissions.
- HTML export from the same template, and Word export with `papercut/docx`, which writes real Word headings, lists, tables and a table of contents field.
- Decorators: ready-made card, callout, blockquote, section, field, choice list, key-value list and badge, restyled from the theme (`<x-doc::callout type="warning">`).
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

HTML and Word exports walk the same node tree instead of the page layout, so they keep the document's
structure (headings, lists, tables) for a browser or for Word to flow.

## Built on

Papercut stands on two libraries by [Denis Skripchenko](https://github.com/dskripchenko), and wouldn't exist without
them.

- **[`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf)** writes every PDF Papercut makes. Papercut decides where things go on the page; php-pdf turns
  that into a PDF. It parses, subsets and embeds the fonts and applies their kerning, embeds the images, draws SVG,
  encodes QR codes, encrypts, and reads existing PDFs for page templates. It can also sign and merge the result.
  When Papercut needed more, such as even-odd fills in SVG and faster font loading, the maintainer added it upstream.
- **[`dskripchenko/php-docx`](https://github.com/dskripchenko/php-docx)** writes Papercut's Word files.

Both are pure PHP and MIT licensed. If Papercut is useful to you, they deserve a star too.

## Not using Laravel?

`papercut/core` is framework-agnostic. Build the same documents with a fluent, immutable PHP API; see
`packages/core` and the runnable scripts in [`examples/core`](examples/core).

## Examples

| | Blade | Plain PHP |
|---|---|---|
| Invoice | [`examples/laravel/views/invoice.blade.php`](examples/laravel/views/invoice.blade.php) | [`examples/core/invoice.php`](examples/core/invoice.php) |
| Transcript | [`examples/laravel/views/transcript.blade.php`](examples/laravel/views/transcript.blade.php) | [`examples/core/transcript.php`](examples/core/transcript.php) |
| Report | [`examples/laravel/views/report.blade.php`](examples/laravel/views/report.blade.php) | [`examples/core/report.php`](examples/core/report.php) |
| Integration guide (mixed orientation, code blocks) | [`examples/laravel/views/guide.blade.php`](examples/laravel/views/guide.blade.php) | [`examples/core/documents/guide.php`](examples/core/documents/guide.php) |
| Certificate (fills in a PDF template; PDF only) | [`examples/laravel/views/certificate.blade.php`](examples/laravel/views/certificate.blade.php) | [`examples/core/documents/certificate.php`](examples/core/documents/certificate.php) |
| Knowledge base article (HTML from a rich text editor) | [`examples/laravel/views/article.blade.php`](examples/laravel/views/article.blade.php) | [`examples/core/documents/article.php`](examples/core/documents/article.php) |
| Academic paper (two-column body, full-width title and abstract) | [`examples/laravel/views/paper.blade.php`](examples/laravel/views/paper.blade.php) | [`examples/core/documents/paper.php`](examples/core/documents/paper.php) |
| Form responses (decorators) | [`examples/laravel/views/responses.blade.php`](examples/laravel/views/responses.blade.php) | [`examples/core/documents/responses.php`](examples/core/documents/responses.php) |

Render the Blade examples with `php examples/laravel/render.php` (after `cd packages/laravel && composer install`).

## Documentation

The guide lives at [one-learning-community.github.io/papercut](https://one-learning-community.github.io/papercut/) (source in [`site/`](site)). Package READMEs: [`packages/math`](packages/math/README.md), [`packages/core`](packages/core/README.md), [`packages/laravel`](packages/laravel/README.md) and [`packages/docx`](packages/docx/README.md).

## Status

0.11, before 1.0. Until 1.0 a minor version may change the API; patch versions won't. [Versions and compatibility](https://one-learning-community.github.io/papercut/guide/compatibility.html) says what the promise covers. Word, LibreOffice and Google Docs lay out a Word file themselves, so its page breaks can differ from the PDF's. No performance or compatibility benchmarks have been
published; don't assume any. Requires PHP 8.4+, and Laravel 12 or 13 for `papercut/laravel` (CI tests against both).

Not supported yet: HarfBuzz shaping (the backend exposes no glyph-level drawing API; its built-in shaping covers kerning
and ligatures within runs), right-to-left and bidirectional paragraph layout, hyphenation, Knuth-Plass line breaking,
fillable form fields (AcroForms) and PDF/A. Filling in an existing PDF works through page templates.
Signing and merging aren't built in, but php-pdf can do both to a Papercut PDF; see
[Passwords, signatures and merging](https://one-learning-community.github.io/papercut/guide/passwords-and-signatures.html).

## Licenses

- Papercut: MIT (`LICENSE`). Third-party notices: [`NOTICE.md`](NOTICE.md).
- Bundled Inter font: SIL Open Font License 1.1 (`packages/core/resources/fonts/Inter/LICENSE.txt`).
- Bundled Cousine font: SIL Open Font License 1.1 (`packages/core/resources/fonts/Cousine/OFL.txt`).
- Color palette derived from Tailwind CSS: MIT.
- PDF backend [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf): MIT.
- Word backend [`dskripchenko/php-docx`](https://github.com/dskripchenko/php-docx): MIT.
