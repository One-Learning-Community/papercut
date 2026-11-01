<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, QrCode, Row, Text};
use Papercut\Style\Align;

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        QrCode::make('https://example.com/track/PP1042883301')->width(in(1.3)),
        Column::make(
            Text::make('Track your parcel')->bold()->size(pt(14)),
            Text::make('Scan the code or visit example.com/track and enter PP1042883301.')->color('slate-600'),
        )->gap(pt(4))->grow(1),
    )->gap(pt(16))->align(Align::Center),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
