<?php

declare(strict_types=1);

// Run: php examples/core/guide.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/guide.php';

$document->toPdf()->save(__DIR__ . '/guide.pdf');
echo "Wrote examples/core/guide.pdf\n";
