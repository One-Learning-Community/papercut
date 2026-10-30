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

<div class="showcase">
<div class="showcase-code">

::: tabs#lang

@tab Blade

@[code blade](../../examples/laravel/views/transcript.blade.php)

@tab PHP

`$courses`, `$credits`, `$points` and `$gpa` come from your own data.

@[code{32-76} php](../../examples/core/documents/transcript.php)

:::

</div>
<figure class="showcase-result">
  <a :href="$withBase('/examples/transcript.pdf')"><img :src="$withBase('/examples/transcript-1.png')" alt="Page 1 of the transcript PDF"></a>
  <figcaption>Page 1 of 3 · <a :href="$withBase('/examples/transcript.pdf')">PDF</a> · <a :href="$withBase('/examples/transcript.docx')" download>Word</a></figcaption>
</figure>
</div>

The 60 courses run over three pages. The header row repeats at the top of each one, and no row is cut in half at a page break.

A Laravel route sends it to the browser as a PDF or as a Word file:

```php
$transcript = Papercut::view('transcript', compact('student', 'courses', 'credits', 'points', 'gpa'));

return $transcript->pdf()->download('transcript.pdf');
// or, with papercut/docx installed: $transcript->docx()->download('transcript.docx');
```

Without Laravel, `$document->toPdf()->save('transcript.pdf')` writes the PHP version to a file, and `->stream()` sends it to the browser. See [more examples](/examples.md).

## Built on php-pdf and php-docx

Papercut decides where everything goes on the page. Writing the file is the work of two libraries by [Denis Skripchenko](https://github.com/dskripchenko): [php-pdf](https://github.com/dskripchenko/php-pdf) writes every PDF, with its fonts, images, SVG, QR codes and encryption, and [php-docx](https://github.com/dskripchenko/php-docx) writes the Word files. Both are pure PHP and MIT licensed, and Papercut wouldn't exist without them.
