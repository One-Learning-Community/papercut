# papercut/core

Component-based, page-aware PDF layout engine for modern PHP. Build documents from typed, immutable
components with flexbox-style layout and page-aware pagination; a pure-PHP engine turns the tree into a PDF.
There is no browser, no HTML parser and no CSS cascade.

> **Status: v0.1, pre-release.** Not yet published to Packagist. Using Laravel? See
> [`papercut/laravel`](../laravel) for the Blade front end of this same engine.

## Requirements

PHP 8.4+, `ext-mbstring`, `ext-zlib`, `ext-dom`. The only Composer dependency is
[`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf) (MIT), used behind a backend adapter.

## Quick start

Every example below is a complete snippet that leaves a `Papercut\Document` in `$document` (and, where needed, a
`PdfOptions` in `$options`); the test suite runs each one (`tests/Feature/ReadmeTest.php`). Real code would end with `$document->toPdf()->save('out.pdf')`.

```php
use Papercut\{Column, Document, Page, Text};
use function Papercut\{mm, pt};

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::h1('Hello, Papercut'),
            Text::p('A column of text. Inter is bundled, so this embeds a real font.'),
        )->gap(pt(8)),
    ),
)->title('Hello');
```

Output: `$document->toPdf()` returns an `Output`: `->save($path)`, `->bytes()` (a string of PDF bytes) or `->stream()`
(writes to output, returns the byte count).

## Elements

| Element | Purpose |
|---|---|
| `Document::make(Page ...)` | Root. Metadata: `title`, `author`, `subject`, `keywords`, `lang`, `createdAt`. |
| `Page::a4()` (also `a3 a5 a6 letter legal`, `size($w, $h)`, `named('a4')`) | A page. `landscape()`, `margin()`, `header()`, `footer()`, `background()`, `body()`. |
| `Row::make(...)`, `Column::make(...)` | Flex containers (horizontal, vertical). |
| `Spacer::flex()`, `Spacer::size($length)` | Flexible or fixed gap. |
| `Text::make()`, `h1`-`h4`, `p`, `markup()`, `spans()` | Text, with inline spans. |
| `Image::file()`, `bytes()` | Raster images, `fit()` modes. |
| `Link::to($uri, ...$children)` | A clickable region around children. |
| `Table::make()` | Page-aware table. |
| `PageNumber::make('Page {page} of {total}')` | Page number and page count. |
| `PageBreak::make()` | Forced break. |

Components are immutable: every method returns a modified copy.

## Units

Lengths are typed values created by helper functions: `pt()`, `px()`, `mm()`, `cm()`, `in()`. Also
`percent()` and `fr()` where a track or size accepts them. A bare number is never a length, so there is
no ambiguity about the unit. Strings are accepted where noted (`'1fr 20mm'`, `'slate-500'`).

```php
use Papercut\{Column, Document, Page, Row, Text};
use Papercut\Style\Justify;
use Papercut\Value\Edges;
use function Papercut\{mm, pt, percent};

$document = Document::make(
    Page::a4()->margin(mm(15))->body(
        Column::make(
            Row::make(
                Text::make('Left')->grow(1),
                Text::make('Right')->right(),
            )->justify(Justify::SpaceBetween)->gap(pt(12)),
            Text::make('Half width')->width(percent(50))->padding(Edges::of(pt(4), pt(8))),
            Text::make('Fixed 5 cm')->width(mm(50))->border(pt(1), 'slate-300'),
        )->gap(mm(4)),
    ),
);
```

## Styles

Style methods are available on every component and cascade to descendants where it makes sense (font, size,
colour, alignment). Layout: `width height minWidth maxWidth minHeight maxHeight padding margin border borderWidth
borderColor radius background opacity grow shrink basis alignSelf gap justify`. Text: `font size weight bold italic
color lineHeight letterSpacing left center right justify underline strike`. Pagination: `keepTogether keepWithNext
breakBefore breakAfter minLines`.

Colours are `Color` values or strings: hex (`'#336699'`) or the bundled Tailwind palette (`'slate-900'`,
`'indigo-500'`).

Named styles live in the `Theme`; apply one by name:

```php
use Papercut\{Column, Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Style\{Style, Theme};
use Papercut\Value\Color;
use function Papercut\{mm, pt};

$theme = Theme::default()->withStyle('callout', Style::make(color: Color::parse('indigo-600'), fontWeight: 700));

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::make('Plain'),
            Text::make('Named style')->apply('callout'),
        )->gap(pt(6)),
    ),
);
$options = new PdfOptions(theme: $theme); // pass to toPdf()
```

Pass `PdfOptions` to `toPdf()`.

## Text and inline markup

`Text::make()` draws plain text. `Text::markup()` parses a small inline vocabulary (never HTML):
bold, italic, underline, strike, `<span>` with style attributes, `<a href>` and `<br>`. Unknown tags are errors.
For full control build spans yourself.

```php
use Papercut\{Column, Document, Page, Span, Text};
use Papercut\Style\Style;
use function Papercut\{mm, pt};

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::markup('Plain, <b>bold</b>, <i>italic</i> and <span color="indigo-600">coloured</span>.'),
            Text::spans(
                Span::text('Built from '),
                Span::text('spans', new Style(fontWeight: 700)),
                Span::break(),
                Span::text('after a line break.'),
            ),
        )->gap(pt(8)),
    ),
);
```

## Tables

Tables take a column track list (`'3fr 25mm 20mm'`, `'1fr 1fr'`, ...), header rows, and body rows. A table breaks
between rows, never through one (unless a single row is taller than a page), and the header repeats on every page
the table crosses (`repeatHeader(false)` turns that off).

```php
use Papercut\{Document, Page, Table, TableRow, Text};
use function Papercut\{mm, pt};

