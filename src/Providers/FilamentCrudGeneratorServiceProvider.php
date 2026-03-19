<?php

declare(strict_types=1);

namespace Freis\FilamentCrudGenerator\Providers;

use Freis\FilamentCrudGenerator\Commands\MakeFilamentCrud;
use Illuminate\Support\ServiceProvider;

class FilamentCrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__.'/../config/filament-resource-generator.php' => config_path('filament-resource-generator.php'),
        ], 'filament-resource-generator-config');

        // Publish the Laravel Pint configuration file
        $this->publishes([
            dirname(__DIR__, 3).'/pint.json' => base_path('pint.json'),
        ], 'pint-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFilamentCrud::class,
            ]);
        }
    }

    public function register(): void
    {
        // Merge configurations
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-resource-generator.php',
            'filament-resource-generator'
        );
    }
}
