<?php

declare(strict_types=1);

use Papercut\{ColumnFlow, Document, Page, Text};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Text::h2('Notes from the field'),
    ColumnFlow::make(
        Text::make('The first survey went out in March and the answers came back within a week. Most were short, a line or two, but a few ran to a page.'),
        Text::make('Readers asked for shorter sections, clearer headings and more room for figures. We kept the order and tightened the wording.'),
        Text::make('A second round followed in June. By then the draft had settled, and the comments were mostly about small things: a missing unit, a table that broke in the wrong place.'),
        Text::make('Everything that came in is filed with the draft, so the next edition can start from what readers said and not from memory.'),
    )->gap(pt(6))->columns(3)->columnGap(pt(18))->columnRule(),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
