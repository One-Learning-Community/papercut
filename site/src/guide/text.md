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

## Inline formatting

Text tags in Blade, and `Text::markup()` in PHP, understand a handful of inline tags: `<b>`, `<i>`, `<u>`, `<s>`, `<mark>`, `<span>` with style attributes, `<a href>` and `<br>`. Any other tag throws an error.

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

`<mark>` gives its text a `yellow-200` background.

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
