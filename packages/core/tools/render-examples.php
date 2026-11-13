<?php

declare(strict_types=1);

// Renders every document under examples/core/documents into a directory, optionally tagged (PDF/UA-1)
// and/or as PDF/A. Any warning raised through onWarning (a heading skip, tagging unavailable, an empty
// structure tree) fails the run.
// Usage: php packages/core/tools/render-examples.php <output-dir> [--tagged] [--pdfa=<level>]
// Check the result with: php packages/core/tools/verapdf.php <flavour> <output-dir>
require __DIR__ . '/../vendor/autoload.php';

use Papercut\Export\PdfA;
use Papercut\Export\PdfOptions;

$out = null;
$tagged = false;
$level = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--tagged') {
        $tagged = true;
    } elseif (str_starts_with($arg, '--pdfa=')) {
        $level = PdfA::tryFrom(substr($arg, 7));
        if ($level === null) {
            fwrite(STDERR, "Unknown PDF/A level in $arg\n");
            exit(2);
        }
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

$files = glob(__DIR__ . '/../../../examples/core/documents/*.php') ?: [];
if ($files === []) {
    fwrite(STDERR, "No example documents found\n");
    exit(2);
}

$failed = 0;
foreach ($files as $file) {
    $name = basename($file, '.php');
    $warnings = [];
    try {
        $document = (static fn () => require $file)();
        $options = new PdfOptions(tagged: $tagged, pdfA: $level, onWarning: static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });
        $document->toPdf($options)->save("$out/$name.pdf");
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL $name: " . $e::class . ': ' . $e->getMessage() . "\n");
        $failed++;
        continue;
    }
    if ($warnings !== []) {
        foreach ($warnings as $warning) {
            fwrite(STDERR, "FAIL $name: warning: $warning\n");
        }
        $failed++;
        continue;
    }
    echo "ok $name\n";
}

exit($failed === 0 ? 0 : 1);
