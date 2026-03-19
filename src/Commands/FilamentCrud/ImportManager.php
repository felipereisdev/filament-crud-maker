<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class ImportManager
{
    /**
     * Mapeamento de componentes para importações completas
     *
     * @var array<string, string>
     */
    private const array IMPORT_MAP = [
        'TextInput' => 'Filament\Forms\Components\TextInput',
        'Textarea' => 'Filament\Forms\Components\Textarea',
        'Select' => 'Filament\Forms\Components\Select',
        'Toggle' => 'Filament\Forms\Components\Toggle',
        'DatePicker' => 'Filament\Forms\Components\DatePicker',
        'DateTimePicker' => 'Filament\Forms\Components\DateTimePicker',
        'TimePicker' => 'Filament\Forms\Components\TimePicker',
        'CheckboxList' => 'Filament\Forms\Components\CheckboxList',
        'Radio' => 'Filament\Forms\Components\Radio',
        'ColorPicker' => 'Filament\Forms\Components\ColorPicker',
        'FileUpload' => 'Filament\Forms\Components\FileUpload',
        'RichEditor' => 'Filament\Forms\Components\RichEditor',
        'MarkdownEditor' => 'Filament\Forms\Components\MarkdownEditor',
        'TagsInput' => 'Filament\Forms\Components\TagsInput',
        'TextColumn' => 'Filament\Tables\Columns\TextColumn',
        'ImageColumn' => 'Filament\Tables\Columns\ImageColumn',
        'ToggleColumn' => 'Filament\Tables\Columns\ToggleColumn',
        'ColorColumn' => 'Filament\Tables\Columns\ColorColumn',
        'IconColumn' => 'Filament\Tables\Columns\IconColumn',
        'EditAction' => 'Filament\Actions\EditAction',
        'BulkActionGroup' => 'Filament\Actions\BulkActionGroup',
        'DeleteBulkAction' => 'Filament\Actions\DeleteBulkAction',
        'Filter' => 'Filament\Tables\Filters\Filter',
        'SelectFilter' => 'Filament\Tables\Filters\SelectFilter',
        'TernaryFilter' => 'Filament\Tables\Filters\TernaryFilter',
    ];

    /**
     * Remove importações duplicadas do código
     */
    public function removeDuplicateImports(string $content): string
    {
        $lines = explode("\n", $content);
        $seenImports = [];
        $result = [];

        foreach ($lines as $line) {
            if (preg_match('/^use\s+([^;]+);$/', $line, $matches)) {
                $import = $matches[1];
                if (! in_array($import, $seenImports)) {
                    $seenImports[] = $import;
                    $result[] = $line;
                }
                // Ignoramos importações duplicadas
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Adiciona importações necessárias com base nos componentes usados
     */
    public function addRequiredImports(string $content, string $model, array $usedComponents, bool $softDeletes): string
    {
        // Verificar se Builder está sendo usado no código
        $needsBuilder = preg_match('/function\s*\(\s*Builder|\(\s*Builder\s*\$|\:\s*Builder|use\s+function.*?Builder|fn\s*\(\s*Builder/', $content);

        // Verificar se DatePicker é usado em filtros
        $needsDatePicker = false;
        if (in_array('Filter', $usedComponents) && (
            strpos($content, 'DatePicker::make') !== false ||
            preg_match('/date|datetime/', $content)
        )) {
            $needsDatePicker = true;
            $usedComponents[] = 'DatePicker';
        }

        // Verificar se TextInput é usado em filtros numéricos
        $needsTextInput = false;
        if (in_array('Filter', $usedComponents) && (
            preg_match('/numeric|integer|decimal|float|double/', $content) ||
            strpos($content, 'TextInput::make') !== false
        )) {
            $needsTextInput = true;
            $usedComponents[] = 'TextInput';
        }

        // Ensure action components are always imported
        $usedComponents[] = 'EditAction';
        $usedComponents[] = 'BulkActionGroup';
        $usedComponents[] = 'DeleteBulkAction';

        // Gerar importações para os componentes usados
        $imports = [];
        foreach ($usedComponents as $component) {
            if (isset(self::IMPORT_MAP[$component])) {
                $imports[] = 'use ' . self::IMPORT_MAP[$component] . ';';
            }
        }

        // Adicionar importação do Builder se necessário
        if ($needsBuilder) {
            $imports[] = 'use Illuminate\Database\Eloquent\Builder;';
        }

        // Adicionar SoftDeletingScope e TrashedFilter se softDeletes for true
        if ($softDeletes) {
            $imports[] = 'use Illuminate\Database\Eloquent\SoftDeletingScope;';
            $imports[] = 'use Filament\Tables\Filters\TrashedFilter;';
        }

        // Adicionar importações padrão necessárias para qualquer recurso Filament
        $requiredImports = [
            'use App\Filament\Resources\\' . $model . 'Resource\Pages;',
            'use App\Filament\Resources\\' . $model . 'Resource\RelationManagers;',
            'use App\Models\\' . $model . ';',
            'use Filament\Resources\Resource;',
            'use Filament\Schemas\Schema;',
            'use Filament\Tables\Table;',
        ];

        // Combinar todas as importações e remover duplicatas
        $imports = array_merge($requiredImports, $imports);
        $imports = array_unique($imports);
        sort($imports); // Ordenar para facilitar a leitura

        // Encontrar a posição logo após o namespace para adicionar as importações
        $namespacePattern = '/namespace\s+App\\\\Filament\\\\Resources;/';
        if (preg_match($namespacePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEndPos = $matches[0][1] + strlen($matches[0][0]);

            // Remover todas as importações existentes entre o namespace e a classe
            $afterNamespace = substr($content, $namespaceEndPos);
            $classPos = strpos($afterNamespace, 'class ' . $model . 'Resource');

            if ($classPos !== false) {
                // Extrair apenas a parte entre o namespace e a classe
                $importSection = substr($afterNamespace, 0, $classPos);

                // Remover todas as importações existentes
                $importSection = preg_replace('/use\s+[^;]+;\s*/', '', $importSection);

                // Substituir o conteúdo entre o namespace e a classe por novas importações
                $importString = "\n\n" . implode("\n", $imports) . "\n\n";
                $content = substr($content, 0, $namespaceEndPos) . $importString .
                          substr($afterNamespace, $classPos);
            }
        }

        return $content;
    }

    /**
     * Adiciona importações para arquivos de Schema (formulário v4)
     *
     * @param array<int, string> $formComponents
     */
    public function addFormFileImports(string $content, array $formComponents): string
    {
        $imports = ['use Filament\Schemas\Schema;'];

        foreach ($formComponents as $component) {
            if (isset(self::IMPORT_MAP[$component]) && str_starts_with(self::IMPORT_MAP[$component], 'Filament\Forms\Components\\')) {
                $imports[] = 'use ' . self::IMPORT_MAP[$component] . ';';
            }
        }

        $imports = array_unique($imports);
        sort($imports);

        return $this->insertImportsIntoContent($content, $imports);
    }

    /**
     * Adiciona importações para arquivos de Table (colunas, filtros, ações v4)
     *
     * @param array<int, string> $tableComponents
     */
    public function addTableFileImports(string $content, array $tableComponents, bool $softDeletes): string
    {
        // Verificar se Builder está sendo usado no código
        $needsBuilder = (bool) preg_match('/function\s*\(\s*Builder|\(\s*Builder\s*\$|\:\s*Builder|use\s+function.*?Builder|fn\s*\(\s*Builder/', $content);

        // Verificar se DatePicker é usado em filtros
        if (in_array('Filter', $tableComponents, true) && (
            str_contains($content, 'DatePicker::make') ||
            (bool) preg_match('/date|datetime/', $content)
        )) {
            $tableComponents[] = 'DatePicker';
        }

        // Verificar se TextInput é usado em filtros numéricos
        if (in_array('Filter', $tableComponents, true) && (
            (bool) preg_match('/numeric|integer|decimal|float|double/', $content) ||
            str_contains($content, 'TextInput::make')
        )) {
            $tableComponents[] = 'TextInput';
        }

        // Sempre adicionar ações
        $tableComponents[] = 'EditAction';
        $tableComponents[] = 'BulkActionGroup';
        $tableComponents[] = 'DeleteBulkAction';

        $imports = ['use Filament\Tables\Table;'];

        foreach (array_unique($tableComponents) as $component) {
            if (isset(self::IMPORT_MAP[$component])) {
                $imports[] = 'use ' . self::IMPORT_MAP[$component] . ';';
            }
        }

        if ($needsBuilder) {
            $imports[] = 'use Illuminate\Database\Eloquent\Builder;';
        }

        if ($softDeletes) {
            $imports[] = 'use Illuminate\Database\Eloquent\SoftDeletingScope;';
            $imports[] = 'use Filament\Tables\Filters\TrashedFilter;';
        }

        $imports = array_unique($imports);
        sort($imports);

        return $this->insertImportsIntoContent($content, $imports);
    }

    /**
     * Insere importações no conteúdo após a declaração de namespace
     *
     * @param array<int, string> $imports
     */
    private function insertImportsIntoContent(string $content, array $imports): string
    {
        if (preg_match('/namespace\s+[^;]+;/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEndPos = $matches[0][1] + strlen($matches[0][0]);
            $afterNamespace = substr($content, $namespaceEndPos);

            if (preg_match('/\bclass\s+\w+/', $afterNamespace, $classMatches, PREG_OFFSET_CAPTURE)) {
                $classPos = $classMatches[0][1];
                $importSection = substr($afterNamespace, 0, $classPos);

                // Remover importações existentes
                $importSection = preg_replace('/use\s+[^;]+;\s*/', '', $importSection);

                // Substituir por novas importações
                $importString = "\n\n" . implode("\n", $imports) . "\n\n";
                $content = substr($content, 0, $namespaceEndPos) . $importString . substr($afterNamespace, $classPos);
            }
        }

        return $content;
    }
}
