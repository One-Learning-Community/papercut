<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, QrCode, Row, Text, View};
use Papercut\Style\Align;

use function Papercut\{in, pt};

$qr = static fn (QrCode $code, string $caption): View => Column::make($code->width(in(1.3)), Text::make($caption)->size(pt(9))->color('slate-500')->center())->gap(pt(4))->align(Align::Center);
$url = 'https://example.com/menu';

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        $qr(QrCode::make($url)->shape('rounded')->eyes('pointy')->color('indigo-700'), 'shape rounded, eyes pointy'),
        $qr(QrCode::make($url)->shape('dots')->eyes('dot')->eyeColor('rose-600'), 'shape dots, eyes dot'),
        $qr(QrCode::make($url)->shape('rounded')->gradient('sky-500', 'violet-600'), 'gradient, two colours'),
    )->gap(pt(20)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
