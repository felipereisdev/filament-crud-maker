<?php

use Illuminate\Support\Facades\Artisan;

test('command is registered', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('make:filament-crud');
});

test('requires model name', function () {
    $this->artisan('make:filament-crud')
        ->expectsOutputToContain('Você deve fornecer um nome de modelo.')
        ->assertExitCode(1);
});

test('displays input information', function () {
    $output = new \Symfony\Component\Console\Output\BufferedOutput();

    try {
        /** @var \Illuminate\Contracts\Console\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('make:filament-crud', [
            'model' => 'Post',
            '--fields' => 'title:string,body:text',
            '--relations' => 'hasMany:Comment',
            '--no-migrate' => true,
            '--no-format' => true,
        ], $output);
    } catch (\Throwable) {
        // Command fails during generation because make:filament-resource is not available in test environment
    }

    $content = $output->fetch();
    expect($content)->toContain('Modelo: Post')
        ->toContain('Campos: title:string,body:text')
        ->toContain('Relações: hasMany:Comment');
});

test('flag --clean-resources works correctly', function () {
    $this->artisan('make:filament-crud', ['--clean-resources' => true])
        ->expectsOutputToContain('Iniciando limpeza de todos os recursos Filament...')
        ->assertExitCode(0);
});
