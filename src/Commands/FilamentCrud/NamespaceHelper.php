<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Support\Str;

class NamespaceHelper
{
    /**
     * Returns the configured model namespace.
     */
    public static function modelNamespace(): string
    {
        $value = config('filament-crud-maker.model_namespace', 'App\\Models');

        return is_string($value) ? $value : 'App\\Models';
    }

    /**
     * Returns the configured Filament resource namespace.
     */
    public static function resourceNamespace(): string
    {
        $value = config('filament-crud-maker.resource_namespace', 'App\\Filament\\Resources');

        return is_string($value) ? $value : 'App\\Filament\\Resources';
    }

    /**
     * Converts an App\ namespace to a filesystem path.
     * e.g. "App\Models" -> app_path("Models")
     */
    public static function namespacePath(string $namespace): string
    {
        $relative = str_replace('\\', '/', Str::after($namespace, 'App\\'));

        return app_path($relative);
    }

    /**
     * Returns the full filesystem path to a model file.
     */
    public static function modelPath(string $model): string
    {
        return self::namespacePath(self::modelNamespace()).'/'.$model.'.php';
    }

    /**
     * Returns the base filesystem path for Filament resources.
     */
    public static function resourceBasePath(): string
    {
        return self::namespacePath(self::resourceNamespace());
    }
}
