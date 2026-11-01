<?php

declare(strict_types=1);

use Papercut\{Document, Page, Text};
use Papercut\Decorator\{Field, Section};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Section::make(
        Text::p('Tell us how you usually find sources for a new project, and which tools you rely on most.'),
        Field::make('Library catalogue first, then a general web search.')->label('Answer'),
    )->number('3')->title('Search habits')->rule()->variant('large'),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
