<?php

declare(strict_types=1);

// Renders the core example documents as Word files. Run: php examples/docx/render.php
// (after composer install in packages/docx). The .docx files are build output and are not committed.
require __DIR__ . '/../../packages/docx/vendor/autoload.php';

use Papercut\Document;
use Papercut\Docx\DocxExporter;

// The document files assign their own variables, so each is loaded inside a function scope.
$load = static function (string $name): Document {
    return require __DIR__ . '/../core/documents/' . $name . '.php';
};

// The certificate is left out: it fills in a PDF template, which this export drops.
foreach (['invoice', 'transcript', 'report', 'responses', 'guide', 'article'] as $name) {
    $load($name)->export(new DocxExporter())->save(__DIR__ . '/' . $name . '.docx');
    echo 'Wrote examples/docx/' . $name . ".docx\n";
}
