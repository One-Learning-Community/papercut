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

## Content that doesn't fit

Occasionally something can't fit even on a fresh, empty page, such as an image with a fixed height taller than the page. By default Papercut throws a `LayoutOverflow` exception that names the component, so you find out straight away.

If you'd rather get the rest of the document, you can have Papercut clip the oversized content and log a warning instead:

```php
use Papercut\OverflowMode;
use Papercut\Export\PdfOptions;

$options = new PdfOptions(overflow: OverflowMode::Clip, onWarning: fn (string $message) => error_log($message));
```

In Laravel, set `overflow` in the config to `'throw'` or `'clip'`. The default, `null`, throws in the `local` and `testing` environments and clips everywhere else, so you see problems during development without breaking documents in production.
