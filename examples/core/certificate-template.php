<?php

declare(strict_types=1);

// Draws the blank certificate that the certificate example fills in. In a real project this PDF would come
// from a design tool; here Papercut makes it so the file can be rebuilt. Run: php examples/core/certificate-template.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Papercut\{Column, Document, Page, Row, Text};
use Papercut\Value\Edges;

use function Papercut\{in, pt};

// A rule with a small label under it, for the date and signature.
$rule = fn (string $label, float $width) => Column::make(
    Column::make()->height(pt(1))->borderWidth(Edges::of(pt(0.75), pt(0), pt(0), pt(0)))->borderColor('slate-500'),
    Text::make($label)->size(pt(9))->color('slate-500')->center()->letterSpacing(pt(0.6)),
)->width(in($width))->gap(pt(4));

$blank = fn (float $width) => Column::make()->height(pt(1))->width(in($width))->borderWidth(Edges::of(pt(0), pt(0), pt(0.75), pt(0)))->borderColor('slate-400');

// Two borders, one heavy and one hairline, as a design tool would draw them.
$inner = Column::make(
    Column::make(
        Text::make('CERTIFICATE')->size(pt(15))->letterSpacing(pt(6))->color('indigo-600')->bold()->center(),
        Text::make('of Completion')->size(pt(40))->bold()->color('slate-900')->center(),
    )->gap(pt(4))->align('center'),
    Column::make(
        Text::make('This certifies that')->size(pt(14))->color('slate-600')->center(),
        $blank(6.4),
    )->gap(pt(56))->align('center'),
    Column::make(
        Text::make('has completed')->size(pt(14))->color('slate-600')->center(),
        $blank(7.4),
    )->gap(pt(52))->align('center'),
    Row::make($rule('DATE', 2.4), $rule('SIGNATURE', 3.0))->justify('space-around')->width(in(8.2))->align('end'),
)->justify('space-between')->align('center')->padding(in(0.62), in(0.5))->height(in(7.31))->border(pt(0.75), 'indigo-600');

$template = Document::make(
    Page::letter()->landscape()->margin(in(0.4))->body(
        Column::make($inner)->padding(pt(5))->border(pt(3), 'indigo-600')->height(in(7.7)),
    ),
)->title('Certificate of Completion template')->createdAt(new DateTimeImmutable('2026-03-02T09:00:00Z'));

$template->toPdf()->save(__DIR__ . '/../assets/certificate-template.pdf');
echo "Wrote examples/assets/certificate-template.pdf\n";
