<?php

declare(strict_types=1);

/**
 * Renders every formula in tests/fixtures/tex/corpus.php through Math::tex -> Math::layout -> SvgRenderer and writes a
 * contact sheet (source, SVG, notes). Usage: php bin/tex-contact-sheet.php [out.html] [--png=dir] [--chem]
 * With --chem the chemistry corpus (tests/fixtures/tex/chem-corpus.php) is drawn instead, with a third column that
 * renders each source in KaTeX and its mhchem extension, loaded from cdn.jsdelivr.net, for a side-by-side check.
 */

use Quoin\Math\Math;
use Quoin\Math\Render\SvgRenderer;
use Quoin\Math\Tex\TexReader;

require __DIR__ . '/../vendor/autoload.php';

$chem = in_array('--chem', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => ! str_starts_with($a, '--')));
$out = $args[0] ?? ($chem ? 'chem-contact-sheet.html' : 'contact-sheet.html');
$pngDir = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--png=')) {
        $pngDir = substr($a, 6);
        @mkdir($pngDir, 0777, true);
    }
}
$corpus = require __DIR__ . '/../tests/fixtures/tex/' . ($chem ? 'chem-corpus.php' : 'corpus.php');
$renderer = new SvgRenderer(Math::stixTwoMath());
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);

$rows = '';
$stats = ['clean' => 0, 'approx' => 0, 'dropped' => 0, 'crash' => 0];
foreach ($corpus as $name => [$tex]) {
    // A chemistry entry is the argument of \ce unless it is already a \pu formula.
    if ($chem && ! str_starts_with($tex, '\\pu{')) {
        $tex = '\\ce{' . $tex . '}';
    }
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
        . ($chem ? '<div class="katex-out" data-tex="' . $h($tex) . '"></div>' : '')
        . ($noteHtml !== '' ? '<ul>' . $noteHtml . '</ul>' : '') . '</section>';
}

$summary = sprintf('%d formulas: %d clean, %d with approximation notes, %d with dropped notes, %d crashed', count($corpus), $stats['clean'], $stats['approx'], $stats['dropped'], $stats['crash']);
$katexHead = $chem
    ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">'
        . '<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>'
        . '<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/mhchem.min.js"></script>'
    : '';
$katexRun = $chem
    ? '<script>document.querySelectorAll(".katex-out").forEach(function(el){try{katex.render(el.dataset.tex,el,{displayMode:true,throwOnError:false});}catch(e){el.textContent=String(e);}});</script>'
    : '';
$html = '<!doctype html><meta charset="utf-8"><title>Math::tex contact sheet</title>' . $katexHead . '<style>'
    . 'body{font:14px system-ui;margin:1.5rem;max-width:62rem}section{border-top:1px solid #ccc;padding:.5rem 0;display:grid;grid-template-columns:' . ($chem ? '16rem 1fr 1fr' : '22rem 1fr') . ';gap:.5rem 1.5rem;align-items:center}'
    . 'h3{grid-column:1/-1;margin:.2rem 0;font-size:13px}h3 small{color:#888;font-weight:normal}.src{margin:0;white-space:pre-wrap;word-break:break-all;font-size:11px;color:#444}'
    . 'ul{grid-column:1/-1;margin:0;font-size:12px}li.dropped{color:#b00}li.approximated{color:#a60}.svg svg{max-width:100%;height:auto}'
    . 'section.crash{background:#fee}.katex-out .katex-display{margin:0;text-align:left}</style><h1>Math::tex contact sheet</h1><p>' . $h($summary) . '</p>' . $rows . $katexRun;
file_put_contents($out, $html);
echo $summary . "\nWrote $out\n";
