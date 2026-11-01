<?php

declare(strict_types=1);

use Papercut\{Document, Page, Table, TableCell};
use Papercut\Value\Edges;

use function Papercut\{in, pt};

// Each cell draws its right and bottom rule and the table its top and left, so the grid has no doubled lines.
$grid = static fn (TableCell $c): TableCell => $c->borderWidth(Edges::of(pt(0), pt(0.75), pt(0.75), pt(0)))->borderColor('slate-300');
$cell = static fn (string $v): TableCell => $grid(TableCell::make($v));
$day = static fn (string $v, int $rows): TableCell => $grid(TableCell::make($v)->rowspan($rows)->bold()->background('indigo-50')->color('indigo-900'));
$head = static fn (string $v): TableCell => $grid(TableCell::th($v)->background('slate-100'));

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Table::make()->columns('auto 1fr auto')
        ->head($head('Day'), $head('Session'), $head('Room'))
        ->row($day('Monday', 2), $cell('Opening keynote'), $cell('Hall A'))
        ->row($cell('Layout workshop'), $cell('Room 2'))
        ->row($day('Tuesday', 3), $cell('Pagination deep dive'), $cell('Hall A'))
        ->row($cell('Fonts and shaping'), $cell('Room 3'))
        ->row($cell('Closing panel'), $cell('Hall A'))
        ->borderWidth(Edges::of(pt(0.75), pt(0), pt(0), pt(0.75)))->borderColor('slate-300'),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
