<?php

declare(strict_types=1);

// Run: php examples/core/transcript.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

$document = require __DIR__ . '/documents/transcript.php';

$document->toPdf()->save(__DIR__ . '/transcript.pdf');
echo "Wrote examples/core/transcript.pdf\n";
