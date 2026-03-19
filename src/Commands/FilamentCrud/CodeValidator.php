<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class CodeValidator
{
    /**
     * Validates whether parentheses, brackets and braces are balanced
     */
    public function validateSyntax(string $code): bool
    {
        $openSymbols = ['{' => 0, '(' => 0, '[' => 0];
        $closeSymbols = ['}' => '{', ')' => '(', ']' => '['];
        $stack = [];

        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];

            if (isset($openSymbols[$char])) {
                $stack[] = $char;
            } elseif (isset($closeSymbols[$char])) {
                if (empty($stack) || array_pop($stack) !== $closeSymbols[$char]) {
                    return false; // Unbalanced
                }
            }
        }

        return empty($stack); // True if everything is balanced
    }

    /**
     * Finds the matching closing brace
     */
    public function findMatchingCloseBrace(string $content, int $openBracePos): int|false
    {
        $length = strlen($content);
        $depth = 1;

        for ($i = $openBracePos + 1; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }
}
