# Layout

Papercut's layout works like a simplified flexbox. Each box measures its children against the width it has, then divides up whatever space is left over.

## Rows, columns and views

| PHP | Blade | Arranges children |
|---|---|---|
| `Row::make(...)` | `<x-doc::row>` | side by side |
| `Column::make(...)` | `<x-doc::column>` | top to bottom |
| `View::make(...)` | `<x-doc::view>` | top to bottom, or set `direction` to change it |

Rows and columns are just views with the direction already set, so use whichever reads best.

::: code-tabs#style
@tab Blade
```blade
<x-doc::row gap="12pt" align="center">
  <x-doc::text bold>Northwind Studio</x-doc::text>
  <x-doc::spacer />
  <x-doc::text color="slate-500">Invoice INV-2026-0142</x-doc::text>
</x-doc::row>
```
@tab PHP
```php
use Papercut\{Document, Page, Row, Spacer, Text};
use Papercut\Style\Align;
use function Papercut\pt;

$document = Document::make(Page::a4()->body(
    Row::make(
        Text::make('Northwind Studio')->bold(),
        Spacer::flex(),
        Text::make('Invoice INV-2026-0142')->color('slate-500'),
    )->gap(pt(12))->align(Align::Center),
));
```
:::

The spacer takes up all the free space in the row, which pushes the two pieces of text to opposite edges.

## Dividing up the space

These work the same way as their flexbox namesakes:

| Method | Blade | What it does |
|---|---|---|
| `gap()` | `gap="8pt"` | Adds space between children. |
| `justify()` | `justify="space-between"` | Positions children along the main axis: `start`, `end`, `center`, `space-between`, `space-around` or `space-evenly`. |
| `align()` | `align="center"` | Positions children on the cross axis: `start`, `end`, `center` or `stretch`. |
| `alignSelf()` | `align-self="end"` | Overrides `align` for a single child. |
| `grow()` | `grow="1"` | How much of the free space a child takes. |
| `shrink()` | `shrink="0"` | How much a child gives up when there isn't enough room. |
| `basis()` | `basis="40%"` | A child's starting size, before growing or shrinking. |
| `wrap()` | `wrap` | Lets a row wrap onto more than one line. |

`Spacer::flex()` grows to fill the available space, and `Spacer::size(mm(10))` adds a fixed gap. In Blade, both are `<x-doc::spacer>`: add a `size` for a fixed gap, or leave it off for a flexible one.

## Sizing boxes

Every component accepts `width`, `height` and their `min-` and `max-` versions, plus `padding`, `margin`, `border`, `radius`, `background` and `opacity`. Padding and margin take one to four lengths in the same order as CSS: top, right, bottom, left.

```php
use Papercut\{Column, Document, Page, Text};
use function Papercut\{mm, percent, pt};

$document = Document::make(Page::a4()->margin(mm(15))->body(
    Column::make(
        Text::make('Half width')->width(percent(50))->padding(pt(4), pt(8)),
        Text::make('Fixed 5 cm')->width(mm(50))->border(pt(1), 'slate-300'),
        Text::make('Tinted')->background('slate-50')->radius(pt(6))->padding(pt(10)),
    )->gap(mm(4)),
));
```

## Units

Lengths always need a unit. A bare `12` isn't accepted, so there's never any doubt about whether it means points, pixels or millimetres.

| Unit | PHP | Blade |
|---|---|---|
| points | `pt(12)` | `12pt` |
| millimetres, centimetres, inches | `mm(20)`, `cm(2)`, `in(1)` | `20mm`, `2cm`, `1in` |
| CSS pixels (0.75pt each) | `px(16)` | `16px` |
| the element's font size | `Length::em(0.5)` | `0.5em` |
| the element's line height | `Length::lh(4)` | `4lh` |
| a percentage of the parent | `percent(50)` | `50%` |
| a share of a table's free width | `fr(1)` | `1fr` |

`em` and `lh` scale with the theme. A box that's `4lh` tall stays four lines tall even if you change the body font size later.

Colours can be Tailwind palette names like `slate-500` or `indigo-600`, or hex codes like `#336699`.

## Next

[Text](./text.md) covers paragraphs, headings, inline formatting and lists.
