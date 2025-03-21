<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationManager
{
    private ?Command $command;

    public function __construct(?Command $command = null)
    {
        $this->command = $command;
    }

    /**
     * Processa os parâmetros de validação e identifica quais são relevantes para migração
     */
    private function processValidations(string $param): ?array
    {
        $result = null;

        // Validações com 'unique' - transformar em índice único
        if ($param === 'unique') {
            $result['unique'] = true;
        }
        // Ignorar validações min/max/between para valores
        elseif (preg_match('/^(min|max|between)=/', $param)) {
            // Não fazer nada - essas são apenas validações de formulário
        }

        return $result;
    }

    /**
     * Atualiza a migração com os campos especificados
     */
    public function updateMigration(string $model, array $fields, array $relationArray = []): bool
    {
        $migrationFiles = File::glob(database_path('migrations/*_create_' . Str::snake(Str::plural($model)) . '_table.php'));

        if (empty($migrationFiles)) {
            $this->log('Arquivo de migração não encontrado.', 'error');

            return false;
        }

        $migrationFile = $migrationFiles[0];
        $content = File::get($migrationFile);

        // Encontrar a posição para inserir os campos
        $tableDefinition = 'Schema::create';
        $closingStatement = '});';
        $startPos = strpos($content, $tableDefinition);
        $endPos = strpos($content, $closingStatement, $startPos);

        if ($startPos === false || $endPos === false) {
            $this->log('Não foi possível encontrar a definição da tabela na migração.', 'error');

            return false;
        }

        // Construir as definições de campo
        $fieldDefinitions = '';
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                $parts = explode(':', $field);
                $fieldName = $parts[0];
                $fieldType = $parts[1];

                // Mapear tipos de campo especiais para tipos reais de migração
                $mappedType = $this->mapFieldType($fieldType);

                // Extrair parâmetros adicionais de tipos como decimal(10,2)
                $typeParams = '';
                if (preg_match('/^(.*?)\((.*?)\)$/', $mappedType, $matches)) {
                    $mappedType = $matches[1];
                    $typeParams = $matches[2];
                }

                $fieldDefinition = "\n            \$table->{$mappedType}('{$fieldName}'";

                // Adicionar parâmetros se houver
                if (! empty($typeParams)) {
                    $fieldDefinition .= ", {$typeParams}";
                }

                $fieldDefinition .= ")";

                // Processar validações e defaults
                $isNullable = false;
                $defaultValue = null;
                $isUnique = false;

                // Verificar todos os parâmetros extras (após o tipo)
                for ($i = 2; $i < count($parts); $i++) {
                    $param = $parts[$i];

                    // Identificar se é um parâmetro de validação ou valor padrão
                    if ($param === 'nullable') {
                        $isNullable = true;
                    } elseif ($param === 'unique') {
                        $isUnique = true;
                    } elseif (strpos($param, '=') !== false || strpos($param, 'required') !== false || strpos($param, 'min') !== false || strpos($param, 'max') !== false) {
                        // Ignorar parâmetros de validação
                        continue;
                    } elseif (is_numeric($param) || in_array($param, ['true', 'false'])) {
                        $defaultValue = $param;
                    }
                }

                // Aplicar nullable se especificado
                if ($isNullable) {
                    $fieldDefinition .= "->nullable()";
                }

                // Aplicar unique se especificado
                if ($isUnique) {
                    $fieldDefinition .= "->unique()";
                }

                // Aplicar valor padrão se especificado
                if ($defaultValue !== null) {
                    if (in_array($defaultValue, ['true', 'false'])) {
                        // Valores booleanos
                        $fieldDefinition .= "->default(" . $defaultValue . ")";
                    } elseif (is_numeric($defaultValue)) {
                        // Valores numéricos
                        $fieldDefinition .= "->default(" . $defaultValue . ")";
                    } else {
                        // String
                        $fieldDefinition .= "->default('" . $defaultValue . "')";
                    }
                }

                $fieldDefinition .= ";";
                $fieldDefinitions .= $fieldDefinition;
            }
        }

        // Adicionar foreign keys para relações belongsTo
        if (! empty($relationArray)) {
            foreach ($relationArray as $relation) {
                if (strpos($relation, ':') !== false) {
                    list($relationType, $relatedModel) = explode(':', $relation);

                    if ($relationType === 'belongsTo') {
                        $fieldDefinitions .= "\n            \$table->foreignId('" . Str::snake($relatedModel) . "_id')->constrained()->onDelete('cascade');";
                    }
                }
            }
        }

        // Adicionar softDeletes se necessário
        if (strpos($content, 'softDeletes') === false && strpos($content, 'SoftDeletes') !== false) {
            $fieldDefinitions .= "\n            \$table->softDeletes();";
        }

        // Adicionar a tabela pivot para relações belongsToMany
        $pivotTables = [];
        if (! empty($relationArray)) {
            $modelPlural = Str::snake(Str::singular($model));
            foreach ($relationArray as $relation) {
                if (strpos($relation, ':') !== false) {
                    list($relationType, $relatedModel) = explode(':', $relation);

                    if ($relationType === 'belongsToMany') {
                        $relatedModelPlural = Str::snake(Str::singular($relatedModel));

                        // Determinar o nome da tabela pivot (ordem alfabética)
                        $tables = [$modelPlural, $relatedModelPlural];
                        sort($tables);
                        $pivotTable = implode('_', $tables);

                        // Adicionar apenas se ainda não estiver no array
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

        // Inserir os campos na migração
        $newContent = substr($content, 0, $endPos) . $fieldDefinitions . "\n" . substr($content, $endPos);

        // Se houver tabelas pivot, adicionar suas definições
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

            // Inserir tabelas pivot após a tabela principal
            $endOfFirstCreate = strpos($newContent, "});", $endPos) + 3;
            $newContent = substr($newContent, 0, $endOfFirstCreate) . $pivotContent . substr($newContent, $endOfFirstCreate);

            // Atualizar o método down() para excluir as tabelas pivot
            $downPos = strpos($newContent, "down()");
            $dropPos = strpos($newContent, "Schema::dropIfExists", $downPos);

            $dropStatements = '';
            foreach ($pivotTables as $pivot) {
                $dropStatements .= "\n        Schema::dropIfExists('{$pivot['table']}');";
            }

            $newContent = substr($newContent, 0, $dropPos) . $dropStatements . "\n" . substr($newContent, $dropPos);
        }

        File::put($migrationFile, $newContent);

        $this->log('Migração atualizada com sucesso.');

        return true;
    }

    /**
     * Mapeia tipos de campo do comando para tipos reais de migração
     */
    private function mapFieldType(string $type): string
    {
        $typeMap = [
            'markdown' => 'text',
            'image' => 'string',
            'color' => 'string',
            'file' => 'string',
        ];

        return $typeMap[$type] ?? $type;
    }

    /**
     * Executa as migrações
     */
    public function runMigrations(?bool $autoConfirm = false): bool
    {
        $this->log('Executando migrações...');

        $confirmMigrate = $autoConfirm;
        if (! $autoConfirm && $this->command) {
            $confirmMigrate = $this->command->confirm('Deseja executar as migrações agora?', true);
        }

        if ($confirmMigrate) {
            $this->log('Executando php artisan migrate');

            if ($this->executeCommand('php artisan migrate', false)) {
                $this->log('Migrações executadas com sucesso!');

                return true;
            } else {
                $this->log('Erro ao executar migrações.', 'error');
                $this->log('Tente executar manualmente: php artisan migrate');

                return false;
            }
        } else {
            $this->log('Migrações não executadas. Execute manualmente quando estiver pronto: php artisan migrate');

            return false;
        }
    }

    /**
     * Executa um comando do sistema e retorna o resultado
     */
    private function executeCommand(string $command, bool $returnOutput = false)
    {
        $this->log("Executando: {$command}");

        if ($returnOutput) {
            return shell_exec($command);
        }

        system($command, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Log mensagens com diferentes níveis
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->command) {
            switch ($level) {
                case 'error':
                    $this->command->error($message);

                    break;
                case 'warn':
                    $this->command->warn($message);

                    break;
                case 'info':
                default:
                    $this->command->info($message);

                    break;
            }
        }
    }
}
