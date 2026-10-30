# Text

Papercut measures text using the actual metrics of the embedded font, so line breaks land where they should. Lines wrap between words, justified text stretches the spaces between words, and bold or italic runs keep their style when they wrap onto the next line.

## Paragraphs and headings

| PHP | Blade | Use it for |
|---|---|---|
| `Text::make('…')` | `<x-doc::text>` | Plain text in whatever style it inherits. |
| `Text::p('…')` | `<x-doc::p>` | A paragraph, using the theme's paragraph style. |
| `Text::h1('…')` to `h4` | `<x-doc::h1>` to `<x-doc::h4>` | Headings. They stay on the same page as the block after them (see below). |

Headings keep with the next block by default, so a heading isn't left alone at the bottom of a page. Turn that off with `->keepWithNext(false)` in PHP, or `:keep-with-next="false"` in Blade. In Word the heading paragraph gets keep-with-next, and in HTML it gets `break-after: avoid`. If a heading and the block after it can't fit on one page together, the heading still ends up alone.

Font, size, weight, colour and alignment are inherited, so you can set them on a box and the text inside will follow:

::: code-tabs#style
@tab Blade
```blade
<x-doc::column color="slate-600" font-size="9pt">
  <x-doc::text>Both lines inherit the colour and size.</x-doc::text>
  <x-doc::text bold align="right">This one is bold and right-aligned.</x-doc::text>
</x-doc::column>
```
@tab PHP
```php
use Papercut\{Column, Document, Page, Text};
use function Papercut\pt;

$document = Document::make(Page::letter()->body(
    Column::make(
        Text::make('Both lines inherit the colour and size.'),
        Text::make('This one is bold and right-aligned.')->bold()->right(),
    )->color('slate-600')->size(pt(9)),
));
```
:::

On text tags, `align` and `size` are shorthand for `text-align` and `font-size`. On boxes, `align` positions the children instead.

## Keeping spaces and line breaks

By default runs of whitespace collapse to one space and a newline is just a space. The `white-space` style attribute (`Style::make(whiteSpace: WhiteSpace::PreWrap)` in PHP) changes that. It takes `normal` (the default) or `pre-wrap`, and it is inherited, so it can be set on a box or on a single span.

Under `pre-wrap`:

- spaces are kept, one for each space in the text, including a line's leading spaces;
- a newline (`\n`, `\r\n` or `\r`) starts a new line;
- a tab moves to the next multiple of four space widths, counted from the start of the line;
- a line longer than the box still wraps, at a space where it can and anywhere otherwise. The spaces at a wrap point are dropped rather than carried to the next line, but the spaces that start a line of the source stay;
- justified text is not stretched on these lines, since their spaces are layout.

A `pre-wrap` span inside normal text keeps its own spaces while the text around it collapses. In HTML a `pre` block is written as `<pre>` and inline `code` as `<code>`, with `white-space: pre-wrap` and `tab-size: 4`. In Word the spaces are kept, tabs become real tabs and line breaks become `w:br`; Word's tab stops differ from four space widths, so tabbed text can line up differently there than in the PDF.

In Blade, `<x-doc::pre>` makes a block in the `pre` style (monospace, `pre-wrap`) and `<x-doc::code>` an inline `code` span inside a text tag. Both take their content literally, so a variable echoed with `{{ $code }}` keeps its `<`, `&` and quotes:

```blade
<x-doc::p>Call <x-doc::code>render()</x-doc::code> once.</x-doc::p>
<x-doc::pre>
    if ($a < $b && $c) {
        echo "ok";
    }
</x-doc::pre>
```

## Code blocks

A code block is a `pre` block with a language: it is coloured by a TextMate theme and carries a small label with the language's name. Highlighting uses [Phiki](https://github.com/phikiphp/phiki), which is optional:

```bash
composer require phiki/phiki
```

::: code-tabs#style
@tab Blade
```blade
<x-doc::pre language="php">
    $total = array_sum($prices);
    echo "Total: {$total}";
</x-doc::pre>
```
@tab PHP
```php
use Papercut\{Document, Page};
use Papercut\Decorator\CodeBlock;

$document = Document::make(Page::letter()->body(
    CodeBlock::make('$total = array_sum($prices);')->language('php'),
));
```
:::

Blade content is literal, as for any `pre`. `{{ $code }}` comes out exactly as the variable's value.

Without Phiki, or for a language it doesn't know, the block is still drawn, as plain text. An unknown theme is replaced by the default theme and the code is still highlighted. In all three cases the export reports "dropped syntax highlighting" (for a missing package, the note says to install `phiki/phiki`), and [strict mode](/guide/rich-text.md#strict-mode) throws instead.

### Labels

The label is the language's display name in upper case, so `language="php"` shows `PHP`. `label` replaces it, and shows your text as written. `false` turns it off:

```blade
<x-doc::pre language="json" label="composer.json">{ "name": "acme/app" }</x-doc::pre>
<x-doc::pre language="json" :label="false">{ "name": "acme/app" }</x-doc::pre>
```

