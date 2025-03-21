<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Namespace do modelo
    |--------------------------------------------------------------------------
    |
    | Este valor define o namespace padrão onde seus modelos serão criados.
    |
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Namespace do Resource do Filament
    |--------------------------------------------------------------------------
    |
    | Este valor define o namespace padrão onde os Resources do Filament serão criados.
    |
    */
    'resource_namespace' => 'App\\Filament\\Resources',

    /*
    |--------------------------------------------------------------------------
    | Migração automática
    |--------------------------------------------------------------------------
    |
    | Se verdadeiro, executará migrações automaticamente após criar os arquivos,
    | a menos que a flag --no-migrate seja fornecida.
    |
    */
    'auto_migrate' => true,

    /*
    |--------------------------------------------------------------------------
    | Formatação automática
    |--------------------------------------------------------------------------
    |
    | Se verdadeiro, formatará automaticamente os arquivos gerados usando PHP CS Fixer,
    | a menos que a flag --no-format seja fornecida.
    |
    */
    'auto_format' => true,

    /*
    |--------------------------------------------------------------------------
    | Configuração do CS Fixer
    |--------------------------------------------------------------------------
    |
    | Nome do arquivo de configuração do PHP CS Fixer a ser usado ou criado.
    |
    */
    'cs_fixer_config_file' => '.php-cs-fixer.dist.php',
]; 