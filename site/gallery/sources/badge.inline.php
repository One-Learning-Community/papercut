<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, Text};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Text::markup('Status <badge type="tip">Paid</badge> since 3 March, with <badge type="warning" variant="outline">2 days</badge> left on the next invoice.'),
        Text::markup('The endpoint returns <badge type="note">201</badge> on success and <badge type="danger">409</badge> when the record already exists.'),
    )->gap(pt(8)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
