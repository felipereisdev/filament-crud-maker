<?php

declare(strict_types=1);

namespace Freis\FilamentCrudGenerator\Commands;

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeFormatter;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CrudGenerator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\FormComponentGenerator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ImportManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\MigrationManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ModelManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ResourceUpdater;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\TableComponentGenerator;
use Illuminate\Console\Command;

class MakeFilamentCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-crud
                            {model? : The model name to generate the CRUD for}
                            {--fields= : Comma-separated list of fields (name:type:default)}
                            {--relations= : Semicolon-separated list of relations (type:model)}
                            {--softDeletes : Add soft deletes to the model}
                            {--no-migrate : Do not run migrations automatically}
                            {--no-format : Do not format the generated code}
                            {--clean-resources : Clean all existing resources}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a complete CRUD in Filament v4';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if we only want to clean resources
        if ($this->option('clean-resources')) {
            return $this->cleanResources();
        }

        // Check if the model was provided
        $model = $this->argument('model');
        if (! is_string($model) || empty($model)) {
            $this->error('You must provide a model name.');

            return 1;
        }

        $fieldsOption = $this->option('fields');
        $relationsOption = $this->option('relations');
        $fields = is_string($fieldsOption) ? $fieldsOption : '';
        $relations = is_string($relationsOption) ? $relationsOption : '';
        $softDeletes = (bool) $this->option('softDeletes');
        $skipMigrations = (bool) $this->option('no-migrate');
        $skipFormatting = (bool) $this->option('no-format');

        // Debug information
        $this->info('=== INPUT INFORMATION ===');
        $this->info("Model: {$model}");
        $this->info("Fields: {$fields}");
        $this->info("Relations: {$relations}");
        $this->info('SoftDeletes: '.($softDeletes ? 'Yes' : 'No'));
        $this->info('Skip migrations: '.($skipMigrations ? 'Yes' : 'No'));
        $this->info('Skip formatting: '.($skipFormatting ? 'Yes' : 'No'));
        $this->info('========================');

        // Initialize the required classes
        $codeValidator = new CodeValidator;
        $importManager = new ImportManager;
        $formGenerator = new FormComponentGenerator;
        $tableGenerator = new TableComponentGenerator;
        $resourceUpdater = new ResourceUpdater(
            $formGenerator,
            $tableGenerator,
            $importManager,
            $codeValidator,
            $this
        );
        $codeFormatter = new CodeFormatter($this);
        $migrationManager = new MigrationManager($this);
        $modelManager = new ModelManager($this);

        // Initialize the CRUD generator
        $crudGenerator = new CrudGenerator(
            $modelManager,
            $migrationManager,
            $resourceUpdater,
            $codeFormatter,
            $this
        );

        // Generate the CRUD
        $result = $crudGenerator->generate(
            $model,
            $fields,
            $relations,
            $softDeletes,
            $skipMigrations,
            $skipFormatting
        );

        return $result ? 0 : 1;
    }

    /**
     * Cleans existing resources
     */
    private function cleanResources(): int
    {
        $this->info('Starting cleanup of all Filament resources...');

        // Initialize the required classes
        $codeValidator = new CodeValidator;
        $importManager = new ImportManager;
        $formGenerator = new FormComponentGenerator;
        $tableGenerator = new TableComponentGenerator;
        $resourceUpdater = new ResourceUpdater(
            $formGenerator,
            $tableGenerator,
            $importManager,
            $codeValidator,
            $this
        );
        $codeFormatter = new CodeFormatter($this);

        // Initialize the CRUD generator with the minimum required classes
        $crudGenerator = new CrudGenerator(
            new ModelManager($this),
            new MigrationManager($this),
            $resourceUpdater,
            $codeFormatter,
            $this
        );

        // Clean all resources
        if ($crudGenerator->cleanAllResources()) {
            $this->info('All resources have been cleaned successfully!');
        } else {
            $this->error('An error occurred while cleaning resources.');

            return 1;
        }

        return 0;
    }
}
