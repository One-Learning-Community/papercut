<?php

return [
    // Blade tag prefix: <x-doc::page>, <x-doc::table>... Lowercase letters, digits and dashes.
    // Read at boot; templates written with another prefix fail with Blade's "Unable to locate a class or view".
    'prefix' => 'doc',
    // Default page size used by <x-doc::page> when no size is given: a3|a4|a5|a6|letter|legal
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
    // Strict export: throw instead of dropping content a format cannot render. Consider true in tests.
    'strict' => false,
    // Base directory for relative <x-doc::image src>
    'image_path' => null, // null = public_path()
    // Kerning cache directory: null = sys_get_temp_dir()/papercut-cache-<uid>, false = disabled, string = path
    'cache_dir' => null,
    'preview' => [
        'enabled' => env('PAPERCUT_PREVIEW', false),
        'path' => '_papercut',
    ],
];
