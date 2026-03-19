<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class FormComponentGenerator
{
    /**
     * Generates a form component based on the field type
     *
     * @param array<string, string> $validationRules
     */
    public function generate(string $fieldName, string $fieldType, array $validationRules = [], ?string $defaultValue = null): string
    {
        $component = match ($fieldType) {
            'string', 'text' => "TextInput::make('{$fieldName}')",
            'textarea', 'longtext' => "Textarea::make('{$fieldName}')",
            'boolean' => "Toggle::make('{$fieldName}')",
            'date' => "DatePicker::make('{$fieldName}')",
            'datetime' => "DateTimePicker::make('{$fieldName}')",
            'time' => "TimePicker::make('{$fieldName}')",
            'select', 'enum' => "Select::make('{$fieldName}')",
            'foreignId' => "Select::make('{$fieldName}')"
                . (str_replace('_id', '', $fieldName) !== $fieldName
                    ? "->relationship('" . str_replace('_id', '', $fieldName) . "', 'name')"
                    : ''),
            'checkboxes' => "CheckboxList::make('{$fieldName}')",
            'radio' => "Radio::make('{$fieldName}')",
            'color' => "ColorPicker::make('{$fieldName}')",
            'file' => "FileUpload::make('{$fieldName}')",
            'image' => "FileUpload::make('{$fieldName}')"
                . "->image()"
                . "->imageResizeMode('cover')"
                . "->imageCropAspectRatio('16:9')",
            'richtext', 'editor' => "RichEditor::make('{$fieldName}')",
            'markdown' => "MarkdownEditor::make('{$fieldName}')",
            'tags' => "TagsInput::make('{$fieldName}')",
            'code', 'json' => "CodeEditor::make('{$fieldName}')",
            'slider', 'range' => "Slider::make('{$fieldName}')",
            'toggleButtons' => "ToggleButtons::make('{$fieldName}')",
            'keyvalue' => "KeyValue::make('{$fieldName}')",
            'checkbox' => "Checkbox::make('{$fieldName}')",
            'decimal', 'float', 'double' => "TextInput::make('{$fieldName}')"
                . "->numeric()"
                . "->inputMode('decimal')",
            'integer', 'bigInteger' => "TextInput::make('{$fieldName}')"
                . "->numeric()"
                . "->inputMode('numeric')"
                . "->step(1)",
            default => "TextInput::make('{$fieldName}')",
        };

        // Add validations if any exist
        if (! empty($validationRules)) {
            foreach ($validationRules as $rule => $value) {
                $component .= match ($rule) {
                    'required' => '->required()',
                    'min' => in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double'])
                        ? "->minValue({$value})"
                        : "->minLength({$value})",
                    'max' => in_array($fieldType, ['integer', 'bigInteger', 'decimal', 'float', 'double'])
                        ? "->maxValue({$value})"
                        : "->maxLength({$value})",
                    'email' => '->email()',
                    'nullable' => '->nullable()',
                    'unique' => '->unique()',
                    'between' => str_contains($value, ',')
                        ? "->minValue(" . explode(',', $value)[0] . ")->maxValue(" . explode(',', $value)[1] . ")"
                        : '',
                    'url' => '->url()',
                    'tel', 'phone', 'telephone' => '->tel()',
                    'password' => '->password()',
                    'confirmed' => '->confirmed()',
                    'exists' => str_contains($value, ',')
                        ? "->exists('" . explode(',', $value)[0] . "', '" . explode(',', $value)[1] . "')"
                        : '',
                    default => '',
                };
            }
        }

        // Add default value if specified
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
     * Returns the component type based on the field type
     */
    public function getComponentType(string $fieldType): string
    {
        return match ($fieldType) {
            'string', 'text' => 'TextInput',
            'textarea', 'longtext' => 'Textarea',
            'boolean' => 'Toggle',
            'date' => 'DatePicker',
            'datetime' => 'DateTimePicker',
            'time' => 'TimePicker',
            'select', 'enum', 'foreignId' => 'Select',
            'checkboxes' => 'CheckboxList',
            'radio' => 'Radio',
            'color' => 'ColorPicker',
            'file', 'image' => 'FileUpload',
            'richtext', 'editor' => 'RichEditor',
            'markdown' => 'MarkdownEditor',
            'tags' => 'TagsInput',
            'code', 'json' => 'CodeEditor',
            'slider', 'range' => 'Slider',
            'toggleButtons' => 'ToggleButtons',
            'keyvalue' => 'KeyValue',
            'checkbox' => 'Checkbox',
            default => 'TextInput',
        };
    }

    /**
     * Updates the form method with the generated fields
     *
     * @param array<int, string> $formFields
     */
    public function updateFormMethod(string $content, array $formFields, CodeValidator $validator): string
    {
        if (empty($formFields)) {
            return $content;
        }

        if (preg_match('/public\s+(?:static\s+)?function\s+(?:form\s*\(\s*Form\s+\$form\s*\)|configure\s*\(\s*Schema\s+\$schema\s*\))\s*:.*?\{/s', $content, $formMatches, PREG_OFFSET_CAPTURE)) {
            $formStartPos = $formMatches[0][1];
            $openBracePos = strpos($content, '{', $formStartPos);
            if ($openBracePos === false) {
                return $content;
            }
            $closeBracePos = $validator->findMatchingCloseBrace($content, $openBracePos);

            if ($closeBracePos !== false) {
                $newFormFunction = substr($content, $formStartPos, $openBracePos - $formStartPos + 1);
                $newFormFunction .= "\n        return \$schema\n            ->components([\n";

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
