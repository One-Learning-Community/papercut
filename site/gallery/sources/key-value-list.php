<?php

declare(strict_types=1);

use Papercut\{Document, Page, Text};
use Papercut\Decorator\{Badge, KeyValueList};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    KeyValueList::make()
        ->row('Respondent', 'Jane Smith')
        ->row('Submitted', '2 March 2026, 14:30')
        ->row('Course', 'Introduction to Research Methods')
        ->row('Status', Badge::make('Complete')->tone('tip')),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
