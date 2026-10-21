# Images, SVG, links and QR codes

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

## SVG

An SVG is drawn as vector paths, so logos, icons and charts stay sharp at any size.

::: code-tabs#style
@tab Blade
```blade
<x-doc::svg src="img/logo.svg" width="40mm" />
<x-doc::svg :data="$chartSvg" width="100%" />

<x-doc::svg height="8mm">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <circle cx="12" cy="12" r="10" fill="#4f46e5" />
  </svg>
</x-doc::svg>
```
@tab PHP
```php
use Papercut\{Document, Page, Svg};
use function Papercut\mm;

// Svg::file('logo.svg') reads a file instead.
$ring = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
    . '<path fill="#4f46e5" fill-rule="evenodd" d="M50 10 A40 40 0 1 1 49.99 10 Z M50 30 A20 20 0 1 1 49.99 30 Z"/>'
    . '</svg>';

$document = Document::make(Page::a4()->body(
    Svg::fromString($ring)->width(mm(30)),
));
```
:::

In Blade, give the tag exactly one source: a `src` file, a `data` string of SVG markup, or the markup itself between the tags. Use `{!! $svg !!}` to put markup from a variable inside the tag, since `{{ }}` would escape it.

### Size

An SVG is sized like an image. Its `width` and `height` attributes give it a natural size. If it only has a `viewBox`, each unit counts as a pixel (0.75pt), and if it has neither, it's 300 by 150 pixels, as in a browser. Set `width` or `height` and the other side follows the aspect ratio. It's scaled down to fit the page width, and it never splits across pages.

If you set both `width` and `height`, the SVG's own `preserveAspectRatio` decides where the drawing sits in that box, just as it would in a browser: centred and whole by default, cropped to fill with `slice`, or stretched with `none`. Anything drawn outside the box is cut off.

### What's drawn

Papercut draws paths (including arcs and curves), rectangles with rounded corners, circles, ellipses, lines, polylines and polygons. Groups with transforms (nested ones too), `<use>` and `<defs>`, `<style>` rules for tags, classes and ids, and fill, stroke and opacity all work, including a fill or stroke set on a group. Linear and radial gradients fill any shape.

`fill-rule="evenodd"` is honoured on the shape itself, in its `style`, in a style sheet or on a group. It's what keeps the hole in a ring, a letter O or a five-point star when the inner outline is drawn the same way round as the outer one.

A few things aren't drawn yet: `<clipPath>`, `<mask>`, `<filter>`, `<pattern>`, `<image>`, `<marker>`, `<symbol>`, `<textPath>`, `<tspan>`, links and nested `<svg>` elements. When an SVG uses any of them, you'll get a warning that names them, and the rest of the drawing still appears.

### Text

Text in an SVG is set in your document's fonts and embedded like the rest of the PDF, so it prints the same everywhere. If the SVG's `font-family` names a family you've [registered](./fonts.md), that family is used; otherwise the text uses the font family of the `<x-doc::svg>` element itself. Bold and italic pick the matching faces, and your fallback families cover any characters the font is missing.

In HTML exports, the browser sets SVG text in its own fonts, because an SVG shown as an image can't use the page's. If text needs to look the same in both formats, convert it to outlines in your design tool.

### SVGs from other people

You can draw SVGs you didn't make yourself, such as an uploaded logo. Papercut never loads files, DTDs or URLs that an SVG refers to, and it refuses an SVG that declares entities. It removes scripts and event handlers, and it refuses SVGs over 10 MB, or ones that would take more than a few seconds to draw, so a crafted file can't keep your server busy for minutes. Papercut estimates that from what the SVG draws, with every `<use>` counted: one unit per path or polygon point, 15 per circle or ellipse, and 5 for any other element, up to a million. That's room for a line chart with several hundred thousand points, or a scatter plot with about 65,000 circles. A refused or broken SVG throws `ImageUnreadable` with the reason.

A relative `src` in Blade follows the same rules as for images, so keep user input out of `src`.

In HTML exports, an SVG becomes an `<img>` with the SVG in a `data:` URI. It's never added to the page as markup, so a browser won't run anything in it or fetch anything it links to. The SVG's `<title>` becomes the image's alt text.

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
