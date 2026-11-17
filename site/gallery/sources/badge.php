<?php

declare(strict_types=1);

use Quoin\{Document, Page, Row};
use Quoin\Decorator\{Badge};

use function Quoin\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        Badge::make('Neutral'),
        Badge::make('Note')->tone('note'),
        Badge::make('Tip')->tone('tip'),
        Badge::make('Warning')->tone('warning'),
        Badge::make('Danger')->tone('danger'),
    )->gap(pt(6)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
