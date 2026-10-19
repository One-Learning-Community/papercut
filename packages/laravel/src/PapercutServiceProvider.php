<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Papercut\Laravel\Rendering\NodeRegistry;

final class PapercutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/papercut.php', 'papercut');

        $this->app->singleton(NodeRegistry::class);

        // The scoped PdfManager binding is added in Task 6, when the class exists.
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/papercut.php' => $this->app->configPath('papercut.php'),
        ], 'papercut-config');

        Blade::componentNamespace('Papercut\\Laravel\\Components', 'pdf');
    }
}
