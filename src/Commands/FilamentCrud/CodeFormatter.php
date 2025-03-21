<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CodeFormatter
{
    private ?Command $command;

    public function __construct(?Command $command = null)
    {
        $this->command = $command;
    }

    /**
     * Formata o código usando PHP CS Fixer
     */
    public function format(): bool
    {
        $this->log('Formatando código com PHP CS Fixer...');

        // Verificar se o PHP CS Fixer está instalado
        $hasCsFixer = $this->executeCommand('which php-cs-fixer', true);
        $composerBin = base_path('vendor/bin/php-cs-fixer');

        if (empty($hasCsFixer) && ! file_exists($composerBin)) {
            $this->log('PHP CS Fixer não encontrado. Tentando instalar via Composer...', 'warn');

            // Verificar se o composer.json existe
            if (! file_exists(base_path('composer.json'))) {
                $this->log('composer.json não encontrado. Não é possível instalar o PHP CS Fixer.', 'error');

                return false;
            }

            // Instalar PHP CS Fixer
            if ($this->executeCommand('composer require friendsofphp/php-cs-fixer --dev', false)) {
                $this->log('PHP CS Fixer instalado com sucesso!');

                // Criar arquivo de configuração se não existir
                $this->createCsFixerConfig();
            } else {
                $this->log('Falha ao instalar PHP CS Fixer. Pulando formatação de código.', 'error');

                return false;
            }
        }

        // Verificar e criar arquivo de configuração se necessário
        $this->createCsFixerConfig();

        // Executar PHP CS Fixer
        $csFixerCommand = file_exists($composerBin) ? $composerBin : 'php-cs-fixer';

        try {
            if (file_exists(base_path('.php-cs-fixer.dist.php'))) {
                // Usar configuração .dist.php
                $this->log('Usando configuração .php-cs-fixer.dist.php');
                $result = $this->executeCommand("{$csFixerCommand} fix --config=.php-cs-fixer.dist.php", false);
            } elseif (file_exists(base_path('.php-cs-fixer.php'))) {
                // Usar configuração existente
                $this->log('Usando configuração existente do PHP CS Fixer');
                $result = $this->executeCommand("{$csFixerCommand} fix --config=.php-cs-fixer.php", false);
            } else {
                // Usar configuração padrão, mas especificando diretórios
                $result = $this->executeCommand("{$csFixerCommand} fix app/ --allow-risky=yes", false);
            }

            if ($result) {
                $this->log('Código formatado com sucesso!');

                return true;
            } else {
                $this->log('O PHP CS Fixer encontrou problemas. Verifique o log para detalhes.', 'warn');

                return false;
            }
        } catch (\Exception $e) {
            $this->log('Erro ao executar PHP CS Fixer: ' . $e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Cria o arquivo de configuração do PHP CS Fixer
     */
    private function createCsFixerConfig(): void
    {
        $configFileOld = base_path('.php-cs-fixer.php');
        $configFileNew = base_path('.php-cs-fixer.dist.php');
        $configFileExample = base_path('.php-cs-fixer.dist.php.example');

        // Verificar primeiro se o arquivo de exemplo existe, e se não existir, criá-lo
        if (! file_exists($configFileExample)) {
            $this->log('Criando configuração de exemplo do PHP CS Fixer...');
            $phpCsFixerConfig = <<<'PHP'
<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder($finder);
PHP;
            File::put($configFileExample, $phpCsFixerConfig);
            $this->log('Arquivo .php-cs-fixer.dist.php.example criado!');
        }

        // Verificar se o arquivo .php-cs-fixer.dist.php existe, se não, copiar do exemplo
        if (! file_exists($configFileNew)) {
            $this->log('Arquivo .php-cs-fixer.dist.php não encontrado. Criando...');

            if (file_exists($configFileExample)) {
                // Copiar do exemplo
                File::copy($configFileExample, $configFileNew);
                $this->log('Arquivo .php-cs-fixer.dist.php criado a partir do exemplo!');
            } else {
                // Criar arquivo diretamente
                File::put($configFileNew, $phpCsFixerConfig);
                $this->log('Arquivo .php-cs-fixer.dist.php criado!');
            }
        }

        // Se o arquivo antigo existir, mas o novo não, migrar para o novo formato
        if (file_exists($configFileOld) && ! file_exists($configFileNew)) {
            File::copy($configFileOld, $configFileNew);
            $this->log('Arquivo .php-cs-fixer.php migrado para .php-cs-fixer.dist.php!');
        }
    }

    /**
     * Executa um comando do sistema e retorna o resultado
     */
    private function executeCommand(string $command, bool $returnOutput = false)
    {
        $this->log("Executando: {$command}");

        if ($returnOutput) {
            return shell_exec($command);
        }

        system($command, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Log mensagens com diferentes níveis
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->command) {
            switch ($level) {
                case 'error':
                    $this->command->error($message);

                    break;
                case 'warn':
                    $this->command->warn($message);

                    break;
                case 'info':
                default:
                    $this->command->info($message);

                    break;
            }
        }
    }
}
