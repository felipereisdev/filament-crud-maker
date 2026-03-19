<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelManager
{
    public function __construct(private readonly ?Command $command = null) {}

    /**
     * Checks if the model already exists and creates it if needed
     */
    public function createIfNotExists(string $model, bool $softDeletes = false): bool
    {
        if (! File::exists(NamespaceHelper::modelPath($model))) {
            $this->log('Creating model '.$model);
            $modelCommand = [
                'name' => $model,
                '-m' => true, // Create migration
            ];

            if ($softDeletes) {
                $modelCommand['-s'] = true; // Add soft deletes
            }

            Artisan::call('make:model', $modelCommand);

            $this->log('Model created successfully!');

            return true;
        } else {
            $this->log('Model already exists. Skipping model creation.');

            // Check if the model has softDeletes and add if needed
            if ($softDeletes) {
                $this->addSoftDeletesIfNotExists($model);
            }

            return false;
        }
    }

    /**
     * Updates the model with relationships and required properties
     *
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $relations
     */
    public function updateModel(string $model, array $fields, array $relations, bool $softDeletes = false): bool
    {
        $modelPath = NamespaceHelper::modelPath($model);

        if (! File::exists($modelPath)) {
            $this->log("Model not found: {$modelPath}", 'error');

            return false;
        }

        $content = File::get($modelPath);

        // Check if the model already uses softDeletes
        $hasSoftDeletes = strpos($content, 'use Illuminate\Database\Eloquent\SoftDeletes;') !== false;
        $usesSoftDeletes = strpos($content, 'use SoftDeletes;') !== false;

        // Add softDeletes if needed
        if ($softDeletes && ! $hasSoftDeletes) {
            $content = str_replace(
                'use Illuminate\Database\Eloquent\Model;',
                "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\SoftDeletes;",
                $content
            );
        }

        if ($softDeletes && ! $usesSoftDeletes) {
            // Find the class to add the trait
            $pattern = '/class\s+'.$model.'\s+extends\s+Model\s*\{/';
            $replacement = "class {$model} extends Model\n{\n    use SoftDeletes;\n";
            $content = preg_replace($pattern, $replacement, $content);
            if ($content === null) {
                return false;
            }
        }

        // Add fillable properties based on fields
        $fillableFields = [];
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                $parts = explode(':', $field);
                $fieldName = $parts[0];
                $fillableFields[] = "'{$fieldName}'";
            }
        }

        // Add foreign keys from belongsTo relations
        if (! empty($relations)) {
            foreach ($relations as $relation) {
                if (strpos($relation, ':') !== false) {
                    [$relationType, $relatedModel] = explode(':', $relation);

                    if ($relationType === 'belongsTo') {
                        $foreignKey = Str::snake($relatedModel).'_id';
                        if (! in_array("'{$foreignKey}'", $fillableFields)) {
                            $fillableFields[] = "'{$foreignKey}'";
                        }
                    }
                }
            }
        }

        // Check if fillable already exists and update it, but only if there are fields to set
        if (! empty($fillableFields)) {
            if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\];/s', $content, $matches)) {
                // Fillable already exists — merge to avoid losing existing entries
                $existingFillable = array_filter(
                    array_map('trim', explode(',', $matches[1])),
                    fn (string $item): bool => $item !== ''
                );
                $mergedFillable = array_values(array_unique(array_merge($existingFillable, $fillableFields)));
                $fillableString = implode(', ', $mergedFillable);
                $newFillable = "protected \$fillable = [{$fillableString}];";
                $content = str_replace($matches[0], $newFillable, $content);
            } else {
                // Add fillable
                $fillableString = implode(', ', $fillableFields);
                $fillableProperty = "\n    protected \$fillable = [{$fillableString}];\n";

                // Find a good place to add fillable (after the class declaration)
                $pattern = '/class\s+'.$model.'\s+extends\s+Model\s*\{[^\}]*?(\n\s*use\s+[^;]+;)?/s';
                if (preg_match($pattern, $content, $matches)) {
                    $position = strpos($content, $matches[0]) + strlen($matches[0]);
                    $content = substr_replace($content, $fillableProperty, $position, 0);
                }
            }
        }

        // Generate $casts based on field types
        $castsArray = $this->buildCastsArray($fields);

        if (! empty($castsArray)) {
            $castsEntries = array_map(
                fn (string $field, string $cast): string => "'{$field}' => '{$cast}'",
                array_keys($castsArray),
                array_values($castsArray)
            );

            if (preg_match('/protected\s+\$casts\s*=\s*\[(.*?)\];/s', $content, $castsMatches)) {
                // $casts already exists — merge to avoid losing existing entries
                $existingEntries = array_filter(
                    array_map('trim', explode(',', $castsMatches[1])),
                    fn (string $item): bool => $item !== ''
                );
                $mergedCasts = array_values(array_unique(array_merge($existingEntries, $castsEntries)));
                $castsString = implode(', ', $mergedCasts);
                $newCasts = "protected \$casts = [{$castsString}];";
                $content = str_replace($castsMatches[0], $newCasts, $content);
            } else {
                // Insert $casts after $fillable if present, otherwise after class declaration
                $castsString = implode(', ', $castsEntries);
                $castsProperty = "\n    protected \$casts = [{$castsString}];\n";

                if (preg_match('/protected\s+\$fillable\s*=\s*\[.*?\];/s', $content, $fillableMatch)) {
                    $fillableEnd = strpos($content, $fillableMatch[0]) + strlen($fillableMatch[0]);
                    $content = substr_replace($content, $castsProperty, $fillableEnd, 0);
                } else {
                    $classPattern = '/class\s+'.$model.'\s+extends\s+Model\s*\{[^\}]*?(\n\s*use\s+[^;]+;)?/s';
                    if (preg_match($classPattern, $content, $classMatches)) {
                        $position = strpos($content, $classMatches[0]) + strlen($classMatches[0]);
                        $content = substr_replace($content, $castsProperty, $position, 0);
                    }
                }
            }
        }

        // Add relationship methods (skipping duplicates)
        if (! empty($relations)) {
            $relationMethods = $this->generateRelationMethods($relations, $model);

            if (! empty($relationMethods)) {
                // Filter out methods that already exist in the content
                $filteredMethods = '';
                $methodBlocks = preg_split('/(?=\n    public function )/', $relationMethods);
                if ($methodBlocks !== false) {
                    foreach ($methodBlocks as $block) {
                        if (trim($block) === '') {
                            continue;
                        }
                        // Extract method name from the block
                        if (preg_match('/function\s+(\w+)\s*\(/', $block, $m)) {
                            // Only add if the method doesn't already exist
                            if (! str_contains($content, 'function '.$m[1].'(')) {
                                $filteredMethods .= $block;
                            }
                        }
                    }
                }

                if (! empty($filteredMethods)) {
                    $endClassPos = strrpos($content, '}');
                    if ($endClassPos !== false) {
                        $content = substr_replace($content, $filteredMethods."\n}", $endClassPos, 1);
                    }
                }
            }
        }

        // Save the changes
        File::put($modelPath, $content);
        $this->log("Model {$model} updated successfully!");

        return true;
    }

    /**
     * Adds soft deletes to an existing model
     */
    private function addSoftDeletesIfNotExists(string $model): void
    {
        $modelPath = NamespaceHelper::modelPath($model);
        $content = File::get($modelPath);

        $hasSoftDeletes = strpos($content, 'use Illuminate\Database\Eloquent\SoftDeletes;') !== false;
        $usesSoftDeletes = strpos($content, 'use SoftDeletes;') !== false;

        if (! $hasSoftDeletes || ! $usesSoftDeletes) {
            $this->updateModel($model, [], [], true);
            $this->log("SoftDeletes added to model {$model}.");
        }
    }

    /**
     * Generates relationship methods based on the relations
     *
     * @param  array<int, string>  $relations
     */
    private function generateRelationMethods(array $relations, string $parentModel = ''): string
    {
        $methods = '';

        foreach ($relations as $relation) {
            if (strpos($relation, ':') !== false) {
                [$relationType, $relatedModel] = explode(':', $relation);

                $methods .= match ($relationType) {
                    'hasOne' => $this->generateHasOneMethod($relatedModel),
                    'hasMany' => $this->generateHasManyMethod($relatedModel),
                    'belongsTo' => $this->generateBelongsToMethod($relatedModel),
                    'belongsToMany' => $this->generateBelongsToManyMethod($relatedModel),
                    'morphTo' => $this->generateMorphToMethod($relatedModel),
                    'morphOne' => $this->generateMorphOneMethod($relatedModel, $parentModel),
                    'morphMany' => $this->generateMorphManyMethod($relatedModel, $parentModel),
                    default => '',
                };
            }
        }

        return $methods;
    }

    private function generateHasOneMethod(string $relatedModel): string
    {
        $relationName = Str::camel($relatedModel);
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return \$this->hasOne(\\{$namespace}\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateHasManyMethod(string $relatedModel): string
    {
        $relationName = Str::camel(Str::plural($relatedModel));
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return \$this->hasMany(\\{$namespace}\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateBelongsToMethod(string $relatedModel): string
    {
        $relationName = Str::camel($relatedModel);
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return \$this->belongsTo(\\{$namespace}\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateBelongsToManyMethod(string $relatedModel): string
    {
        $relationName = Str::camel(Str::plural($relatedModel));
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return \$this->belongsToMany(\\{$namespace}\\{$relatedModel}::class);
    }
PHP;
    }

    /**
     * Generates a morphTo method. The $morphName is the morph relationship name (e.g. "commentable").
     */
    private function generateMorphToMethod(string $morphName): string
    {
        $relationName = Str::camel($morphName);

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return \$this->morphTo();
    }
PHP;
    }

    /**
     * Generates a morphOne method. The morph name is derived from the related model.
     */
    private function generateMorphOneMethod(string $relatedModel, string $parentModel): string
    {
        $relationName = Str::camel($relatedModel);
        $morphName = Str::snake($relatedModel).'able';
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return \$this->morphOne(\\{$namespace}\\{$relatedModel}::class, '{$morphName}');
    }
PHP;
    }

    /**
     * Generates a morphMany method. The morph name is derived from the related model.
     */
    private function generateMorphManyMethod(string $relatedModel, string $parentModel): string
    {
        $relationName = Str::camel(Str::plural($relatedModel));
        $morphName = Str::snake($relatedModel).'able';
        $namespace = NamespaceHelper::modelNamespace();

        return <<<PHP

    public function {$relationName}(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return \$this->morphMany(\\{$namespace}\\{$relatedModel}::class, '{$morphName}');
    }
PHP;
    }

    /**
     * Builds the $casts array from field definitions.
     *
     * @param  array<int, string>  $fields
     * @return array<string, string>
     */
    public function buildCastsArray(array $fields): array
    {
        $casts = [];

        $typeMap = [
            'boolean' => 'boolean',
            'checkbox' => 'boolean',
            'integer' => 'integer',
            'bigInteger' => 'integer',
            'slider' => 'integer',
            'range' => 'integer',
            'decimal' => 'decimal:2',
            'float' => 'float',
            'double' => 'double',
            'datetime' => 'datetime',
            'date' => 'date',
            'json' => 'array',
            'tags' => 'array',
            'keyvalue' => 'array',
            'enum' => 'string',
        ];

        foreach ($fields as $field) {
            if (strpos($field, ':') === false) {
                continue;
            }

            $parts = explode(':', $field);
            $fieldName = trim($parts[0]);
            $fieldType = trim($parts[1]);

            if (isset($typeMap[$fieldType])) {
                $casts[$fieldName] = $typeMap[$fieldType];
            }
        }

        return $casts;
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
