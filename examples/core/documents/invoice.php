<?php

declare(strict_types=1);

// Builds the invoice Document; examples/core/invoice.php saves it as PDF and examples/html/render.php as HTML.
use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Table, TableRow, Text};
use Papercut\Style\Justify;
use Papercut\Value\Edges;

use function Papercut\{mm, pt};

$items = [
    ['Layout engine consulting', 'Flexbox-style design review and API workshop', 12, 140.00],
    ['PDF backend integration', 'Font subsetting and deterministic output', 8, 140.00],
    ['Table pagination', 'Repeating headers, keep-together rules', 6, 140.00],
    ['Template migration', 'Port 14 legacy reports to components', 20, 120.00],
    ['Quality assurance', 'Visual regression suite and PDF validation', 10, 110.00],
    ['Documentation', 'Developer guide and examples', 5, 95.00],
];

$money = fn (float $v): string => '$' . number_format($v, 2);
$subtotal = array_sum(array_map(fn (array $i) => $i[2] * $i[3], $items));
$tax = round($subtotal * 0.0825, 2);
$total = $subtotal + $tax;

$label = fn (string $s) => Text::make($s)->size(pt(8))->bold()->color('slate-500')->letterSpacing(pt(0.6));

$letterhead = Row::make(
    Column::make(
        Text::make('Northwind Studio')->bold()->size(pt(16))->color('indigo-600'),
        Text::make('48 Harbor Street, Suite 210')->color('slate-500'),
        Text::make('Portland, OR 97204')->color('slate-500'),
        Text::make('billing@northwind.example')->color('slate-500'),
    )->gap(pt(2)),
    Spacer::flex(),
    Column::make(
        Text::make('INVOICE')->bold()->size(pt(26))->color('slate-900')->right(),
        Text::make('No. INV-2026-0142')->right()->color('slate-600'),
        Text::make('Issued 2026-03-02 / Due 2026-04-01')->right()->color('slate-600'),
    )->gap(pt(2)),
)->padding(Edges::of(pt(0), pt(0), pt(14), pt(0)))
    ->borderWidth(Edges::of(pt(0), pt(0), pt(1.5), pt(0)))->borderColor('indigo-600');

$billTo = Row::make(
    Column::make(
        $label('BILL TO'),
        Text::make('Marlowe & Finch Publishing')->bold(),
        Text::make('Attn: Accounts Payable'),
        Text::make('900 Meridian Avenue'),
        Text::make('Seattle, WA 98101'),
    )->gap(pt(2))->grow(1),
    Column::make(
        $label('PROJECT'),
        Text::make('Report Platform Rebuild'),
        $label('PO NUMBER')->margin(Edges::of(pt(6), pt(0), pt(0), pt(0))),
        Text::make('PO-77310'),
    )->gap(pt(2))->grow(1),
)->gap(pt(16))->padding(pt(12))->background('slate-50')->radius(pt(6));

$table = Table::make()
    ->columns('1fr 20mm 25mm 25mm')
    ->headerRow(TableRow::make(
        Text::make('Description')->bold()->size(pt(9)),
        Text::make('Hours')->bold()->size(pt(9))->right(),
        Text::make('Rate')->bold()->size(pt(9))->right(),
        Text::make('Amount')->bold()->size(pt(9))->right(),
    )->background('slate-100'));
foreach ($items as [$name, $desc, $hours, $rate]) {
    $table = $table->bodyRow(TableRow::make(
        Column::make(Text::make($name)->bold(), Text::make($desc)->size(pt(8.5))->color('slate-500'))->gap(pt(1)),
        Text::make((string) $hours)->right(),
        Text::make($money($rate))->right(),
        Text::make($money($hours * $rate))->right(),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}

$totalRow = fn (string $l, string $v, bool $strong = false) => Row::make(
    Text::make($l)->color($strong ? 'slate-900' : 'slate-600')->bold($strong)->size(pt($strong ? 12 : 10)),
    Spacer::flex(),
    Text::make($v)->bold($strong)->size(pt($strong ? 12 : 10))->right(),
);

$totals = Row::make(
    Spacer::flex(),
    Column::make(
        $totalRow('Subtotal', $money($subtotal)),
        $totalRow('Tax (8.25%)', $money($tax)),
        $totalRow('Total due', $money($total), true)->margin(Edges::of(pt(4), pt(0), pt(0), pt(0)))
            ->padding(Edges::of(pt(6), pt(0), pt(0), pt(0)))
            ->borderWidth(Edges::of(pt(1), pt(0), pt(0), pt(0)))->borderColor('slate-900'),
    )->gap(pt(4))->width(mm(70)),
);

$document = Document::make(
    Page::a4()->margin(mm(20))
        ->body(Column::make($letterhead, $billTo, $table, $totals)->gap(pt(20)))
        ->footer(
            Column::make(
                Row::make(
                    Text::make('Payment terms: net 30. Wire or ACH to Northwind Studio, account ending 4417.')->size(pt(8))->color('slate-500')->grow(1),
                    PageNumber::make('Page {page} of {total}')->size(pt(8))->color('slate-500')->right(),
                )->justify(Justify::SpaceBetween)->gap(pt(12)),
            )->padding(Edges::of(pt(8), pt(0), pt(0), pt(0)))
                ->borderWidth(Edges::of(pt(0.5), pt(0), pt(0), pt(0)))->borderColor('slate-200'),
        ),
)->title('Invoice INV-2026-0142')->createdAt(new DateTimeImmutable('2026-03-02T09:00:00Z'));

return $document;
