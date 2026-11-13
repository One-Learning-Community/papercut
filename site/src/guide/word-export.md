# Word export

::: tip Word lays out its own pages
Word, LibreOffice and Google Docs lay out the file themselves, so spacing and page breaks can differ slightly from the PDF. Check exported files in the readers your users have, and [report what looks wrong](https://github.com/One-Learning-Community/papercut/issues).
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
| Table cell `height` or `min-height` | the row's minimum height, so Word grows the row if its text needs more room; a fixed `height` is reported as ≈ |
| `height` or `min-height` on a cell that spans rows | ≈ shared evenly among its rows |
| A block link around several paragraphs | ≈ a link on each paragraph's text |
| Corner radius on a box | ✗ left out |
| Corner radius on a badge in a line of text | ≈ square corners |
| List numbers below 1, or roman numerals above 3999 | ≈ typed as text, which Word does not renumber |
| A list item whose number is not the previous one plus one (`withValue()`, `<li value>`) | ≈ starts a new Word list at that number |
| An image, QR code or SVG in a background (before anything else in it) or alone in an overlay place | ≈ a floating picture at the same place on every page; Word draws it with the header, so an overlay sits behind the body's text |
| Anything else in a background or overlay layer, a border or background on a layer picture, PDF page templates, embedded PDF pages | ✗ left out |
| Styled QR codes without Imagick | ≈ plain squares |
| SVG text | ≈ in the system's fonts |
| Equations | in Cambria Math, whatever the PDF's math font; LibreOffice ignores their colour and size ([below](#equations-in-word)) |
| Pictures without the PHP extension they need (below) | ✗ left out |

### Columns

A [column flow](./layout.md#column-flow) that sits on the page, with only plain wrappers (boxes with no border, background, padding, width, height or alignment) around it, becomes a section of its own that starts on the same page, with real Word columns: the count, the widths, the gaps and the rule. They stay editable in Word. Full-width content before and after it sits in one-column sections of its own. A `ColumnBreak` becomes a Word column break. Content in a flow is sized to the column, as it is in the PDF.

Word levels columns itself before a section break, so the columns end level in Word as in the PDF. The rule in Word is Word's plain line: the width and colour the PDF uses don't carry over.

A flow inside a box (a bordered or padded view, a card) comes out as one column, since Word has no columns inside boxes. It's reported as approximated, with the flow's path.

### Table of contents

A [table of contents](./pages.md#tables-of-contents) becomes a real Word table of contents field. Papercut fills it in with the entries and the page numbers from the PDF layout, so readers see them as soon as the file opens. Word paginates a little differently from the PDF, so a number can be a page off. Right-click the table and choose Update Field for exact numbers.

An updated field rebuilds itself from Word's Heading 1 to Heading 4 styles, so two things aren't honoured after an update: `toc(false)` and markup's `toc` option (the headings come back), and a level set with `bookmark($title, $level)` (the heading's own level is used).

To get those page numbers, Word export lays out the pages, as for a PDF, for a document that has a table of contents, and only for those. That costs about as much as a PDF export. If the page layout can't run, for instance because a font isn't registered, the export still succeeds and the field is written without page numbers. Right-click the table and choose Update Field in Word to fill them in.

When speed matters more than numbers on first open, turn the layout off. The entries are still listed and linked, without page numbers until the reader updates the field:

::: code-tabs#style
@tab Laravel
```php
// config/papercut.php
'docx' => [
    'toc_page_numbers' => false,
],
```
@tab PHP
```php
use Papercut\Docx\{DocxExporter, DocxOptions};

$document->export(new DocxExporter(), new DocxOptions(tocPageNumbers: false))->save('report.docx');
```
:::

`DocxOptions` takes the same `theme`, `fonts`, `onWarning` and `strict` as `ExportOptions`. Its `rsvgConvert` draws SVG with `rsvg-convert` ([below](#svg-in-word)).

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

### Equations in Word

[Equations](./equations.md) are written as live Word equations (OMML), always in Cambria Math, whatever math font the PDF uses. A block equation is a paragraph of its own with an explicit alignment. An inline equation sits among the runs of its line. They stay editable in Word's equation editor.

How other editors treat them differs:

- Word and Google Docs open and show them.
- LibreOffice shows them but ignores the colour and size set on math runs.
- Apple Pages can't open a Word file that has equations. It fails to load, so send such documents as PDF to Pages users.

### Pictures

Word shows PNG, JPEG, GIF and BMP as they are. Everything else is drawn into a picture at the size the PDF gives it, so it looks the same in Word, LibreOffice and Google Docs:

| In the PDF | In Word | Needs |
|---|---|---|
| WebP and AVIF images | converted to PNG | `ext-gd` |
| Image `cover` | cropped to the box from the middle | `ext-gd` (else ≈ `contain`) |
| Opacity on an image, QR code or SVG | faded in the picture | `ext-gd` |
| QR codes | sharp squares at 300 dpi | `ext-gd` |
| Styled QR codes (shapes, eyes, colours, gradients) | drawn as in the PDF at 300 dpi | `ext-imagick` and `bacon/bacon-qr-code` |
| SVG | drawn at 300 dpi; nothing outside the file is loaded | `rsvg-convert` (recommended) or `ext-imagick` that reads SVG ([below](#svg-in-word)) |

Imagick is only needed for Word export, for styled QR codes and for SVG when you don't use `rsvg-convert`. PDF and HTML don't use it.

#### SVG in Word

<a id="svg-with-rsvg-convert"></a>

Word can't take an SVG as it is, so Papercut draws each one into a picture. Two programs can do that, and without either, SVGs are left out of the Word file and reported:

- **librsvg's `rsvg-convert`** (recommended). It's a small command-line program, not a PHP extension, so you don't need Imagick at all. It draws SVG properly: gradients, patterns, clip paths, masks, filters and dashed strokes.
- **Imagick**, when its ImageMagick build reads SVG. How well it draws them depends on how ImageMagick was built. Many builds use ImageMagick's own basic renderer, which paints gradients and patterns solid black and leaves out clip paths and dashed strokes.

To use `rsvg-convert`, install it and give Papercut its path:

```bash
apt install librsvg2-bin    # Debian, Ubuntu
apk add rsvg-convert        # Alpine
brew install librsvg        # macOS
which rsvg-convert          # the path to give Papercut
```

::: code-tabs#style
@tab Laravel
```ini
# .env
PAPERCUT_RSVG_CONVERT=/usr/bin/rsvg-convert
```
@tab PHP
```php
use Papercut\Docx\{DocxExporter, DocxOptions};

$document->export(new DocxExporter(), new DocxOptions(rsvgConvert: '/usr/bin/rsvg-convert'))->save('report.docx');
```
:::

In Laravel, the config key is `docx.rsvg_convert`, which reads `PAPERCUT_RSVG_CONVERT` by default. Leave it unset or empty to use Imagick.

A few things to know:

- Papercut runs `rsvg-convert` through `proc_open()`, so that function must be enabled.
- The path must be absolute. The export throws when it isn't an executable file, or when `rsvg-convert --version` doesn't answer as rsvg-convert, so a wrong setting shows up on the first export.
- Each SVG is cleaned first, as it is for Imagick: images, links to files and outside `url()`s are taken out. The rest is passed on standard input, so nothing outside the file can be loaded.
- If `rsvg-convert` fails on an SVG, or takes longer than 30 seconds, Imagick draws that one instead, when it can.
- `rsvg-convert` draws some things the PDF doesn't draw yet ([what's drawn](./images-and-links.md#what-s-drawn)), such as clip paths, patterns and filters, so the Word picture can show more than the PDF.
- Text in an SVG is set in the server's system fonts, not your document's fonts, with either program.
- On Windows, PHP can't read the program's output as it comes, so a large picture can wait out the 30 seconds and fall back to Imagick.
