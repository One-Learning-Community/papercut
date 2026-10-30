<?php

declare(strict_types=1);

// Run: php examples/core/article.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/article.php';

$document->toPdf()->save(__DIR__ . '/article.pdf');
echo "Wrote examples/core/article.pdf\n";
