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

Browsers don't have pages, so a few things are approximated: fraction column widths, the page itself, and headers and footers, which appear once per page. Each approximation is reported as a warning. Nothing is ever dropped, so [strict mode](./rich-text.md#strict-mode) won't fail an HTML export.

The HTML uses meaningful tags where it can: sections become `<section>`, callouts `<aside>`, blockquotes `<blockquote>`, lists `<ol>` or `<ul>`, and tables `<table>`.

## Fonts

A PDF carries its fonts inside the file, but a browser has to load them from somewhere. By default the HTML only names the fonts and the browser uses whatever it has. To embed your fonts, link to files you host, or load them from Google Fonts, see [Fonts in HTML](./web-fonts.md).

## Image URLs

Images are embedded as `data:` URIs by default. To load them from your own URLs instead, pass an `imageUrl` closure in PHP, or set `html.image_url` to a resolver class in Laravel. Return `null` for any image you'd still like embedded.
