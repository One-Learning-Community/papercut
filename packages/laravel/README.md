# papercut/laravel

Blade components for [Papercut](../../README.md): `<x-pdf::*>` tags compile into Papercut's layout engine and out to a PDF. Not HTML-to-PDF.

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

then `composer require papercut/laravel:@dev`. Requires PHP 8.4+, `ext-zlib`, `ext-mbstring`, `ext-dom` and Laravel 12 or 13. The service provider and the `Pdf` facade are auto-discovered.

## Quick start

```blade
{{-- resources/views/pdf/hello.blade.php --}}
<x-pdf::document title="Hello">
  <x-pdf::page>
    <x-pdf::h1>Hello, {{ $name }}</x-pdf::h1>
    <x-pdf::p>Rendered by Papercut.</x-pdf::p>
  </x-pdf::page>
</x-pdf::document>
```

```php
use Papercut\Laravel\Facades\Pdf;

Route::get('/hello', fn () => Pdf::view('pdf.hello', ['name' => 'Ada'])->download('hello.pdf'));
```

A view must have exactly one `<x-pdf::document>` root. `Pdf::view($view, $data)` returns a `PendingPdf` with `download($filename)`, `inline($filename)`, `save($path)` and `toString()`. A `PendingPdf` returned from a route renders inline.

Full examples: [`examples/laravel`](../../examples/laravel) (invoice, transcript, report). Content inside `<x-pdf::document>` may also contain your own Blade components, which can themselves contain `<x-pdf::*>` tags.

## Configuration

```
php artisan vendor:publish --tag=papercut-config
```

This publishes `config/papercut.php`:

| Key | Default | Meaning |
|---|---|---|
| `paper` | `'a4'` | Page size when `<x-pdf::page>` has no `size`: `a3`, `a4`, `a5`, `a6`, `letter`, `legal` |
| `margin` | `'20mm'` | Page margin when `<x-pdf::page>` has no `margin` |
| `fonts` | `[]` | Extra font families, see [Fonts](#fonts) |
| `fallback_fonts` | `[]` | Family names tried, in order, for glyphs the primary font lacks |
| `theme` | `[]` | Defaults: `font_family`, `font_size` (points, number), `color`, `line_height` (number) |
| `styles` | `[]` | [Named styles](#named-styles) |
| `overflow` | `null` | `'throw'`, `'clip'`, or `null` = throw in `local`/`testing`, clip elsewhere |
| `image_path` | `null` | Base directory for relative `<x-pdf::image src>`; `null` = `public_path()` |
| `preview.enabled` | `env('PAPERCUT_PREVIEW', false)` | Enable [preview routes](#preview-routes) |
| `preview.path` | `'_papercut'` | URL prefix for preview routes |

> Apps that also use barryvdh/laravel-dompdf (which claims the same `Pdf` alias) should import the facade explicitly: `use Papercut\Laravel\Facades\Pdf;`.

## Tags

Style attributes (next section) work on every tag marked "style" below. Values are plain strings (`gap="8pt"`), or bound PHP values (`:gap="$gap"`). Boolean attributes can be written bare (`bold`) or bound.

| Tag | Own attributes | Notes |
|---|---|---|
| `<x-pdf::document>` | `title`, `author`, `subject`, `keywords`, `lang` | Root. Children must be `<x-pdf::page>`; with no page, content gets an implicit page. No style attributes. |
| `<x-pdf::page>` | `size`, `orientation` (`portrait`/`landscape`), `margin`, `header-gap`, `footer-gap` | Slots: `<x-slot:header>`, `<x-slot:footer>`, `<x-slot:background>`. No style attributes. |
| `<x-pdf::view>` | `align` | Style. Generic container. |
| `<x-pdf::row>` | `align` | Style. Lays children out horizontally. |
| `<x-pdf::column>` | `align` | Style. Lays children out vertically. |
| `<x-pdf::text>` | `align`, `size` | Style. Inline markup allowed: `<b>`, `<i>`, `<u>`, `<s>`, `<a href>`, `<br>`, `<span>`. |
| `<x-pdf::h1>` ... `<x-pdf::h4>` | `align`, `size` | Style. Heading presets. |
| `<x-pdf::p>` | `align`, `size` | Style. Paragraph preset. |
| `<x-pdf::image>` | `src`, `data`, `fit` (`contain`/`cover`/`fill`) | Style. Exactly one of `src` or `data` (raw bytes). Absolute and scheme paths (`/x.png`, `C:\x.png`, `file://`) are trusted as given; relative paths are contained to `papercut.image_path` (`..` cannot escape it). Remote URLs are not supported. Never pass untrusted input to `src`. |
| `<x-pdf::link>` | `href` | Style. Required `href`. |
| `<x-pdf::table>` | `columns`, `repeat-header` | Style. Children: one optional `<x-pdf::thead>` first, then `<x-pdf::tr>`. `columns` is a track list such as `"3fr 25mm 20mm"`. `repeat-header` defaults to on. |
| `<x-pdf::thead>` | none | Contains `<x-pdf::th>`/`<x-pdf::td>` cells (one header row) or `<x-pdf::tr>` rows. |
| `<x-pdf::tr>` | none | Style. Contains `<x-pdf::td>`/`<x-pdf::th>`. |
| `<x-pdf::td>` / `<x-pdf::th>` | `colspan`, `align` (`left`/`right`/`center`/`justify`) | Style. `th` is a header cell. |
| `<x-pdf::spacer>` | `size` | `size="10mm"` is a fixed gap; with no `size` it is flexible (`grow`, default 1). Only `grow` is accepted besides `size`/`style`. |
| `<x-pdf::page-number>` | `format`, `align`, `size` | Style. `format` uses `{page}` and `{total}`; no content allowed. |
| `<x-pdf::page-break>` | none | Forces a new page. |

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
<x-pdf::column style="card">...</x-pdf::column>
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
Pdf::preview('pdf.invoice', fn () => ['invoice' => Invoice::factory()->make()]);
```

Set `PAPERCUT_PREVIEW=true`. Only in the `local` environment, `GET /_papercut` lists the registered views and `GET /_papercut/{view}` renders one inline.

## Errors

- `Papercut\Exception\UnknownAttribute` and `Papercut\Exception\InvalidValue`: reported with the tag (`on <x-pdf::td>`), the attribute and, where possible, a suggestion.
- `Papercut\Laravel\Exception\InvalidStructure`: misplaced content, such as a `<x-pdf::tr>` outside a table, a missing `<x-pdf::document>` root, or an unknown page slot.
- `Papercut\Exception\ImageUnreadable`: the `src` file is missing or cannot be read as an image.
- `Papercut\Exception\LayoutOverflow`: a block cannot fit on a page even after every permitted split. Controlled by `overflow`: throw (default in `local`/`testing`) or clip.

Blade wraps exceptions thrown while rendering a view in `Illuminate\View\ViewException`; the original is its `getPrevious()`.
