<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Papercut\Laravel\Console\FontsCommand;
use Papercut\Laravel\Preview\{PreviewController, PreviewRegistry};
use Papercut\Laravel\Rendering\NodeRegistry;

final class PapercutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/papercut.php', 'papercut');

        $this->app->singleton(NodeRegistry::class);

        $this->app->singleton(PreviewRegistry::class);

        $this->app->scoped(PdfManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/papercut.php' => $this->app->configPath('papercut.php'),
        ], 'papercut-config');

        if ($this->app->runningInConsole()) {
            $this->commands([FontsCommand::class]);
        }

        Blade::componentNamespace('Papercut\\Laravel\\Components', 'pdf');

        if (config('papercut.preview.enabled') && $this->app->environment('local')) {
            Route::prefix((string) config('papercut.preview.path', '_papercut'))->group(static function (): void {
                Route::get('/', [PreviewController::class, 'index']);
                Route::get('/{view}', [PreviewController::class, 'show']);
            });
        }
    }
}
