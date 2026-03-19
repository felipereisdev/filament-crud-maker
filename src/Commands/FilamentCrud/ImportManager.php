<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class ImportManager
{
    /**
     * Mapping of components to full import paths
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
        'CodeEditor' => 'Filament\Forms\Components\CodeEditor',
        'Slider' => 'Filament\Forms\Components\Slider',
        'ToggleButtons' => 'Filament\Forms\Components\ToggleButtons',
        'KeyValue' => 'Filament\Forms\Components\KeyValue',
        'Checkbox' => 'Filament\Forms\Components\Checkbox',
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
     * Removes duplicate imports from the code
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
                // We ignore duplicate imports
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Adds required imports based on the components used
     *
     * @param  array<int, string>  $usedComponents
     */
    public function addRequiredImports(string $content, string $model, array $usedComponents, bool $softDeletes): string
    {
        // Check if Builder is being used in the code
        $needsBuilder = preg_match('/function\s*\(\s*Builder|\(\s*Builder\s*\$|\:\s*Builder|use\s+function.*?Builder|fn\s*\(\s*Builder/', $content);

        // Check if DatePicker is used in filters
        $needsDatePicker = false;
        if (in_array('Filter', $usedComponents) && (
            strpos($content, 'DatePicker::make') !== false ||
            preg_match('/date|datetime/', $content)
        )) {
            $needsDatePicker = true;
            $usedComponents[] = 'DatePicker';
        }

        // Check if TextInput is used in numeric filters
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

        // Generate imports for the used components
        $imports = [];
        foreach ($usedComponents as $component) {
            if (isset(self::IMPORT_MAP[$component])) {
                $imports[] = 'use '.self::IMPORT_MAP[$component].';';
            }
        }

        // Add Builder import if needed
        if ($needsBuilder) {
            $imports[] = 'use Illuminate\Database\Eloquent\Builder;';
        }

        // Add SoftDeletingScope and TrashedFilter if softDeletes is true
        if ($softDeletes) {
            $imports[] = 'use Illuminate\Database\Eloquent\SoftDeletingScope;';
            $imports[] = 'use Filament\Tables\Filters\TrashedFilter;';
        }

        $resourceNamespace = NamespaceHelper::resourceNamespace();
        $modelNamespace = NamespaceHelper::modelNamespace();

        // Extract the actual namespace declared in the file (may be a sub-namespace in Filament v5)
        $actualResourceNamespace = $resourceNamespace;
        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            $actualResourceNamespace = trim($nsMatch[1]);
        }

        // Add default imports required for any Filament resource
        $requiredImports = [
            'use '.$actualResourceNamespace.'\\'.$model.'Resource\\Pages;',
            'use '.$actualResourceNamespace.'\\'.$model.'Resource\\RelationManagers;',
            'use '.$modelNamespace.'\\'.$model.';',
            'use Filament\Resources\Resource;',
            'use Filament\Schemas\Schema;',
            'use Filament\Tables\Table;',
        ];

        // Combine all managed imports and remove duplicates
        $imports = array_merge($requiredImports, $imports);
        $imports = array_unique($imports);

        // Build the set of managed FQCNs so we can preserve unmanaged imports
        $managedFqcns = $this->buildManagedFqcnSet($imports);

        // Find the position right after the namespace to add imports.
        // The pattern supports sub-namespaces (e.g. App\Filament\Resources\Categories in Filament v5).
        $namespacePattern = '/namespace\s+'.preg_quote($resourceNamespace, '/').'(?:\\\\[A-Za-z0-9_]+)*;/';
        if (preg_match($namespacePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEndPos = $matches[0][1] + strlen($matches[0][0]);

            $afterNamespace = substr($content, $namespaceEndPos);
            $classPos = strpos($afterNamespace, 'class '.$model.'Resource');

            if ($classPos !== false) {
                // Extract only the part between the namespace and the class
                $importSection = substr($afterNamespace, 0, $classPos);

                // Collect existing imports that are NOT managed by us — these must be preserved
                $preservedImports = $this->extractUnmanagedImports($importSection, $managedFqcns);

                // Merge preserved + generated imports, deduplicate, sort
                $imports = array_unique(array_merge($imports, $preservedImports));
                sort($imports);

                // Remove all existing imports from the section
                $importSection = preg_replace('/use\s+[^;]+;\s*/', '', $importSection);

                // Replace the content between the namespace and the class with new imports
                $importString = "\n\n".implode("\n", $imports)."\n\n";
                $content = substr($content, 0, $namespaceEndPos).$importString.
                          substr($afterNamespace, $classPos);
            }
        }

        return $content;
    }

    /**
     * Adds imports for Schema files (v4 form)
     *
     * @param  array<int, string>  $formComponents
     */
    public function addFormFileImports(string $content, array $formComponents): string
    {
        $imports = ['use Filament\Schemas\Schema;'];

        foreach ($formComponents as $component) {
            if (isset(self::IMPORT_MAP[$component]) && str_starts_with(self::IMPORT_MAP[$component], 'Filament\Forms\Components\\')) {
                $imports[] = 'use '.self::IMPORT_MAP[$component].';';
            }
        }

        $imports = array_unique($imports);
        sort($imports);

        return $this->insertImportsIntoContent($content, $imports);
    }

    /**
     * Adds imports for Table files (v4 columns, filters, actions)
     *
     * @param  array<int, string>  $tableComponents
     */
    public function addTableFileImports(string $content, array $tableComponents, bool $softDeletes): string
    {
        // Check if Builder is being used in the code
        $needsBuilder = (bool) preg_match('/function\s*\(\s*Builder|\(\s*Builder\s*\$|\:\s*Builder|use\s+function.*?Builder|fn\s*\(\s*Builder/', $content);

        // Check if DatePicker is used in filters
        if (in_array('Filter', $tableComponents, true) && (
            str_contains($content, 'DatePicker::make') ||
            (bool) preg_match('/date|datetime/', $content)
        )) {
            $tableComponents[] = 'DatePicker';
        }

        // Check if TextInput is used in numeric filters
        if (in_array('Filter', $tableComponents, true) && (
            (bool) preg_match('/numeric|integer|decimal|float|double/', $content) ||
            str_contains($content, 'TextInput::make')
        )) {
            $tableComponents[] = 'TextInput';
        }

        // Always add actions
        $tableComponents[] = 'EditAction';
        $tableComponents[] = 'BulkActionGroup';
        $tableComponents[] = 'DeleteBulkAction';

        $imports = ['use Filament\Tables\Table;'];

        foreach (array_unique($tableComponents) as $component) {
            if (isset(self::IMPORT_MAP[$component])) {
                $imports[] = 'use '.self::IMPORT_MAP[$component].';';
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
     * Inserts imports into the content after the namespace declaration, preserving unmanaged imports
     *
     * @param  array<int, string>  $imports
     */
    private function insertImportsIntoContent(string $content, array $imports): string
    {
        if (preg_match('/namespace\s+[^;]+;/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEndPos = $matches[0][1] + strlen($matches[0][0]);
            $afterNamespace = substr($content, $namespaceEndPos);

            if (preg_match('/\bclass\s+\w+/', $afterNamespace, $classMatches, PREG_OFFSET_CAPTURE)) {
                $classPos = $classMatches[0][1];
                $importSection = substr($afterNamespace, 0, $classPos);

                // Build managed FQCN set from the new imports
                $managedFqcns = $this->buildManagedFqcnSet($imports);

                // Preserve any existing imports not managed by us
                $preservedImports = $this->extractUnmanagedImports($importSection, $managedFqcns);
                $imports = array_unique(array_merge($imports, $preservedImports));
                sort($imports);

                // Remove existing imports
                $importSection = preg_replace('/use\s+[^;]+;\s*/', '', $importSection);

                // Replace with new imports
                $importString = "\n\n".implode("\n", $imports)."\n\n";
                $content = substr($content, 0, $namespaceEndPos).$importString.substr($afterNamespace, $classPos);
            }
        }

        return $content;
    }

    /**
     * Builds a set of FQCNs that are managed by the import manager
     *
     * @param  array<int, string>  $managedImports
     * @return array<int, string>
     */
    private function buildManagedFqcnSet(array $managedImports): array
    {
        $fqcns = [];

        // All FQCNs from IMPORT_MAP are managed
        foreach (self::IMPORT_MAP as $fqcn) {
            $fqcns[] = $fqcn;
        }

        // FQCNs from the managed import statements
        foreach ($managedImports as $importLine) {
            if (preg_match('/^use\s+([^;]+);$/', $importLine, $m)) {
                $fqcns[] = $m[1];
            }
        }

        // Additional managed FQCNs
        $fqcns[] = 'Illuminate\Database\Eloquent\Builder';
        $fqcns[] = 'Illuminate\Database\Eloquent\SoftDeletingScope';
        $fqcns[] = 'Filament\Tables\Filters\TrashedFilter';
        $fqcns[] = 'Filament\Schemas\Schema';
        $fqcns[] = 'Filament\Tables\Table';
        $fqcns[] = 'Filament\Resources\Resource';

        return array_unique($fqcns);
    }

    /**
     * Extracts import statements from a code section that are not in the managed FQCN set
     *
     * @param  array<int, string>  $managedFqcns
     * @return array<int, string>
     */
    private function extractUnmanagedImports(string $section, array $managedFqcns): array
    {
        $preserved = [];

        if (preg_match_all('/use\s+([^;]+);/', $section, $matches)) {
            foreach ($matches[1] as $fqcn) {
                $fqcn = trim($fqcn);
                if (! in_array($fqcn, $managedFqcns, true)) {
                    $preserved[] = 'use '.$fqcn.';';
                }
            }
        }

        return $preserved;
    }
}
