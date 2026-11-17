<?php

declare(strict_types=1);

// Run: php examples/laravel/render.php
//
// Boots a throwaway Laravel application with Orchestra Testbench (a dev dependency of
// packages/laravel), so no full Laravel install is needed. Install the dependencies first:
//   cd packages/laravel && composer install
require __DIR__ . '/../../packages/laravel/vendor/autoload.php';

use Orchestra\Testbench\Foundation\Application as Testbench;
use Quoin\Laravel\Facades\Quoin;
use Quoin\Laravel\QuoinServiceProvider;

$app = Testbench::create();
// Testbench does not auto-discover this repo's own package, so register the provider by hand.
$app->register(QuoinServiceProvider::class);
$app['view']->getFinder()->prependLocation(__DIR__ . '/views');
// Relative template and image paths in the views, such as the certificate's, resolve against this directory.
$app['config']->set('quoin.image_path', __DIR__ . '/../assets');

/** @var array<string, array<string, mixed>> $data */
$data = require __DIR__ . '/data.php';

foreach ($data as $view => $viewData) {
    $path = __DIR__ . "/$view.pdf";
    Quoin::view($view, $viewData)->pdf()->save($path);
    echo "Wrote examples/laravel/$view.pdf\n";
    // HTML export leaves page templates out, so the certificate has no HTML version.
    if ($view === 'certificate') {
        continue;
    }
    Quoin::view($view, $viewData)->html()->page()->save(__DIR__ . "/$view.html");
    echo "Wrote examples/laravel/$view.html\n";
}
