<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Papercut\Laravel\PapercutServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PapercutServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [...(array) $app['config']->get('view.paths', []), __DIR__ . '/views']);
    }
}
