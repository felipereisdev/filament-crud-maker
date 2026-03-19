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
use Illuminate\Support\Str;

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
    protected $description = 'Generates a complete CRUD for Filament v4/v5';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if we only want to clean resources
        if ($this->option('clean-resources')) {
            return $this->cleanResources();
        }

        // Check if the model was provided; prompt interactively if missing
        $model = $this->argument('model');
        $isInteractive = $this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN);

        if (! is_string($model) || empty($model)) {
            if ($isInteractive) {
                $model = $this->ask('Model name');
            }

            if (! is_string($model) || empty($model)) {
                $this->error('You must provide a model name.');

                return 1;
            }
        }

        $fieldsOption = $this->option('fields');
        $relationsOption = $this->option('relations');
        $fields = is_string($fieldsOption) ? $fieldsOption : '';
        $relations = is_string($relationsOption) ? $relationsOption : '';
        $softDeletes = (bool) $this->option('softDeletes');

        // Interactive wizard when --fields is not provided
        if (empty($fields) && $isInteractive) {
            if ($this->confirm('No --fields provided. Use interactive wizard?', true)) {
                [$fields, $relations, $softDeletes] = $this->runInteractiveWizard();
            }
        }

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
     * Interactively collects field, relation, and soft-delete configuration from the user.
     *
     * @return array{0: string, 1: string, 2: bool}
     */
    private function runInteractiveWizard(): array
    {
        $fieldTypes = [
            'string', 'text', 'textarea', 'boolean', 'integer', 'decimal', 'float',
            'date', 'datetime', 'select', 'foreignId', 'color', 'image', 'file',
            'richtext', 'markdown', 'json', 'tags', 'keyvalue',
        ];

        $fieldParts = [];
        $this->info('--- Field Wizard (leave name empty to finish) ---');

        while (true) {
            $fieldName = $this->ask('Field name (empty to finish)');
            if (! is_string($fieldName) || $fieldName === '') {
                break;
            }

            $fieldTypeRaw = $this->choice('Field type', $fieldTypes, 0);
            $fieldType = is_array($fieldTypeRaw) ? implode(',', $fieldTypeRaw) : $fieldTypeRaw;
            $nullable = $this->confirm('Nullable?', false);

            $fieldDef = $fieldName.':'.$fieldType;
            if ($nullable) {
                $fieldDef .= ':nullable';
            }

            $fieldParts[] = $fieldDef;
        }

        $relationParts = [];
        if ($this->confirm('Add relationships?', false)) {
            $relationTypes = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany'];

            while (true) {
                $relationTypeRaw = $this->choice('Relation type', $relationTypes, 0);
                $relationType = is_array($relationTypeRaw) ? implode(',', $relationTypeRaw) : $relationTypeRaw;
                $prompt = $relationType === 'morphTo' ? 'Morph name (e.g. commentable)' : 'Related model name';
                $relatedModel = $this->ask($prompt);

                if (! is_string($relatedModel) || $relatedModel === '') {
                    break;
                }

                $relationDef = $relationType.':'.$relatedModel;

                // For morphOne/morphMany, ask for the morph name (optional)
                if (in_array($relationType, ['morphOne', 'morphMany'])) {
                    $defaultMorphName = Str::snake($relatedModel).'able';
                    $morphNameInput = $this->ask("Morph name (leave empty for '{$defaultMorphName}')");
                    if (is_string($morphNameInput) && $morphNameInput !== '' && $morphNameInput !== $defaultMorphName) {
                        $relationDef .= ':'.$morphNameInput;
                    }
                }

                $relationParts[] = $relationDef;

                if (! $this->confirm('Add another relationship?', false)) {
                    break;
                }
            }
        }

        $softDeletes = $this->confirm('Add soft deletes?', false);

        return [
            implode(',', $fieldParts),
            implode(';', $relationParts),
            $softDeletes,
        ];
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