$rows = [];
for ($i = 1; $i <= 120; $i++) {
    $rows[] = ["Item $i", $i * 3, number_format($i * 12.5, 2)];
}

$table = Table::make()
    ->columns('1fr 25mm 30mm')
    ->headerRow(TableRow::make(
        Text::make('Name')->bold(),
        Text::make('Qty')->bold()->right(),
        Text::make('Price')->bold()->right(),
    )->background('slate-100'))
    ->rows($rows, fn (array $r) => [$r[0], Text::make((string) $r[1])->right(), Text::make($r[2])->right()]);

$document = Document::make(Page::a4()->margin(mm(20))->body($table));
```

## Headers, footers and page numbers

`Page::header()`, `footer()` and `background()` repeat on every page the body fills.
`PageNumber::make('Page {page} of {total}')` fills in both numbers, even though `{total}` is only known after layout.

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

## Pagination controls

- `keepTogether()`: do not split this block across pages; move it whole to the next page.
- `keepWithNext()`: keep this block on the same page as its next sibling (headings use this by default).
- `breakBefore()` / `breakAfter()` and `PageBreak::make()`: force a new page.
- `minLines(n)`: minimum lines left at the bottom (and carried to the top) when a paragraph splits.

```php
use Papercut\{Column, Document, Page, PageBreak, Text};
use function Papercut\{mm, pt};

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::h2('Section one')->keepWithNext(),
            Text::make(str_repeat('Body text. ', 200))->minLines(3),
            PageBreak::make(),
            Text::h2('Section two')->breakBefore(false),
            Column::make(Text::make('A block'), Text::make('that stays whole'))->keepTogether(),
        )->gap(pt(8)),
    ),
);
```

## Images and links

```php
use Papercut\{Column, Document, Image, Link, Page, Text};
use Papercut\Style\ImageFit;
use function Papercut\{mm, pt};

// A 1x1 PNG, inline to keep the example self-contained. Use Image::file('logo.png') in practice.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Image::bytes($png)->fit(ImageFit::Contain)->width(mm(30))->height(mm(30)),
            Link::to('https://example.com', Text::make('A link to example.com')->underline()),
        )->gap(pt(8)),
    ),
);
```

## Fonts

Inter (Regular, Bold, Italic, BoldItalic) is bundled, subset and embedded; the 14 standard PDF fonts are never
used. Add your own families with a `FontSet` of `FontFace`s, pick them with `font()`, and list fallback families for glyphs
the primary font lacks.

```php
use Papercut\{Column, Document, Page, Text};
use Papercut\Export\PdfOptions;
use Papercut\Text\{FontFace, FontRegistry, FontSet};
use function Papercut\{mm, pt};

// Add your own family (paths to TTFs), here reusing the bundled files under another name:
$inter = dirname((new ReflectionClass(FontRegistry::class))->getFileName(), 3) . '/resources/fonts/Inter';
$fonts = (new FontSet())
    ->with(new FontFace('Brand', $inter . '/Inter-Regular.ttf', 400), new FontFace('Brand', $inter . '/Inter-Bold.ttf', 700))
    ->withFallbacks('Inter');

$document = Document::make(
    Page::a4()->margin(mm(20))->body(
        Column::make(
            Text::make('Set in Brand')->font('Brand')->size(pt(18)),
            Text::make('Brand bold')->font('Brand')->bold(),
        )->gap(pt(8)),
    ),
);
$options = new PdfOptions(fonts: $fonts);
// $document->toPdf($options)->save('out.pdf');
```

The backend shapes and reorders text within a single drawn run (kerning, ligatures). Right-to-left and bidirectional
paragraph layout (direction, reordering across line breaks, direction-aware alignment) is unsupported and untested
in v0.1, and so is HarfBuzz shaping.

## Fonts in HTML

`HtmlOptions::fontMode` decides how a browser gets the fonts of an HTML export:

- `FontMode::None` (default): only the `font-family` stack is written. The browser uses what it has.
- `FontMode::Embed`: the registered files are embedded as `data:` URIs in `@font-face` rules.
- `FontMode::Link`: the registered files are linked; the `fontUrl` callback returns each face's URL.
- `FontMode::Sources`: an ordered stack of font sources decides, family by family, where the browser gets the font. Pass the stack as `fontSources`. A source is a `FontSource` object or a closure `fn (FontRequest $r): ?FontAnswer`; return `null` to pass a family to the next source.

```php
use Papercut\{Document, Page, Text};
use Papercut\Export\{FontMode, HtmlOptions};
use Papercut\Html\{GoogleFonts, LocalFonts};

