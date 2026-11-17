<?php

declare(strict_types=1);

/**
 * Renders every formula in tests/fixtures/tex/corpus.php through Math::tex -> Math::layout -> SvgRenderer and writes a
 * contact sheet (source, SVG, notes). Usage: php bin/tex-contact-sheet.php [out.html] [--png=dir]
 */

use Papercut\Math\Math;
use Papercut\Math\Render\SvgRenderer;
use Papercut\Math\Tex\TexReader;

require __DIR__ . '/../vendor/autoload.php';

$out = $argv[1] ?? 'contact-sheet.html';
$pngDir = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--png=')) {
        $pngDir = substr($a, 6);
        @mkdir($pngDir, 0777, true);
    }
}
$corpus = require __DIR__ . '/../tests/fixtures/tex/corpus.php';
$renderer = new SvgRenderer(Math::stixTwoMath());
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);

$rows = '';
$stats = ['clean' => 0, 'approx' => 0, 'dropped' => 0, 'crash' => 0];
foreach ($corpus as $name => [$tex]) {
    $notes = [];
    $svg = '';
    $status = 'clean';
    try {
        $res = TexReader::read($tex);
        $notes = $res->notes();
        $box = Math::layout($res->node, 22.0, $res->display);
        $svg = $renderer->render($box, 3.0);
        foreach ($notes as $n) {
            $status = $n->level->name === 'Dropped' ? 'dropped' : ($status === 'dropped' ? 'dropped' : 'approx');
        }
    } catch (\Throwable $e) {
        $status = 'crash';
        $svg = '<pre style="color:#b00">' . $h($e::class . ': ' . $e->getMessage()) . '</pre>';
    }
    $stats[$status]++;
    if ($pngDir !== null && str_starts_with($svg, '<svg')) {
        file_put_contents("$pngDir/$name.svg", $svg);
    }
    $noteHtml = '';
    foreach ($notes as $n) {
        $noteHtml .= '<li class="' . strtolower($n->level->name) . '">' . $h($n->level->name . ' ' . $n->element . ': ' . $n->reason) . '</li>';
    }
    $rows .= '<section class="' . $status . '"><h3>' . $h($name) . ' <small>' . $status . '</small></h3>'
        . '<pre class="src">' . $h($tex) . '</pre><div class="svg">' . $svg . '</div>'
        . ($noteHtml !== '' ? '<ul>' . $noteHtml . '</ul>' : '') . '</section>';
}

$summary = sprintf('%d formulas: %d clean, %d with approximation notes, %d with dropped notes, %d crashed', count($corpus), $stats['clean'], $stats['approx'], $stats['dropped'], $stats['crash']);
$html = '<!doctype html><meta charset="utf-8"><title>Math::tex contact sheet</title><style>'
    . 'body{font:14px system-ui;margin:1.5rem;max-width:62rem}section{border-top:1px solid #ccc;padding:.5rem 0;display:grid;grid-template-columns:22rem 1fr;gap:.5rem 1.5rem;align-items:center}'
    . 'h3{grid-column:1/3;margin:.2rem 0;font-size:13px}h3 small{color:#888;font-weight:normal}.src{margin:0;white-space:pre-wrap;word-break:break-all;font-size:11px;color:#444}'
    . 'ul{grid-column:1/3;margin:0;font-size:12px}li.dropped{color:#b00}li.approximated{color:#a60}.svg svg{max-width:100%;height:auto}'
    . 'section.crash{background:#fee}</style><h1>Math::tex contact sheet</h1><p>' . $h($summary) . '</p>' . $rows;
file_put_contents($out, $html);
echo $summary . "\nWrote $out\n";
