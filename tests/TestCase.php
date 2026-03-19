<?php

namespace Freis\FilamentCrudGenerator\Tests;

use Freis\FilamentCrudGenerator\Providers\FilamentCrudGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentCrudGeneratorServiceProvider::class,
        ];
    }
}
