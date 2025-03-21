<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

class CodeValidator
{
    /**
     * Valida se os parênteses, colchetes e chaves estão balanceados
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
                    return false; // Desbalanceado
                }
            }
        }

        return empty($stack); // True se tudo estiver balanceado
    }

    /**
     * Encontra a chave de fechamento correspondente
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
