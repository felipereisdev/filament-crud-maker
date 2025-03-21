<?php

declare(strict_types=1);

namespace Freis\FilamentCrudGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Freis\FilamentCrudGenerator\Commands\MakeFilamentCrud;

class FilamentCrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publicar o arquivo de configuração
        $this->publishes([
            __DIR__ . '/../config/filament-crud-generator.php' => config_path('filament-crud-generator.php'),
        ], 'filament-crud-generator-config');

        // Publicar o arquivo de configuração do PHP CS Fixer
        $this->publishes([
            __DIR__ . '/../config/php-cs-fixer.dist.php.example' => base_path('.php-cs-fixer.dist.php.example'),
        ], 'php-cs-fixer-config');

        // Registrar comandos
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFilamentCrud::class,
            ]);
        }
    }

    public function register(): void
    {
        // Mesclar configurações
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-crud-generator.php',
            'filament-crud-generator'
        );
    }
} 