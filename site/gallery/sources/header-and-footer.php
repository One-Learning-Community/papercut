<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, PageBreak, PageNumber, Row, Text};
use Papercut\Style\Justify;
use Papercut\Value\Edges;

use function Papercut\{in, pt};

$para = 'The new warehouse opened in March and now ships most orders the same day. Returns are handled at the front desk, and the old site closes at the end of the quarter.';

return Document::make(
    Page::size(in(4.25), in(5.5))->margin(in(0.75), in(0.4))
        ->header(Row::make(
            Text::make('Northwind Studio')->bold()->color('indigo-600'),
            Text::make('Operations update')->size(pt(8))->color('slate-500'),
        )->justify(Justify::SpaceBetween)->padding(Edges::of(pt(0), pt(0), pt(4), pt(0)))->borderWidth(Edges::of(pt(0), pt(0), pt(0.75), pt(0)))->borderColor('slate-200'))
        ->footer(Row::make(
            Text::make('northwind.example')->size(pt(8))->color('slate-500'),
            PageNumber::make('{page}')->size(pt(8))->color('slate-500'),
        )->justify(Justify::SpaceBetween))
        ->body(
            Column::make(Text::h2('Spring operations'), Text::p($para), Text::p($para))->gap(pt(4)),
            PageBreak::make(),
            Column::make(Text::h2('Looking ahead'), Text::p($para))->gap(pt(4)),
        ),
)->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
