# papercut/laravel

Blade components for [Papercut](../../README.md): `<x-doc::*>` tags compile into Papercut's layout engine and out to a PDF. Not HTML-to-PDF.

> **Status: v0.1, pre-release. Not yet published to Packagist.**

## Install

Once published:

```
composer require papercut/laravel
```

To try it now, clone the monorepo and add path repositories to your app's `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "../php-pdf/packages/core" },
    { "type": "path", "url": "../php-pdf/packages/laravel" }
  ],
  "minimum-stability": "dev"
}
```

then `composer require papercut/laravel:@dev`. Requires PHP 8.4+, `ext-zlib`, `ext-mbstring`, `ext-dom` and Laravel 12 or 13. The service provider and the `Papercut` facade are auto-discovered.

## Quick start

```blade
{{-- resources/views/pdf/hello.blade.php --}}
<x-doc::document title="Hello">
  <x-doc::page>
    <x-doc::h1>Hello, {{ $name }}</x-doc::h1>
    <x-doc::p>Rendered by Papercut.</x-doc::p>
  </x-doc::page>
</x-doc::document>
```

```php
use Papercut\Laravel\Facades\Papercut;

Route::get('/hello', fn () => Papercut::view('pdf.hello', ['name' => 'Ada'])->pdf()->download('hello.pdf'));
```

A view must have exactly one `<x-doc::document>` root. `Papercut::view($view, $data)` returns a `PendingDocument`; `->document()` gives the core `Document` and `->pdf()` a `PendingOutput` with `download($filename)`, `inline($filename)`, `save($path)`, `toString()` and `output()`. The filename defaults to `document.pdf`. A `PendingOutput` returned from a route renders inline. `Papercut::make($document)` wraps a `Document` you built in PHP the same way.

Full examples: [`examples/laravel`](../../examples/laravel) (invoice, transcript, report). Content inside `<x-doc::document>` may also contain your own Blade components, which can themselves contain `<x-doc::*>` tags.

## Configuration

```
php artisan vendor:publish --tag=papercut-config
```

This publishes `config/papercut.php`:

| Key | Default | Meaning |
|---|---|---|
| `prefix` | `'doc'` | Blade tag prefix (`<x-doc::page>`): lowercase letters, digits and dashes. Read at boot |
| `paper` | `'a4'` | Page size when `<x-doc::page>` has no `size`: `a0`–`a6`, `b4`, `b5`, `letter`, `legal`, `tabloid`, `executive`, or a width and height such as `'8.5in 11in'` |
| `margin` | `'20mm'` | Page margin when `<x-doc::page>` has no `margin` |
| `fonts` | `[]` | Extra font families, see [Fonts](#fonts) |
| `fallback_fonts` | `[]` | Family names tried, in order, for glyphs the primary font lacks |
| `theme` | `[]` | Defaults: `font_family`, `font_size` (points, number), `color`, `line_height` (number) |
| `styles` | `[]` | [Named styles](#named-styles) |
| `overflow` | `null` | `'throw'`, `'clip'`, or `null` = throw in `local`/`testing`, clip elsewhere |
| `strict` | `false` | `true` makes an export throw `UnsupportedFeature` for content a format cannot render, instead of dropping it with a warning. Consider `true` in tests |
| `html.fonts` | `'none'` | Fonts in HTML output: `'none'` (a `system-ui` fallback stack, small files), `'embed'` (base64 `@font-face`, large files), `'link'` (`@font-face` with URLs from `html.font_url`; requires it) or `'sources'` (see [Font sources](#font-sources)) |
| `html.image_url` | `null` | Class name implementing `Papercut\Laravel\Html\ImageUrlResolver`; `null` embeds images as data URIs (see [HTML URLs](#html-urls)) |
| `html.font_url` | `null` | Class name implementing `Papercut\Laravel\Html\FontUrlResolver`; required when `html.fonts` is `'link'` |
| `html.font_sources` | `[GoogleFonts::class]` | Used when `html.fonts` is `'sources'`: an ordered list of class names implementing `Papercut\Html\FontSource` |
| `html.google_fonts.host` | `'https://fonts.googleapis.com'` | Host of the `GoogleFonts` source; `'https://fonts.bunny.net'` for Bunny Fonts |
| `html.google_fonts.display` | `'swap'` | `font-display`: `auto`, `block`, `swap`, `fallback` or `optional` |
| `html.google_fonts.families` | `null` | `null` = every family, or a list of family names (allowlist) |
| `html.local_fonts.mode` | `'embed'` | `LocalFonts` source: `'embed'` (base64) or `'link'` (URLs from `html.font_url`; requires it) |
| `html.local_fonts.families` | `null` | `null` = every registered family, or a list of family names |
| `markup.images` | `null` | Class name implementing `Papercut\Laravel\Markup\ImageResolver`; `null` treats every `<img>` in markup as missing (see [Rich text](#rich-text-from-your-database)) |
| `markup.styles` | `'theme'` | `'theme'` (HTML gives structure, the theme styles it) or `'original'` (inline `style=""` applied on top) |
| `markup.blank_paragraphs` | `'drop'` | `'drop'` or `'keep'` paragraphs that hold only `&nbsp;` or `<br>` |
| `image_path` | `null` | Base directory for relative `<x-doc::image src>`; `null` = `public_path()` |
| `cache_dir` | `null` | Font kerning cache directory: `null` = `sys_get_temp_dir()/papercut-cache-<uid>`, `false` = no disk cache, or a path (see core's "Kerning cache") |
| `preview.enabled` | `env('PAPERCUT_PREVIEW', false)` | Enable [preview routes](#preview-routes) |
| `preview.path` | `'_papercut'` | URL prefix for preview routes |

## HTML output

The same document exports to HTML. `->html()` shares the theme, fonts, named styles and strict setting with `->pdf()`.

```php
// A route: a full HTML page (Content-Type text/html; charset=UTF-8)
return Papercut::view('pdf.invoice', $data)->html();

