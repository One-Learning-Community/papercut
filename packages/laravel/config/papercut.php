<?php

return [
    // Default page size used by <x-pdf::page> when no size is given: a3|a4|a5|a6|letter|legal
    'paper' => 'a4',
    'margin' => '20mm',
    // Font families: name => [weight => ['normal' => path, 'italic' => path]]. Inter is always available.
    'fonts' => [],
    'fallback_fonts' => [],
    // Theme overrides: font_family, font_size (pt float), color, line_height
    'theme' => [],
    // Named styles usable as style="card": name => attribute array (same keys as tag attributes)
    'styles' => [],
    // throw | clip | null (null = throw in local/testing, clip elsewhere)
    'overflow' => null,
    // Base directory for relative <x-pdf::image src>
    'image_path' => null, // null = public_path()
    // Kerning cache directory: null = sys_get_temp_dir()/papercut-cache-<uid>, false = disabled, string = path
    'cache_dir' => null,
    'preview' => [
        'enabled' => env('PAPERCUT_PREVIEW', false),
        'path' => '_papercut',
    ],
];
