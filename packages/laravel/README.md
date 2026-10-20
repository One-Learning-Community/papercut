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
| `paper` | `'a4'` | Page size when `<x-doc::page>` has no `size`: `a3`, `a4`, `a5`, `a6`, `letter`, `legal` |
| `margin` | `'20mm'` | Page margin when `<x-doc::page>` has no `margin` |
| `fonts` | `[]` | Extra font families, see [Fonts](#fonts) |
| `fallback_fonts` | `[]` | Family names tried, in order, for glyphs the primary font lacks |
| `theme` | `[]` | Defaults: `font_family`, `font_size` (points, number), `color`, `line_height` (number) |
| `styles` | `[]` | [Named styles](#named-styles) |
| `overflow` | `null` | `'throw'`, `'clip'`, or `null` = throw in `local`/`testing`, clip elsewhere |
| `strict` | `false` | `true` makes an export throw `UnsupportedFeature` for content a format cannot render, instead of dropping it with a warning. Consider `true` in tests |
| `html.fonts` | `'none'` | Fonts in HTML output: `'none'` (a `system-ui` fallback stack, small files) or `'embed'` (base64 `@font-face`, large files) |
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
Papercut::view('pdf.invoice', $data)->html()->download('invoice.html');
Papercut::view('pdf.invoice', $data)->html()->save(storage_path('invoice.html'));
```

Inside another Blade view, `{{ $doc->html() }}`, `{!! $doc->html() !!}` and `(string) $doc->html()` all give the embeddable fragment (one `.papercut` element, no `<html>`), where `$doc` is `Papercut::view(...)` or `Papercut::make($document)`. `toString()`, `save()`, `download()` and returning it from a route give the full page. HTML reports approximations through the same warning log as PDF and never drops content, so `strict` does not throw for it.

Preview routes accept `?format=pdf` (default) or `?format=html`; any other value is a 404.

## Tags

Style attributes (next section) work on every tag marked "style" below. Values are plain strings (`gap="8pt"`), or bound PHP values (`:gap="$gap"`). Boolean attributes can be written bare (`bold`) or bound.

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-doc::document>` | `title`, `author`, `subject`, `keywords`, `lang` | Root. Children must be `<x-doc::page>`; with no page, content gets an implicit page. No style attributes. |
| `<x-doc::page>` | `size`, `orientation` (`portrait`/`landscape`), `margin`, `header-gap`, `footer-gap` | Slots: `<x-slot:header>`, `<x-slot:footer>`, `<x-slot:background>`. No style attributes. |
| `<x-doc::view>` | `align` | Style. Generic container. |
| `<x-doc::row>` | `align` | Style. Lays children out horizontally. |
| `<x-doc::column>` | `align` | Style. Lays children out vertically. |
| `<x-doc::text>` | `align`, `size` | Style. Inline markup allowed: `<b>`, `<i>`, `<u>`, `<s>`, `<a href>`, `<br>`, `<span>`. |
| `<x-doc::h1>` ... `<x-doc::h4>` | `align`, `size` | Style. Heading presets. |
| `<x-doc::p>` | `align`, `size` | Style. Paragraph preset. |
| `<x-doc::image>` | `src`, `data`, `fit` (`contain`/`cover`/`fill`) | Style. Exactly one of `src` or `data` (raw bytes). Absolute and scheme paths (`/x.png`, `C:\x.png`, `file://`) are trusted as given; relative paths are contained to `papercut.image_path` (`..` cannot escape it). Remote URLs are not supported. Never pass untrusted input to `src`. |
| `<x-doc::link>` | `href` | Style. Required `href`. |
| `<x-doc::table>` | `columns`, `repeat-header` | Style. Children: one optional `<x-doc::thead>` first, then `<x-doc::tr>`. `columns` is a track list such as `"3fr 25mm 20mm"`. `repeat-header` defaults to on. |
| `<x-doc::thead>` | none | Contains `<x-doc::th>`/`<x-doc::td>` cells (one header row) or `<x-doc::tr>` rows. |
| `<x-doc::tr>` | none | Style. Contains `<x-doc::td>`/`<x-doc::th>`. |
| `<x-doc::td>` / `<x-doc::th>` | `colspan`, `align` (`left`/`right`/`center`/`justify`) | Style. `th` is a header cell. |
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
