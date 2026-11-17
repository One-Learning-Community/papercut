<?php

declare(strict_types=1);

use Quoin\{Document, Page, Row};
use Quoin\Decorator\{ChoiceList};

use function Quoin\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        ChoiceList::checkbox()->option('Library catalog', selected: true)->option('Web search', selected: true)->option('Ask a colleague')->emphasizeSelected()->grow(1),
        ChoiceList::radio()->option('Daily')->option('Weekly', selected: true)->option('Rarely')->emphasizeSelected()->grow(1),
    )->gap(pt(24)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
