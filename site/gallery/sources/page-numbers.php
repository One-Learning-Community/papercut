<?php

declare(strict_types=1);

use Papercut\{Document, Page, PageBreak, PageNumber, Text};

use function Papercut\{in, pt};

$para = 'Every page gets its number when the document is laid out, so the total is right however the content breaks.';

return Document::make(
    Page::size(in(4.25), in(5.5))->margin(in(0.5), in(0.4), in(0.7), in(0.4))
        ->footer(PageNumber::make('Page {page} of {total}')->size(pt(9))->color('slate-500')->center())
        ->body(Text::h2('Chapter one'), Text::p($para), PageBreak::make(), Text::h2('Chapter two'), Text::p($para)),
)->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
