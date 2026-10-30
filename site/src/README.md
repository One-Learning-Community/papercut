---
home: true
title: Papercut
heroImage: /papercut-mark.svg
heroImageDark: /papercut-mark-dark.svg
heroAlt: Papercut logo
heroHeight: 160
heroText: Papercut
tagline: Paperwork without the sting.
actions:
  - text: Get started
    link: /guide/
    type: primary
  - text: Using Laravel?
    link: /laravel/
    type: secondary
features:
  - title: Layout you already know
    details: Rows, columns, gaps and grow factors. If you've used flexbox, you can lay out a page.
  - title: Pagination built in
    details: Long tables repeat their header and never split a row. Headings stay with what follows them. "Page 3 of 12" is one tag.
  - title: Blade or PHP
    details: Write documents as Blade views, or build them with a fluent PHP API. Both produce exactly the same thing.
  - title: Word export (experimental)
    details: Export the same document as a Word file people can edit. Headings, lists, tables, headers and page numbers are real Word features, not a picture of the PDF.
  - title: PDF and HTML
    details: Render the same document as a PDF to download, or as HTML to show in the browser.
  - title: Fill in existing PDFs
    details: Put names, dates and signatures at exact points on a PDF you already have, such as a certificate or a letterhead.
  - title: HTML from your editor
    details: Turn HTML from a rich text editor, tables and images included, into document content. Anything it can't convert is reported.
  - title: Components included
    details: Cards, callouts, form fields, checkboxes, badges and code blocks, all styled from one theme so your documents match.
  - title: No headless browser
    details: Pure PHP from start to finish. There's no Chrome or wkhtmltopdf to install on your servers.
footer: MIT licensed. PDFs are written by php-pdf and Word files by php-docx, both by Denis Skripchenko. Inter and Cousine are bundled under the SIL Open Font License.
---

Papercut builds PDFs, Word files and HTML from Blade or plain PHP, with page breaks that just work.

## This code makes this PDF

This is the course table from a three-page transcript. `$courses` comes from your own data, and the full source is below.

<div class="showcase">
<div class="showcase-code">

::: tabs#lang

@tab Blade

```blade
<x-doc::table columns="0.9in 1fr 1in 0.75in 0.75in">
    <x-doc::thead>
        <x-doc::tr background="slate-100">
            <x-doc::th>Code</x-doc::th>
            <x-doc::th>Course</x-doc::th>
            <x-doc::th>Term</x-doc::th>
            <x-doc::th align="right">Credits</x-doc::th>
            <x-doc::th align="right">Grade</x-doc::th>
        </x-doc::tr>
    </x-doc::thead>
    @foreach ($courses as [$code, $title, $term, $cr, $grade])
        <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
            <x-doc::td><x-doc::text color="slate-500">{{ $code }}</x-doc::text></x-doc::td>
            <x-doc::td>{{ $title }}</x-doc::td>
            <x-doc::td><x-doc::text color="slate-600">{{ $term }}</x-doc::text></x-doc::td>
            <x-doc::td align="right">{{ $cr }}</x-doc::td>
            <x-doc::td align="right"><x-doc::text bold>{{ $grade }}</x-doc::text></x-doc::td>
        </x-doc::tr>
    @endforeach
</x-doc::table>
```

@tab PHP

```php
$table = Table::make()->columns('0.9in 1fr 1in 0.75in 0.75in')
    ->headerRow(TableRow::make(
        Text::make('Code')->bold(),
        Text::make('Course')->bold(),
        Text::make('Term')->bold(),
        Text::make('Credits')->bold()->right(),
        Text::make('Grade')->bold()->right(),
    )->background('slate-100'));
foreach ($courses as $c) {
    $table = $table->bodyRow(TableRow::make(
        Text::make($c[0])->color('slate-500'),
        $c[1],
        Text::make($c[2])->color('slate-600'),
        Text::make((string) $c[3])->right(),
        Text::make($c[4])->right()->bold(),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}
```

:::

</div>
<figure class="showcase-result">
  <a :href="$withBase('/examples/transcript.pdf')"><img :src="$withBase('/examples/transcript-2.png')" alt="Page 2 of the transcript PDF, with the table's header row repeated at the top"></a>
  <figcaption>Page 2 of 3 · <a :href="$withBase('/examples/transcript.pdf')">PDF</a> · <a :href="$withBase('/examples/transcript.docx')" download>Word</a></figcaption>
</figure>
</div>

The 60 courses run over three pages. The header row repeats at the top of each one, and no row is cut in half at a page break.

::::: details Show the full source

:::: tabs#lang

@tab Blade

@[code blade](../../examples/laravel/views/transcript.blade.php)

@tab PHP

`$courses`, `$credits`, `$points` and `$gpa` come from your own data.

@[code{32-79} php](../../examples/core/documents/transcript.php)

::::

:::::

A Laravel route sends it to the browser as a PDF or as a Word file:

```php
$transcript = Papercut::view('transcript', compact('student', 'courses', 'credits', 'points', 'gpa'));

return $transcript->pdf()->download('transcript.pdf');
// or, with papercut/docx installed: $transcript->docx()->download('transcript.docx');
```

Without Laravel, `$document->toPdf()->save('transcript.pdf')` writes the PHP version to a file, and `->stream()` sends it to the browser. See [more examples](/examples.md).

## Built on php-pdf and php-docx

Papercut decides where everything goes on the page. Writing the file is the work of two libraries by [Denis Skripchenko](https://github.com/dskripchenko): [php-pdf](https://github.com/dskripchenko/php-pdf) writes every PDF, with its fonts, images, SVG, QR codes and encryption, and [php-docx](https://github.com/dskripchenko/php-docx) writes the Word files. Both are pure PHP and MIT licensed, and Papercut wouldn't exist without them.
