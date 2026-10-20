<?php

declare(strict_types=1);

// Run: php examples/core/invoice.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/invoice.php';

$document->toPdf()->save(__DIR__ . '/invoice.pdf');
echo "Wrote examples/core/invoice.pdf\n";
