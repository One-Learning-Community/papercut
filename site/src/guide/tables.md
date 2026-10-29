# Tables

A table has a list of column widths, an optional header and the body rows. When a table runs past the end of a page, it breaks between rows and repeats the header at the top of the next page. A row is only ever split if it's taller than a whole page on its own.

::: code-tabs#style
@tab Blade
```blade
<x-doc::table columns="1fr 25mm 30mm">
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
use Papercut\{Document, Page, Table, TableRow, Text};
use function Papercut\mm;

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
:::

## Column widths

`columns` is a space-separated list with one entry per column. Each entry can be a length (`25mm`), a percentage (`20%`), `auto`, or a fraction (`1fr`). Fractions share out whatever width is left after the other columns have taken theirs, just like in CSS grid.

### Filling the width

Auto columns normally take only the width their content needs, so a table of short cells ends well before the right margin. In PHP, `->fillWidth()` shares the width left over among the auto columns, in proportion to how wide their content wants to be (evenly when none has any). It does nothing when an `fr` column already takes the rest. Tables that come from `Markup::html()` fill the width this way.

```php
$table = Table::make()->columns('auto auto auto')->fillWidth()->row('Mon', 'Tue', 'Wed');
```

## Cells

A cell can hold any content, not just text: a column with two lines, an image or a badge, for example. Use `colspan` to make a cell span several columns and `rowspan` to make it span several rows. `<x-doc::th>` is a header cell, and its text is bold.

In Blade, `<x-doc::thead>` goes first. Put cells straight inside it for a single header row, or `<x-doc::tr>` rows if you need more than one. In PHP, `headerRow()` adds a header row, and `bodyRow()` or `rows()` add body rows.

## Spanning rows

`rowspan` makes a cell reach down into the rows below it. Those rows leave a gap in the spanned column, so write only the cells that remain, just as in HTML. `colspan` and `rowspan` can be combined on one cell.

::: code-tabs#style
@tab Blade
```blade
<x-doc::table columns="20mm 1fr 1fr">
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
use Papercut\{Table, TableCell};

$table = Table::make()
    ->columns('20mm 1fr 1fr')
    ->head('Time', 'Mon', 'Tue')
    ->row('9:00', TableCell::make('Workshop')->rowspan(2)->background('amber-100'), 'Reading')
    ->row('10:00', 'Writing')
    ->row('11:00', TableCell::make('Lunch')->colspan(2));
```
:::

The rows a cell spans stay together on a page. A group of rows is only split across pages when it is taller than a whole page, and then the spanning cell continues on the next page under the repeated header.

## Showing the header only once

If you only want the header on the first page, use `repeat-header="false"` in Blade or `->repeatHeader(false)` in PHP.
