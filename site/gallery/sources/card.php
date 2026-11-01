<?php

declare(strict_types=1);

use Papercut\{Document, Page, Text};
use Papercut\Decorator\{Card, KeyValueList};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Card::make(
        Text::make('Jane Smith')->bold(),
        Text::make('Product designer, joined in March. Leads the onboarding redesign and the new billing pages.')->color('slate-600'),
        KeyValueList::make()->row('Team', 'Growth')->row('Location', 'Lisbon'),
    )->title('Respondent'),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
