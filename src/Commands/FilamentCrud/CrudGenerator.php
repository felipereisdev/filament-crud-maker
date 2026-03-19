<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CrudGenerator
{
    public function __construct(
        private readonly ModelManager $modelManager,
        private readonly MigrationManager $migrationManager,
        private readonly ResourceUpdater $resourceUpdater,
        private readonly CodeFormatter $codeFormatter,
        private readonly ?Command $command = null
    ) {
    }

    /**
     * Generates a complete CRUD for the specified model
     */
    public function generate(
        string $model,
        string $fields = '',
        string $relations = '',
        bool $softDeletes = false,
        bool $skipMigrations = false,
        bool $skipCsFixer = false
    ): bool {
        $this->log("Generating CRUD for model {$model}");

        // Process fields - improved to handle complex fields
        $fieldArray = [];
        if (! empty($fields)) {
            // Split by comma, but respecting values that contain commas within rules
            $pattern = '/(?:[^,"]|"(?:\\\\.|[^"\\\\])*")+/';
            preg_match_all($pattern, $fields, $matches);
            $fieldArray = $matches[0];

            // Clean possible extra spaces
            foreach ($fieldArray as $key => $field) {
                $fieldArray[$key] = trim($field);
            }
        }

        $this->log("Fields to process: " . count($fieldArray));
        foreach ($fieldArray as $field) {
            $this->log("Field: {$field}");
        }

        // Process relations
        $relationArray = [];
        $relatedFieldsMap = [];

        if (! empty($relations)) {
            $relationGroups = explode(';', $relations);

            foreach ($relationGroups as $relationGroup) {
                // Check if the group is not empty
                if (empty(trim($relationGroup))) {
                    continue;
                }

                // Split into parts: type:model:fields
                $parts = explode(':', $relationGroup);

                if (count($parts) >= 2) {
                    $relationType = trim($parts[0]);
                    $relatedModel = trim($parts[1]);

                    // Add the relation to the relations array
                    $relationArray[] = $relationType . ':' . $relatedModel;

                    // If fields are specified, process them
                    if (count($parts) > 2) {
                        // Extract all fields from the related model
                        $relatedFields = [];

                        // Rebuild the fields string after the model
                        $fieldsStr = implode(':', array_slice($parts, 2));

                        // Split by comma, considering possible commas in values
                        preg_match_all($pattern, $fieldsStr, $fieldMatches);
                        if (! empty($fieldMatches[0])) {
                            $relatedFields = $fieldMatches[0];
                            // Clean possible extra spaces
                            foreach ($relatedFields as $key => $field) {
                                $relatedFields[$key] = trim($field);
                            }
                        }

                        $relatedFieldsMap[$relatedModel] = $relatedFields;

                        $this->log("Fields for related model {$relatedModel}: " . count($relatedFields));
                        foreach ($relatedFields as $field) {
                            $this->log("Related field: {$field}");
                        }
                    }
                }
            }

            // Create related models first
            $this->createRelatedModels($relationArray, $model, $relatedFieldsMap, $softDeletes);
        }

        // Check if the model already exists, if not, create it
        $this->modelManager->createIfNotExists($model, $softDeletes);

        // Update the migration with the fields
        $this->migrationManager->updateMigration($model, $fieldArray, $relationArray);

        // Create the Filament resource
        $this->log('Creating Filament resource for ' . $model);
        Artisan::call('make:filament-resource', [
            'name' => $model,
            '--generate' => true,
        ]);

        // Update the model with the required fields
        $this->modelManager->updateModel($model, $fieldArray, $relationArray, $softDeletes);

        // Update the resource with the fields
        $this->resourceUpdater->update($model, $fieldArray, $softDeletes);

        // Create Filament Resources for related models
        if (! empty($relationArray)) {
            $this->createRelatedResources($relationArray, $relatedFieldsMap);
        }

        // Format code if needed
        if (! $skipCsFixer) {
            $this->codeFormatter->format();
        }

        // Run migrations if not skipping
        if (! $skipMigrations) {
            $this->migrationManager->runMigrations();
        }

        $this->log('Filament CRUD for ' . $model . ' generated successfully!');

        return true;
    }

    /**
     * Creates related models
     */
    private function createRelatedModels(array $relationArray, string $mainModel, array $relatedFieldsMap, bool $softDeletes): void
    {
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                list($relationType, $relatedModel) = explode(':', $relation);

                // Do not create the main model again
                if ($relatedModel === $mainModel) {
                    continue;
                }

                // Check if the model already exists
                if (! File::exists(app_path('Models/' . $relatedModel . '.php'))) {
                    $this->log('Creating related model ' . $relatedModel);

                    // Create the model
                    $this->modelManager->createIfNotExists($relatedModel, $softDeletes);

                    // If fields are defined for this model, update it
                    if (isset($relatedFieldsMap[$relatedModel])) {
                        $fields = $relatedFieldsMap[$relatedModel];

                        // Update the migration
                        $this->migrationManager->updateMigration($relatedModel, $fields);

                        // Update the model
                        $this->modelManager->updateModel($relatedModel, $fields, [], $softDeletes);
                    }

                    $this->log('Related model ' . $relatedModel . ' created successfully!');
                } else {
                    $this->log('Related model ' . $relatedModel . ' already exists.');

                    // Add soft deletes if needed
                    if ($softDeletes) {
                        // The model already exists, so update it with soft deletes
                        $this->modelManager->updateModel($relatedModel, [], [], $softDeletes);
                    }
                }
            }
        }
    }

    /**
     * Creates Filament resources for related models
     */
    private function createRelatedResources(array $relationArray, array $relatedFieldsMap): void
    {
        $processedModels = [];

        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                list($relationType, $relatedModel) = explode(':', $relation);

                // Avoid duplication
                if (in_array($relatedModel, $processedModels)) {
                    continue;
                }

                $processedModels[] = $relatedModel;

                // Check if the resource already exists
                if (! File::exists(app_path('Filament/Resources/' . $relatedModel . 'Resource.php'))) {
                    $this->log('Creating Filament resource for ' . $relatedModel);

                    Artisan::call('make:filament-resource', [
                        'name' => $relatedModel,
                        '--generate' => true,
                    ]);

                    // Get custom fields for the related model if available
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];

                    if (! empty($fieldsToUse)) {
                        $this->log("Updating resource for {$relatedModel} with " . count($fieldsToUse) . " fields");
                        // Update the resource with the fields
                        $this->resourceUpdater->update($relatedModel, $fieldsToUse, false);
                    } else {
                        $this->log("No fields found for related model {$relatedModel}");
                    }

                    $this->log('Resource for ' . $relatedModel . ' created successfully!');
                } else {
                    $this->log('Resource for ' . $relatedModel . ' already exists.');

                    // Update the resource even if it already exists
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];
                    if (! empty($fieldsToUse)) {
                        $this->log("Updating existing resource for {$relatedModel} with " . count($fieldsToUse) . " fields");
                        $this->resourceUpdater->update($relatedModel, $fieldsToUse, false);
                    }
                }
            }
        }
    }

    /**
     * Cleans existing resources, removing unnecessary imports and fixing issues
     */
    public function cleanAllResources(): bool
    {
        $resourceFiles = File::glob(app_path('Filament/Resources/*Resource.php'));

        foreach ($resourceFiles as $file) {
            $modelName = basename($file, 'Resource.php');
            $this->log("Cleaning resource: {$modelName}");

            $this->resourceUpdater->update($modelName, [], false);
        }

        // Format code
        $this->codeFormatter->format();

        $this->log('All resources have been cleaned successfully!');

        return true;
    }

    /**
     * Logs messages with different levels
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->command) {
            match ($level) {
                'error' => $this->command->error($message),
                'warn' => $this->command->warn($message),
                default => $this->command->info($message),
            };
        }
    }
}
