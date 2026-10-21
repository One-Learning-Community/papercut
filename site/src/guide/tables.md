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

## Cells

A cell can hold any content, not just text: a column with two lines, an image or a badge, for example. Use `colspan` to make a cell span several columns. `<x-doc::th>` is a header cell, and its text is bold.

In Blade, `<x-doc::thead>` goes first. Put cells straight inside it for a single header row, or `<x-doc::tr>` rows if you need more than one. In PHP, `headerRow()` adds a header row, and `bodyRow()` or `rows()` add body rows.

## Showing the header only once

If you only want the header on the first page, use `repeat-header="false"` in Blade or `->repeatHeader(false)` in PHP.
