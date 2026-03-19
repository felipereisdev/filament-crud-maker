<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class TableComponentGenerator
{
    /**
     * Gera uma coluna de tabela com base no tipo de campo
     */
    public function generateColumn(string $fieldName, string $fieldType, array $validationRules = [], ?string $defaultValue = null): string
    {
        $column = match ($fieldType) {
            'string', 'text' => "TextColumn::make('{$fieldName}')",
            'textarea', 'longtext', 'markdown', 'richtext', 'editor' => "TextColumn::make('{$fieldName}')"
                . "->limit(50)"
                . "->tooltip(function (\$state): ?string {
                           return strlen(\$state) > 50 ? \$state : null;
                         })",
            'enum' => "TextColumn::make('{$fieldName}')"
                . "->badge()",
            'boolean' => "ToggleColumn::make('{$fieldName}')",
            'date' => "TextColumn::make('{$fieldName}')"
                . "->date()",
            'datetime' => "TextColumn::make('{$fieldName}')"
                . "->dateTime()",
            'time' => "TextColumn::make('{$fieldName}')"
                . "->time()",
            'decimal', 'float', 'double' => "TextColumn::make('{$fieldName}')"
                . (str_contains($fieldName, 'price') || str_contains($fieldName, 'preco') || str_contains($fieldName, 'valor')
                    ? "->money('BRL')"
                    : "->numeric(2)"),
            'integer', 'bigInteger' => "TextColumn::make('{$fieldName}')"
                . "->numeric(0)",
            'color' => "ColorColumn::make('{$fieldName}')",
            'image' => "ImageColumn::make('{$fieldName}')"
                . "->circular()",
            'foreignId' => "TextColumn::make('" . str_replace('_id', '', $fieldName) . ".name')",
            'tags' => "TextColumn::make('{$fieldName}')"
                . "->badge()",
            default => "TextColumn::make('{$fieldName}')",
        };

        // Adicionar propriedades comuns para colunas
        if (in_array($fieldType, ['string', 'text', 'textarea', 'longtext', 'enum', 'email', 'url'])) {
            $column .= '->searchable()->sortable()';
        } elseif (in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double', 'date', 'datetime', 'time'])) {
            $column .= '->sortable()';
        }

        return $column;
    }

    /**
     * Gera um filtro de tabela com base no tipo de campo
     */
    public function generateFilter(string $fieldName, string $fieldType, array $validationRules = []): ?string
    {
        $filter = match ($fieldType) {
            'boolean' => "TernaryFilter::make('{$fieldName}')",
            'foreignId' => "SelectFilter::make('{$fieldName}')"
                . "->relationship('" . str_replace('_id', '', $fieldName) . "', 'name')",
            'select', 'enum' => "SelectFilter::make('{$fieldName}')",
            'date', 'datetime' => "Filter::make('{$fieldName}')"
                . "->form(["
                . "DatePicker::make('{$fieldName}_from')"
                . "->label('Data inicial'),"
                . "DatePicker::make('{$fieldName}_until')"
                . "->label('Data final')"
                . "])"
                . "->query(function (Builder \$query, array \$data): Builder {
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
            'decimal', 'float', 'double', 'integer', 'bigInteger' => "Filter::make('{$fieldName}')"
                . "->form(["
                . "TextInput::make('{$fieldName}_from')"
                . "->label('Valor mínimo')"
                . "->numeric(),"
                . "TextInput::make('{$fieldName}_until')"
                . "->label('Valor máximo')"
                . "->numeric()"
                . "])"
                . "->query(function (Builder \$query, array \$data): Builder {
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
                    ? "SelectFilter::make('{$fieldName}')"
                    : null
            ),
            default => null,
        };

        return $filter;
    }

    /**
     * Retorna o tipo de componente de tabela com base no tipo de campo
     */
    public function getComponentType(string $fieldType, string $context = 'column'): string
    {
        if ($context === 'column') {
            return match ($fieldType) {
                'boolean' => 'ToggleColumn',
                'image' => 'ImageColumn',
                'color' => 'ColorColumn',
                'icon' => 'IconColumn',
                'enum', 'tags' => 'TextColumn',
                default => 'TextColumn',
            };
        } elseif ($context === 'filter') {
            return match ($fieldType) {
                'boolean' => 'TernaryFilter',
                'select', 'enum', 'foreignId', 'status', 'type', 'category' => 'SelectFilter',
                'date', 'datetime', 'time', 'decimal', 'float', 'double', 'integer', 'bigInteger' => 'Filter',
                default => '',
            };
        }

        return '';
    }

    /**
     * Atualiza o método table com as colunas e filtros gerados
     */
    public function updateTableMethod(string $content, array $tableColumns, array $filterFields, CodeValidator $validator): string
    {
        if (empty($tableColumns) && empty($filterFields)) {
            return $content;
        }

        if (preg_match('/public\s+(?:static\s+)?function\s+(?:table|configure)\s*\(\s*Table\s+\$table\s*\)\s*:.*?\{/s', $content, $tableMatches, PREG_OFFSET_CAPTURE)) {
            $tableStartPos = $tableMatches[0][1];
            $openBracePos = strpos($content, '{', $tableStartPos);
            $closeBracePos = $validator->findMatchingCloseBrace($content, $openBracePos);

            if ($closeBracePos !== false) {
                $newTableFunction = substr($content, $tableStartPos, $openBracePos - $tableStartPos + 1);
                $newTableFunction .= "\n        return \$table\n";

                // Colunas
                $newTableFunction .= "            ->columns([\n";
                foreach ($tableColumns as $column) {
                    $newTableFunction .= "                {$column},\n";
                }
                $newTableFunction .= "            ])\n";

                // Filtros
                if (! empty($filterFields)) {
                    $newTableFunction .= "            ->filters([\n";
                    foreach ($filterFields as $filter) {
                        $newTableFunction .= "                {$filter},\n";
                    }
                    $newTableFunction .= "            ])\n";
                }

                // Actions - usar Filament v4 API
                $newTableFunction .= "            ->recordActions([\n";
                $newTableFunction .= "                EditAction::make(),\n";
                $newTableFunction .= "            ])\n";

                // BulkActions - usar Filament v4 API
                $newTableFunction .= "            ->toolbarActions([\n";
                $newTableFunction .= "                BulkActionGroup::make([\n";
                $newTableFunction .= "                    DeleteBulkAction::make(),\n";
                $newTableFunction .= "                ])\n"; // Fechamento do BulkActionGroup
                $newTableFunction .= "            ]);\n"; // Fechamento do toolbarActions + ponto e vírgula

                // Fechamento da função
                $newTableFunction .= "    }";

                $content = substr_replace($content, $newTableFunction, $tableStartPos, $closeBracePos - $tableStartPos + 1);
            }
        }

        return $content;
    }
}