// Download or save
Papercut::view('pdf.invoice', $data)->html()->page()->download('invoice.html');
Papercut::view('pdf.invoice', $data)->html()->page()->save(storage_path('invoice.html'));

// The embeddable fragment as a string or file
$fragment = Papercut::view('pdf.invoice', $data)->html()->fragment()->toString();
```

Inside another Blade view, `{{ $doc->html() }}`, `{!! $doc->html() !!}` and `(string) $doc->html()` all give the embeddable fragment (one `.papercut` element, no `<html>`), where `$doc` is `Papercut::view(...)` or `Papercut::make($document)`. Returning `->html()` from a route sends the full page. `PendingHtml` has no `toString()`, `save()` or `download()` of its own, so a string never means two things: `->html()->page()` and `->html()->fragment()` each return a `PendingOutput` (`toString()`, `save()`, `download()`, `inline()`, default name `document.html`). HTML reports approximations through the same warning log as PDF. Every approximation is reported (for example fr column widths, the page box, and header and footer once per page), and HTML reports nothing as dropped, so `strict` does not throw for it.

### HTML URLs

`html.image_url` and `html.font_url` name invokable classes, built by the container (constructor dependencies work). Use class names, not closures: a class name survives `php artisan config:cache`, and a closure in config is rejected with an error naming the key. Return `null` to embed an image as a data URI, or to skip a font face.

```php
namespace App\Pdf;

use Papercut\Image;
use Papercut\Laravel\Html\ImageUrlResolver;

final class CdnImageUrl implements ImageUrlResolver
{
    public function __invoke(Image $image): ?string
    {
        ['kind' => $kind, 'value' => $value] = $image->source();

        return $kind === 'file' ? asset('img/' . basename($value)) : null; // null = embed
    }
}

// config/papercut.php
'html' => ['fonts' => 'link', 'image_url' => \App\Pdf\CdnImageUrl::class, 'font_url' => \App\Pdf\CdnFontUrl::class],
```

Invalid `html.*` config is reported at the first `->html()` export, not at boot.

### Font sources

`html.fonts = 'sources'` sends each font family to the first source in `html.font_sources` that accepts it. It works with no other setting: the default list is Google Fonts, so each family becomes a `<link>` to `fonts.googleapis.com`. For how sources, answers and fallbacks work, see "Fonts in HTML" in the [core README](../core/README.md).

```php
// Bunny Fonts instead of Google: a host change
'html' => ['fonts' => 'sources', 'google_fonts' => ['host' => 'https://fonts.bunny.net']],

// Fonts registered in papercut.fonts embedded; anything else from Google
'html' => [
    'fonts' => 'sources',
    'font_sources' => [\Papercut\Html\LocalFonts::class, \Papercut\Html\GoogleFonts::class],
    'local_fonts' => ['families' => ['Acme']], // null = every registered family
],
```

A custom source is a class implementing `Papercut\Html\FontSource`, built by the container, so constructor dependencies work:

```php
use Papercut\Html\{FontAnswer, FontRequest, FontSource};