In PHP, that is `->label('composer.json')` and `->label(false)`. The plain words `text`, `plaintext`, `txt` and `plain` give a `TEXT` label and no colours. `nohighlight` and `no-highlight` give neither a label nor colours.

### Looks

By default the label sits at the top right of the box, on a small line above the code. Two variants move it:

- `strip`: a header bar across the top of the box, with the label in it.
- `caption`: the label above the box, and a rule down the left side instead of a border.

```blade
<x-doc::pre language="php" variant="strip">$a = 1;</x-doc::pre>
```

In PHP use `->variant('strip')` or `->variant('caption')`; the two can't be combined. Set a default for every block with `papercut.code.variant` in Laravel. In plain PHP there is no document-wide default: pass `new CodeHighlighting(variant: 'caption')` to each block with `->highlighting()`, or to Markup with `new MarkupOptions(code: new CodeHighlighting(variant: 'caption'))`.

### Keeping a block on one page

A block that would straddle a page break is split like any other text. To move it to the next page whole, use `->keepTogether()` in PHP, or `keep-together` on `<x-doc::pre>`:

```blade
<x-doc::pre language="php" keep-together>$a = 1;</x-doc::pre>
```

A block taller than a page still splits. In Word the default look keeps its lines together, and `strip` keeps its header bar with the code.

### Themes and colours

The colours come from the TextMate theme: the block's background and text, and every token. The default theme is `papercut-light`. Pick another per block, or for all of them:

::: code-tabs#style
@tab Blade
```blade
<x-doc::pre language="php" theme="github-dark">$a = 1;</x-doc::pre>
```
@tab PHP
```php
use Papercut\Code\CodeHighlighting;
use Papercut\Decorator\CodeBlock;

CodeBlock::make('$a = 1;')->language('php')->theme('github-dark');

// every block that gets this configuration
CodeBlock::make('$a = 1;')->language('php')->highlighting(new CodeHighlighting(theme: 'nord'));
```
:::

Phiki bundles many themes and languages, among them `github-light`, `github-dark`, `dracula`, `nord`, `one-dark-pro` and `solarized-light`. The full lists are in [Phiki's documentation](https://phiki.dev). An unknown theme falls back to the default, with a "dropped syntax highlighting" note.

The muted label and border colours are worked out from the brightness of the theme's background, so a dark theme gets a light label. Geometry (padding, radius, the label's size) comes from the `code-block` [named styles](/reference/decorator-styles.md), and the block's own style, such as `->background()` or `background="…"`, overrides the theme's colours. To recolour every block, pick or write a theme.

### Your own grammars and themes

Register a grammar (a TextMate grammar JSON file), a theme (a VS Code or TextMate theme JSON file), an alias, or an injection grammar on the highlighter:

::: code-tabs#style
@tab Laravel
```php
// config/papercut.php
'code' => [
    'theme' => 'my-theme',
    'grammars' => ['demo' => resource_path('grammars/demo.tmLanguage.json')],
    'themes' => ['my-theme' => resource_path('themes/my-theme.json')],
    'aliases' => ['dm' => 'demo'],
    'injections' => [
        ['path' => resource_path('grammars/todo.injection.json'), 'into' => ['source.demo', 'source.php']],
    ],
],
```
@tab PHP
```php
use Papercut\Code\{CodeHighlighting, PhikiHighlighter};
use Papercut\Decorator\CodeBlock;

$highlighter = (new PhikiHighlighter())
    ->grammar('demo', 'grammars/demo.tmLanguage.json')
    ->theme('my-theme', 'themes/my-theme.json')
    ->alias('dm', 'demo')
    ->injection('grammars/todo.injection.json', ['source.demo', 'source.php']);

$block = CodeBlock::make('demo text')
    ->language('dm')
    ->highlighting(new CodeHighlighting($highlighter, theme: 'my-theme'));
```
:::

Register on your own `new PhikiHighlighter()` and pass it to `new CodeHighlighting($highlighter, ...)`, as above. Don't register on the shared default (`CodeHighlighting::default()->highlighter()`): it is one instance for the whole process, so everything you add would reach every document after it.

An injection grammar (VS Code style, with an `injectionSelector` and `patterns`) is merged into each grammar listed in `into`, named by scope. It applies when one of those is the block's language, not where that grammar is only embedded inside another. Register the host grammars first (in config, `grammars` is read before `injections`); bundled ones are already there. A registered grammar can be embedded in another by its scope name. A path that doesn't exist, or a file that isn't a valid grammar or theme, throws when the highlighter is built, and in Laravel the message names the config key, such as `papercut.code.grammars.demo`.

### In markup

`Markup::html()` and `<x-doc::markup>` turn `<pre><code class="language-php">` into a code block, using the document's defaults (theme, variant, registered grammars). `lang-php` works too, and so does the class on the `pre`. Tags inside the code are dropped and their text is kept, with an approximated note for anything other than `span`, `code` and `br`; `<br>` becomes a newline. To keep these as plain `pre` blocks, turn it off: `new MarkupOptions(code: false)` in PHP, or `'markup' => ['highlight' => false]` in Laravel. In plain PHP, set the defaults for these blocks with `new MarkupOptions(code: new CodeHighlighting(theme: 'github-dark', variant: 'strip'))`.

