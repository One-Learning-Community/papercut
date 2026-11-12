<?php

declare(strict_types=1);

// Renders the Blade views under examples/laravel/views as tagged PDFs (PDF/UA-1) into a directory.
// Any warning raised through onWarning (a heading skip, tagging unavailable) fails the run.
// Usage: php packages/laravel/tools/render-tagged-examples.php <output-dir>
// Check the result with: php packages/core/tools/verapdf-ua1.php <output-dir>
require __DIR__ . '/../vendor/autoload.php';

use Orchestra\Testbench\Foundation\Application as Testbench;
use Papercut\Export\PdfOptions;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutServiceProvider;

$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "Usage: php render-tagged-examples.php <output-dir>\n");
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
$app['config']->set('papercut.image_path', "$examples/../assets");

/** @var array<string, array<string, mixed>> $data */
$data = require "$examples/data.php";

$failed = 0;
foreach ($data as $view => $viewData) {
    $warnings = [];
    try {
        $options = new PdfOptions(tagged: true, onWarning: static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });
        Papercut::view($view, $viewData)->document()->toPdf($options)->save("$out/$view.pdf");
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL $view: " . $e::class . ': ' . $e->getMessage() . "\n");
        $failed++;
        continue;
    }
    if ($warnings !== []) {
        foreach ($warnings as $warning) {
            fwrite(STDERR, "FAIL $view: warning: $warning\n");
        }
        $failed++;
        continue;
    }
    echo "ok $view\n";
}

exit($failed === 0 ? 0 : 1);
