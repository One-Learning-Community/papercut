# Pages and pagination

## Page size and margins

::: code-tabs#style
@tab Blade
```blade
<x-doc::page size="letter" orientation="landscape" margin="18mm 20mm">
  …
</x-doc::page>
```
@tab PHP
```php
use Papercut\{Document, Page, Text};
use function Papercut\mm;

$document = Document::make(
    Page::letter()->landscape()->margin(mm(18), mm(20))->body(Text::make('…')),
);
```
:::

The named sizes are `a0` to `a6`, `b4`, `b5`, `letter`, `legal`, `tabloid` and `executive`. In PHP, the common ones have shortcuts (`Page::a4()`, `a3()`, `a5()`, `a6()`, `letter()` and `legal()`), `Page::named('tabloid')` covers the rest, and `Page::size($width, $height)` takes any custom size. In Blade, write a custom size as `size="210mm 297mm"`.

## Headers, footers and backgrounds

A header, footer or background is repeated on every page, and the body content flows in the space between them.

::: code-tabs#style
@tab Blade
```blade
<x-doc::page>
  <x-slot:header>
    <x-doc::text size="8pt" color="slate-500">Quarterly report</x-doc::text>
  </x-slot:header>

  …body…

  <x-slot:footer>
    <x-doc::page-number format="Page {page} of {total}" align="right" size="8pt" />
  </x-slot:footer>
</x-doc::page>
```
@tab PHP
```php
use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Text};
use function Papercut\{mm, pt};

$body = [];
for ($i = 1; $i <= 80; $i++) {
    $body[] = Text::make("Paragraph $i. " . str_repeat('Lorem ipsum dolor sit amet. ', 6));
}

$document = Document::make(
    Page::a4()->margin(mm(20))
        ->header(Text::make('Quarterly report')->color('slate-500')->size(pt(8)))
        ->footer(Row::make(Spacer::flex(), PageNumber::make('Page {page} of {total}')->size(pt(8))))
        ->body(Column::make(...$body)->gap(pt(6))),
);
```
:::

In the page number format, `{page}` is the current page and `{total}` is the total page count. The total is filled in once layout has finished, so it's always correct. To adjust the space between the body and the header or footer, set `header-gap` or `footer-gap` on the page.

## Filling in an existing PDF

Sometimes the design already exists as a PDF, such as a certificate or a letterhead made in a design tool. A page template draws a page of that PDF behind your page, at the same size, and the overlay places your content at exact points on top of it.

::: code-tabs#style
@tab Blade
```blade
<x-doc::page template="certificates/completion.pdf">
  <x-slot:overlay>
    <x-doc::place x="50%" y="42%" anchor="top">
      <x-doc::text size="32pt" bold>{{ $learner->name }}</x-doc::text>
    </x-doc::place>
    <x-doc::place x="50%" y="55%" anchor="top">
      <x-doc::text size="14pt">Completed {{ $course->title }} on {{ $completedOn }}</x-doc::text>
    </x-doc::place>
    <x-doc::place x="85%" y="80%" anchor="center" width="12%">
      <x-doc::qr-code :value="$verifyUrl" width="100%" />
    </x-doc::place>
  </x-slot:overlay>
</x-doc::page>
```
@tab PHP
```php
use Papercut\{Document, Page, PdfPage, Place, QrCode, Text};
use Papercut\Style\Anchor;
use function Papercut\{percent, pt};

$document = Document::make(
    Page::template(PdfPage::file('/path/to/certificates/completion.pdf'))->overlay(
        Place::at('50%', '42%', Text::make('Ada Lovelace')->size(pt(32))->bold())->anchor(Anchor::Top),
        Place::at('50%', '55%', Text::make('Completed Analytical Engines on 21 October 2026')->size(pt(14)))->anchor(Anchor::Top),
        Place::at('85%', '80%', QrCode::make('https://example.com/verify/123')->width(percent(100)))
            ->width('12%')->anchor(Anchor::Center),
    ),
);
```
:::

### The template

The page takes its size from the template page, so you can't set `size` or `orientation` alongside it. The template is drawn as it is, not as a picture of it, so its text and artwork stay sharp at any zoom. Anything else you put on the page, including the background, is drawn over it.

In Blade, a relative `template` path stays inside `papercut.image_path`, the same as for images. If the PDF isn't a file on disk, for example when it's stored on S3, pass its bytes with `:template-data="Storage::get($path)"` instead. Use `template-page="2"` to draw a page other than the first, and `template-password` to open a password-protected template. In PHP, `PdfPage::file($path, page: 2, password: '…')` and `PdfPage::bytes($bytes)` take the same options.

`Page::template()` reads the PDF straight away to learn its size, so a missing file or a wrong password throws a `PdfUnreadable` exception there, rather than when you render.

### Placing content

Each place puts its content at a point given by `x` and `y`. These are measured from the top-left corner of the page, as lengths like `20mm` or as percentages of the page's width and height. Page margins don't apply to the overlay.

The anchor says which point of the content sits at that spot:

