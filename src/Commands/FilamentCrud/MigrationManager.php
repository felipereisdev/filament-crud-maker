<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationManager
{
    public function __construct(private readonly ?Command $command = null) {}

    /**
     * Updates the migration with the specified fields
     *
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $relationArray
     */
    public function updateMigration(string $model, array $fields, array $relationArray = []): bool
    {
        $migrationFiles = File::glob(database_path('migrations/*_create_'.Str::snake(Str::plural($model)).'_table.php'));

        if (empty($migrationFiles)) {
            $this->log('Migration file not found.', 'error');

            return false;
        }

        $migrationFile = $migrationFiles[0];
        $content = File::get($migrationFile);

        // Find the position to insert the fields
        $tableDefinition = 'Schema::create';
        $closingStatement = '});';
        $startPos = strpos($content, $tableDefinition);

        if ($startPos === false) {
            $this->log('Could not find the table definition in the migration.', 'error');

            return false;
        }

        $endPos = strpos($content, $closingStatement, $startPos);

        if ($endPos === false) {
            $this->log('Could not find the table definition in the migration.', 'error');

            return false;
        }

        // Build the field definitions
        $fieldDefinitions = '';
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                $parts = explode(':', $field);
                $fieldName = $parts[0];
                $fieldType = $parts[1];

                // Map special field types to actual migration types
                $mappedType = $this->mapFieldType($fieldType);

                // Extract additional parameters from types like decimal(10,2)
                $typeParams = '';
                if (preg_match('/^(.*?)\((.*?)\)$/', $mappedType, $matches)) {
                    $mappedType = $matches[1];
                    $typeParams = $matches[2];
                }

                $fieldDefinition = "\n            \$table->{$mappedType}('{$fieldName}'";

                // Add parameters if any
                if (! empty($typeParams)) {
                    $fieldDefinition .= ", {$typeParams}";
                }

                $fieldDefinition .= ')';

                // Process validations and defaults
                $isNullable = false;
                $defaultValue = null;
                $isUnique = false;

                // Check all extra parameters (after the type)
                for ($i = 2; $i < count($parts); $i++) {
                    $param = $parts[$i];

                    // Identify if it is a validation parameter or default value
                    if ($param === 'nullable') {
                        $isNullable = true;
                    } elseif ($param === 'unique') {
                        $isUnique = true;
                    } elseif (strpos($param, '=') !== false || strpos($param, 'required') !== false || strpos($param, 'min') !== false || strpos($param, 'max') !== false) {
                        // Ignore validation parameters
                        continue;
                    } elseif (is_numeric($param) || in_array($param, ['true', 'false'])) {
                        $defaultValue = $param;
                    }
                }

                // Apply nullable if specified
                if ($isNullable) {
                    $fieldDefinition .= '->nullable()';
                }

                // Apply unique if specified
                if ($isUnique) {
                    $fieldDefinition .= '->unique()';
                }

                // Apply default value if specified
                if ($defaultValue !== null) {
                    $fieldDefinition .= '->default('.$defaultValue.')';
                }

                $fieldDefinition .= ';';
                $fieldDefinitions .= $fieldDefinition;
            }
        }

        // Add foreign keys for belongsTo relations
        if (! empty($relationArray)) {
            foreach ($relationArray as $relation) {
                if (strpos($relation, ':') !== false) {
                    [$relationType, $relatedModel] = explode(':', $relation);

                    if ($relationType === 'belongsTo') {
                        $fieldDefinitions .= "\n            \$table->foreignId('".Str::snake($relatedModel)."_id')->constrained()->onDelete('cascade');";
                    }
                }
            }
        }

        // Add softDeletes if needed
        if (strpos($content, 'softDeletes') === false && strpos($content, 'SoftDeletes') !== false) {
            $fieldDefinitions .= "\n            \$table->softDeletes();";
        }

        // Add pivot table for belongsToMany relations
        $pivotTables = [];
        if (! empty($relationArray)) {
            $modelPlural = Str::snake(Str::singular($model));
            foreach ($relationArray as $relation) {
                if (strpos($relation, ':') !== false) {
                    [$relationType, $relatedModel] = explode(':', $relation);

                    if ($relationType === 'belongsToMany') {
                        $relatedModelPlural = Str::snake(Str::singular($relatedModel));

                        // Determine the pivot table name (alphabetical order)
                        $tables = [$modelPlural, $relatedModelPlural];
                        sort($tables);
                        $pivotTable = implode('_', $tables);

                        // Add only if not already in the array
                        if (! in_array($pivotTable, $pivotTables)) {
                            $pivotTables[] = [
                                'table' => $pivotTable,
                                'model1' => $modelPlural,
                                'model2' => $relatedModelPlural,
                            ];
                        }
                    }
                }
            }
        }

        // Insert the fields into the migration
        $newContent = substr($content, 0, $endPos).$fieldDefinitions."\n".substr($content, $endPos);

        // If there are pivot tables, add their definitions
        if (! empty($pivotTables)) {
            $pivotContent = '';
            foreach ($pivotTables as $pivot) {
                $pivotContent .= "\n\n        Schema::create('{$pivot['table']}', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('{$pivot['model1']}_id')->constrained()->onDelete('cascade');
            \$table->foreignId('{$pivot['model2']}_id')->constrained()->onDelete('cascade');
            \$table->unique(['{$pivot['model1']}_id', '{$pivot['model2']}_id']);
        });";
            }

            // Insert pivot tables after the main table
            $endOfFirstCreate = strpos($newContent, '});', $endPos) + 3;
            $newContent = substr($newContent, 0, $endOfFirstCreate).$pivotContent.substr($newContent, $endOfFirstCreate);

            // Update the down() method to drop the pivot tables
            $downPos = strpos($newContent, 'down()');

            if ($downPos !== false) {
                $dropPos = strpos($newContent, 'Schema::dropIfExists', $downPos);

                if ($dropPos !== false) {
                    $dropStatements = '';
                    foreach ($pivotTables as $pivot) {
                        $dropStatements .= "\n        Schema::dropIfExists('{$pivot['table']}');";
                    }

                    $newContent = substr($newContent, 0, $dropPos).$dropStatements."\n".substr($newContent, $dropPos);
                }
            }
        }

        File::put($migrationFile, $newContent);

        $this->log('Migration updated successfully.');

        return true;
    }

    /**
     * Maps command field types to actual migration types
     */
    private function mapFieldType(string $type): string
    {
        $typeMap = [
            'markdown' => 'text',
            'image' => 'string',
            'color' => 'string',
            'file' => 'string',
            'code' => 'longText',
            'slider' => 'integer',
            'range' => 'integer',
            'toggleButtons' => 'string',
            'keyvalue' => 'json',
            'checkbox' => 'boolean',
        ];

        return $typeMap[$type] ?? $type;
    }

    /**
     * Runs the migrations
     */
    public function runMigrations(?bool $autoConfirm = false): bool
    {
        $this->log('Running migrations...');

        $confirmMigrate = $autoConfirm;
        if (! $autoConfirm && $this->command) {
            $confirmMigrate = $this->command->confirm('Do you want to run migrations now?', true);
        }

        if ($confirmMigrate) {
            $this->log('Running php artisan migrate');

            if ($this->executeCommand('php artisan migrate', false)) {
                $this->log('Migrations executed successfully!');

                return true;
            } else {
                $this->log('Error running migrations.', 'error');
                $this->log('Try running manually: php artisan migrate');

                return false;
            }
        } else {
            $this->log('Migrations not executed. Run manually when ready: php artisan migrate');

            return false;
        }
    }

    /**
     * Executes a system command and returns the result
     */
    private function executeCommand(string $command, bool $returnOutput = false): string|bool|null
    {
        $this->log("Running: {$command}");

        if ($returnOutput) {
            return shell_exec($command);
        }

        system($command, $returnCode);

        return $returnCode === 0;
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
