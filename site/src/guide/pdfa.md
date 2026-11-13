# Archival PDFs (PDF/A)

PDF/A is the ISO 19005 family of standards for PDFs meant to be kept for decades. A PDF/A file is self-contained: every font is embedded, colours are declared in a colour profile, and nothing depends on external content or on features a future viewer might drop. Archives, courts, tax authorities and public bodies often require it. Papercut writes one when you ask.

## Turning it on

::: code-tabs#style
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Export\{PdfA, PdfOptions};

$document = Document::make(Page::a4()->body(
    Text::h1('Contract 2026-114'),
    Text::p('Signed by both parties.'),
))
    ->title('Contract 2026-114')
    ->lang('en-GB');

$document->toPdf(new PdfOptions(pdfA: PdfA::A2u))->save('contract.pdf');
```
@tab Laravel
```php
use Papercut\Export\PdfA;
use Papercut\Laravel\Facades\Papercut;

return Papercut::view('pdf.contract', $data)
    ->pdfA(PdfA::A2u)   // or ->pdfA('2u')
    ->pdf()
    ->download('contract.pdf');
```
:::

`PdfOptions::withPdfA()` copies options with a level set (or `null` to go back to a regular PDF), and `pdfA()` reads it back.

In Laravel, `->pdfA()` turns it on for one document. To write every PDF as PDF/A, set `'pdfa' => '2u'` in `config/papercut.php`. A document can still opt out with `->pdfA(null)`, which overrides the config. The config accepts `null` or one of `'1b'`, `'2b'`, `'2u'`, `'2a'`, `'3b'`, `'3u'` and `'3a'`; anything else throws. See [Configuration](/laravel/configuration.md).

Papercut embeds an sRGB colour profile (sRGB2014) as the file's output intent, so you don't supply one. A document title and author are written to the file's XMP metadata as well as to its info dictionary, as the standard requires.

## Which level to pick

A level is a part (1, 2 or 3) and a conformance letter (b, u or a).

| Level | Pick it when |
|---|---|
| `PdfA::A2u` | The default choice. PDF/A-2 allows transparency, compression and everything Papercut draws. The "u" means all text maps to Unicode, so it can be searched and copied. |
| `PdfA::A2a` | The archive wants accessible files. It writes a [tagged PDF](./accessibility.md) too. |
| `PdfA::A2b` | The archive asks for "basic" conformance. "b" only promises that the page looks the same; Papercut's "u" files meet it too, so 2u is rarely a worse choice. |
| `PdfA::A1b` | Only when an archive demands PDF/A-1. It is the oldest part and refuses transparency (below). |
| `PdfA::A3b`, `A3u`, `A3a` | Only when an archive demands part 3. Part 3 differs from part 2 by allowing attached files, but Papercut attaches none, so these are the same as part 2 apart from the label. |

Levels ending in "a" turn tagging on. They follow the rules of a tagged PDF: a document title and a language, or Papercut throws `InvalidStructure`. They also identify the file as PDF/UA-1. You can get PDF/A-2u plus PDF/UA-1 without the "a" level by setting both options: `new PdfOptions(pdfA: PdfA::A2u, tagged: true)`. See [Accessible PDFs](./accessibility.md) for what Papercut tags and what it can't judge.

Levels "b" and "u" don't need a language unless `tagged` is on. If the document has none, Papercut writes an empty language entry rather than claiming one. Set `->lang()` anyway when you know it.

## Rules

- **No encryption.** PDF/A forbids it. Combining `pdfA` with `encryption` throws `InvalidArgumentException` before anything is rendered.
- **No transparency in PDF/A-1.** With `PdfA::A1b`, Papercut throws `InvalidStructure` for an element that is partly transparent, naming it where it can: an opacity below 1, a colour with an alpha value, an image with an alpha channel, and SVG `opacity`, `fill-opacity` or `stroke-opacity`. Remove the transparency or use `PdfA::A2b` or later. A PNG saved with an alpha channel counts even when every pixel is opaque, because the channel is kept; save it again without alpha. Parts 2 and 3 have no such limit.
- **Imported PDF pages must comply themselves.** A [page template](./pages.md#filling-in-an-existing-pdf) or `PdfPage` is copied into the file as it is. Papercut doesn't inspect it, so a template with unembedded fonts or transparency (in part 1) makes the result fail validation.
- **Signing keeps conformance, with two catches.** php-pdf's signer appends an incremental update to the rendered file, so [signing](./passwords-and-signatures.md#digital-signatures) it leaves a PDF/A file PDF/A. A visible signature stamp needs an embedded font under PDF/A, and php-pdf throws a `LogicException` unless you set `SignatureAppearance::$font`. Anything that rewrites the file afterwards, such as filling form fields without `incremental: true`, breaks both conformance and the signature.
- **Merging breaks conformance.** Combining PDFs, as in [Merging and stamping](./passwords-and-signatures.md#merging-and-stamping), writes a new file whose metadata and structure the merger decides. Validate the result. To include another PDF's pages in a PDF/A file, use a [page template](./pages.md#filling-in-an-existing-pdf) or `PdfPage`; those pages must comply themselves.
- **PDF export only.** Word and HTML exports ignore the option.

## Checking your output

[veraPDF](https://verapdf.org/) validates a file against the level you asked for:

```bash
verapdf --flavour 2u contract.pdf
```

The flavour is the level (`1b`, `2b`, `2u`, `2a`, `3b`, `3u` or `3a`). For a file that is also PDF/UA, add `--flavour ua1` in a second run, as in [Accessible PDFs](./accessibility.md#checking-your-output).

Papercut's own examples are validated this way in CI, as PDF/A-2u and as PDF/A-2a with PDF/UA-1. A validator checks the file's structure. It can't judge content: whether the fonts are the ones you meant, whether the text is legible, or whether the file is what your archive will accept. Check your archive's requirements for the level and any extra rules it adds.

## Limits

- ZUGFeRD and Factur-X (invoices with an attached XML file) aren't supported. Papercut attaches no files, so part 3 gives you nothing beyond the label.
- Pages imported from other PDFs aren't checked, as above.
- Tagged PDF/A-1 files are written without object streams, which part 1 doesn't allow. That makes them somewhat larger.
