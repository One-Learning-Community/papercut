<?php

declare(strict_types=1);

// Renders every document under examples/core/documents as a tagged PDF (PDF/UA-1) into a directory.
// Any warning raised through onWarning (a heading skip, a missing alt text, tagging unavailable) fails the run.
// Usage: php packages/core/tools/render-tagged-examples.php <output-dir>
// Check the result with: php packages/core/tools/verapdf-ua1.php <output-dir>
require __DIR__ . '/../vendor/autoload.php';

use Papercut\Export\PdfOptions;

$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "Usage: php render-tagged-examples.php <output-dir>\n");
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
        $options = new PdfOptions(tagged: true, onWarning: static function (string $message) use (&$warnings): void {
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
