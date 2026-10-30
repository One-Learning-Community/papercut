# Word export <Badge type="warning" text="experimental" />

::: warning Experimental
Word export is new. Word, LibreOffice and Google Docs lay out the file themselves, so spacing and page breaks can differ slightly from the PDF, and the output may change between minor versions while it settles. Check exported files in the readers your users have, and [report what looks wrong](https://github.com/One-Learning-Community/papercut/issues).
:::

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

$document = Document::make(Page::letter()->body(Text::make('Hello')));

$document->export(new DocxExporter())->save('hello.docx');
```
:::

In Laravel, `->docx()` returns a `PendingOutput`, with the same `toString()`, `save()`, `store()`, `download()` and `inline()` methods as a PDF. Without `papercut/docx` installed, the export throws `MissingExporter` with the command to run. Preview routes show a Word file with `?format=docx`, or a 404 with the install command when the package is missing.

## What readers see

Word, LibreOffice and Google Docs lay out the pages themselves, so a page break can land a line or two away from where it is in the PDF.

Papercut writes the layout in a form all three understand. Columns flow as paragraphs. A row of short inline items (text, links, badges) that the PDF sets on one line becomes one paragraph; any other row becomes a table with no borders, at the widths the PDF uses. A box with padding, a border or a background becomes a table with one cell. Lists are real Word numbering. Tables, links, headings, page numbers and pagination rules such as keep-together carry over. Each run of pages with the same size, margins, header, footer and layers becomes a Word section of its own, so a landscape page or a page without the header comes out as it does in the PDF.

Headers and footers carry over, including first-page and even-page ones (Word's "Different First Page" and "Different Odd & Even Pages") and mirrored margins. A kept-together table, or a kept-together box that Word draws as a table, stays on one page. A heading gets keep-with-next, so it isn't left alone at the bottom of a page. Code blocks come out as shaded paragraphs or tables in the mono font; the colours need `phiki/phiki`. A table too wide for its box gets the same shrunk column widths as the PDF, and the export reports each such table at its own path.

Some things are approximated or left out. The ones in the table below are reported as a warning with the node's path, and [strict mode](./rich-text.md#strict-mode) fails on anything left out. The way the layout itself is written (rows as paragraphs or tables, boxes as one-cell tables, header and footer distances, fonts by name, colours with transparency blended onto white) applies to every document, so it isn't reported each time.

Opacity carries over: Word has none, so each colour of a faded node is blended onto what lies beneath it (the fill around it, else white). Text backgrounds keep their exact colour.

| In the PDF | In Word |
|---|---|
| Font weights other than regular and bold | ≈ bold from 600, regular below |
| Fixed heights, flexible spacers in a column, wrapping rows | ≈ |
| `min-lines` above 2 | ≈ Word keeps 2 lines together |
| Table cell `justify` other than start, center or end | ≈ top |
| A block link around several paragraphs | ≈ a link on each paragraph's text |
| Corner radius on a box | ✗ left out |
| Corner radius on a badge in a line of text | ≈ square corners |
| List numbers below 1, or roman numerals above 3999 | ≈ typed as text, which Word does not renumber |
| A list item whose number is not the previous one plus one (`withValue()`, `<li value>`) | ≈ starts a new Word list at that number |
| An image, QR code or SVG in a background (before anything else in it) or alone in an overlay place | ≈ a floating picture at the same place on every page; Word draws it with the header, so an overlay sits behind the body's text |
| Anything else in a background or overlay layer, a border or background on a layer picture, PDF page templates, embedded PDF pages | ✗ left out |
| Styled QR codes without Imagick | ≈ plain squares |
| SVG text | ≈ in the system's fonts |
| Pictures without the PHP extension they need (below) | ✗ left out |

### Fonts in Word

Word has no way to embed Papercut's fonts, so a reader sees the font by name. Papercut writes each font's PANOSE number, family class and pitch in the document's font table, which lets Word pick a close stand-in when the font is not installed: a sans-serif for Inter, a monospace for Cousine.

To have Word use a font every machine has, set the name Word should see. The PDF keeps using the font file:

```php
$fonts = (new FontSet())->withWordName('Inter', 'Arial');

$document->export(new DocxExporter(), new ExportOptions(fonts: $fonts))->save('hello.docx');
```

In Laravel, set the names in `config/papercut.php`:

```php
'font_word_names' => ['Inter' => 'Arial'],
```

The bundled Cousine is written as Courier New without any setting. These pairs are metric-compatible, so lines break in the same places whichever one a reader has:

| Papercut font | Word name |
|---|---|
| Arimo | Arial |
| Tinos | Times New Roman |
| Cousine | Courier New |
| Carlito | Calibri |
| Caladea | Cambria |

A family the font set does not know is written as it is, with an unspecified family class.

### Pictures

Word shows PNG, JPEG, GIF and BMP as they are. Everything else is drawn into a picture at the size the PDF gives it, so it looks the same in Word, LibreOffice and Google Docs:

| In the PDF | In Word | Needs |
|---|---|---|
| WebP and AVIF images | converted to PNG | `ext-gd` |
| Image `cover` | cropped to the box from the middle | `ext-gd` (else ≈ `contain`) |
| Opacity on an image, QR code or SVG | faded in the picture | `ext-gd` |
| QR codes | sharp squares at 300 dpi | `ext-gd` |
| Styled QR codes (shapes, eyes, colours, gradients) | drawn as in the PDF at 300 dpi | `ext-imagick` and `bacon/bacon-qr-code` |
| SVG | drawn at 300 dpi; nothing outside the file is loaded | `ext-imagick` that reads SVG |

Imagick is only needed for Word export; PDF and HTML don't use it.
