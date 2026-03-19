<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResourceUpdater
{
    public function __construct(
        private readonly FormComponentGenerator $formGenerator,
        private readonly TableComponentGenerator $tableGenerator,
        private readonly ImportManager $importManager,
        private readonly CodeValidator $codeValidator,
        private readonly ?Command $command = null
    ) {}

    /**
     * Resolves the Filament resource file path, supporting both v4 and v5 structures.
     * Filament v5 uses Resources/{Plurals}/{Model}Resource.php,
     * v4 uses Resources/{Model}Resource.php.
     */
    public function resolveResourcePath(string $model): ?string
    {
        $plural = Str::plural($model);
        $base = NamespaceHelper::resourceBasePath();

        // Filament v5: Resources/Categories/CategoryResource.php
        $v5 = "{$base}/{$plural}/{$model}Resource.php";
        if (File::exists($v5)) {
            return $v5;
        }

        // Filament v4: Resources/CategoryResource.php
        $v4 = "{$base}/{$model}Resource.php";
        if (File::exists($v4)) {
            return $v4;
        }

        return null;
    }

    /**
     * Resolves the Filament resource directory, supporting both v4 and v5 structures.
     */
    public function resolveResourceDir(string $model): ?string
    {
        $plural = Str::plural($model);
        $base = NamespaceHelper::resourceBasePath();

        // Filament v5 Mode B: Resources/Categories/CategoryResource/
        $v5ModeB = "{$base}/{$plural}/{$model}Resource";
        if (File::isDirectory($v5ModeB)) {
            return $v5ModeB;
        }

        // Filament v5 Mode A (default): Resources/Categories/ contains Schemas/Tables/Pages directly
        $v5ModeA = "{$base}/{$plural}";
        if (File::isDirectory($v5ModeA) && (
            File::isDirectory("{$v5ModeA}/Schemas") ||
            File::isDirectory("{$v5ModeA}/Tables") ||
            File::isDirectory("{$v5ModeA}/Pages")
        )) {
            return $v5ModeA;
        }

        // Filament v4: Resources/CategoryResource/
        $v4 = "{$base}/{$model}Resource";
        if (File::isDirectory($v4)) {
            return $v4;
        }

        return null;
    }

    /**
     * Updates a Filament resource with fields, columns and filters
     *
     * @param  array<int, string>  $fields
     */
    public function update(string $model, array $fields, bool $softDeletes = false): bool
    {
        $resourcePath = $this->resolveResourcePath($model);

        if ($resourcePath === null) {
            $this->log("Resource file not found for model: {$model}", 'error');

            return false;
        }

        // Process fields
        [$formFields, $tableColumns, $filterFields, $formComponents, $tableComponents] = $this->processFields($fields);

        $this->log('Total form fields: '.count($formFields));
        $this->log('Total table columns: '.count($tableColumns));
        $this->log('Total filters: '.count($filterFields));

        // Detect Schemas/Tables directory structure independently
        $resourceDir = $this->resolveResourceDir($model);
        $schemaPath = $resourceDir !== null ? $resourceDir.'/Schemas/'.$model.'Form.php' : null;
        $tablePath = $resourceDir !== null ? $resourceDir.'/Tables/'.Str::plural($model).'Table.php' : null;

        $hasSchemaFile = $resourceDir !== null && $schemaPath !== null
            && File::isDirectory($resourceDir.'/Schemas') && File::exists($schemaPath);
        $hasTableFile = $resourceDir !== null && $tablePath !== null
            && File::isDirectory($resourceDir.'/Tables') && File::exists($tablePath);

        if ($hasSchemaFile || $hasTableFile) {
            $this->log('Separate file structure detected (schema='.($hasSchemaFile ? 'yes' : 'no').', table='.($hasTableFile ? 'yes' : 'no').')');

            // Update schema file if it exists
            if ($hasSchemaFile && ! empty($formFields)) {
                $resolvedSchemaPath = (string) $schemaPath;
                if (! $this->updateSchemaFile($model, $resolvedSchemaPath, $formFields, $formComponents)) {
                    return false;
                }
            }

            // Prepend TrashedFilter so soft-deleted records are filterable
            if ($softDeletes) {
                array_unshift($filterFields, 'TrashedFilter::make()');
            }

            // Update table file if it exists
            if ($hasTableFile && (! empty($tableColumns) || ! empty($filterFields))) {
                $resolvedTablePath = (string) $tablePath;
                if (! $this->updateTableFile($model, $resolvedTablePath, $tableColumns, $filterFields, $tableComponents, $softDeletes)) {
                    return false;
                }
            }

            // For parts without a separate file, fall back to inline in the Resource file
            if (! $hasSchemaFile || ! $hasTableFile) {
                $inlineFormFields = $hasSchemaFile ? [] : $formFields;
                $inlineTableColumns = $hasTableFile ? [] : $tableColumns;
                $inlineFilterFields = $hasTableFile ? [] : $filterFields;
                $usedComponents = array_unique(array_merge($formComponents, $tableComponents));

                return $this->updateInlineResource($model, $resourcePath, $inlineFormFields, $inlineTableColumns, $inlineFilterFields, $usedComponents, $softDeletes);
            }

            return true;
        }

        // Fallback: update inline in the Resource file
        $this->log('Inline structure detected (fallback)');
        $usedComponents = array_unique(array_merge($formComponents, $tableComponents));

        return $this->updateInlineResource($model, $resourcePath, $formFields, $tableColumns, $filterFields, $usedComponents, $softDeletes);
    }

    /**
     * Processes fields and returns separate arrays for form and table
     *
     * @param  array<int, string>  $fields
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

            // Split the field in format name:type:default:validations
            $parts = explode(':', $field);
            $fieldName = trim($parts[0]);
            $fieldType = trim($parts[1]);

            // Extract validations and default values
            $validationRules = [];
            $defaultValue = null;

            $validationKeywords = ['required', 'nullable', 'unique', 'email', 'url', 'tel', 'password', 'confirmed'];

            for ($i = 2; $i < count($parts); $i++) {
                $part = trim($parts[$i]);

                if ($i == 2 && ! preg_match('/[=]/', $part) && ! in_array($part, $validationKeywords)) {
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

            $this->log("Processing field: {$fieldName} of type {$fieldType}".
                       ($defaultValue ? " with default value {$defaultValue}" : '').
                       (! empty($validationRules) ? ' and '.count($validationRules).' validations' : ''));

            // Generate form component
            $formComponent = $this->formGenerator->generate($fieldName, $fieldType, $validationRules, $defaultValue);
            if ($formComponent) {
                $formFields[] = $formComponent;
                $formComponents[] = $this->formGenerator->getComponentType($fieldType);
            }

            // Generate table column
            $tableColumn = $this->tableGenerator->generateColumn($fieldName, $fieldType, $validationRules, $defaultValue);
            if ($tableColumn) {
                $tableColumns[] = $tableColumn;
                $tableComponents[] = $this->tableGenerator->getComponentType($fieldType, 'column');
            }

            // Generate filter
            $filter = $this->tableGenerator->generateFilter($fieldName, $fieldType, $validationRules);
            if ($filter) {
                $filterFields[] = $filter;
                $componentType = $this->tableGenerator->getComponentType($fieldType, 'filter', $fieldName);
                if ($componentType) {
                    $tableComponents[] = $componentType;
                }
            }
        }

        $allComponents = array_unique(array_merge($formComponents, $tableComponents));
        if (! empty($allComponents)) {
            $this->log('Components used: '.implode(', ', $allComponents));
        }

        return [$formFields, $tableColumns, $filterFields, $formComponents, $tableComponents];
    }

    /**
     * Updates the Schema file with the form fields
     *
     * @param  array<int, string>  $formFields
     * @param  array<int, string>  $formComponents
     */
    private function updateSchemaFile(string $model, string $schemaPath, array $formFields, array $formComponents): bool
    {
        $content = File::get($schemaPath);
        $content = $this->importManager->removeDuplicateImports($content);

        $content = $this->formGenerator->updateFormMethod($content, $formFields, $this->codeValidator);
        $content = $this->importManager->addFormFileImports($content, array_unique($formComponents));

        if ($this->isVerbose()) {
            $tempFile = storage_path('app/debug_schema_'.$model.'.php');
            File::put($tempFile, $content);
            $this->log("Debug version of Schema saved at: {$tempFile}");
        }

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Syntax error in generated Schema code.', 'error');

            return false;
        }

        File::put($schemaPath, $content);
        $this->log("Schema {$model}Form updated successfully!");

        return true;
    }

    /**
     * Updates the Table file with columns, filters and actions
     *
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $filterFields
     * @param  array<int, string>  $tableComponents
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

        if ($this->isVerbose()) {
            $tempFile = storage_path('app/debug_table_'.$model.'.php');
            File::put($tempFile, $content);
            $this->log("Debug version of Table saved at: {$tempFile}");
        }

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Syntax error in generated Table code.', 'error');

            return false;
        }

        File::put($tablePath, $content);
        $this->log('Table '.Str::plural($model).'Table updated successfully!');

        return true;
    }

    /**
     * Updates the inline resource (original/fallback behavior)
     *
     * @param  array<int, string>  $formFields
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $filterFields
     * @param  array<int, string>  $usedComponents
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

        // Prepend TrashedFilter so soft-deleted records are filterable
        if ($softDeletes) {
            array_unshift($filterFields, 'TrashedFilter::make()');
        }

        if (! empty($tableColumns) || ! empty($filterFields)) {
            $content = $this->tableGenerator->updateTableMethod($content, $tableColumns, $filterFields, $this->codeValidator);
        }

        $content = $this->importManager->addRequiredImports($content, $model, $usedComponents, $softDeletes);

        if ($this->isVerbose()) {
            $tempFile = storage_path('app/debug_resource_'.$model.'.php');
            File::put($tempFile, $content);
            $this->log("Debug version saved at: {$tempFile}");
        }

        if (! $this->codeValidator->validateSyntax($content)) {
            $this->log('Syntax error in generated code. Checking for issues...', 'error');

            return false;
        }

        File::put($resourcePath, $content);
        $this->log("Resource {$model} updated successfully!");

        return true;
    }

    /**
     * Checks if the command is running in verbose mode
     */
    private function isVerbose(): bool
    {
        return $this->command !== null && $this->command->getOutput()->isVerbose();
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
