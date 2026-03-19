<?php

namespace Freis\FilamentCrudGenerator\Commands\FilamentCrud;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CodeFormatter
{
    public function __construct(private readonly ?Command $command = null) {}

    /**
     * Formats the code using Laravel Pint
     */
    public function format(): bool
    {
        $this->log('Formatting code with Laravel Pint...');

        $composerBin = base_path('vendor/bin/pint');

        if (! file_exists($composerBin)) {
            $this->log('Laravel Pint not found. Trying to install via Composer...', 'warn');

            if (! file_exists(base_path('composer.json'))) {
                $this->log('composer.json not found. Cannot install Laravel Pint.', 'error');

                return false;
            }

            if ($this->executeCommand('composer require laravel/pint --dev', false)) {
                $this->log('Laravel Pint installed successfully!');
                $this->createPintConfig();
            } else {
                $this->log('Failed to install Laravel Pint. Skipping code formatting.', 'error');

                return false;
            }
        }

        $this->createPintConfig();

        try {
            $result = $this->executeCommand("{$composerBin}", false);

            if ($result) {
                $this->log('Code formatted successfully!');

                return true;
            } else {
                $this->log('Laravel Pint found issues. Check the log for details.', 'warn');

                return false;
            }
        } catch (\Exception $e) {
            $this->log('Error running Laravel Pint: '.$e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Creates the Laravel Pint configuration file if it does not exist
     */
    private function createPintConfig(): void
    {
        $configFile = base_path('pint.json');

        if (file_exists($configFile)) {
            return;
        }

        $this->log('Creating Laravel Pint configuration...');

        /** @var array<string, mixed> $pintConfig */
        $pintConfig = [
            'preset' => 'laravel',
            'rules' => [
                'blank_line_before_statement' => [
                    'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
                ],
                'class_attributes_separation' => [
                    'elements' => ['method' => 'one'],
                ],
                'method_argument_space' => [
                    'on_multiline' => 'ensure_fully_multiline',
                    'keep_multiple_spaces_after_comma' => true,
                ],
                'phpdoc_scalar' => true,
                'phpdoc_single_line_var_spacing' => true,
                'phpdoc_var_without_name' => true,
            ],
        ];

        $json = json_encode($pintConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json !== false) {
            File::put($configFile, $json."\n");
            $this->log('pint.json file created!');
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
