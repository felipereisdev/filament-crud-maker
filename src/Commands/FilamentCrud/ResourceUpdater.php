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
     */
    public function update(string $model, array $fields, bool $softDeletes = false): bool
    {
        $resourcePath = app_path('Filament/Resources/' . $model . 'Resource.php');

        if (! File::exists($resourcePath)) {
            $this->log("Arquivo de recurso não encontrado: {$resourcePath}", 'error');

            return false;
        }

        $content = File::get($resourcePath);

        // Remover importações duplicadas
        $content = $this->importManager->removeDuplicateImports($content);

        // Construir os campos do formulário
        $formFields = [];
        $tableColumns = [];
        $filterFields = [];
        $usedComponents = [];

        // Processar cada campo
        foreach ($fields as $field) {
            if (strpos($field, ':') !== false) {
                // Dividir o campo no formato nome:tipo:default:validações
                $parts = explode(':', $field);
                $fieldName = trim($parts[0]);
                $fieldType = trim($parts[1]);

                // Extrair validações e valores padrão
                $validationRules = [];
                $defaultValue = null;

                // Processar validações embutidas como required, min=10, etc.
                for ($i = 2; $i < count($parts); $i++) {
                    $part = trim($parts[$i]);

                    // Se for o primeiro valor adicional e não contiver = ou => é considerado um valor padrão
                    if ($i == 2 && ! preg_match('/[=]/', $part)) {
                        $defaultValue = $part;

                        continue;
                    }

                    // Processar validações no formato regra=valor ou regra
                    if (preg_match('/([^=]+)=(.+)/', $part, $matches)) {
                        $rule = trim($matches[1]);
                        $value = trim($matches[2]);
                        $validationRules[$rule] = $value;
                    } elseif (preg_match('/([^=]+)=>(.+)/', $part, $matches)) {
                        $rule = trim($matches[1]);
                        $value = trim($matches[2]);
                        $validationRules[$rule] = $value;
                    } else {
                        // Regra sem valor (ex: required, nullable)
                        $validationRules[$part] = '';
                    }
                }

                $this->log("Processando campo: {$fieldName} do tipo {$fieldType}" .
                           ($defaultValue ? " com valor padrão {$defaultValue}" : "") .
                           (! empty($validationRules) ? " e " . count($validationRules) . " validações" : ""));

                // Gerar componente de formulário
                $formComponent = $this->formGenerator->generate($fieldName, $fieldType, $validationRules, $defaultValue);
                if ($formComponent) {
                    $formFields[] = $formComponent;
                    $usedComponents[] = $this->formGenerator->getComponentType($fieldType);
                }

                // Gerar coluna de tabela
                $tableColumn = $this->tableGenerator->generateColumn($fieldName, $fieldType, $validationRules, $defaultValue);
                if ($tableColumn) {
                    $tableColumns[] = $tableColumn;
                    $usedComponents[] = $this->tableGenerator->getComponentType($fieldType, 'column');
                }

                // Gerar filtro
                $filter = $this->tableGenerator->generateFilter($fieldName, $fieldType, $validationRules);
                if ($filter) {
                    $filterFields[] = $filter;
                    $componentType = $this->tableGenerator->getComponentType($fieldType, 'filter');
                    if ($componentType) {
                        $usedComponents[] = $componentType;
                    }
                }
            }
        }

        $this->log("Total de campos de formulário: " . count($formFields));
        $this->log("Total de colunas de tabela: " . count($tableColumns));
        $this->log("Total de filtros: " . count($filterFields));

        // Debug: Mostrar os componentes usados
        if (! empty($usedComponents)) {
            $this->log("Componentes usados: " . implode(', ', array_unique($usedComponents)));
        }

        // Atualizar os métodos form e table apenas se houver campos para adicionar
        if (! empty($formFields)) {
            $content = $this->formGenerator->updateFormMethod($content, $formFields, $this->codeValidator);
        }

        if (! empty($tableColumns) || ! empty($filterFields)) {
            $content = $this->tableGenerator->updateTableMethod($content, $tableColumns, $filterFields, $this->codeValidator);
        }

        // Adicionar apenas importações necessárias
        $content = $this->importManager->addRequiredImports($content, $model, array_unique($usedComponents), $softDeletes);

        // Salvar em um arquivo temporário para inspeção durante o desenvolvimento
        $tempFile = storage_path('app/debug_resource_' . $model . '.php');
        File::put($tempFile, $content);
        $this->log("Versão para depuração salva em: {$tempFile}");

        // Validar a sintaxe do código antes de salvar
        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log("Erro na sintaxe do código gerado. Verificando os problemas...", 'error');

            return false;
        }

        // Salvar o arquivo atualizado
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