### Word and HTML

In Word, the default look is shaded, bordered paragraphs, and `strip` and `caption` are tables. In HTML, a code block is a `<pre><code>` with coloured spans, and `class="language-php"` on the `<code>` when there is a language.

Word can't round corners, so the default look's `radius` is reported as dropped (`strip` has it too; `caption` has none). Strict Word export throws for it unless you set `radius: 0` on the block. The label's weight of 600 is reported as approximated, which strict export allows.

## Inline formatting

Text tags in Blade, and `Text::markup()` in PHP, understand a handful of inline tags: `<b>`, `<i>`, `<u>`, `<s>`, `<mark>`, `<span>` with style attributes, `<a href>`, `<br>` and `<code>`. Any other tag throws an error.

::: code-tabs#style
@tab Blade
```blade
<x-doc::text>
  Plain, <b>bold</b>, <i>italic</i>, <span color="indigo-600">coloured</span> and a <mark>highlighted</mark> word.
</x-doc::text>
```
@tab PHP
```php
use Papercut\{Document, Page, Text};

$document = Document::make(Page::letter()->body(
    Text::markup('Plain, <b>bold</b>, <i>italic</i>, <span color="indigo-600">coloured</span> and a <mark>highlighted</mark> word.'),
));
```
:::

`<mark>` gives its text a `yellow-200` background. `<code>` sets it in the monospace font with the `code` style.

In PHP you can also build the runs of text yourself:

```php
use Papercut\{Document, Page, Span, Text};
use Papercut\Style\Style;

$document = Document::make(Page::letter()->body(
    Text::spans(
        Span::text('Built from '),
        Span::text('spans', new Style(fontWeight: 700)),
        Span::break(),
        Span::text('after a line break.'),
    ),
));
```

A span can also carry a theme style name and a `base` style (what was set outside it). The span's own style wins over the named style, which wins over `base`. Markup sets these; you rarely need to.

This page is about text you write in your templates. For HTML from a rich text editor, or plain text from a form, see [Rich text](./rich-text.md).

## Lists

`ListNode::ordered()` and `ListNode::unordered()` take a list of items. Strings and other components are wrapped in a list item for you. An item can hold any content, including another list.

::: code-tabs#style
@tab Blade
```blade
<x-doc::ol start="3" marker="upper-alpha">
  <x-doc::li>
    Plan
    <x-doc::ul><x-doc::li>Scope</x-doc::li><x-doc::li>Budget</x-doc::li></x-doc::ul>
  </x-doc::li>
  <x-doc::li>Build</x-doc::li>
  <x-doc::li value="10">Ship</x-doc::li>
</x-doc::ol>
```
@tab PHP
```php
use Papercut\{Document, ListItem, ListNode, Page};
use Papercut\Style\ListMarker;

$document = Document::make(Page::letter()->body(
    ListNode::ordered(
        ListItem::make('Plan', ListNode::unordered('Scope', 'Budget')),
        'Build',
        ListItem::make('Ship')->withValue(10),
    )->start(3)->marker(ListMarker::UpperAlpha),
));
```
:::

If you don't pick a marker, it changes with the nesting depth: numbered lists go `1.`, `a.`, `i.` and bulleted lists go `•`, `◦`, `▪`. Markers are right-aligned, so `9.` and `10.` line up neatly.

| Marker | Looks like |
|---|---|
| `decimal`, `lower-alpha`, `upper-alpha`, `lower-roman`, `upper-roman` | `1.` `a.` `A.` `i.` `I.` |
| `disc`, `circle`, `square` | `•` `◦` `▪` |
| `none` | no marker, but the indent stays |

A long list item can split across pages, and its marker always stays with the first line.

A top-level list has the theme's paragraph spacing after it. If the last item already ends with that spacing, for example because it ends in a paragraph, the list doesn't get it twice. Nested lists add none, and neither does a list that is the last thing in a table cell, or in a box with padding, a border or a background, since the box's own edge follows it there. A list with a margin set keeps the spacing that margin gives it.

In Word export, lists are real Word lists, except a list with marker `none`, which stays as indented paragraphs. Press Enter in Word and the list continues, and renumbering and the bullet and number buttons work. Word draws the markers its own way (left-aligned), unlike the right-aligned markers in the PDF. A very wide label, such as `VIII.` or `100.` and beyond, pushes the item's text to the next tab stop in Word, and alphabetic numbering past `z` follows Word (`aa`, `bb`, and so on). Nested lists sit one level deeper, and extra blocks in an item, such as paragraphs and tables, stay indented under it. An item whose number breaks the sequence partway through a numbered list (`ListItem::withValue()` or `<li value>`) starts a new Word list from that item on, and this is reported as approximated. A value on the first item is the list's start number. Bulleted lists ignore these values. Lists whose numbers Word can't show, meaning any number below 1 or roman numerals above 3999, keep typed markers instead, also reported as approximated.
