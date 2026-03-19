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
    | If true, generated files will be automatically formatted using PHP CS Fixer,
    | unless the --no-format flag is provided.
    |
    */
    'auto_format' => true,

    /*
    |--------------------------------------------------------------------------
    | CS Fixer Configuration
    |--------------------------------------------------------------------------
    |
    | Name of the PHP CS Fixer configuration file to use or create.
    |
    */
    'cs_fixer_config_file' => '.php-cs-fixer.dist.php',
];