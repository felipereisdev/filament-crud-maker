<?php

declare(strict_types=1);

namespace Freis\FilamentCrudGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Freis\FilamentCrudGenerator\Commands\MakeFilamentCrud;

class FilamentCrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__ . '/../config/filament-crud-generator.php' => config_path('filament-crud-generator.php'),
        ], 'filament-crud-generator-config');

        // Publish the PHP CS Fixer configuration file
        $this->publishes([
            __DIR__ . '/../config/php-cs-fixer.dist.php.example' => base_path('.php-cs-fixer.dist.php.example'),
        ], 'php-cs-fixer-config');

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
            __DIR__ . '/../config/filament-crud-generator.php',
            'filament-crud-generator'
        );
    }
}