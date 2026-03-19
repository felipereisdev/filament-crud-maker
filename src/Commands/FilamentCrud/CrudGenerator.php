<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CrudGenerator
{
    public function __construct(
        private readonly ModelManager $modelManager,
        private readonly MigrationManager $migrationManager,
        private readonly ResourceUpdater $resourceUpdater,
        private readonly CodeFormatter $codeFormatter,
        private readonly ?Command $command = null
    ) {}

    /**
     * Generates a complete CRUD for the specified model
     */
    public function generate(
        string $model,
        string $fields = '',
        string $relations = '',
        bool $softDeletes = false,
        bool $skipMigrations = false,
        bool $skipFormatting = false
    ): bool {
        $this->log("Generating CRUD for model {$model}");

        // Process fields - context-aware splitting that respects commas inside validation values
        $fieldArray = [];
        if (! empty($fields)) {
            $fieldArray = self::splitFields($fields);
        }

        $this->log('Fields to process: '.count($fieldArray));
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

                // Split into parts: type:model[:morphName]:fields
                $parts = explode(':', $relationGroup);

                if (count($parts) >= 2) {
                    $relationType = trim($parts[0]);
                    $relatedModel = trim($parts[1]);

                    // For morphOne/morphMany, check if the third segment is a morph name
                    // (lowercase identifier, not a field definition like "name" in "name:string")
                    $morphName = null;
                    $fieldsStartIndex = 2;

                    if (in_array($relationType, ['morphOne', 'morphMany']) && count($parts) > 2) {
                        $candidate = trim($parts[2]);
                        // A morph name is a lowercase snake_case identifier that is NOT followed by
                        // a field type (i.e. the next part would be a known field type or another morph name segment)
                        if (preg_match('/^[a-z][a-z_]*$/', $candidate)) {
                            // Check if this looks like a morph name (not a field name with type after it)
                            $hasTypeAfter = count($parts) > 3 && preg_match('/^[a-z][a-zA-Z]+$/', trim($parts[3]));
                            $isFieldDef = count($parts) > 3 && in_array(trim($parts[3]), [
                                'string', 'text', 'textarea', 'longtext', 'boolean', 'integer', 'bigInteger',
                                'decimal', 'float', 'double', 'date', 'datetime', 'time', 'select', 'enum',
                                'foreignId', 'checkboxes', 'radio', 'color', 'file', 'image', 'richtext',
                                'editor', 'markdown', 'tags', 'code', 'json', 'slider', 'range',
                                'toggleButtons', 'keyvalue', 'checkbox',
                            ]);

                            if (! $isFieldDef && ! $hasTypeAfter) {
                                // Standalone morph name with no fields after it
                                $morphName = $candidate;
                                $fieldsStartIndex = 3;
                            } elseif ($isFieldDef) {
                                // Next part is a field type, so this is a morph name followed by fields
                                $morphName = $candidate;
                                $fieldsStartIndex = 3;
                            }
                        }
                    }

                    // Build the relation entry with optional morph name
                    $relationEntry = $relationType.':'.$relatedModel;
                    if ($morphName !== null) {
                        $relationEntry .= ':'.$morphName;
                    }
                    $relationArray[] = $relationEntry;

                    // If fields are specified, process them
                    if (count($parts) > $fieldsStartIndex) {
                        // Rebuild the fields string after the model (and optional morph name)
                        $fieldsStr = implode(':', array_slice($parts, $fieldsStartIndex));

                        // Split by comma, respecting commas inside validation values
                        $relatedFields = self::splitFields($fieldsStr);

                        $relatedFieldsMap[$relatedModel] = $relatedFields;

                        $this->log("Fields for related model {$relatedModel}: ".count($relatedFields));
                        foreach ($relatedFields as $field) {
                            $this->log("Related field: {$field}");
                        }
                    }
                }
            }

            // Create related models first
            $this->createRelatedModels($relationArray, $model, $relatedFieldsMap);
        }

        // Check if the model already exists, if not, create it
        $this->modelManager->createIfNotExists($model, $softDeletes);

        // Update the migration with the fields
        $this->migrationManager->updateMigration($model, $fieldArray, $relationArray, $softDeletes);

        // For hasMany/hasOne relations, add the FK column to the related model's migration
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);
                if (in_array($relationType, ['hasMany', 'hasOne'])) {
                    if ($this->migrationManager->shouldUseAlterMigration($relatedModel)) {
                        $this->migrationManager->createAlterMigration($relatedModel, $model);
                    } else {
                        $this->migrationManager->updateMigration($relatedModel, [], ['belongsTo:'.$model]);
                    }
                }
            }
        }

        // Create the Filament resource
        $this->log('Creating Filament resource for '.$model);
        $this->callMakeFilamentResource($model);

        // Update the model with the required fields
        $this->modelManager->updateModel($model, $fieldArray, $relationArray, $softDeletes);

        // Add inverse belongsToMany on related models
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);
                if ($relationType === 'belongsToMany') {
                    $this->modelManager->updateModel($relatedModel, [], ['belongsToMany:'.$model], false);
                }
            }
        }

        // Auto-generate foreignId fields from belongsTo relations so the form includes Select components
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);
                if ($relationType === 'belongsTo') {
                    $foreignKey = Str::snake($relatedModel).'_id';
                    $alreadyDefined = false;
                    foreach ($fieldArray as $existingField) {
                        if (str_starts_with($existingField, $foreignKey.':')) {
                            $alreadyDefined = true;

                            break;
                        }
                    }
                    if (! $alreadyDefined) {
                        $fieldArray[] = $foreignKey.':foreignId';
                    }
                }
            }
        }

        // Auto-generate belongsToMany pseudo-fields so the form includes multi-Select components
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);
                if ($relationType === 'belongsToMany') {
                    $pluralRelation = Str::camel(Str::plural($relatedModel));
                    $fieldArray[] = $pluralRelation.':belongsToMany';
                }
            }
        }

        // Update the resource with the fields
        $this->resourceUpdater->update($model, $fieldArray, $softDeletes);

        // Create Filament Resources for related models
        if (! empty($relationArray)) {
            $this->createRelatedResources($relationArray, $relatedFieldsMap);
        }

        // Format code if needed
        if (! $skipFormatting) {
            $this->codeFormatter->format();
        }

        // Run migrations if not skipping
        if (! $skipMigrations) {
            $this->migrationManager->runMigrations();
        }

        $this->log('Filament CRUD for '.$model.' generated successfully!');

        return true;
    }

    /**
     * Splits a comma-separated fields string, respecting commas inside validation values like between=1,12
     *
     * @return array<int, string>
     */
    public static function splitFields(string $fields): array
    {
        $result = [];
        $current = '';
        $inValue = false;
        $length = strlen($fields);

        for ($i = 0; $i < $length; $i++) {
            $char = $fields[$i];

            if ($char === '=' && ! $inValue) {
                $inValue = true;
            } elseif ($char === ':' && $inValue) {
                $inValue = false;
            } elseif ($char === ',') {
                if ($inValue) {
                    // Lookahead: check if the text after this comma starts a new field definition.
                    // A new field starts with a valid field name (letter/underscore) followed by ':type'.
                    $remaining = substr($fields, $i + 1);
                    $nextComma = strpos($remaining, ',');
                    $nextSegment = $nextComma !== false ? substr($remaining, 0, $nextComma) : $remaining;

                    if (preg_match('/^[a-zA-Z_]\w*:/', $nextSegment)) {
                        // Next segment starts a new field — split here
                        $inValue = false;
                        $result[] = trim($current);
                        $current = '';

                        continue;
                    }
                    // Otherwise the comma is part of the validation value (e.g. between=1,12)
                } else {
                    $result[] = trim($current);
                    $current = '';

                    continue;
                }
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $result[] = trim($current);
        }

        return $result;
    }

    /**
     * Creates related models
     *
     * @param  array<int, string>  $relationArray
     * @param  array<string, array<int, string>>  $relatedFieldsMap
     */
    private function createRelatedModels(array $relationArray, string $mainModel, array $relatedFieldsMap): void
    {
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);

                // Do not create the main model again
                if ($relatedModel === $mainModel) {
                    continue;
                }

                // morphTo carries a morph name, not an actual model class
                if ($relationType === 'morphTo') {
                    continue;
                }

                // Check if the model already exists
                if (! File::exists(NamespaceHelper::modelPath($relatedModel))) {
                    $this->log('Creating related model '.$relatedModel);

                    // Related models never inherit soft deletes from the main model
                    $this->modelManager->createIfNotExists($relatedModel, false);

                    // If fields are defined for this model, update it
                    if (isset($relatedFieldsMap[$relatedModel])) {
                        $fields = $relatedFieldsMap[$relatedModel];

                        // Update the migration
                        $this->migrationManager->updateMigration($relatedModel, $fields);

                        // Update the model (no soft deletes for related models)
                        $this->modelManager->updateModel($relatedModel, $fields, [], false);
                    }

                    $this->log('Related model '.$relatedModel.' created successfully!');
                } else {
                    $this->log('Related model '.$relatedModel.' already exists.');
                }
            }
        }
    }

    /**
     * Creates Filament resources for related models
     *
     * @param  array<int, string>  $relationArray
     * @param  array<string, array<int, string>>  $relatedFieldsMap
     */
    private function createRelatedResources(array $relationArray, array $relatedFieldsMap): void
    {
        $processedModels = [];

        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);

                // Avoid duplication
                if (in_array($relatedModel, $processedModels)) {
                    continue;
                }

                // morphTo carries a morph name, not a resource-backed model
                if ($relationType === 'morphTo') {
                    continue;
                }

                $processedModels[] = $relatedModel;

                // Check if the resource already exists
                if ($this->resourceUpdater->resolveResourcePath($relatedModel) === null) {
                    $this->log('Creating Filament resource for '.$relatedModel);

                    $this->callMakeFilamentResource($relatedModel);

                    // Get custom fields for the related model if available
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];

                    if (! empty($fieldsToUse)) {
                        $this->log("Updating resource for {$relatedModel} with ".count($fieldsToUse).' fields');
                        // Update the resource with the fields
                        $this->resourceUpdater->update($relatedModel, $fieldsToUse, false);
                    } else {
                        $this->log("No fields found for related model {$relatedModel}");
                    }

                    $this->log('Resource for '.$relatedModel.' created successfully!');
                } else {
                    $this->log('Resource for '.$relatedModel.' already exists.');

                    // Update the resource even if it already exists
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];
                    if (! empty($fieldsToUse)) {
                        $this->log("Updating existing resource for {$relatedModel} with ".count($fieldsToUse).' fields');
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
        $base = NamespaceHelper::resourceBasePath();
        $v4 = File::glob($base.'/*Resource.php') ?: [];
        $v5 = File::glob($base.'/*/*Resource.php') ?: [];
        $resourceFiles = array_merge($v4, $v5);

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
     * Calls make:filament-resource using the correct argument name for the installed version.
     * Filament v5 uses `model`, v4 uses `name`.
     */
    private function callMakeFilamentResource(string $model): void
    {
        $argName = 'name';
        $version = InstalledVersions::getPrettyVersion('filament/filament');
        if ($version !== null && version_compare(ltrim($version, 'v'), '5.0.0', '>=')) {
            $argName = 'model';
        }

        Artisan::call('make:filament-resource', [$argName => $model, '--generate' => true]);
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
