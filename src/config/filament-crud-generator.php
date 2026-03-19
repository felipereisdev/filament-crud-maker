<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Namespace
    |--------------------------------------------------------------------------
    |
    | This value defines the default namespace where your models will be created.
    |
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Filament Resource Namespace
    |--------------------------------------------------------------------------
    |
    | This value defines the default namespace where Filament Resources will be created.
    |
    */
    'resource_namespace' => 'App\\Filament\\Resources',

    /*
    |--------------------------------------------------------------------------
    | Auto Migration
    |--------------------------------------------------------------------------
    |
    | If true, migrations will be run automatically after creating the files,
    | unless the --no-migrate flag is provided.
    |
    */
    'auto_migrate' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Formatting
    |--------------------------------------------------------------------------
    |
    | If true, generated files will be automatically formatted using Laravel Pint,
    | unless the --no-format flag is provided.
    |
    */
    'auto_format' => true,
];
