<?php

declare(strict_types=1);

use Papercut\{Document, Page, Row};
use Papercut\Decorator\{Badge};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        Badge::make('Neutral')->variant('outline'),
        Badge::make('Note')->tone('note')->variant('outline'),
        Badge::make('Tip')->tone('tip')->variant('outline'),
        Badge::make('Warning')->tone('warning')->variant('outline'),
        Badge::make('Danger')->tone('danger')->variant('outline'),
    )->gap(pt(6)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
