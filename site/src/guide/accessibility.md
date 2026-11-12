# Accessible PDFs

A normal PDF is a picture of a page: a screen reader sees text in a drawing order, with no headings, lists or table cells. A tagged PDF also carries a structure tree that says what each piece of content is, so assistive technology can navigate it. Papercut writes one when you ask, and identifies it as PDF/UA-1, the ISO standard for accessible PDFs.

## Turning it on

::: code-tabs#style
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Export\PdfOptions;

$document = Document::make(Page::a4()->body(
    Text::h1('Annual report'),
    Text::p('Revenue grew in every region.'),
))
    ->title('Annual report')
    ->lang('en-US');

$document->toPdf(new PdfOptions(tagged: true))->save('report.pdf');
```
@tab Laravel
```php
use Papercut\Laravel\Facades\Papercut;

// The view sets title and lang on its document tag.
return Papercut::view('pdf.report', $data)
    ->tagged()
    ->pdf()
    ->download('report.pdf');
```
:::

A tagged PDF needs a document title and a language. Without them Papercut throws `InvalidStructure`. The title is what a reader announces when the file opens, and the language tells a screen reader which pronunciation rules to use. Set them with `Document::title()` and `->lang()`, or `title` and `lang` on `<x-doc::document>`. Untagged exports don't need either.

In Laravel, `->tagged()` turns it on for one document (`->tagged(false)` turns it off). To tag every PDF, set `'tagged' => true` in `config/papercut.php`. A document can still opt out.

If the installed php-pdf is too old to write tagged PDFs, Papercut warns through `onWarning` and writes an untagged PDF instead. In [strict mode](./rich-text.md#strict-mode) it throws.

## Writing accessible content

Structure comes from how you build the document, so most of the work is choosing the right content.

**Headings.** Use real headings (`Text::h1()` to `h4()`, `<x-doc::h1>` to `h4`, or a section or card with a `heading`) rather than bold paragraphs. A tagged PDF's headings must start at level 1 and must not skip a level: an H3 straight after an H1 is an error in PDF/UA. When the look you want and the level you need differ, set the level separately and keep the look:

::: code-tabs#style
@tab Blade
```blade
<x-doc::h3 heading="2">Units</x-doc::h3>
```
@tab PHP
```php
Text::h3('Units')->asHeading(2);
```
:::

A skipped level is reported through `onWarning`, naming the heading and its page. In strict mode it throws `InvalidStructure`. The checks apply to tagged output only, and page headers and footers are ignored.

**Alternative text.** Give every image that carries information a short description of what it shows, with `alt`. `Svg` and `QrCode` take `alt` too; an SVG without one uses its `<title>`, and a QR code is described as `QR code: <value>`. See [Images, SVG, links and QR codes](./images-and-links.md).

::: code-tabs#style
@tab Blade
```blade
<x-doc::image src="img/chart.png" alt="Revenue by region, 2024 to 2026" />
```
@tab PHP
```php
Image::file('chart.png')->alt('Revenue by region, 2024 to 2026');
```
:::

**Decorative images.** An image with no alt text, or a blank one, is treated as decoration: it is kept out of the structure tree and a screen reader skips it. That is right for rules, flourishes and backgrounds, and wrong for anything the reader needs. The same goes for an SVG with neither `alt` nor `<title>`.

**Links.** Link text should make sense on its own. A screen reader can list every link in a document, and "click here" tells the reader nothing. Papercut describes each link with the text it covers, a link around an image with the image's alt text, and falls back to the address for a link with no text.

**Tables.** Put column titles in the table's header (`<x-doc::thead>`, or `headerRow()` in PHP) so they are marked as header cells. Row and column spans are written into the structure.

## What Papercut tags for you

| Content | Structure role |
|---|---|
| Headings | H1 to H6 (Papercut's own headings reach H4) |
| Paragraphs and other text | P |
| Lists | L, with LI, Lbl and LBody for each item |
| Tables | Table, with TR, TH and TD; header cells carry a scope and spans |
| Images, SVG and QR codes | Figure, with alternative text |
| Links | Link, described by their text |
| Block quotes, sections, cards and callouts, code blocks, tables of contents | BlockQuote, Sect, Div, Code, TOC with TOCI |
| Page headers, footers, backgrounds and overlays, repeated table headers | Artifacts: kept out of the reading order |

A custom [decorator](./decorators.md) keeps the roles of what it contains.

## Encryption

An [encrypted](./passwords-and-signatures.md) tagged PDF still lets screen readers extract its text, whatever permissions you set. PDF/UA requires that, and Papercut doesn't let you turn it off.

## Checking your output

[veraPDF](https://verapdf.org/) validates a file against PDF/UA-1:

```bash
verapdf --flavour ua1 report.pdf
```

It reports each failed rule. A passing file is a good start, not the finish. A validator can see that a figure has alt text, but not whether the text describes the figure, and it can't tell whether the reading order makes sense. Open the PDF with a screen reader (NVDA, VoiceOver) and with Acrobat's accessibility checker before you rely on it.

## Limits

- A paragraph or heading that runs over a page break becomes one structure element on each page, not one element across both.
- Papercut can't judge alt text or reading order. Content is read in the order it appears in the document, so put it there in the order a reader should hear it.
- Only PDF export is tagged. HTML exports write `alt` on images, and Word exports write it as the picture's description. A decorative image in Word gets the description "image", because the library that writes Word files can't mark one as decorative.
