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
        if (! File::exists(app_path('Models/'.$model.'.php'))) {
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
        $modelPath = app_path('Models/'.$model.'.php');

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

        // Check if fillable already exists and update it
        if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            // Fillable already exists, update it
            $currentFillable = $matches[1];
            $fillableString = implode(', ', $fillableFields);
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

        // Add relationship methods
        if (! empty($relations)) {
            $relationMethods = $this->generateRelationMethods($relations);

            // Check if the model already has relationship methods
            if (! empty($relationMethods)) {
                // Find the end of the class to add the methods
                $endClassPos = strrpos($content, '}');
                if ($endClassPos !== false) {
                    $content = substr_replace($content, $relationMethods."\n}", $endClassPos, 1);
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
        $modelPath = app_path('Models/'.$model.'.php');
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
    private function generateRelationMethods(array $relations): string
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
                    default => '',
                };
            }
        }

        return $methods;
    }

    private function generateHasOneMethod(string $relatedModel): string
    {
        $relationName = Str::camel($relatedModel);

        return <<<PHP

    public function {$relationName}()
    {
        return \$this->hasOne(\\App\\Models\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateHasManyMethod(string $relatedModel): string
    {
        $relationName = Str::camel(Str::plural($relatedModel));

        return <<<PHP

    public function {$relationName}()
    {
        return \$this->hasMany(\\App\\Models\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateBelongsToMethod(string $relatedModel): string
    {
        $relationName = Str::camel($relatedModel);

        return <<<PHP

    public function {$relationName}()
    {
        return \$this->belongsTo(\\App\\Models\\{$relatedModel}::class);
    }
PHP;
    }

    private function generateBelongsToManyMethod(string $relatedModel): string
    {
        $relationName = Str::camel(Str::plural($relatedModel));

        return <<<PHP

    public function {$relationName}()
    {
        return \$this->belongsToMany(\\App\\Models\\{$relatedModel}::class);
    }
PHP;
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
