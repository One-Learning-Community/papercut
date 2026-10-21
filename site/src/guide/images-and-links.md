# Images, links and QR codes

## Images

PNG and JPEG always work. WebP, GIF, BMP and AVIF need the `gd` extension.

::: code-tabs#style
@tab Blade
```blade
<x-doc::image src="img/logo.png" width="30mm" fit="contain" />
<x-doc::image :data="$chartPng" width="100%" />
```
@tab PHP
```php
use Papercut\{Column, Document, Image, Page};
use Papercut\Style\ImageFit;
use function Papercut\{mm, pt};

// A 1x1 PNG keeps the example self-contained. Use Image::file('logo.png') in practice.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

$document = Document::make(Page::a4()->body(
    Column::make(
        Image::bytes($png)->fit(ImageFit::Contain)->width(mm(30))->height(mm(30)),
    )->gap(pt(8)),
));
```
:::

`fit` can be `contain`, `cover` or `fill`, and works like CSS `object-fit`. Images are scaled down to fit the page width, and an image taller than a page is scaled down to fit on one. The exception is an image with an explicit `height`: Papercut keeps that height, and if it can't fit on an empty page you'll get a `LayoutOverflow` error (see [Content that doesn't fit](./pages.md#content-that-doesn-t-fit)).

If a file isn't a supported image, Papercut throws `ImageUnreadable`.

In Blade, a relative `src` is looked up inside `papercut.image_path`, which defaults to `public_path()`, and `..` can't be used to escape that directory. Absolute paths are used as written. Remote URLs are never fetched.

::: danger
Never pass user input to `src`.
:::

## Links

A link makes everything inside it clickable.

::: code-tabs#style
@tab Blade
```blade
<x-doc::link href="https://example.com">
  <x-doc::text underline color="indigo-600">example.com</x-doc::text>
</x-doc::link>
```
@tab PHP
```php
use Papercut\{Document, Link, Page, Text};

$document = Document::make(Page::a4()->body(
    Link::to('https://example.com', Text::make('example.com')->underline()->color('indigo-600')),
));
```
:::

To link a few words in the middle of a sentence, use `<a href="…">` inside a text tag.

## QR codes

A QR code is drawn as vector squares, so it stays sharp at any print size and doesn't need an image library.

::: code-tabs#style
@tab Blade
```blade
<x-doc::qr-code :value="route('invoices.pay', $invoice)" width="30mm" />
```
@tab PHP
```php
use Papercut\{Document, Page, QrCode};
use function Papercut\mm;

$document = Document::make(Page::a4()->body(
    QrCode::make('https://acme.test/pay/INV-142')->width(mm(30)),
));
```
:::

A QR code is always square. Set `width` (or `height`) to size it; without one it's 25mm across, which scans comfortably from a phone. The blank margin scanners need around the code, four modules wide, is included in that size.

The squares use the text color, so `color="indigo-700"` works. Keep the contrast high, and set `background` if the code sits on something dark. Padding, borders and rounded corners work as they do on any box.

| Attribute | PHP | What it does |
|---|---|---|
| `value` | `QrCode::make($value)` | The text or URL to encode. |
| `level` | `QrCode::make($value, ErrorCorrection::High)` | How much damage the code survives: `L`, `M` (the default), `Q` or `H`. Higher levels hold less text. |
| `quiet-zone` | `->quietZone(2)` | The blank margin in modules. Lower it only if the code already sits on a plain background. |

QR codes hold up to 213 characters at the default level, which is plenty for URLs and payment references. Longer text throws an error saying how much fits. To encode more, you can plug in another encoder, such as BaconQrCode's, by implementing `Papercut\Barcode\QrEncoder`, or pass a grid you've encoded yourself to `QrCode::fromModules()`.

In HTML exports, a QR code becomes an inline SVG.
