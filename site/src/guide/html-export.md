# HTML export

Any document can also be exported as HTML, using the same theme, fonts and named styles. That's handy for a preview in the browser, an email body, or showing a document inside another page.

::: code-tabs#style
@tab Laravel
```php
$invoice = Papercut::view('pdf.invoice', compact('invoice'));

return $invoice->pdf()->download('invoice.pdf');   // PDF
return $invoice->html();                           // a full HTML page
```

```blade
{{-- the document as a fragment inside another view --}}
{!! $invoice->html() !!}
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use Papercut\Export\HtmlOptions;

$document = Document::make(Page::a4()->body(Text::make('Hello')));

$page     = $document->toHtml(new HtmlOptions(fullPage: true))->bytes();
$fragment = $document->toHtml()->bytes();   // one .papercut element, no <html>
```
:::

In Laravel, `->html()->page()` and `->html()->fragment()` both return a `PendingOutput`, with the same `toString()`, `save()`, `download()` and `inline()` methods as a PDF. If you return `->html()` from a route you get the full page, and if you echo it in a view you get the fragment.

## What's different in HTML

Browsers don't have pages, so a few things are approximated: fraction column widths, the page itself, and headers and footers, which appear once per page. Each approximation is reported as a warning.

- Even-page headers and footers aren't shown. HTML has one header and one footer for the whole run.
- A first-page footer is shown only when the page has no normal footer. If it has both, the normal footer is shown.
- Backgrounds and overlays cover the whole page section, which is at least one page tall and grows to fit its content. They're drawn once, not on every printed page, and overlay content is placed against that taller box, so it lands about where it does in the PDF. Both are reported as approximated.

A few things are dropped, and [strict mode](./rich-text.md#strict-mode) fails an HTML export on them: PDF page templates and embedded PDF pages. Code highlighting that can't be done is dropped in every format, as [Code blocks](./text.md#code-blocks) explains. Without strict mode these are reported as warnings.

A [table of contents](./pages.md#tables-of-contents) becomes a `<nav class="papercut-toc">` with nested ordered lists of links to the headings. Headings that are entries get an `id` (a slug of their text, with `-2` added on a clash). It has no page numbers or leaders, and each table of contents is reported once as approximated.

To pick the HTML element a view exports as, call `htmlTag()` (`div`, `section`, `aside`, `blockquote`, `header`, `footer` or `figure`). PDF ignores it.

The HTML uses meaningful tags where it can: sections become `<section>`, callouts `<aside>`, blockquotes `<blockquote>`, lists `<ol>` or `<ul>`, and tables `<table>`.

## Columns

A [column flow](./layout.md#column-flow) exports as an element with CSS columns (`column-count`, `column-gap`, `column-rule`), and a `ColumnBreak` as a column break. The browser fills the columns itself, so they won't match the PDF line for line, and a child that starts a new column can show an extra gap above it, because CSS can't know where a column starts. Unequal column widths and unequal gaps are approximated as equal columns and equal gaps, reported as approximated.

## Fonts

A PDF carries its fonts inside the file, but a browser has to load them from somewhere. By default the HTML only names the fonts and the browser uses whatever it has. To embed your fonts, link to files you host, or load them from Google Fonts, see [Fonts in HTML](./web-fonts.md).

## Image URLs

Images are embedded as `data:` URIs by default. To load them from your own URLs instead, pass an `imageUrl` closure in PHP, or set `html.image_url` to a resolver class in Laravel. Return `null` for any image you'd still like embedded. SVGs are always embedded as `data:` URIs, so `imageUrl` doesn't apply to them.
