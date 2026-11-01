<?php

declare(strict_types=1);

use Papercut\{Document, Page, Row};
use Papercut\Decorator\{Stat};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        Stat::make('$48,200')->label('Revenue')->note('Up 12% on Q2')->grow(1),
        Stat::make('1,204')->label('Orders')->note('38 refunded')->grow(1),
        Stat::make('4.7')->label('Rating')->note('from 312 reviews')->grow(1),
    )->gap(pt(12)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
