<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class TableComponentGenerator
{
    /**
     * Gera uma coluna de tabela com base no tipo de campo
     */
    public function generateColumn(string $fieldName, string $fieldType, array $validationRules = [], ?string $defaultValue = null): string
    {
        $column = null;

        switch ($fieldType) {
            case 'string':
            case 'text':
                $column = "TextColumn::make('{$fieldName}')";

                break;
            case 'textarea':
            case 'longtext':
            case 'markdown':
            case 'richtext':
            case 'editor':
                $column = "TextColumn::make('{$fieldName}')"
                       . "->limit(50)"
                       . "->tooltip(function (\$state): ?string {
                           return strlen(\$state) > 50 ? \$state : null;
                         })";

                break;
            case 'enum':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->badge()";

                break;
            case 'boolean':
                $column = "ToggleColumn::make('{$fieldName}')";

                break;
            case 'date':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->date()";

                break;
            case 'datetime':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->dateTime()";

                break;
            case 'time':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->time()";

                break;
            case 'decimal':
            case 'float':
            case 'double':
                // Para campos de dinheiro/preço
                if (strpos($fieldName, 'price') !== false ||
                    strpos($fieldName, 'preco') !== false ||
                    strpos($fieldName, 'valor') !== false) {
                    $column = "TextColumn::make('{$fieldName}')"
                            . "->money('BRL')";
                } else {
                    $column = "TextColumn::make('{$fieldName}')"
                            . "->numeric(2)";
                }

                break;
            case 'integer':
            case 'bigInteger':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->numeric(0)";

                break;
            case 'color':
                $column = "ColorColumn::make('{$fieldName}')";

                break;
            case 'image':
                $column = "ImageColumn::make('{$fieldName}')"
                        . "->circular()";

                break;
            case 'foreignId':
                // Tentar extrair o nome da relação do nome do campo
                $relationName = str_replace('_id', '', $fieldName);
                $column = "TextColumn::make('{$relationName}.name')";

                break;
            case 'tags':
                $column = "TextColumn::make('{$fieldName}')"
                        . "->badge()";

                break;
            default:
                $column = "TextColumn::make('{$fieldName}')";

                break;
        }

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
        $filter = null;

        switch ($fieldType) {
            case 'boolean':
                $filter = "TernaryFilter::make('{$fieldName}')";

                break;
            case 'foreignId':
                // Tentar extrair o nome da relação do nome do campo
                $relationName = str_replace('_id', '', $fieldName);
                $relatedModelName = ucfirst($relationName);
                $filter = "SelectFilter::make('{$fieldName}')"
                        . "->relationship('{$relationName}', 'name')";

                break;
            case 'select':
            case 'enum':
                $filter = "SelectFilter::make('{$fieldName}')";

                break;
            case 'date':
            case 'datetime':
                $filter = "Filter::make('{$fieldName}')"
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
                        })";

                break;
            case 'decimal':
            case 'float':
            case 'double':
            case 'integer':
            case 'bigInteger':
                $filter = "Filter::make('{$fieldName}')"
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
                        })";

                break;
            case 'string':
            case 'text':
            case 'textarea':
            case 'longtext':
                // Somente criar filtro para campos de texto que provavelmente representam status ou categorias
                if (strpos($fieldName, 'status') !== false ||
                    strpos($fieldName, 'type') !== false ||
                    strpos($fieldName, 'tipo') !== false ||
                    strpos($fieldName, 'category') !== false ||
                    strpos($fieldName, 'categoria') !== false) {
                    $filter = "SelectFilter::make('{$fieldName}')";
                }

                break;
            default:
                // Não criar filtros para outros tipos por padrão
                break;
        }

        return $filter;
    }

    /**
     * Retorna o tipo de componente de tabela com base no tipo de campo
     */
    public function getComponentType(string $fieldType, string $context = 'column'): string
    {
        if ($context === 'column') {
            switch ($fieldType) {
                case 'boolean':
                    return 'ToggleColumn';
                case 'image':
                    return 'ImageColumn';
                case 'color':
                    return 'ColorColumn';
                case 'icon':
                    return 'IconColumn';
                case 'enum':
                case 'tags':
                    return 'BadgeColumn';
                default:
                    return 'TextColumn';
            }
        } elseif ($context === 'filter') {
            switch ($fieldType) {
                case 'boolean':
                    return 'TernaryFilter';
                case 'select':
                case 'enum':
                case 'foreignId':
                case 'status':
                case 'type':
                case 'category':
                    return 'SelectFilter';
                case 'date':
                case 'datetime':
                case 'time':
                case 'decimal':
                case 'float':
                case 'double':
                case 'integer':
                case 'bigInteger':
                    return 'Filter';
                default:
                    return '';
            }
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

        if (preg_match('/public\s+static\s+function\s+table\s*\(\s*Table\s+\$table\s*\)\s*:.*?\{/s', $content, $tableMatches, PREG_OFFSET_CAPTURE)) {
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

                // Actions - manter as existentes ou adicionar padrão
                $newTableFunction .= "            ->actions([\n";
                $newTableFunction .= "                Tables\Actions\EditAction::make(),\n";
                $newTableFunction .= "            ])\n";

                // BulkActions - garantir que a estrutura seja corretamente fechada
                $newTableFunction .= "            ->bulkActions([\n";
                $newTableFunction .= "                Tables\Actions\BulkActionGroup::make([\n";
                $newTableFunction .= "                    Tables\Actions\DeleteBulkAction::make(),\n";
                $newTableFunction .= "                ])\n"; // Fechamento do BulkActionGroup
                $newTableFunction .= "            ]);\n"; // Fechamento do bulkActions + ponto e vírgula

                // Fechamento da função
                $newTableFunction .= "    }";

                $content = substr_replace($content, $newTableFunction, $tableStartPos, $closeBracePos - $tableStartPos + 1);
            }
        }

        return $content;
    }
}
