<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
    public function updateMigration(string $model, array $fields, array $relationArray = [], bool $softDeletes = false): bool
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

                // Extract modifiers and defaults from extra parts before building the field definition
                $isNullable = false;
                $defaultValue = null;
                $isUnique = false;
                $maxLength = null;
                $validationKeywords = ['required', 'email', 'url', 'tel', 'password', 'confirmed'];

                for ($i = 2; $i < count($parts); $i++) {
                    $param = $parts[$i];

                    if ($param === 'nullable') {
                        $isNullable = true;
                    } elseif ($param === 'unique') {
                        $isUnique = true;
                    } elseif (preg_match('/^max=(\d+)$/', $param, $maxMatch)) {
                        // max=N can double as a string column length
                        $maxLength = $maxMatch[1];
                    } elseif (strpos($param, '=') !== false || in_array($param, $validationKeywords)) {
                        // Other validation parameters (min=N, max=N for non-string, required, etc.)
                        continue;
                    } elseif ($i === 2) {
                        // Position 2 is the default value slot
                        if (is_numeric($param) || in_array($param, ['true', 'false'])) {
                            $defaultValue = $param;
                        } else {
                            // String default value — wrap in quotes
                            $defaultValue = "'{$param}'";
                        }
                    }
                }

                $fieldDefinition = "\n            \$table->{$mappedType}('{$fieldName}'";

                // Add parameters: explicit type params take precedence, then max=N for string columns
                if (! empty($typeParams)) {
                    $fieldDefinition .= ", {$typeParams}";
                } elseif ($mappedType === 'string' && $maxLength !== null) {
                    $fieldDefinition .= ", {$maxLength}";
                }

                $fieldDefinition .= ')';

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

                    if ($relationType === 'morphTo') {
                        $morphName = Str::snake($relatedModel);
                        $fieldDefinitions .= "\n            \$table->morphs('{$morphName}');";
                    }
                }
            }
        }

        // Add softDeletes if needed
        if ($softDeletes && strpos($content, 'softDeletes') === false) {
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
     * Determines whether an alter migration should be used instead of inlining FK into the create migration.
     * Returns true when the table already exists in the DB, when no create migration file is found,
     * or when the create migration has custom fields beyond id/timestamps/softDeletes.
     */
    public function shouldUseAlterMigration(string $model): bool
    {
        $tableName = Str::snake(Str::plural($model));

        // If the table already exists in the database, must use alter migration
        if (Schema::hasTable($tableName)) {
            return true;
        }

        // If there is no create migration file, use alter migration
        $migrationFiles = File::glob(database_path('migrations/*_create_'.$tableName.'_table.php'));
        if (empty($migrationFiles)) {
            return true;
        }

        // Read the migration content and check if it has custom fields
        $content = File::get($migrationFiles[0]);

        // Remove id(), timestamps(), and softDeletes() calls, then check if any $table-> calls remain
        $cleaned = preg_replace('/\$table->(id|timestamps|softDeletes)\(\)\s*;/', '', $content) ?? $content;

        // If there are remaining $table-> calls, the migration has custom fields (FK ordering risk)
        if (preg_match('/\$table->(?!id\b|timestamps\b|softDeletes\b)\w+/', $cleaned)) {
            return true;
        }

        return false;
    }

    /**
     * Creates a separate alter migration to add a FK column to an existing table.
     * This avoids FK ordering issues when the referenced table's migration runs after the target table.
     */
    public function createAlterMigration(string $model, string $foreignModel): bool
    {
        $tableName = Str::snake(Str::plural($model));
        $fkColumn = Str::snake($foreignModel).'_id';
        $fileName = date('Y_m_d_His').'_add_'.$fkColumn.'_to_'.$tableName.'_table.php';
        $filePath = database_path('migrations/'.$fileName);

        $content = <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$tableName}', function (Blueprint \$table) {
                    \$table->foreignId('{$fkColumn}')->constrained()->onDelete('cascade');
                });
            }

            public function down(): void
            {
                Schema::table('{$tableName}', function (Blueprint \$table) {
                    \$table->dropForeign(['{$fkColumn}']);
                    \$table->dropColumn('{$fkColumn}');
                });
            }
        };
        PHP;

        File::put($filePath, $content);

        $this->log("Alter migration created: {$fileName}");

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
            'select' => 'string',
            'enum' => 'string',
            'textarea' => 'text',
            'radio' => 'string',
            'tags' => 'json',
            'datetime' => 'dateTime',
            'richtext' => 'longText',
            'editor' => 'longText',
            'decimal' => 'decimal(10, 2)',
            'float' => 'float(8, 2)',
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
