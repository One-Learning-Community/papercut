# Rich text

Sometimes the content isn't in your template. It's HTML saved from a rich text editor, or plain text someone typed into a form. `Markup` converts either one into Papercut components. It handles a practical subset of HTML, and tells you about anything it couldn't convert. There's no browser involved.

::: code-tabs#style
@tab Blade
```blade
<x-doc::markup :html="$slide->body" />
<x-doc::markup :text="$prompt" />
<x-doc::markup :html="$slide->body" styles="original" blank-paragraphs="keep" />
```
@tab PHP
```php
use Papercut\{Column, Document, Page};
use Papercut\Markup\{Markup, MarkupOptions, StyleMode};
use function Papercut\pt;

$slide = '<h2>Agenda</h2><p>Welcome to <b>Papercut</b>.</p><ol start="3"><li>Intro</li><li>Demo</li></ol>';

$document = Document::make(Page::a4()->body(
    Column::make(
        Markup::html($slide, new MarkupOptions(styles: StyleMode::Theme)),
        Markup::text("Line one\nLine two\n\nA new paragraph."),
    )->gap(pt(8)),
));
```
:::

In Blade, bind the value with `:html` rather than `html`, otherwise Blade escapes the HTML before Papercut sees it.

`Markup::text()` turns each newline into a line break and each blank line into a new paragraph. Characters like `<` are treated as plain text.

## Inside a line of text

Put markup inside a text tag and it joins that line, picking up the surrounding style:

```blade
<x-doc::text bold>× <x-doc::markup :html="$label" /></x-doc::text>
```

In PHP, `Markup::flatten()` does the same thing and merges the converted content into a single `Text`. Block-level content gets flattened and images are dropped, and both are reported.

## What gets converted

| HTML | Becomes |
|---|---|
| `p`, `div` | a paragraph |
| `h1` to `h6` | the theme's headings (`h5` and `h6` use the `h4` style) |
| `b`, `strong`, `i`, `em`, `u`, `s`, `strike`, `del`, `span`, `br` | inline formatting |
| `a href` | a link, for `http`, `https`, `mailto`, `tel` and `#fragment` URLs |
| `ol`, `ul`, `li` | a list, including `start` and `type` on `ol` and `value` on `li` |
| `img` | an image, loaded by your resolver (see below) |
| `figure`, `figcaption` | the content with its caption underneath |
| `iframe`, `video`, `audio` | a paragraph linking to the source |
| `blockquote` | a quotation, styled by the theme's blockquote decorator. A trailing `cite` or `footer` becomes its attribution |
| `mark` | inline text with a highlight |
| `pre` | a block in the `pre` style: monospace, with spaces, tabs and line breaks kept. `<pre><code>` gives one block |
| `code`, `kbd`, `samp`, `tt` | inline monospace text in the `code` style |
| `table`, `caption`, `thead`, `tbody`, `tfoot`, `tr`, `td`, `th` | a table: header rows repeat on every page, `colspan` and `rowspan` are kept, and columns are sized by their content. In `original` mode a `width` on a cell sets its column and a `width` on the table sets the table; a table width is kept as written but never wider than its container, and columns wider than the space they have are scaled down to fit (percentages over 100% are scaled to 100%, with a note). Nested tables are flattened into paragraphs, with a note |
| `hr` | a horizontal rule; in `original` mode `border-color` or `color` sets its colour |
| other block tags, like `section` | the tag is dropped, and its content kept as paragraphs |
| anything else | the tag is dropped, and its content kept inline |

`script`, `style`, `template`, `head`, `noscript`, `object` and `embed` are removed along with everything inside them. Tables and rules take the named styles `markup.table`, `markup.th`, `markup.td` and `markup.hr`, so you can restyle them through `papercut.styles` like any other style. Classes, ids and `data-*` attributes are ignored. Empty paragraphs are dropped unless you ask to keep them.

## Style modes

- **`theme`** (the default): the HTML provides the structure, and your theme decides how it looks. Inline `style="…"` attributes are ignored.
- **`original`**: inline styles are applied on top of the theme. Colour, font size, weight, italic and text decoration work everywhere. Background, alignment, margin, padding and sizes work on block elements. Anything else is dropped and reported.

## Images

Papercut never downloads an `<img src>` by itself. Instead, you give it a resolver: a function that receives the `src` exactly as written and returns the image data, or `null` if the image can't be found.

```php
use Papercut\{Document, Page};
use Papercut\Markup\{Markup, MarkupOptions};

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$markup = new MarkupOptions(images: fn (string $src): ?string => $src === 'logo.png' ? $png : null);

$document = Document::make(Page::a4()->body(
    Markup::html('<p><img src="logo.png" width="40"></p><p><img src="gone.png" alt="Missing logo"></p>', $markup),
));
```

When an image is missing, its `alt` text is shown in italics. In Laravel, set `papercut.markup.images` to a resolver class. The [Laravel configuration page](/laravel/configuration.md#markup-images) has a full example.

::: danger Treat src as user input
Your resolver decides which files can be read. Don't pass `src` straight to the filesystem or a storage disk. Check it against the paths you expect, and reject anything containing `..`.
:::

## Conversion warnings

Everything Markup couldn't convert exactly is recorded, and those notes are passed to the warning callback and the fidelity report of each export. There are two kinds:

- **Approximated:** the content is there, but looks different from the original. For example, an iframe shown as a link, a missing image shown as its alt text, or an unknown tag removed with its content kept.
- **Dropped:** the content is gone. For example, a missing image with no alt text, a link with an unsupported URL, or an unsupported CSS property.

## Strict mode

If you'd rather fail than silently lose content, turn on strict mode with `strict: true` in the export options, or `'strict' => true` in the Laravel config. An export that would drop content then throws `UnsupportedFeature` instead. Approximations are still allowed.

Strict mode works well in tests: a template that starts losing content fails the build instead of shipping.