final class BrandFonts implements FontSource
{
    public function __invoke(FontRequest $request): ?FontAnswer
    {
        return $request->family === 'Acme' ? FontAnswer::stylesheet('https://brand.example/fonts.css') : null; // null = ask the next source
    }
}

'font_sources' => [\App\Pdf\BrandFonts::class, \Papercut\Html\GoogleFonts::class],
```

- **Order.** The first source that answers wins, so list specific sources first and catch-all hosts such as Google last.
- **Privacy.** With `GoogleFonts` the visitor's browser fetches the stylesheet and fonts from the host, and so sends it their IP address. A German court held this can breach data protection law without consent (LG München I, 3 O 17493/20). Bunny Fonts is a host that states it does not log IP addresses, but the request still reaches a third party and this is not a compliance guarantee. The fully private routes are `LocalFonts` (embed, or link to your own files) and the `'embed'` and `'link'` modes.
- **CSP.** If your site sends a Content-Security-Policy, allow: `style-src` for the stylesheet host (`fonts.googleapis.com` for Google, `fonts.bunny.net` for Bunny); `font-src` for the font domain (`fonts.gstatic.com` for Google, `fonts.bunny.net` for Bunny); and `font-src data:` for embedded faces (`local_fonts` in its default `embed` mode).
- **Config is checked lazily.** `font_sources` is read only when `html.fonts` is `'sources'`, and `google_fonts` and `local_fonts` only when their class is in the list, at the first `->html()` export. Class names only: closures are rejected naming the key and index, for example `papercut.html.font_sources.1`.

Preview routes accept `?format=pdf` (default) or `?format=html`; any other value is a 404.

## Tags

Style attributes (next section) work on every tag marked "style" below. Values are plain strings (`gap="8pt"`), or bound PHP values (`:gap="$gap"`). Boolean attributes can be written bare (`bold`) or bound.

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::document>` | `title`, `author`, `subject`, `keywords`, `lang` | Root. Children must be `<x-doc::page>`; with no page, content gets an implicit page. No style attributes. |
| `<x-doc::page>` | `size` (a paper name or `"<width> <height>"`), `orientation` (`portrait`/`landscape`), `margin`, `header-gap`, `footer-gap` | Slots: `<x-slot:header>`, `<x-slot:footer>`, `<x-slot:background>`. No style attributes. |
| `<x-doc::view>` | `align` | Style. Generic container. |
| `<x-doc::row>` | `align` | Style. Lays children out horizontally. |
| `<x-doc::column>` | `align` | Style. Lays children out vertically. |
| `<x-doc::text>` | `align`, `size` | Style. Inline markup allowed: `<b>`, `<i>`, `<u>`, `<s>`, `<a href>`, `<br>`, `<span>`. |
| `<x-doc::h1>` ... `<x-doc::h4>` | `align`, `size` | Style. Heading presets. |
| `<x-doc::p>` | `align`, `size` | Style. Paragraph preset. |
| `<x-doc::image>` | `src`, `data`, `fit` (`contain`/`cover`/`fill`) | Style. Exactly one of `src` or `data` (raw bytes). PNG and JPEG; WebP, GIF, BMP and AVIF need `ext-gd`. An image taller than the page shrinks to fit unless it has an explicit `height`. Absolute and scheme paths (`/x.png`, `C:\x.png`, `file://`) are trusted as given; relative paths are contained to `papercut.image_path` (`..` cannot escape it). Remote URLs are not supported. Never pass untrusted input to `src`. |
| `<x-doc::link>` | `href` | Style. Required `href`. |
| `<x-doc::table>` | `columns`, `repeat-header` | Style. Children: one optional `<x-doc::thead>` first, then `<x-doc::tr>`. `columns` is a track list such as `"3fr 25mm 20mm"`. `repeat-header` defaults to on. |
| `<x-doc::thead>` | none | Contains `<x-doc::th>`/`<x-doc::td>` cells (one header row) or `<x-doc::tr>` rows. |
| `<x-doc::tr>` | none | Style. Contains `<x-doc::td>`/`<x-doc::th>`. |
| `<x-doc::td>` / `<x-doc::th>` | `colspan`, `align` (`left`/`right`/`center`/`justify`) | Style. `th` is a header cell. |
| `<x-doc::ol>` | `start`, `marker` | Style. Numbered list of `<x-doc::li>`. `marker` is `decimal`, `lower-alpha`, `upper-alpha`, `lower-roman` or `upper-roman`; unset follows nesting depth (`1.`, `a.`, `i.`). |
| `<x-doc::ul>` | `marker` | Style. Bulleted list of `<x-doc::li>`. `marker` is `disc`, `circle`, `square` or `none`; unset follows nesting depth. |
| `<x-doc::li>` | `value` | Style. A list item; holds any block content, including nested lists. `value` sets its number. |
| `<x-doc::markup>` | `html` or `text` (exactly one), `styles`, `blank-paragraphs` | Converts HTML or plain text to nodes, see [Rich text](#rich-text-from-your-database). Inside a text tag it contributes inline content and inherits the text style. `styles` and `blank-paragraphs` override the config per use. |
| `<x-doc::node>` | `node` | Embeds a PHP-built `Node` or `Component` (anything else throws `InvalidValue`). As a block among blocks, or inline inside a text tag when the node is a `Text`. |
| `<x-doc::spacer>` | `size` | `size="10mm"` is a fixed gap; with no `size` it is flexible (`grow`, default 1). Only `grow` is accepted besides `size`/`style`. |
| `<x-doc::page-number>` | `format`, `align`, `size` | Style. `format` uses `{page}` and `{total}`; no content allowed. |
| `<x-doc::page-break>` | none | Forces a new page. |

On `row`, `column` and `view`, `align` is a shorthand for `align-items`. On text tags, `align` is `text-align` and `size` is `font-size`. Every style-capable tag also accepts `style="name"` (a [named style](#named-styles)).

## Style attributes

These attributes are accepted by every tag marked "style" above. Lengths are `pt`, `mm`, `cm`, `in` or `px`; percentages are where noted; colors are palette names such as `slate-500`, hex, or other forms Papercut's `Color::parse` accepts.

| Attribute | Example | Notes |
|---|---|---|
| `width`, `height`, `basis` | `width="70mm"` | length, percentage or `auto` |
| `min-width`, `max-width`, `min-height`, `max-height` | `max-width="80%"` | length or percentage |
| `padding`, `margin`, `border-width` | `padding="12pt"`, `margin="6pt 0 0 0"` | 1 to 4 lengths (top right bottom left) |
| `border-color`, `background`, `color` | `background="slate-50"` | color |
| `border-style` | `border-style="dashed"` | `solid`, `dashed` |
| `radius` | `radius="8pt"` | length |
| `opacity` | `opacity="0.5"` | 0 to 1 |
| `direction` | `direction="column"` | `row`, `column` |
| `gap` | `gap="8pt"` | length |
| `justify` | `justify="space-between"` | `start`, `end`, `center`, `space-between`, `space-around`, `space-evenly` |
| `align-items`, `align-self` | `align-items="center"` | `start`, `end`, `center`, `stretch` |
| `grow`, `shrink` | `grow="1"` | number, 0 or more |
| `wrap` | `wrap` | boolean |
| `font` | `font="Merriweather"` | family name |
| `font-size` | `font-size="9pt"` | length, above 0 |
| `weight` | `weight="600"` | integer 1 to 1000 |
| `bold`, `italic`, `underline`, `strike` | `bold` | boolean |
| `line-height` | `line-height="1.4"` | number, above 0 |
| `letter-spacing` | `letter-spacing="0.6pt"` | length |
| `text-align` | `text-align="right"` | `left`, `right`, `center`, `justify` |
| `keep-together`, `keep-with-next` | `keep-together` | boolean; do not split the block / stay on the page with the next block |
| `break-before`, `break-after` | `break-before` | boolean; force a page break |
| `min-lines` | `min-lines="2"` | integer 1 or more (widow/orphan control) |

Boolean values: bare attribute or `true`/`1`/`false`/`0`. Any attribute not listed here (or in the tag's own attributes) throws `UnknownAttribute`, with the tag named and a suggestion when one is close.

## Named styles

Define styles in `config/papercut.php`, using the same keys as tag attributes:

```php
'styles' => [
    'card' => ['padding' => '12pt', 'background' => 'slate-50', 'radius' => '8pt'],
],
```

```blade
<x-doc::column style="card">...</x-doc::column>
```

The named style is the base; attributes written on the tag override it for any property both set.

## Rich text from your database

`<x-doc::markup>` converts stored HTML or plain text into document nodes. Pass the value bound (`:html`), so Blade
does not escape it.

```blade
<x-doc::markup :html="$slide->body" />
<x-doc::markup :text="$prompt" />
<x-doc::markup :html="$slide->body" styles="original" blank-paragraphs="keep" />
```

Used inside a text tag it joins the line and inherits the surrounding style:

```blade
<x-doc::text bold>× <x-doc::markup :html="$label" /></x-doc::text>
```

To embed a node you built in PHP, use `<x-doc::node :node="$node" />`. Limitation: in Blade an inline tag cannot span
an embedded node. `<x-doc::text><b>x <x-doc::node :node="$t"/> y</b></x-doc::text>` throws "<b> is not closed"; close
the tag on each side instead (`<b>x </b><x-doc::node :node="$t"/><b> y</b>`).

### Images

Papercut never reads an `<img src>` itself. Name a resolver class in `papercut.markup.images`. It receives the raw
decoded `src` and returns the image bytes, or `null` when the image is missing. The container builds it, so it can take
dependencies. For example, an editor that stores uploads on the default disk and serves resized copies from
`/public/images/{size}/files/uploaded/editor/...`:

```php
namespace App\Markup;

use Illuminate\Support\Facades\Storage;
use Papercut\Laravel\Markup\ImageResolver;

final class EditorImages implements ImageResolver
{
    public function __invoke(string $src): ?string
    {
        if (preg_match('#^/public/images/[^/]+/(files/uploaded/editor/.+)$#', $src, $m) !== 1) {
            return null;
        }
        $disk = Storage::disk();

        return $disk->exists($m[1]) ? $disk->get($m[1]) : null;
    }
}
```

```php
// config/papercut.php
'markup' => [
    'images' => \App\Markup\EditorImages::class,
    'styles' => 'theme',
    'blank_paragraphs' => 'drop',
],
```

A missing image (`null`, a throw, or bytes that are not a recognised image format) shows its `alt` text in italics, or
nothing without `alt`, and is reported. Invalid `markup.*` config is reported at first use.

### What is converted and reported

The element mapping, style modes, supported CSS properties and the full report-level table are in
[core's README](../core/README.md#markup-input). In short: structure comes from the HTML and the look from your theme
(`styles="original"` also applies inline `style=""`: supported properties only, `px` at 0.75pt). Things that cannot be
drawn are reported through `onWarning` and the fidelity report. `approximated` ones (an iframe shown as a link, a missing
image shown as alt text, an unknown tag removed with its content kept) never fail a strict export. `dropped` ones (a
missing image without alt text, an unsafe link scheme, an unsupported CSS declaration) do. Nesting deeper than 256
levels is flattened to text with one report. Known gaps in `original` mode: CSS colour keywords such as `red` and
`hsl()` are not recognised yet, and `!important` declarations are dropped.

## Fonts

Inter (regular, bold, italic, bold italic) is bundled and always available. Register more families by path (relative paths resolve against `base_path()`):

```php
'fonts' => [
    'Merriweather' => [
        400 => ['normal' => 'resources/fonts/Merriweather-Regular.ttf', 'italic' => 'resources/fonts/Merriweather-Italic.ttf'],
        700 => ['normal' => 'resources/fonts/Merriweather-Bold.ttf'],
    ],
],
'fallback_fonts' => ['Noto Sans'],
'theme' => ['font_family' => 'Merriweather', 'font_size' => 10],
```

`php artisan papercut:fonts` lists every available face and reports whether each file is `ok`, `missing` or `invalid`. A glyph missing from the primary family is looked up in `fallback_fonts`; if no family has it, a warning is logged (`[papercut] ...`).

## Preview routes

For fast iteration on a layout, register a view with sample data (for example in a service provider) and enable previews:

```php
Papercut::preview('pdf.invoice', fn () => ['invoice' => Invoice::factory()->make()]);
```

Set `PAPERCUT_PREVIEW=true`. Only in the `local` environment, `GET /_papercut` lists the registered views and `GET /_papercut/{view}` renders one inline.

## Errors

- `Papercut\Exception\UnknownAttribute` and `Papercut\Exception\InvalidValue`: reported with the tag (`on <x-doc::td>`), the attribute and, where possible, a suggestion.
- `Papercut\Laravel\Exception\InvalidStructure`: misplaced content, such as a `<x-doc::tr>` outside a table, a missing `<x-doc::document>` root, or an unknown page slot.
- `Papercut\Exception\ImageUnreadable`: the `src` file is missing or cannot be read as an image.
- `Papercut\Exception\LayoutOverflow`: a block cannot fit on a page even after every permitted split. Controlled by `overflow`: throw (default in `local`/`testing`) or clip.

Blade wraps exceptions thrown while rendering a view in `Illuminate\View\ViewException`; the original is its `getPrevious()`.
