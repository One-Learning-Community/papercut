# Images and links

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
