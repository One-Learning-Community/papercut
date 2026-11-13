<?php

declare(strict_types=1);

/**
 * Renders every Fixtures::all() entry to tests/out/<name>.svg (and .png when rsvg-convert is installed),
 * plus tests/out/index.html showing them all. Run from packages/math: php tests/render-fixtures.php
 */

use Papercut\Math\Math;
use Papercut\Math\Render\SvgRenderer;
use Papercut\Math\Tests\Fixtures;

require __DIR__ . '/../vendor/autoload.php';

$out = __DIR__ . '/out';
if (! is_dir($out) && ! mkdir($out, 0777, true)) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}

$renderer = new SvgRenderer(Math::stixTwoMath());
$rsvg = trim((string) shell_exec('command -v rsvg-convert 2>/dev/null'));

$html = '<!doctype html><meta charset="utf-8"><title>papercut/math fixtures</title>'
    . '<style>body{font:14px system-ui;margin:2rem;max-width:56rem}'
    . '.row{display:flex;align-items:center;gap:1.5rem;border-bottom:1px solid #ddd;padding:.6rem 0}'
    . '.n{width:14rem;color:#666;font-size:12px}</style><h1>papercut/math fixtures</h1>';

foreach (Fixtures::all() as $name => $node) {
    $display = ! str_ends_with($name, '-inline');
    $box = Math::layout($node, 24.0, $display);
    $svg = $renderer->render($box, 2.0);
    file_put_contents("$out/$name.svg", $svg);
    if ($rsvg !== '') {
        exec(sprintf('%s -z 4 -b white %s -o %s', escapeshellarg($rsvg), escapeshellarg("$out/$name.svg"), escapeshellarg("$out/$name.png")));
    }
    $html .= sprintf(
        '<div class="row"><div class="n">%s (%s)<br>w %.1f, asc %.1f, desc %.1f pt</div>%s</div>',
        htmlspecialchars($name),
        $display ? 'display' : 'inline',
        $box->width,
        $box->ascent,
        $box->descent,
        $svg,
    );
}

file_put_contents("$out/index.html", $html);
echo 'Wrote ' . count(Fixtures::all()) . " fixtures to $out\n";
