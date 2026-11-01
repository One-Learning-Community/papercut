<?php

declare(strict_types=1);

use Papercut\{Document, Page, Column};
use Papercut\Decorator\{Callout};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Callout::make('Read each statement before you answer.')->tone('note')->title('Note'),
        Callout::make('Drafts are saved every minute, so you can come back later.')->tone('tip')->title('Tip'),
        Callout::make('Your session ends after 30 minutes without activity.')->tone('warning')->title('Warning'),
        Callout::make('Answers cannot be changed once submitted.')->tone('danger')->title('Danger'),
    )->gap(pt(8)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
