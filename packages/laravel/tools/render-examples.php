<?php

declare(strict_types=1);

// Renders the Blade views under examples/laravel/views into a directory, optionally tagged (PDF/UA-1)
// and/or as PDF/A. Strict mode is on, so a heading skip or unavailable tagging fails the run.
// Usage: php packages/laravel/tools/render-examples.php <output-dir> [--tagged] [--pdfa=<level>]
// Check the result with: php packages/core/tools/verapdf.php <flavour> <output-dir>
require __DIR__ . '/../vendor/autoload.php';

use Orchestra\Testbench\Foundation\Application as Testbench;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutServiceProvider;

$out = null;
$tagged = false;
$level = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--tagged') {
        $tagged = true;
    } elseif (str_starts_with($arg, '--pdfa=')) {
        $level = substr($arg, 7);
    } elseif ($out === null && !str_starts_with($arg, '-')) {
        $out = $arg;
    } else {
        $out = null;
        break;
    }
}
if ($out === null) {
    fwrite(STDERR, "Usage: php render-examples.php <output-dir> [--tagged] [--pdfa=<level>]\n");
    exit(2);
}
if (!is_dir($out) && !mkdir($out, 0777, true)) {
    fwrite(STDERR, "Could not create $out\n");
    exit(2);
}

$examples = __DIR__ . '/../../../examples/laravel';
$app = Testbench::create();
$app->register(PapercutServiceProvider::class);
$app['view']->getFinder()->prependLocation("$examples/views");
$app['config']->set('papercut.strict', true);
$app['config']->set('papercut.image_path', "$examples/../assets");

/** @var array<string, array<string, mixed>> $data */
$data = require "$examples/data.php";

$failed = 0;
foreach ($data as $view => $viewData) {
    try {
        Papercut::view($view, $viewData)->tagged($tagged)->pdfA($level)->pdf()->save("$out/$view.pdf");
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL $view: " . $e::class . ': ' . $e->getMessage() . "\n");
        $failed++;
        continue;
    }
    echo "ok $view\n";
}

exit($failed === 0 ? 0 : 1);