$document = Document::make(Page::a4()->body(Text::make('Hello')));

// Google Fonts
$google = new HtmlOptions(fullPage: true, fontMode: FontMode::Sources, fontSources: [new GoogleFonts()]);

// Bunny Fonts: same API, another host
$bunny = new HtmlOptions(fullPage: true, fontMode: FontMode::Sources, fontSources: [new GoogleFonts(host: 'https://fonts.bunny.net')]);

// Mixed: your own Acme font is embedded, everything else comes from Google
$mixed = new HtmlOptions(
    fullPage: true,
    fontMode: FontMode::Sources,
    fontSources: [LocalFonts::embed(['Acme']), new GoogleFonts()],
);

$html = $document->toHtml($google)->bytes();
```

A full page gets one `<link rel="stylesheet">` per stylesheet in `<head>`. A fragment has no head, so the same stylesheets are written as `@import` rules at the very start of its `<style>` block (a browser ignores an `@import` that follows another rule).

**Order matters.** Each family goes to the first source that answers for it, so put specific sources first and catch-all hosts last. `GoogleFonts` accepts every family unless you give it an allowlist, so a catch-all `GoogleFonts` placed above `LocalFonts` would take your own families. For the same reason Bunny above Google only makes sense when Bunny has an allowlist (`new GoogleFonts(host: 'https://fonts.bunny.net', families: ['Inter'])`).

A source that answers with an unsafe URL (a scheme other than http or https, a protocol-relative `//host`, control characters, an empty face list) is skipped, a warning is sent to `onWarning`, and the next source is asked. A family no source answers for is reported as `font not linked` (an approximation) and the browser falls back to the `font-family` stack.

**Privacy.** With a third-party host the visitor's browser sends its IP address to that host. LG München I (3 O 17493/20, January 2022) held that this can breach data protection law without consent. Moving to Bunny Fonts changes the host, not the question. The fully private routes are `LocalFonts`, `FontMode::Embed` and `FontMode::Link`, which serve fonts you control.

**Content Security Policy.** Allow the stylesheet host in `style-src` (for example `https://fonts.googleapis.com`) and the font host in `font-src` (`https://fonts.gstatic.com` for Google; `data:` for embedded faces).

**Limits.** The font stylesheets are built from the families and weights the document uses. Google rejects the stylesheet of a family it does not have, or of a weight it does not offer, and the browser then falls back silently. The library cannot see that, because it never fetches the stylesheet.

## Kerning cache

Parsing a font's GPOS pair-kerning table costs tens of milliseconds, so the default backend stores it on disk and
reads it back in later processes. Each font face gets one file of roughly 5-6 MB (the pair table, serialised) in
`sys_get_temp_dir()/papercut-cache-<uid>`. The key covers the font's path, mtime and size plus the php-pdf version,
so an updated font or library simply gets a new entry. Entries unused for 30 days are pruned. Any problem (an
unwritable directory, a symlinked or foreign-owned one, a corrupt file) falls back silently to an in-memory parse;
output is identical either way.

```php
use Papercut\{Document, Page, Text};
use Papercut\Export\PdfOptions;

$document = Document::make(Page::a4()->body(Text::make('Hello')));
$options = new PdfOptions(cacheDir: '/var/cache/myapp/papercut'); // your own directory
$options = new PdfOptions(cacheDir: false);                       // no disk cache
```

## Errors

Every error extends `Papercut\PapercutException` and says what is wrong and where.

| Exception | Cause |
|---|---|
| `UnknownAttribute` | A style attribute that does not exist; the message suggests the closest valid name. |
| `InvalidValue` | A value that cannot be parsed (`'12 parsecs'`, a bad colour, a bad track list). |
| `FontNotFound` | A family/weight/style that is not registered. |
| `ImageUnreadable` | A file or bytes that are not a supported image. |
| `LayoutOverflow` | Content that cannot fit on an empty page. |

By default overflow throws. `new PdfOptions(overflow: OverflowMode::Clip)` clips instead and reports through
`onWarning`.

```php
use Papercut\{Column, Document, Page, PapercutException, Text};
use function Papercut\{mm, pt};

try {
    $text = Text::make('Oops')->attributes(['colour' => 'red']);
} catch (PapercutException $e) {
    $text = Text::make('Caught: ' . $e->getMessage());
}

$document = Document::make(Page::a4()->margin(mm(20))->body(Column::make($text)->gap(pt(4))));
```

## Examples

Runnable scripts in [`examples/core`](../../examples/core): an invoice, a multi-page transcript and a report.

## License

MIT. Bundled Inter font: SIL Open Font License 1.1 (`resources/fonts/Inter/LICENSE.txt`).
Color palette derived from Tailwind CSS (MIT). See [`NOTICE.md`](../../NOTICE.md).
