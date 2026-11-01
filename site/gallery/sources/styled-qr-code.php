<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, QrCode, Row, Text, View};

use function Papercut\{in, pt};

$qr = static fn (QrCode $code, string $caption): View => Column::make($code->width(in(1.3)), Text::make($caption)->size(pt(9))->color('slate-500'))->gap(pt(4));
$url = 'https://example.com/menu';

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        $qr(QrCode::make($url)->shape('rounded')->eyes('pointy')->color('indigo-700'), 'rounded, pointy eyes'),
        $qr(QrCode::make($url)->shape('dots')->eyes('dot')->eyeColor('rose-600'), 'dots, round eyes'),
        $qr(QrCode::make($url)->shape('rounded')->gradient('sky-500', 'violet-600'), 'gradient'),
    )->gap(pt(20)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
