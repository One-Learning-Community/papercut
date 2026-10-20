<?php

declare(strict_types=1);

// Run: php examples/core/report.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/report.php';

$document->toPdf()->save(__DIR__ . '/report.pdf');
echo "Wrote examples/core/report.pdf\n";
