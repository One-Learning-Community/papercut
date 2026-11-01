<?php

declare(strict_types=1);

use Papercut\{Document, Page, Row};
use Papercut\Decorator\{ChoiceList};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        ChoiceList::checkbox()->option('Library catalogue', selected: true)->option('Web search', selected: true)->option('Ask a colleague')->emphasizeSelected()->grow(1),
        ChoiceList::radio()->option('Daily')->option('Weekly', selected: true)->option('Rarely')->emphasizeSelected()->grow(1),
    )->gap(pt(24)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
