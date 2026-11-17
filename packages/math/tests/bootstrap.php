<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Xdebug's develop mode aborts at 512 frames; the deepest TeX nests the reader allows recurse past that.
if (function_exists('xdebug_info')) {
    ini_set('xdebug.max_nesting_level', '2048');
}
