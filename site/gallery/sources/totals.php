<?php

declare(strict_types=1);

use Papercut\{Document, Page, Column};
use Papercut\Decorator\{Totals};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Totals::make()
            ->row('Subtotal', '$7,615.00')
            ->row('Tax (8.25%)', '$628.24')
            ->total('Total due', '$8,243.24')
            ->width(in(2.75)),
    ),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
