<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CodeFormatter
{
    public function __construct(private readonly ?Command $command = null)
    {
    }

    /**
     * Formats the code using PHP CS Fixer
     */
    public function format(): bool
    {
        $this->log('Formatting code with PHP CS Fixer...');

        // Check if PHP CS Fixer is installed
        $hasCsFixer = $this->executeCommand('which php-cs-fixer', true);
        $composerBin = base_path('vendor/bin/php-cs-fixer');

        if (empty($hasCsFixer) && ! file_exists($composerBin)) {
            $this->log('PHP CS Fixer not found. Trying to install via Composer...', 'warn');

            // Check if composer.json exists
            if (! file_exists(base_path('composer.json'))) {
                $this->log('composer.json not found. Cannot install PHP CS Fixer.', 'error');

                return false;
            }

            // Install PHP CS Fixer
            if ($this->executeCommand('composer require friendsofphp/php-cs-fixer --dev', false)) {
                $this->log('PHP CS Fixer installed successfully!');

                // Create configuration file if it does not exist
                $this->createCsFixerConfig();
            } else {
                $this->log('Failed to install PHP CS Fixer. Skipping code formatting.', 'error');

                return false;
            }
        }

        // Check and create configuration file if needed
        $this->createCsFixerConfig();

        // Run PHP CS Fixer
        $csFixerCommand = file_exists($composerBin) ? $composerBin : 'php-cs-fixer';

        try {
            if (file_exists(base_path('.php-cs-fixer.dist.php'))) {
                // Use .dist.php configuration
                $this->log('Using .php-cs-fixer.dist.php configuration');
                $result = $this->executeCommand("{$csFixerCommand} fix --config=.php-cs-fixer.dist.php", false);
            } elseif (file_exists(base_path('.php-cs-fixer.php'))) {
                // Use existing configuration
                $this->log('Using existing PHP CS Fixer configuration');
                $result = $this->executeCommand("{$csFixerCommand} fix --config=.php-cs-fixer.php", false);
            } else {
                // Use default configuration, specifying directories
                $result = $this->executeCommand("{$csFixerCommand} fix app/ --allow-risky=yes", false);
            }

            if ($result) {
                $this->log('Code formatted successfully!');

                return true;
            } else {
                $this->log('PHP CS Fixer found issues. Check the log for details.', 'warn');

                return false;
            }
        } catch (\Exception $e) {
            $this->log('Error running PHP CS Fixer: ' . $e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Creates the PHP CS Fixer configuration file
     */
    private function createCsFixerConfig(): void
    {
        $configFileOld = base_path('.php-cs-fixer.php');
        $configFileNew = base_path('.php-cs-fixer.dist.php');
        $configFileExample = base_path('.php-cs-fixer.dist.php.example');

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

        // First check if the example file exists, and create it if not
        if (! file_exists($configFileExample)) {
            $this->log('Creating PHP CS Fixer example configuration...');
            File::put($configFileExample, $phpCsFixerConfig);
            $this->log('.php-cs-fixer.dist.php.example file created!');
        }

        // Check if the .php-cs-fixer.dist.php file exists, if not, copy from example
        if (! file_exists($configFileNew)) {
            $this->log('.php-cs-fixer.dist.php file not found. Creating...');

            if (file_exists($configFileExample)) {
                // Copy from example
                File::copy($configFileExample, $configFileNew);
                $this->log('.php-cs-fixer.dist.php file created from example!');
            } else {
                // Create file directly
                File::put($configFileNew, $phpCsFixerConfig);
                $this->log('.php-cs-fixer.dist.php file created!');
            }
        }

        // If the old file exists but the new one does not, migrate to the new format
        if (file_exists($configFileOld) && ! file_exists($configFileNew)) {
            File::copy($configFileOld, $configFileNew);
            $this->log('.php-cs-fixer.php migrated to .php-cs-fixer.dist.php!');
        }
    }

    /**
     * Executes a system command and returns the result
     */
    private function executeCommand(string $command, bool $returnOutput = false): string|bool|null
    {
        $this->log("Running: {$command}");

        if ($returnOutput) {
            return shell_exec($command);
        }

        system($command, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Logs messages with different levels
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->command) {
            match ($level) {
                'error' => $this->command->error($message),
                'warn' => $this->command->warn($message),
                default => $this->command->info($message),
            };
        }
    }
}
