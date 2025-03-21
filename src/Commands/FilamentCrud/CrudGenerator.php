<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CrudGenerator
{
    private ModelManager $modelManager;
    private MigrationManager $migrationManager;
    private ResourceUpdater $resourceUpdater;
    private CodeFormatter $codeFormatter;
    private ?Command $command;

    public function __construct(
        ModelManager $modelManager,
        MigrationManager $migrationManager,
        ResourceUpdater $resourceUpdater,
        CodeFormatter $codeFormatter,
        ?Command $command = null
    ) {
        $this->modelManager = $modelManager;
        $this->migrationManager = $migrationManager;
        $this->resourceUpdater = $resourceUpdater;
        $this->codeFormatter = $codeFormatter;
        $this->command = $command;
    }

    /**
     * Gera um CRUD completo para o modelo especificado
     */
    public function generate(
        string $model,
        string $fields = '',
        string $relations = '',
        bool $softDeletes = false,
        bool $skipMigrations = false,
        bool $skipCsFixer = false
    ): bool {
        $this->log("Gerando CRUD para o modelo {$model}");

        // Processar campos - melhorado para lidar com campos complexos
        $fieldArray = [];
        if (! empty($fields)) {
            // Dividir por vírgula, mas respeitando valores que contêm vírgulas dentro de regras
            $pattern = '/(?:[^,"]|"(?:\\\\.|[^"\\\\])*")+/';
            preg_match_all($pattern, $fields, $matches);
            $fieldArray = $matches[0];

            // Limpar possíveis espaços extras
            foreach ($fieldArray as $key => $field) {
                $fieldArray[$key] = trim($field);
            }
        }

        $this->log("Campos para processar: " . count($fieldArray));
        foreach ($fieldArray as $field) {
            $this->log("Campo: {$field}");
        }

        // Processar relações
        $relationArray = [];
        $relatedFieldsMap = [];

        if (! empty($relations)) {
            $relationGroups = explode(';', $relations);

            foreach ($relationGroups as $relationGroup) {
                // Verificar se o grupo não está vazio
                if (empty(trim($relationGroup))) {
                    continue;
                }

                // Separar em partes: tipo:modelo:campos
                $parts = explode(':', $relationGroup);

                if (count($parts) >= 2) {
                    $relationType = trim($parts[0]);
                    $relatedModel = trim($parts[1]);

                    // Adicionar a relação ao array de relações
                    $relationArray[] = $relationType . ':' . $relatedModel;

                    // Se houver campos especificados, processar
                    if (count($parts) > 2) {
                        // Extrair todos os campos do modelo relacionado
                        $relatedFields = [];

                        // Reconstruir a string de campos após o modelo
                        $fieldsStr = implode(':', array_slice($parts, 2));

                        // Dividir por vírgula, considerando possíveis vírgulas em valores
                        preg_match_all($pattern, $fieldsStr, $fieldMatches);
                        if (! empty($fieldMatches[0])) {
                            $relatedFields = $fieldMatches[0];
                            // Limpar possíveis espaços extras
                            foreach ($relatedFields as $key => $field) {
                                $relatedFields[$key] = trim($field);
                            }
                        }

                        $relatedFieldsMap[$relatedModel] = $relatedFields;

                        $this->log("Campos para modelo relacionado {$relatedModel}: " . count($relatedFields));
                        foreach ($relatedFields as $field) {
                            $this->log("Campo relacionado: {$field}");
                        }
                    }
                }
            }

            // Criar modelos relacionados primeiro
            $this->createRelatedModels($relationArray, $model, $relatedFieldsMap, $softDeletes);
        }

        // Verificar se o modelo já existe, se não, criar
        $this->modelManager->createIfNotExists($model, $softDeletes);

        // Atualizar a migração com os campos
        $this->migrationManager->updateMigration($model, $fieldArray, $relationArray);

        // Criar o resource do Filament
        $this->log('Criando resource Filament para ' . $model);
        Artisan::call('make:filament-resource', [
            'name' => $model,
            '--generate' => true,
        ]);

        // Atualizar o model com os campos necessários
        $this->modelManager->updateModel($model, $fieldArray, $relationArray, $softDeletes);

        // Atualizar o resource com os campos
        $this->resourceUpdater->update($model, $fieldArray, $softDeletes);

        // Criar os Resources do Filament para os modelos relacionados
        if (! empty($relationArray)) {
            $this->createRelatedResources($relationArray, $relatedFieldsMap);
        }

        // Formatar código se necessário
        if (! $skipCsFixer) {
            $this->codeFormatter->format();
        }

        // Executar migrações se não estiver pulando
        if (! $skipMigrations) {
            $this->migrationManager->runMigrations();
        }

        $this->log('CRUD Filament para ' . $model . ' gerado com sucesso!');

        return true;
    }

    /**
     * Cria modelos relacionados
     */
    private function createRelatedModels(array $relationArray, string $mainModel, array $relatedFieldsMap, bool $softDeletes): void
    {
        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                list($relationType, $relatedModel) = explode(':', $relation);

                // Não criar o modelo principal novamente
                if ($relatedModel === $mainModel) {
                    continue;
                }

                // Verificar se o modelo já existe
                if (! File::exists(app_path('Models/' . $relatedModel . '.php'))) {
                    $this->log('Criando modelo relacionado ' . $relatedModel);

                    // Criar o modelo
                    $this->modelManager->createIfNotExists($relatedModel, $softDeletes);

                    // Se houver campos definidos para este modelo, atualizar
                    if (isset($relatedFieldsMap[$relatedModel])) {
                        $fields = $relatedFieldsMap[$relatedModel];

                        // Atualizar a migração
                        $this->migrationManager->updateMigration($relatedModel, $fields);

                        // Atualizar o modelo
                        $this->modelManager->updateModel($relatedModel, $fields, [], $softDeletes);
                    }

                    $this->log('Modelo relacionado ' . $relatedModel . ' criado com sucesso!');
                } else {
                    $this->log('Modelo relacionado ' . $relatedModel . ' já existe.');

                    // Adicionar soft deletes se necessário
                    if ($softDeletes) {
                        // O modelo já existe, então atualizá-lo com soft deletes
                        $this->modelManager->updateModel($relatedModel, [], [], $softDeletes);
                    }
                }
            }
        }
    }

    /**
     * Cria recursos Filament para modelos relacionados
     */
    private function createRelatedResources(array $relationArray, array $relatedFieldsMap): void
    {
        $processedModels = [];

        foreach ($relationArray as $relation) {
            if (strpos($relation, ':') !== false) {
                list($relationType, $relatedModel) = explode(':', $relation);

                // Evitar duplicação
                if (in_array($relatedModel, $processedModels)) {
                    continue;
                }

                $processedModels[] = $relatedModel;

                // Verificar se o resource já existe
                if (! File::exists(app_path('Filament/Resources/' . $relatedModel . 'Resource.php'))) {
                    $this->log('Criando resource Filament para ' . $relatedModel);

                    Artisan::call('make:filament-resource', [
                        'name' => $relatedModel,
                        '--generate' => true,
                    ]);

                    // Obter campos personalizados para o modelo relacionado se disponíveis
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];

                    if (! empty($fieldsToUse)) {
                        $this->log("Atualizando resource de {$relatedModel} com " . count($fieldsToUse) . " campos");
                        // Atualizar o resource com os campos
                        $this->resourceUpdater->update($relatedModel, $fieldsToUse, false);
                    } else {
                        $this->log("Não foram encontrados campos para o modelo relacionado {$relatedModel}");
                    }

                    $this->log('Resource para ' . $relatedModel . ' criado com sucesso!');
                } else {
                    $this->log('Resource para ' . $relatedModel . ' já existe.');

                    // Atualizar o resource mesmo que já exista
                    $fieldsToUse = isset($relatedFieldsMap[$relatedModel]) ? $relatedFieldsMap[$relatedModel] : [];
                    if (! empty($fieldsToUse)) {
                        $this->log("Atualizando resource existente de {$relatedModel} com " . count($fieldsToUse) . " campos");
                        $this->resourceUpdater->update($relatedModel, $fieldsToUse, false);
                    }
                }
            }
        }
    }

    /**
     * Limpa recursos existentes, removendo imports desnecessários e corrigindo problemas
     */
    public function cleanAllResources(): bool
    {
        $resourceFiles = File::glob(app_path('Filament/Resources/*Resource.php'));

        foreach ($resourceFiles as $file) {
            $modelName = basename($file, 'Resource.php');
            $this->log("Limpando resource: {$modelName}");

            $this->resourceUpdater->update($modelName, [], false);
        }

        // Formatar código
        $this->codeFormatter->format();

        $this->log('Todos os recursos foram limpos com sucesso!');

        return true;
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