| `anchor` | The point placed at `x`, `y` |
|---|---|
| `top-left` (the default) | The top-left corner |
| `top`, `bottom` | The middle of the top or bottom edge |
| `top-right`, `bottom-left`, `bottom-right` | That corner |
| `left`, `right` | The middle of the left or right edge |
| `center` | The centre |

With `anchor="top"`, `x` is the middle of the content, so a short name and a long one both stay centred on the same point.

Without a `width`, a place is as wide as its content needs, up to the width of the page, and longer text wraps at the page edge. With a `width`, either a length or a percentage of the page width, the content is laid out in that width, and percentages inside the place are of the place's width. That's how the QR code above fills a box 12% of the page wide.

The overlay is drawn on top of everything else on the page and is repeated on every page the page template makes, so you can also use it for a stamp or a page number in a fixed spot. A `<x-doc::place>` only works in the `overlay` slot, and the `overlay` slot only takes `<x-doc::place>` tags.

::: tip
Blade needs a space or a line break after a closing slot tag. If you write `</x-slot:overlay>Body` on one line, Blade reads it as an unknown `@endslotBody` directive and the slot is never closed.
:::

### Pages of a PDF inside your content

You can also draw a page of a PDF anywhere in your content, the way you'd draw an image, with `<x-doc::pdf-page src="form.pdf" width="60mm" />` or `PdfPage::file('form.pdf')->width(mm(60))`. It's sized like an image too: from the page's own size, and setting one side keeps its proportions.

### In HTML exports

A browser can't draw a page of a PDF inline, so HTML exports leave templates and PDF pages out and report each one as dropped. Places are positioned over the page with CSS, which is close to the PDF but not exact.

## Controlling page breaks

Papercut splits content wherever it needs to by default. These options let you control where the breaks fall:

| PHP | Blade | What it does |
|---|---|---|
| `keepTogether()` | `keep-together` | Moves the whole block to the next page instead of splitting it. A block taller than a page will still split. |
| `keepWithNext()` | `keep-with-next` | Keeps the block on the same page as the one after it. Headings do this by default. |
| `minLines(3)` | `min-lines="3"` | Leaves at least this many lines of a paragraph on each side of a break. |
| `breakBefore()`, `breakAfter()` | `break-before`, `break-after` | Starts a new page before or after the block. |
| `PageBreak::make()` | `<x-doc::page-break />` | Starts a new page at this point. |

```php
use Papercut\{Column, Document, Page, PageBreak, Text};
use function Papercut\{mm, pt};

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::h2('Section one'),
            Text::make(str_repeat('Body text. ', 200))->minLines(3),
            PageBreak::make(),
            Text::h2('Section two'),
            Column::make(Text::make('A block'), Text::make('that stays whole'))->keepTogether(),
        )->gap(pt(8)),
    ),
);
```

## Bookmarks

PDF viewers show a bookmarks panel beside the page, and Papercut fills it in for you. Every heading from `h1` to `h4` becomes a bookmark, nested by level, that jumps straight to that heading.

To bookmark something that isn't a heading, or to change a heading's entry, set `bookmark`:

::: code-tabs#style
@tab Blade
```blade
<x-doc::h1>Annual report</x-doc::h1>
<x-doc::h2 bookmark="Summary">A long heading that reads badly in a sidebar</x-doc::h2>
<x-doc::h2 :bookmark="false">Left out of the panel</x-doc::h2>
<x-doc::card title="Terms" bookmark="Terms and conditions">…</x-doc::card>
```
@tab PHP
```php
use Papercut\{Column, Document, Page, Text};
use Papercut\Decorator\Card;

$document = Document::make(Page::a4()->body(Column::make(
    Text::h1('Annual report'),
    Text::h2('A long heading that reads badly in a sidebar')->bookmark('Summary'),
    Text::h2('Left out of the panel')->bookmark(false),
    Card::make(Text::make('…'))->title('Terms')->bookmark('Terms and conditions'),
)));
```
:::

Bookmarks you add are top-level unless you give them a level, with `bookmark-level="2"` in Blade or `->bookmark('Terms', 2)` in PHP. If a level skips one, like an `h3` straight after an `h1`, it's nested one step down instead of leaving a gap. Headings in headers, footers and backgrounds are ignored, since they repeat on every page.

To leave the panel empty, pass `bookmarks: false` to `PdfOptions`, or set `'bookmarks' => false` in the Laravel config. HTML exports don't have a bookmarks panel, so this only affects PDFs.

## Content that doesn't fit

Occasionally something can't fit even on a fresh, empty page, such as an image with a fixed height taller than the page. By default Papercut throws a `LayoutOverflow` exception that names the component, so you find out straight away.

If you'd rather get the rest of the document, you can have Papercut clip the oversized content and log a warning instead:

```php
use Papercut\OverflowMode;
use Papercut\Export\PdfOptions;

$options = new PdfOptions(overflow: OverflowMode::Clip, onWarning: fn (string $message) => error_log($message));
```

In Laravel, set `overflow` in the config to `'throw'` or `'clip'`. The default, `null`, throws in the `local` and `testing` environments and clips everywhere else, so you see problems during development without breaking documents in production.
