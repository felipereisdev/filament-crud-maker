<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ResourceUpdater
{
    public function __construct(
        private readonly FormComponentGenerator $formGenerator,
        private readonly TableComponentGenerator $tableGenerator,
        private readonly ImportManager $importManager,
        private readonly CodeValidator $codeValidator,
        private readonly ?Command $command = null
    ) {
    }

    /**
     * Atualiza um recurso Filament com campos, colunas e filtros
     *
     * @param array<int, string> $fields
     */
    public function update(string $model, array $fields, bool $softDeletes = false): bool
    {
        $resourcePath = app_path('Filament/Resources/' . $model . 'Resource.php');

        if (! File::exists($resourcePath)) {
            $this->log("Arquivo de recurso não encontrado: {$resourcePath}", 'error');

            return false;
        }

        // Processar campos
        [$formFields, $tableColumns, $filterFields, $formComponents, $tableComponents] = $this->processFields($fields);

        $this->log("Total de campos de formulário: " . count($formFields));
        $this->log("Total de colunas de tabela: " . count($tableColumns));
        $this->log("Total de filtros: " . count($filterFields));

        // Detectar estrutura de diretórios v4 (Schemas/ + Tables/)
        $resourceDir = app_path('Filament/Resources/' . $model . 'Resource');
        $schemaPath = $resourceDir . '/Schemas/' . $model . 'Form.php';
        $tablePath = $resourceDir . '/Tables/' . $model . 'sTable.php';

        if (File::isDirectory($resourceDir . '/Schemas') && File::isDirectory($resourceDir . '/Tables')) {
            $this->log('Estrutura v4 detectada (Schemas/ + Tables/)');

            return $this->updateV4Structure(
                $model,
                $schemaPath,
                $tablePath,
                $formFields,
                $tableColumns,
                $filterFields,
                $formComponents,
                $tableComponents,
                $softDeletes
            );
        }

        // Fallback: atualizar inline no arquivo Resource
        $this->log('Estrutura inline detectada (fallback)');
        $usedComponents = array_unique(array_merge($formComponents, $tableComponents));

        return $this->updateInlineResource($model, $resourcePath, $formFields, $tableColumns, $filterFields, $usedComponents, $softDeletes);
    }

    /**
     * Processa campos e retorna arrays separados para form e table
     *
     * @param array<int, string> $fields
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>, 3: array<int, string>, 4: array<int, string>}
     */
    private function processFields(array $fields): array
    {
        $formFields = [];
        $tableColumns = [];
        $filterFields = [];
        $formComponents = [];
        $tableComponents = [];

        foreach ($fields as $field) {
            if (strpos($field, ':') === false) {
                continue;
            }

            // Dividir o campo no formato nome:tipo:default:validações
            $parts = explode(':', $field);
            $fieldName = trim($parts[0]);
            $fieldType = trim($parts[1]);

            // Extrair validações e valores padrão
            $validationRules = [];
            $defaultValue = null;

            for ($i = 2; $i < count($parts); $i++) {
                $part = trim($parts[$i]);

                if ($i == 2 && ! preg_match('/[=]/', $part)) {
                    $defaultValue = $part;

                    continue;
                }

                if (preg_match('/([^=]+)=(.+)/', $part, $matches)) {
                    $validationRules[trim($matches[1])] = trim($matches[2]);
                } elseif (preg_match('/([^=]+)=>(.+)/', $part, $matches)) {
                    $validationRules[trim($matches[1])] = trim($matches[2]);
                } else {
                    $validationRules[$part] = '';
                }
            }

            $this->log("Processando campo: {$fieldName} do tipo {$fieldType}" .
                       ($defaultValue ? " com valor padrão {$defaultValue}" : '') .
                       (! empty($validationRules) ? ' e ' . count($validationRules) . ' validações' : ''));

            // Gerar componente de formulário
            $formComponent = $this->formGenerator->generate($fieldName, $fieldType, $validationRules, $defaultValue);
            if ($formComponent) {
                $formFields[] = $formComponent;
                $formComponents[] = $this->formGenerator->getComponentType($fieldType);
            }

            // Gerar coluna de tabela
            $tableColumn = $this->tableGenerator->generateColumn($fieldName, $fieldType, $validationRules, $defaultValue);
            if ($tableColumn) {
                $tableColumns[] = $tableColumn;
                $tableComponents[] = $this->tableGenerator->getComponentType($fieldType, 'column');
            }

            // Gerar filtro
            $filter = $this->tableGenerator->generateFilter($fieldName, $fieldType, $validationRules);
            if ($filter) {
                $filterFields[] = $filter;
                $componentType = $this->tableGenerator->getComponentType($fieldType, 'filter');
                if ($componentType) {
                    $tableComponents[] = $componentType;
                }
            }
        }

        $allComponents = array_unique(array_merge($formComponents, $tableComponents));
        if (! empty($allComponents)) {
            $this->log('Componentes usados: ' . implode(', ', $allComponents));
        }

        return [$formFields, $tableColumns, $filterFields, $formComponents, $tableComponents];
    }

    /**
     * Atualiza arquivos separados de Schema e Table (estrutura Filament v4)
     *
     * @param array<int, string> $formFields
     * @param array<int, string> $tableColumns
     * @param array<int, string> $filterFields
     * @param array<int, string> $formComponents
     * @param array<int, string> $tableComponents
     */
    private function updateV4Structure(
        string $model,
        string $schemaPath,
        string $tablePath,
        array $formFields,
        array $tableColumns,
        array $filterFields,
        array $formComponents,
        array $tableComponents,
        bool $softDeletes
    ): bool {
        // Atualizar arquivo de Schema (formulário)
        if (! empty($formFields) && File::exists($schemaPath)) {
            if (! $this->updateSchemaFile($model, $schemaPath, $formFields, $formComponents)) {
                return false;
            }
        }

        // Atualizar arquivo de Table (colunas, filtros, ações)
        if ((! empty($tableColumns) || ! empty($filterFields)) && File::exists($tablePath)) {
            if (! $this->updateTableFile($model, $tablePath, $tableColumns, $filterFields, $tableComponents, $softDeletes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Atualiza o arquivo de Schema com os campos do formulário
     *
     * @param array<int, string> $formFields
     * @param array<int, string> $formComponents
     */
    private function updateSchemaFile(string $model, string $schemaPath, array $formFields, array $formComponents): bool
    {
        $content = File::get($schemaPath);
        $content = $this->importManager->removeDuplicateImports($content);

        $content = $this->formGenerator->updateFormMethod($content, $formFields, $this->codeValidator);
        $content = $this->importManager->addFormFileImports($content, array_unique($formComponents));

        $tempFile = storage_path('app/debug_schema_' . $model . '.php');
        File::put($tempFile, $content);
        $this->log("Versão para depuração do Schema salva em: {$tempFile}");

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Erro na sintaxe do código gerado no Schema.', 'error');

            return false;
        }

        File::put($schemaPath, $content);
        $this->log("Schema {$model}Form atualizado com sucesso!");

        return true;
    }

    /**
     * Atualiza o arquivo de Table com colunas, filtros e ações
     *
     * @param array<int, string> $tableColumns
     * @param array<int, string> $filterFields
     * @param array<int, string> $tableComponents
     */
    private function updateTableFile(
        string $model,
        string $tablePath,
        array $tableColumns,
        array $filterFields,
        array $tableComponents,
        bool $softDeletes
    ): bool {
        $content = File::get($tablePath);
        $content = $this->importManager->removeDuplicateImports($content);

        $content = $this->tableGenerator->updateTableMethod($content, $tableColumns, $filterFields, $this->codeValidator);
        $content = $this->importManager->addTableFileImports($content, array_unique($tableComponents), $softDeletes);

        $tempFile = storage_path('app/debug_table_' . $model . '.php');
        File::put($tempFile, $content);
        $this->log("Versão para depuração da Table salva em: {$tempFile}");

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Erro na sintaxe do código gerado na Table.', 'error');

            return false;
        }

        File::put($tablePath, $content);
        $this->log("Table {$model}sTable atualizado com sucesso!");

        return true;
    }

    /**
     * Atualiza o recurso inline (comportamento original/fallback)
     *
     * @param array<int, string> $formFields
     * @param array<int, string> $tableColumns
     * @param array<int, string> $filterFields
     * @param array<int, string> $usedComponents
     */
    private function updateInlineResource(
        string $model,
        string $resourcePath,
        array $formFields,
        array $tableColumns,
        array $filterFields,
        array $usedComponents,
        bool $softDeletes
    ): bool {
        $content = File::get($resourcePath);
        $content = $this->importManager->removeDuplicateImports($content);

        if (! empty($formFields)) {
            $content = $this->formGenerator->updateFormMethod($content, $formFields, $this->codeValidator);
        }

        if (! empty($tableColumns) || ! empty($filterFields)) {
            $content = $this->tableGenerator->updateTableMethod($content, $tableColumns, $filterFields, $this->codeValidator);
        }

        $content = $this->importManager->addRequiredImports($content, $model, $usedComponents, $softDeletes);

        $tempFile = storage_path('app/debug_resource_' . $model . '.php');
        File::put($tempFile, $content);
        $this->log("Versão para depuração salva em: {$tempFile}");

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Erro na sintaxe do código gerado. Verificando os problemas...', 'error');

            return false;
        }

        File::put($resourcePath, $content);
        $this->log("Resource {$model} atualizado com sucesso!");

        return true;
    }

    /**
     * Log mensagens com diferentes níveis
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
