<?php

declare(strict_types=1);

// Run: php examples/core/certificate.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/certificate.php';

$document->toPdf()->save(__DIR__ . '/certificate.pdf');
echo "Wrote examples/core/certificate.pdf\n";
