<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, Row, Text, View};
use Papercut\Style\{Align, Justify};

use function Papercut\{in, pt};

$box = static fn (string $label, string $note, string $tint): View => View::make(
    Text::make($label)->bold()->color($tint . '-900'),
    Text::make($note)->size(pt(9))->color($tint . '-700'),
)->background($tint . '-50')->borderWidth(\Papercut\Value\Edges::all(pt(1)))->borderColor($tint . '-200')->radius(pt(6))->padding(pt(10))->gap(pt(2));

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Row::make(
            Text::make('Row with a growing middle')->bold(),
            Text::make('justify: space-between')->size(pt(9))->color('slate-500'),
        )->justify(Justify::SpaceBetween)->align(Align::Center),
        Row::make(
            $box('Fixed', 'width 1in', 'sky')->width(in(1)),
            $box('Grows', 'grow 1 takes the rest', 'emerald')->grow(1),
            $box('Fixed', 'width 1in', 'sky')->width(in(1)),
        )->gap(pt(8)),
        Row::make(
            Column::make(
                $box('Column', 'top to bottom', 'violet'),
                $box('Column', 'gap 6pt', 'violet'),
            )->gap(pt(6))->grow(1),
            $box('Centred', 'align-self: center', 'amber')->grow(1)->alignSelf(Align::Center),
        )->gap(pt(8)),
    )->gap(pt(10)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
