# Word export

Any document can also be exported as a Word file (`.docx`) that people can open and edit in Word, LibreOffice or Google Docs. It uses the same theme, fonts and named styles as the PDF.

Word export lives in its own package, so apps that only make PDFs don't install it:

```
composer require papercut/docx
```

::: code-tabs#style
@tab Laravel
```php
$invoice = Papercut::view('pdf.invoice', compact('invoice'));

return $invoice->pdf()->download('invoice.pdf');    // PDF
return $invoice->docx()->download('invoice.docx');  // Word
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Docx\DocxExporter;

$document = Document::make(Page::a4()->body(Text::make('Hello')));

$document->export(new DocxExporter())->save('hello.docx');
```
:::

In Laravel, `->docx()` returns a `PendingOutput`, with the same `toString()`, `save()`, `store()`, `download()` and `inline()` methods as a PDF. Without `papercut/docx` installed, the export throws `MissingExporter` with the command to run. Preview routes show a Word file with `?format=docx`.

## What readers see

Word, LibreOffice and Google Docs lay out the pages themselves, so a page break can land a line or two away from where it is in the PDF.

Papercut writes the layout in a form all three understand. Columns flow as paragraphs. Rows become tables with no borders, at the widths the PDF uses. A box with padding, a border or a background becomes a table with one cell. Lists, tables, links, headings, page numbers and pagination rules such as keep-together carry over.

Some things are approximated or left out. The ones in the table below are reported as a warning with the node's path, and [strict mode](./rich-text.md#strict-mode) fails on anything left out. The way the layout itself is written (rows as tables, boxes as one-cell tables, header and footer distances, fonts by name, colours with transparency blended onto white) applies to every document, so it isn't reported each time.

| In the PDF | In Word |
|---|---|
| A different page size, header or footer on a later page | ≈ the first page's setup applies to every page |
| Font weights other than regular and bold | ≈ bold from 600, regular below |
| Text backgrounds | ≈ the nearest of Word's highlight colours |
| Fixed heights, flexible spacers in a column, wrapping rows | ≈ |
| Image `cover` | ≈ drawn as `contain` |
| `min-lines` above 2 | ≈ Word keeps 2 lines together |
| Table cell `justify` other than start, center or end | ≈ top |
| WebP, AVIF and other images Word can't open (PNG, JPEG, GIF and BMP work) | ✗ left out |
| A block link around several paragraphs | ≈ a link on each paragraph's text |
| Corner radius, opacity | ✗ left out |
| Background and overlay layers, PDF page templates, embedded PDF pages | ✗ left out |
| QR codes, SVG | ✗ left out |
