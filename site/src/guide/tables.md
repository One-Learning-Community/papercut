# Tables

A table has a list of column widths, an optional header and the body rows. When a table runs past the end of a page, it breaks between rows and repeats the header at the top of the next page. A row is split only when it is taller than a whole page.

::: code-tabs#style
@tab Blade
```blade
<x-doc::table columns="1fr 1in 1.25in">
  <x-doc::thead>
    <x-doc::tr background="slate-100">
      <x-doc::th>Name</x-doc::th>
      <x-doc::th align="right">Qty</x-doc::th>
      <x-doc::th align="right">Price</x-doc::th>
    </x-doc::tr>
  </x-doc::thead>
  @foreach ($rows as [$name, $qty, $price])
    <x-doc::tr border-width="0 0 0.5pt 0" border-color="slate-200">
      <x-doc::td>{{ $name }}</x-doc::td>
      <x-doc::td align="right">{{ $qty }}</x-doc::td>
      <x-doc::td align="right">{{ $price }}</x-doc::td>
    </x-doc::tr>
  @endforeach
</x-doc::table>
```
@tab PHP
```php
use Quoin\{Document, Page, Table, TableRow, Text};
use function Quoin\in;

$rows = [];
for ($i = 1; $i <= 120; $i++) {
    $rows[] = ["Item $i", $i * 3, number_format($i * 12.5, 2)];
}

$table = Table::make()
    ->columns('1fr 1in 1.25in')
    ->headerRow(TableRow::make(
        Text::make('Name')->bold(),
        Text::make('Qty')->bold()->right(),
        Text::make('Price')->bold()->right(),
    )->background('slate-100'))
    ->rows($rows, fn (array $r) => [$r[0], Text::make((string) $r[1])->right(), Text::make($r[2])->right()]);

$document = Document::make(Page::letter()->margin(in(0.75))->body($table));
```
:::

## Column widths

`columns` is a space-separated list with one entry per column. Each entry can be a length in any unit (`1in`, `2.5cm`, `40mm`, `120pt`), a percentage (`20%`), `auto`, or a fraction (`1fr`). Fractions share the width left after the other columns, as in CSS grid. `'1in 1fr 2.5cm'` is a valid list.

### Filling the width

Auto columns take only the width their content needs, so a table of short cells ends well before the right margin. In PHP, `->fillWidth()` shares the width left over among the auto columns, in proportion to how wide their content wants to be (evenly when none has any). It does nothing when an `fr` column already takes the rest. Tables that come from `Markup::html()` fill the width this way.

```php
$table = Table::make()->columns('auto auto auto')->fillWidth()->row('Mon', 'Tue', 'Wed');
```

### Tables wider than their box

When the columns don't fit even at their narrowest (the longest word in each, or the longest piece between hyphens and slashes), the widest `auto` and `fr` columns give way first. They're narrowed to a shared width so the table fits, and long words in them break across lines. Narrow columns keep their width. A column never gets narrower than its content can go: its cell padding plus its widest letter, or the full width of a fixed-width box or a nested table's own narrowest columns. Images, SVGs and PDF pages scale down. This is reported as an approximation through `onWarning`, with how much more room the table needed. Smaller text or less cell padding keeps words whole. Word export uses the same shrunk column widths.

Length columns (`1in`) keep the width you gave them. If they, or the narrowest widths left after shrinking, still don't fit, the table can't fit its box: a `LayoutOverflow` is thrown or, in clip mode, the table is drawn too wide with a warning. See [Content that doesn't fit](/guide/pages.md#content-that-doesn-t-fit). Tables from `Markup::html()` shrink their length columns too, and a markup table too wide for its page overflows with a note instead of throwing.

A table inside a `Row` is narrowed to the space its row gives it, with the same note. If it still cannot fit, it throws `LayoutOverflow` like a table in a column; Word export reports it as a note instead.

## Cells

A cell can hold any content: a column with two lines, an image or a badge. Use `colspan` to make a cell span several columns and `rowspan` to make it span several rows. `<x-doc::th>` is a header cell with bold text. A cell's `height` or `min-height` sets the least height of its row, which is how you leave blank rows to write in; a cell spanning rows shares it among them.

In Blade, `<x-doc::thead>` goes first. Put cells straight inside it for a single header row, or `<x-doc::tr>` rows for more than one. In PHP, `headerRow()` adds a header row, and `bodyRow()` or `rows()` add body rows.

## Spanning rows

`rowspan` extends a cell into the rows below. Those rows leave a gap in the spanned column, so write only the cells that remain, as in HTML. `colspan` and `rowspan` can be combined on one cell. A `rowspan` that runs past the end of its section, or a `colspan` that runs into a `rowspan` from above, is clamped, and the table warns once. A `colspan` is also clamped at column 1000, with one note in PDF, HTML and Word, so a mistyped value can't make a table with millions of cells. Word shows at most 63 columns in a table, so a table with more is noted in Word export. In PHP, `TableCell::colsSpanned()` and `rowsSpanned()` return the spans.

::: code-tabs#style
@tab Blade
```blade
<x-doc::table columns="0.75in 1fr 1fr">
  <x-doc::thead>
    <x-doc::th>Time</x-doc::th>
    <x-doc::th>Mon</x-doc::th>
    <x-doc::th>Tue</x-doc::th>
  </x-doc::thead>
  <x-doc::tr>
    <x-doc::td>9:00</x-doc::td>
    <x-doc::td rowspan="2" background="amber-100">Workshop</x-doc::td>
    <x-doc::td>Reading</x-doc::td>
  </x-doc::tr>
  <x-doc::tr>
    <x-doc::td>10:00</x-doc::td>
    <x-doc::td>Writing</x-doc::td>
  </x-doc::tr>
  <x-doc::tr>
    <x-doc::td>11:00</x-doc::td>
    <x-doc::td colspan="2">Lunch</x-doc::td>
  </x-doc::tr>
</x-doc::table>
```
@tab PHP
```php
use Quoin\{Table, TableCell};

$table = Table::make()
    ->columns('0.75in 1fr 1fr')
    ->head('Time', 'Mon', 'Tue')
    ->row('9:00', TableCell::make('Workshop')->rowspan(2)->background('amber-100'), 'Reading')
    ->row('10:00', 'Writing')
    ->row('11:00', TableCell::make('Lunch')->colspan(2));
```
:::

The rows a cell spans stay together on a page. A group of rows is split across pages only when it is taller than a whole page, and then the spanning cell continues on the next page under the repeated header.

## Showing the header only once

To show the header on the first page only, use `repeat-header="false"` in Blade or `->repeatHeader(false)` in PHP.

A header so tall that the next row can't fit even its first line below it gives way: a repeated header is left out on that page, and on the first page the header stands alone and the rows start on the next one. Both are reported as warnings.
