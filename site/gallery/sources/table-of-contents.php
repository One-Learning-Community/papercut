<?php

declare(strict_types=1);

use Papercut\{Document, Page, PageBreak, Text, Toc};

use function Papercut\{in, pt};

$para = 'Requests and responses are JSON. Every call can be tried in the sandbox first.';

return Document::make(
    Page::size(in(4.25), in(5.5))->margin(in(0.5), in(0.4))->body(
        Toc::make()->title('Contents')->levels(1, 2),
        PageBreak::make(),
        Text::h1('Getting started'),
        Text::h2('Create an account'),
        Text::p($para),
        Text::h2('Get a test key'),
        Text::p($para),
        Text::h1('Shipments'),
        Text::h2('Create a shipment'),
        Text::p($para),
    ),
)->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
