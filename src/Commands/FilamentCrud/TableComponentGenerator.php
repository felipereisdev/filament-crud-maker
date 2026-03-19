<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class TableComponentGenerator
{
    /**
     * Generates a table column based on the field type
     *
     * @param  array<string, string>  $validationRules
     */
    public function generateColumn(string $fieldName, string $fieldType, array $validationRules = [], ?string $defaultValue = null): string
    {
        if ($fieldType === 'belongsToMany') {
            return '';
        }

        $column = match ($fieldType) {
            'string', 'text' => "TextColumn::make('{$fieldName}')",
            'textarea', 'longtext', 'markdown', 'richtext', 'editor', 'code', 'json', 'keyvalue' => "TextColumn::make('{$fieldName}')"
                .'->limit(50)'
                .'->tooltip(function ($state): ?string {
                           return strlen($state) > 50 ? $state : null;
                         })',
            'select', 'enum' => "TextColumn::make('{$fieldName}')"
                .'->badge()',
            'boolean', 'checkbox' => "ToggleColumn::make('{$fieldName}')",
            'slider', 'range' => "TextColumn::make('{$fieldName}')"
                .'->numeric()',
            'toggleButtons' => "TextColumn::make('{$fieldName}')"
                .'->badge()',
            'date' => "TextColumn::make('{$fieldName}')"
                .'->date()',
            'datetime' => "TextColumn::make('{$fieldName}')"
                .'->dateTime()',
            'time' => "TextColumn::make('{$fieldName}')"
                .'->time()',
            'decimal', 'float', 'double' => "TextColumn::make('{$fieldName}')"
                .(str_contains($fieldName, 'price') || str_contains($fieldName, 'preco') || str_contains($fieldName, 'valor')
                    ? "->money('BRL')"
                    : '->numeric(2)'),
            'integer', 'bigInteger' => "TextColumn::make('{$fieldName}')"
                .'->numeric(0)',
            'color' => "ColorColumn::make('{$fieldName}')",
            'image' => "ImageColumn::make('{$fieldName}')"
                .'->circular()',
            'foreignId' => "TextColumn::make('".str_replace('_id', '', $fieldName).".name')",
            'tags' => "TextColumn::make('{$fieldName}')"
                .'->badge()',
            default => "TextColumn::make('{$fieldName}')",
        };

        // Add common properties for columns
        if (in_array($fieldType, ['string', 'text', 'textarea', 'longtext', 'select', 'enum', 'email', 'url'])) {
            $column .= '->searchable()->sortable()';
        } elseif (in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double', 'date', 'datetime', 'time'])) {
            $column .= '->sortable()';
        }

        return $column;
    }

    /**
     * Generates a table filter based on the field type
     *
     * @param  array<string, string>  $validationRules
     */
    public function generateFilter(string $fieldName, string $fieldType, array $validationRules = []): ?string
    {
        $filter = match ($fieldType) {
            'boolean', 'checkbox' => "TernaryFilter::make('{$fieldName}')",
            'toggleButtons' => "SelectFilter::make('{$fieldName}')->options([ /* TODO: add your options here */ ])",
            'foreignId' => "SelectFilter::make('{$fieldName}')"
                ."->relationship('".str_replace('_id', '', $fieldName)."', 'name')",
            'select', 'enum' => "SelectFilter::make('{$fieldName}')->options([ /* TODO: add your options here */ ])",
            'belongsToMany' => null,
            'date', 'datetime' => "Filter::make('{$fieldName}')"
                .'->form(['
                ."DatePicker::make('{$fieldName}_from')"
                ."->label('Start date'),"
                ."DatePicker::make('{$fieldName}_until')"
                ."->label('End date')"
                .'])'
                ."->query(function (Builder \$query, array \$data): Builder {
                            return \$query
                                ->when(
                                    \$data['{$fieldName}_from'],
                                    fn (Builder \$query, \$date): Builder => \$query->whereDate('{$fieldName}', '>=', \$date),
                                )
                                ->when(
                                    \$data['{$fieldName}_until'],
                                    fn (Builder \$query, \$date): Builder => \$query->whereDate('{$fieldName}', '<=', \$date),
                                );
                        })",
            'decimal', 'float', 'double', 'integer', 'bigInteger', 'slider', 'range' => "Filter::make('{$fieldName}')"
                .'->form(['
                ."TextInput::make('{$fieldName}_from')"
                ."->label('Minimum value')"
                .'->numeric(),'
                ."TextInput::make('{$fieldName}_until')"
                ."->label('Maximum value')"
                .'->numeric()'
                .'])'
                ."->query(function (Builder \$query, array \$data): Builder {
                            return \$query
                                ->when(
                                    \$data['{$fieldName}_from'],
                                    fn (Builder \$query, \$min): Builder => \$query->where('{$fieldName}', '>=', \$min),
                                )
                                ->when(
                                    \$data['{$fieldName}_until'],
                                    fn (Builder \$query, \$max): Builder => \$query->where('{$fieldName}', '<=', \$max),
                                );
                        })",
            'string', 'text', 'textarea', 'longtext' => (
                str_contains($fieldName, 'status') || str_contains($fieldName, 'type') || str_contains($fieldName, 'tipo') || str_contains($fieldName, 'category') || str_contains($fieldName, 'categoria')
                    ? "SelectFilter::make('{$fieldName}')->options([ /* TODO: add your options here */ ])"
                    : null
            ),
            default => null,
        };

        return $filter;
    }

    /**
     * Returns the table component type based on the field type
     */
    public function getComponentType(string $fieldType, string $context = 'column'): string
    {
        if ($context === 'column') {
            return match ($fieldType) {
                'boolean', 'checkbox' => 'ToggleColumn',
                'image' => 'ImageColumn',
                'color' => 'ColorColumn',
                'icon' => 'IconColumn',
                'enum', 'tags', 'toggleButtons' => 'TextColumn',
                'code', 'json', 'keyvalue', 'slider', 'range' => 'TextColumn',
                'belongsToMany' => '',
                default => 'TextColumn',
            };
        } elseif ($context === 'filter') {
            return match ($fieldType) {
                'boolean', 'checkbox' => 'TernaryFilter',
                'select', 'enum', 'foreignId', 'status', 'type', 'category', 'toggleButtons' => 'SelectFilter',
                'date', 'datetime', 'time', 'decimal', 'float', 'double', 'integer', 'bigInteger', 'slider', 'range' => 'Filter',
                'belongsToMany' => '',
                default => '',
            };
        }

        return '';
    }

    /**
     * Updates the table method with the generated columns and filters
     *
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $filterFields
     */
    public function updateTableMethod(string $content, array $tableColumns, array $filterFields, CodeValidator $validator): string
    {
        if (empty($tableColumns) && empty($filterFields)) {
            return $content;
        }

        if (preg_match('/public\s+(?:static\s+)?function\s+(?:table|configure)\s*\(\s*Table\s+\$table\s*\)\s*:.*?\{/s', $content, $tableMatches, PREG_OFFSET_CAPTURE)) {
            $tableStartPos = $tableMatches[0][1];
            $openBracePos = strpos($content, '{', $tableStartPos);
            if ($openBracePos === false) {
                return $content;
            }
            $closeBracePos = $validator->findMatchingCloseBrace($content, $openBracePos);

            if ($closeBracePos !== false) {
                $newTableFunction = substr($content, $tableStartPos, $openBracePos - $tableStartPos + 1);
                $newTableFunction .= "\n        return \$table\n";

                // Columns
                $newTableFunction .= "            ->columns([\n";
                foreach ($tableColumns as $column) {
                    $newTableFunction .= "                {$column},\n";
                }
                $newTableFunction .= "            ])\n";

                // Filters
                if (! empty($filterFields)) {
                    $newTableFunction .= "            ->filters([\n";
                    foreach ($filterFields as $filter) {
                        $newTableFunction .= "                {$filter},\n";
                    }
                    $newTableFunction .= "            ])\n";
                }

                // Actions - using Filament v4 API
                $newTableFunction .= "            ->recordActions([\n";
                $newTableFunction .= "                EditAction::make(),\n";
                $newTableFunction .= "            ])\n";

                // BulkActions - using Filament v4 API
                $newTableFunction .= "            ->toolbarActions([\n";
                $newTableFunction .= "                BulkActionGroup::make([\n";
                $newTableFunction .= "                    DeleteBulkAction::make(),\n";
                $newTableFunction .= "                ])\n"; // BulkActionGroup closing
                $newTableFunction .= "            ]);\n"; // toolbarActions closing + semicolon

                // Function closing
                $newTableFunction .= '    }';

                $content = substr_replace($content, $newTableFunction, $tableStartPos, $closeBracePos - $tableStartPos + 1);
            }
        }

        return $content;
    }
}
