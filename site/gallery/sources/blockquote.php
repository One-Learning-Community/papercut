<?php

declare(strict_types=1);

use Papercut\{Document, Page, Text};
use Papercut\Decorator\{Blockquote};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Blockquote::make(Text::make('Simplicity is not the absence of clutter. It is the result of knowing exactly what belongs on the page.'))
        ->cite('Studio handbook, chapter 2'),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
