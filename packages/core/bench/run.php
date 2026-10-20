<?php

declare(strict_types=1);

/**
 * Renders bench/transcript.php once to warm up, then $argv[1] (default 5) timed
 * runs, and prints JSON: {median_ms, runs_ms[], pages, bytes, opcache, php}.
 *
 * Run with Xdebug off and OPcache on:
 *   php -d xdebug.mode=off -d opcache.enable_cli=1 bench/run.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$runs = max(1, (int) ($argv[1] ?? 5));
$build = static fn (): Papercut\Document => require __DIR__ . '/transcript.php';

$pdf = $build()->toPdf()->bytes(); // warm-up: autoload, OPcache, font parsing
$times = [];
for ($i = 0; $i < $runs; $i++) {
    $start = hrtime(true);
    $pdf = $build()->toPdf()->bytes();
    $times[] = (hrtime(true) - $start) / 1e6;
}

$sorted = $times;
sort($sorted);
$mid = intdiv(count($sorted), 2);
$median = count($sorted) % 2 === 1 ? $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;

preg_match_all('#/Type\s*/Page[^s]#', $pdf, $m);

echo json_encode([
    'median_ms' => round($median, 2),
    'runs_ms' => array_map(static fn (float $t): float => round($t, 2), $times),
    'pages' => count($m[0]),
    'bytes' => strlen($pdf),
    'opcache' => function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false),
    'php' => PHP_VERSION,
], JSON_PRETTY_PRINT), "\n";
