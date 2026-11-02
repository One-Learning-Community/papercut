<?php

declare(strict_types=1);

// Run: php examples/core/paper.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/paper.php';

$document->toPdf()->save(__DIR__ . '/paper.pdf');
echo "Wrote examples/core/paper.pdf\n";
