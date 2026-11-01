<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, Text};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Text::h1('Quarterly review'),
        Text::p('Revenue grew for the third quarter in a row, led by the new subscription plans and a steady rise in renewals.'),
        Text::h2('What changed'),
        Text::p('Annual plans now make up most new sign-ups. Support response times fell by a third after the help desk moved to a single queue.'),
        Text::h3('Next steps'),
        Text::p('Extend the trial to thirty days and publish the updated pricing page before the next billing cycle.'),
    )->gap(pt(2)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
