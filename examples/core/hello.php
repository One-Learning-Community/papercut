<?php

declare(strict_types=1);

// For contributors: draws a card with the backend directly, using the internal display list
// (Quoin\Backend and Quoin\Paint are @internal and may change in any release). Run: php examples/core/hello.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Quoin\Backend\PhpPdf\PhpPdfBackend;
use Quoin\Paint\{DocumentMeta, Line, Link, PageDisplayList, Rect, TextRun};
use Quoin\Text\FontRegistry;
use Quoin\Value\Color;

use function Quoin\mm;

$backend = new PhpPdfBackend();
$fonts = FontRegistry::withDefaults($backend);
$bold = $fonts->resolve('Inter', 700);
$regular = $fonts->resolve('Inter');

$x = mm(20)->points;
$title = 'Quoin';
$ops = [
    new Rect($x, $x, 595 - 2 * $x, 120, fill: Color::parse('slate-50'), stroke: Color::parse('slate-200'), strokeWidth: 1, radius: 8),
    new TextRun($x + 20, $x + 48, $title, $bold, 28, Color::parse('slate-900')),
    new Line($x + 20, $x + 62, $x + 20 + $bold->advance($title, 28), $x + 62, Color::parse('indigo-500'), 2),
    new TextRun($x + 20, $x + 92, 'Component-based PDF layout for modern PHP.', $regular, 12, Color::parse('slate-600')),
    new Link($x, $x, 595 - 2 * $x, 120, 'https://github.com/'),
];

$pdf = $backend->render(new DocumentMeta(title: 'Hello Quoin'), [new PageDisplayList(595.28, 841.89, $ops)]);
file_put_contents(__DIR__ . '/hello.pdf', $pdf);
echo 'Wrote examples/core/hello.pdf (' . strlen($pdf) . " bytes)\n";
