<?php

declare(strict_types=1);

use Papercut\{Column, Document, Equation, Page, Span, Text};
use Papercut\Math\Math;

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Equation::of('x', '=', Math::frac(Math::row('−b', '±', Math::sqrt(Math::sup('b', '2'), '−', '4ac')), '2a'))->center(),
        Equation::of('A', '=', 'P', Math::sup(Math::fenced('(', ')', '1', '+', Math::frac('r', 'n')), 'nt'))->center(),
        Text::spans(
            Span::text('In running text, a circle of radius '),
            Span::math('r'),
            Span::text(' has area '),
            Span::math('π', Math::sup('r', '2')),
            Span::text(', and the fitted slope was '),
            Span::math(Math::sub('β', '1'), '=', '−0.04'),
            Span::text('.'),
        ),
    )->gap(pt(10)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
