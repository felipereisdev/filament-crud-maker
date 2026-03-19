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
        'BadgeColumn' => 'Filament\Tables\Columns\BadgeColumn',
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
            'use Filament\Forms;',
            'use Filament\Forms\Form;',
            'use Filament\Resources\Resource;',
            'use Filament\Tables;',
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
}
