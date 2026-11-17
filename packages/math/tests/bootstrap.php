<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Xdebug's develop mode aborts at 512 frames by default; the deepest TeX nests the reader allows (MAX_DEPTH 256, about 10 frames each) recurse past that.
if (function_exists('xdebug_info')) {
    ini_set('xdebug.max_nesting_level', '4096');
}
