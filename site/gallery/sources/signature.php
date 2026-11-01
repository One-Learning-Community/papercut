<?php

declare(strict_types=1);

use Papercut\{Document, Page, Row, Text};
use Papercut\Decorator\{Signature};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        Signature::make(Text::make('Jane Smith')->italic()->size(pt(18)))->name('Jane Smith')->role('Director')->date('12 March 2026'),
        Signature::make()->name('Witness')->role('Name and date'),
    )->gap(pt(28)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
