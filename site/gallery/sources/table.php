<?php

declare(strict_types=1);

use Papercut\{Document, Page, Table, TableCell, TableRow};
use Papercut\Value\Edges;

use function Papercut\{in, pt};

$num = static fn (string $v): TableCell => TableCell::make($v)->align('right');
$rule = static fn (TableRow $r): TableRow => $r->borderWidth(Edges::of(pt(0), pt(0), pt(0.75), pt(0)))->borderColor('slate-200');

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Table::make()->columns('1fr auto auto auto')
        ->headerRow(TableRow::make(TableCell::th('Item'), TableCell::th('Hours')->align('right'), TableCell::th('Rate')->align('right'), TableCell::th('Amount')->align('right'))->background('slate-100'))
        ->bodyRow($rule(TableRow::make('Layout engine consulting', $num('12'), $num('$140.00'), $num('$1,680.00'))))
        ->bodyRow($rule(TableRow::make('PDF backend integration', $num('8'), $num('$140.00'), $num('$1,120.00'))))
        ->bodyRow($rule(TableRow::make('Template migration', $num('20'), $num('$120.00'), $num('$2,400.00'))))
        ->bodyRow($rule(TableRow::make('Documentation', $num('5'), $num('$95.00'), $num('$475.00')))),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
