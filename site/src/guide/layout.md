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
use function Papercut\pt;

$document = Document::make(Page::letter()->body(
    Row::make(
        Text::make('Northwind Studio')->bold(),
        Spacer::flex(),
        Text::make('Invoice INV-2026-0142')->color('slate-500'),
    )->gap(pt(12))->align('center'),
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

`Spacer::flex()` grows to fill the available space, and `Spacer::fixed(in(0.4))` adds a fixed gap. In Blade, both are `<x-doc::spacer>`: add a `size` for a fixed gap, or leave it off for a flexible one.

## Sizing boxes

Every component accepts `width`, `height` and their `min-` and `max-` versions, plus `padding`, `margin`, `border`, `radius`, `background` and `opacity`. Padding and margin take one to four lengths in the same order as CSS: top, right, bottom, left.

```php
use Papercut\{Column, Document, Page, Text};
use function Papercut\{in, percent, pt};

$document = Document::make(Page::letter()->margin(in(0.6))->body(
    Column::make(
        Text::make('Half width')->width(percent(50))->padding(pt(4), pt(8)),
        Text::make('Fixed 2 in')->width(in(2))->border(pt(1), 'slate-300'),
        Text::make('Tinted')->background('slate-50')->radius(pt(6))->padding(pt(10)),
    )->gap(in(0.15)),
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

## Column flow

A column flow sets its content in newspaper columns, the way Word's columns work. Content fills the first column from top to bottom, continues at the top of the second, and moves to the next page after the last column. Nothing spans columns: for a full-width title above columns, or a note below them, end the flow and put the full-width content outside it.

| PHP | Blade |
|---|---|
| `ColumnFlow::make(...)` | `<x-doc::column-flow>` |
| `ColumnBreak::make()` | `<x-doc::column-break />` |

::: code-tabs#style
@tab Blade
```blade
<x-doc::h2>Notes from the field</x-doc::h2>
<x-doc::column-flow columns="3" column-gap="18pt" rule>
  <x-doc::text>The first survey went out in March…</x-doc::text>
  <x-doc::text>Readers asked for shorter sections…</x-doc::text>
  <x-doc::column-break />
  <x-doc::text>A second round followed in June…</x-doc::text>
</x-doc::column-flow>
```
@tab PHP
```php
use Papercut\{ColumnBreak, ColumnFlow, Document, Page, Text};
use function Papercut\pt;

$document = Document::make(Page::letter()->body(
    Text::h2('Notes from the field'),
    ColumnFlow::make(
        Text::make('The first survey went out in March…'),
        Text::make('Readers asked for shorter sections…'),
        ColumnBreak::make(),
        Text::make('A second round followed in June…'),
    )->columns(3)->columnGap(pt(18))->columnRule(),
));
```
:::

`ColumnFlow::make()` returns a view with two equal columns and a 36pt gap, Word's half inch. The settings are the ones a Word section has:

| PHP | Blade | Sets |
|---|---|---|
| `columns(3)` | `columns="3"` | The number of equal columns, from 1 to 45. |
| `columnWidths(2, 1)` | `widths="2 1"` | Unequal columns, as ratios of the space left after the gaps. The number of widths is the number of columns, so with `columns` too the two must agree. |
| `columnGap(pt(24))` | `column-gap="24pt"` | The space between columns. Give one length for every gap, or one per space (`columnGap(pt(24), pt(12))`) when the gaps differ. |
| `columnRule()` | `rule` | A thin line in each gap. `columnRule(pt(1), Color::parse('slate-400'))` and `rule="1pt slate-400"` set the width (default 0.5pt) and the colour (default black). |

`gap` is the ordinary style gap, as on any box: it spaces the flow's children, and `columnGap()` / `column-gap` is the space between columns. The flow itself can't have its own border, background, padding or size. To draw a box around columns, wrap the flow in a `View`:

```php
View::make(
    ColumnFlow::make(/* … */)->columns(2),
)->borderWidth(Edges::all(pt(1)))->borderColor('slate-300')->padding(pt(12));
```

### How content flows

- Content fills column 1, then column 2 and so on, then column 1 of the next page, under that page's header and above its footer. Text splits between lines across columns as it does across pages, and tables and lists split as they do across pages.
- `keepTogether` and `keepWithNext` work at column breaks as they do at page breaks, so a heading stays with its first paragraph at the bottom of a column.
- A `PageBreak` ends the page. What follows starts in column 1 of the next page, still in the same flow.
- A `ColumnBreak` sends what follows to the top of the next column. In the last column it goes to the next page. Outside a flow it's an error. Breaks collapse at the top of a column: a `ColumnBreak` right before a `PageBreak` doesn't add a page, and two `ColumnBreak`s in a row advance one column.
- Content too wide for its column is handled like content too wide anywhere else, and an error names the path through the flow, for example `page > body > column-flow > image[1]`.

### Ending level

The flow's last page is balanced: its columns end as level as the content allows, and whatever follows the flow starts below the tallest column. Pages the flow fills completely aren't balanced, and neither are pages cut short by a forced break (a `PageBreak`, or a `ColumnBreak` in the last column): their columns fill in order, as in Word. Keep rules and unsplittable content can leave columns uneven. Lines are placed greedily, so seven lines in three columns come out as 3, 3 and 1.

### Where a flow can go

A flow can sit on the page or inside any box: a bordered or padded `View`, a card, a list item. It takes the width its container gives it and splits across pages with that container. It can't go in a table cell, a row (a `Row`, or a `View` with a row direction), another flow, or a header, footer, background or overlay. From PHP or Blade that throws `InvalidStructure`, naming the flow and the container. Flows made from [HTML markup](./rich-text.md#columns) never throw: they lay out as one column, with a note.

Several flows can follow one another on a page, with full-width content between them and a different column count each time.

### Tables of contents

A `Toc` inside a flow works. Its entries are laid out in the flow like any other content, one column wide, so leaders and page numbers are set to the column's width. It lists the headings anywhere in the document, including those inside flows.

### In HTML and Word

HTML uses CSS columns, and Word gets real section columns that stay editable. [HTML export](./html-export.md#columns) and [Word export](./word-export.md#columns) list what differs.

## Next

[Text](./text.md) covers paragraphs, headings, inline formatting and lists.
