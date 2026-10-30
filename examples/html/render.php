<?php

declare(strict_types=1);

// Renders the core example documents as full-page HTML (fonts linked by relative path). Run: php examples/html/render.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Papercut\Document;
use Papercut\Export\{FontMode, HtmlOptions};
use Papercut\Text\FontFace;

// Font files are referenced by their path relative to this directory (no base64), so the HTML stays small. Open it from a checkout.
$root = (string) realpath(__DIR__ . '/../..');
$fontUrl = static function (FontFace $face) use ($root): ?string {
    $path = (string) (realpath($face->path) ?: $face->path);
    // A face outside the repository has no relative URL that works elsewhere; null skips it.
    if (!str_starts_with($path, $root . '/')) {
        return null;
    }
    return '../../' . ltrim(substr($path, strlen($root)), '/');
};

// The document files assign their own variables, so each is loaded inside a function scope.
$load = static function (string $name): Document {
    return require __DIR__ . '/../core/documents/' . $name . '.php';
};

foreach (['invoice', 'transcript', 'report', 'responses', 'guide'] as $name) {
    $load($name)->toHtml(new HtmlOptions(fullPage: true, fontMode: FontMode::Link, fontUrl: $fontUrl))->save(__DIR__ . '/' . $name . '.html');
    echo 'Wrote examples/html/' . $name . ".html\n";
}
