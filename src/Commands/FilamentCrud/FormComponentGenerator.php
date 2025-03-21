<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class FormComponentGenerator
{
    /**
     * Gera componente de formulário com base no tipo de campo
     */
    public function generate(string $fieldName, string $fieldType, array $validationRules = [], ?string $defaultValue = null): string
    {
        $component = null;

        switch ($fieldType) {
            case 'string':
            case 'text':
                $component = "TextInput::make('{$fieldName}')";

                break;
            case 'textarea':
            case 'longtext':
                $component = "Textarea::make('{$fieldName}')";

                break;
            case 'boolean':
                $component = "Toggle::make('{$fieldName}')";

                break;
            case 'date':
                $component = "DatePicker::make('{$fieldName}')";

                break;
            case 'datetime':
                $component = "DateTimePicker::make('{$fieldName}')";

                break;
            case 'time':
                $component = "TimePicker::make('{$fieldName}')";

                break;
            case 'select':
            case 'enum':
                $component = "Select::make('{$fieldName}')";

                break;
            case 'foreignId':
                // Tentar extrair o nome da relação do nome do campo
                $relationName = str_replace('_id', '', $fieldName);
                $component = "Select::make('{$fieldName}')";
                // Se o nome da relação for diferente do nome do campo, definir a relação
                if ($relationName != $fieldName) {
                    $relatedModelName = ucfirst($relationName);
                    $component .= "->relationship('{$relationName}', 'name')";
                }

                break;
            case 'checkboxes':
                $component = "CheckboxList::make('{$fieldName}')";

                break;
            case 'radio':
                $component = "Radio::make('{$fieldName}')";

                break;
            case 'color':
                $component = "ColorPicker::make('{$fieldName}')";

                break;
            case 'file':
                $component = "FileUpload::make('{$fieldName}')";

                break;
            case 'image':
                $component = "FileUpload::make('{$fieldName}')"
                           . "->image()"
                           . "->imageResizeMode('cover')"
                           . "->imageCropAspectRatio('16:9')";

                break;
            case 'richtext':
            case 'editor':
                $component = "RichEditor::make('{$fieldName}')";

                break;
            case 'markdown':
                $component = "MarkdownEditor::make('{$fieldName}')";

                break;
            case 'tags':
                $component = "TagsInput::make('{$fieldName}')";

                break;
            case 'decimal':
            case 'float':
            case 'double':
                $component = "TextInput::make('{$fieldName}')"
                            . "->numeric()"
                            . "->inputMode('decimal')";

                break;
            case 'integer':
            case 'bigInteger':
                $component = "TextInput::make('{$fieldName}')"
                            . "->numeric()"
                            . "->inputMode('numeric')"
                            . "->step(1)";

                break;
            default:
                $component = "TextInput::make('{$fieldName}')";

                break;
        }

        // Adicionar validações se existirem
        if (! empty($validationRules)) {
            foreach ($validationRules as $rule => $value) {
                switch ($rule) {
                    case 'required':
                        $component .= '->required()';

                        break;
                    case 'min':
                        // Verificar se é min para números ou strings
                        if (in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double'])) {
                            $component .= "->minValue({$value})";
                        } else {
                            $component .= "->minLength({$value})";
                        }

                        break;
                    case 'max':
                        // Verificar se é max para números ou strings
                        if (in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double'])) {
                            $component .= "->maxValue({$value})";
                        } else {
                            $component .= "->maxLength({$value})";
                        }

                        break;
                    case 'email':
                        $component .= '->email()';

                        break;
                    case 'nullable':
                        $component .= '->nullable()';

                        break;
                    case 'unique':
                        $component .= '->unique(ignoreRecord: true)';

                        break;
                    case 'between':
                        if (strpos($value, ',') !== false) {
                            list($min, $max) = explode(',', $value);
                            $component .= "->minValue({$min})->maxValue({$max})";
                        }

                        break;
                    case 'url':
                        $component .= '->url()';

                        break;
                    case 'tel':
                    case 'phone':
                    case 'telephone':
                        $component .= '->tel()';

                        break;
                    case 'password':
                        $component .= '->password()';

                        break;
                    case 'confirmed':
                        $component .= '->confirmed()';

                        break;
                    case 'exists':
                        // Formato padrão: exists:table,column
                        if (strpos($value, ',') !== false) {
                            list($table, $column) = explode(',', $value);
                            $component .= "->exists('{$table}', '{$column}')";
                        }

                        break;
                }
            }
        }

        // Adicionar valor padrão se especificado
        if ($defaultValue !== null && $defaultValue !== '') {
            if (in_array($fieldType, ['string', 'text', 'textarea', 'longtext', 'email', 'color'])) {
                $component .= "->default('{$defaultValue}')";
            } elseif ($fieldType === 'boolean') {
                $defaultValue = strtolower($defaultValue) === 'true' ? 'true' : 'false';
                $component .= "->default({$defaultValue})";
            } elseif (is_numeric($defaultValue) || $defaultValue === '0') {
                $component .= "->default({$defaultValue})";
            }
        }

        return $component;
    }

    /**
     * Retorna o tipo de componente com base no tipo de campo
     */
    public function getComponentType(string $fieldType): string
    {
        switch ($fieldType) {
            case 'string':
            case 'text':
                return 'TextInput';
            case 'textarea':
            case 'longtext':
                return 'Textarea';
            case 'boolean':
                return 'Toggle';
            case 'date':
                return 'DatePicker';
            case 'datetime':
                return 'DateTimePicker';
            case 'time':
                return 'TimePicker';
            case 'select':
            case 'enum':
            case 'foreignId':
                return 'Select';
            case 'checkboxes':
                return 'CheckboxList';
            case 'radio':
                return 'Radio';
            case 'color':
                return 'ColorPicker';
            case 'file':
            case 'image':
                return 'FileUpload';
            case 'richtext':
            case 'editor':
                return 'RichEditor';
            case 'markdown':
                return 'MarkdownEditor';
            case 'tags':
                return 'TagsInput';
            default:
                return 'TextInput';
        }
    }

    /**
     * Atualiza o método form com os campos gerados
     */
    public function updateFormMethod(string $content, array $formFields, CodeValidator $validator): string
    {
        if (empty($formFields)) {
            return $content;
        }

        if (preg_match('/public\s+static\s+function\s+form\s*\(\s*Form\s+\$form\s*\)\s*:.*?\{/s', $content, $formMatches, PREG_OFFSET_CAPTURE)) {
            $formStartPos = $formMatches[0][1];
            $openBracePos = strpos($content, '{', $formStartPos);
            $closeBracePos = $validator->findMatchingCloseBrace($content, $openBracePos);

            if ($closeBracePos !== false) {
                $newFormFunction = substr($content, $formStartPos, $openBracePos - $formStartPos + 1);
                $newFormFunction .= "\n        return \$form\n            ->schema([\n";

                foreach ($formFields as $field) {
                    $newFormFunction .= "                {$field},\n";
                }

                $newFormFunction .= "            ]);\n    }";

                $content = substr_replace($content, $newFormFunction, $formStartPos, $closeBracePos - $formStartPos + 1);
            }
        }

        return $content;
    }
}
