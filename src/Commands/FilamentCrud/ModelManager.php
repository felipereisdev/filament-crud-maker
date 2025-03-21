<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelManager
{
    private ?Command $command;

    public function __construct(?Command $command = null)
    {
        $this->command = $command;
    }

    /**
     * Verifica se o modelo já existe e o cria se necessário
     */
    public function createIfNotExists(string $model, bool $softDeletes = false): bool
    {
        if (! File::exists(app_path('Models/' . $model . '.php'))) {
            $this->log('Criando modelo ' . $model);
            $modelCommand = [
                'name' => $model,
                '-m' => true, // Cria migração
            ];

            if ($softDeletes) {
                $modelCommand['-s'] = true; // Adiciona soft deletes
            }

            Artisan::call('make:model', $modelCommand);

            $this->log('Modelo criado com sucesso!');

            return true;
        } else {
            $this->log('Modelo já existe. Pulando criação do modelo.');

            // Verificar se o modelo tem softDeletes e adicionar se necessário
            if ($softDeletes) {
                $this->addSoftDeletesIfNotExists($model);
            }

            return false;
        }
    }

    /**
     * Atualiza o modelo com relacionamentos e propriedades necessárias
     */
    public function updateModel(string $model, array $fields, array $relations, bool $softDeletes = false): bool
    {
        $modelPath = app_path('Models/' . $model . '.php');

        if (! File::exists($modelPath)) {
            $this->log("Modelo não encontrado: {$modelPath}", 'error');

            return false;
        }

        $content = File::get($modelPath);

        // Verificar se o modelo já usa softDeletes
        $hasSoftDeletes = strpos($content, 'use Illuminate\Database\Eloquent\SoftDeletes;') !== false;
        $usesSoftDeletes = strpos($content, 'use SoftDeletes;') !== false;

        // Adicionar softDeletes se necessário
        if ($softDeletes && ! $hasSoftDeletes) {
            $content = str_replace(
                'use Illuminate\Database\Eloquent\Model;',
                "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\SoftDeletes;",
                $content
            );
        }

        if ($softDeletes && ! $usesSoftDeletes) {
            // Encontrar a classe para adicionar o trait
            $pattern = '/class\s+' . $model . '\s+extends\s+Model\s*\{/';
            $replacement = "class {$model} extends Model\n{\n    use SoftDeletes;\n";
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Adicionar propriedades fillable com base nos campos
        $fillableFields = [];
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                $parts = explode(':', $field);
                $fieldName = $parts[0];
                $fillableFields[] = "'{$fieldName}'";
            }
        }

        // Adicionar chaves estrangeiras das relações belongsTo
        if (! empty($relations)) {
            foreach ($relations as $relation) {
                if (strpos($relation, ':') !== false) {
                    list($relationType, $relatedModel) = explode(':', $relation);

                    if ($relationType === 'belongsTo') {
                        $foreignKey = Str::snake($relatedModel) . '_id';
                        if (! in_array("'{$foreignKey}'", $fillableFields)) {
                            $fillableFields[] = "'{$foreignKey}'";
                        }
                    }
                }
            }
        }

        // Verificar se fillable já existe e atualizá-lo
        if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            // Fillable já existe, atualizar
            $currentFillable = $matches[1];
            $fillableString = implode(', ', $fillableFields);
            $newFillable = "protected \$fillable = [{$fillableString}];";
            $content = str_replace($matches[0], $newFillable, $content);
        } else {
            // Adicionar fillable
            $fillableString = implode(', ', $fillableFields);
            $fillableProperty = "\n    protected \$fillable = [{$fillableString}];\n";

            // Encontrar um bom lugar para adicionar fillable (após a declaração da classe)
            $pattern = '/class\s+' . $model . '\s+extends\s+Model\s*\{[^\}]*?(\n\s*use\s+[^;]+;)?/s';
            if (preg_match($pattern, $content, $matches)) {
                $position = strpos($content, $matches[0]) + strlen($matches[0]);
                $content = substr_replace($content, $fillableProperty, $position, 0);
            }
        }

        // Adicionar métodos de relacionamento
        if (! empty($relations)) {
            $relationMethods = $this->generateRelationMethods($relations);

            // Verificar se o modelo já tem métodos de relacionamento
            if (! empty($relationMethods)) {
                // Encontrar o final da classe para adicionar os métodos
                $endClassPos = strrpos($content, '}');
                if ($endClassPos !== false) {
                    $content = substr_replace($content, $relationMethods . "\n}", $endClassPos, 1);
                }
            }
        }

        // Salvar as alterações
        File::put($modelPath, $content);
        $this->log("Modelo {$model} atualizado com sucesso!");

        return true;
    }

    /**
     * Adiciona soft deletes a um modelo existente
     */
    private function addSoftDeletesIfNotExists(string $model): void
    {
        $modelPath = app_path('Models/' . $model . '.php');
        $content = File::get($modelPath);

        $hasSoftDeletes = strpos($content, 'use Illuminate\Database\Eloquent\SoftDeletes;') !== false;
        $usesSoftDeletes = strpos($content, 'use SoftDeletes;') !== false;

        if (! $hasSoftDeletes || ! $usesSoftDeletes) {
            $this->updateModel($model, [], [], true);
            $this->log("SoftDeletes adicionado ao modelo {$model}.");
        }
    }

    /**
     * Gera métodos de relacionamento com base nas relações
     */
    private function generateRelationMethods(array $relations): string
    {
        $methods = '';

        foreach ($relations as $relation) {
            if (strpos($relation, ':') !== false) {
                list($relationType, $relatedModel) = explode(':', $relation);

                switch ($relationType) {
                    case 'hasOne':
                        $methods .= $this->generateHasOneMethod($relatedModel);

                        break;
                    case 'hasMany':
                        $methods .= $this->generateHasManyMethod($relatedModel);

                        break;
                    case 'belongsTo':
                        $methods .= $this->generateBelongsToMethod($relatedModel);

                        break;
                    case 'belongsToMany':
                        $methods .= $this->generateBelongsToManyMethod($relatedModel);

                        break;
                }
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
