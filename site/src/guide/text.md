# Text

Papercut measures text using the actual metrics of the embedded font, so line breaks land where they should. Lines wrap between words, justified text stretches the spaces between words, and bold or italic runs keep their style when they wrap onto the next line.

## Paragraphs and headings

| PHP | Blade | Use it for |
|---|---|---|
| `Text::make('…')` | `<x-doc::text>` | Plain text in whatever style it inherits. |
| `Text::p('…')` | `<x-doc::p>` | A paragraph, using the theme's paragraph style. |
| `Text::h1('…')` to `h4` | `<x-doc::h1>` to `<x-doc::h4>` | Headings. They stay on the same page as the block after them. |

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

$document = Document::make(Page::a4()->body(
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

$document = Document::make(Page::a4()->body(
    Text::markup('Plain, <b>bold</b>, <i>italic</i>, <span color="indigo-600">coloured</span> and a <mark>highlighted</mark> word.'),
));
```
:::

`<mark>` gives its text a `yellow-200` background. `<code>` sets it in the monospace font with the `code` style.

In PHP you can also build the runs of text yourself:

```php
use Papercut\{Document, Page, Span, Text};
use Papercut\Style\Style;

$document = Document::make(Page::a4()->body(
    Text::spans(
        Span::text('Built from '),
        Span::text('spans', new Style(fontWeight: 700)),
        Span::break(),
        Span::text('after a line break.'),
    ),
));
```

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

$document = Document::make(Page::a4()->body(
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
