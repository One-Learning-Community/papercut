<?php

declare(strict_types=1);

use Quoin\{Document, Page, Row};
use Quoin\Decorator\{Field};

use function Quoin\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        Field::make('Jane Smith')->label('Name')->variant('line')->grow(1),
        Field::make()->label('Email')->placeholder('Not given')->lines(1)->variant('line')->grow(1),
    )->gap(pt(16)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
