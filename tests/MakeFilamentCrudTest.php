<?php

namespace Freis\FilamentCrudGenerator\Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Freis\FilamentCrudGenerator\Providers\FilamentCrudGeneratorServiceProvider;

class MakeFilamentCrudTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            FilamentCrudGeneratorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Configurações adicionais para o teste
    }

    /** @test */
    public function the_command_is_registered()
    {
        $this->assertTrue(array_key_exists('make:filament-crud', Artisan::all()));
    }

    /** @test */
    public function it_requires_model_name()
    {
        $this->artisan('make:filament-crud')
            ->expectsOutput('Você deve fornecer um nome de modelo.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_input_information()
    {
        $this->artisan('make:filament-crud', ['model' => 'Produto'])
            ->expectsOutput('=== INFORMAÇÕES DE ENTRADA ===')
            ->expectsOutput('Modelo: Produto')
            ->expectsOutput('Campos: ')
            ->expectsOutput('Relações: ')
            ->expectsOutput('SoftDeletes: Não')
            ->expectsOutput('Pular migrações: Não')
            ->expectsOutput('Pular formatação: Não')
            ->expectsOutput('============================');
    }
}