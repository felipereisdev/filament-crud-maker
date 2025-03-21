<?php

declare(strict_types=1);

namespace Freis\FilamentCrudGenerator\Commands;

use Illuminate\Console\Command;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeFormatter;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CrudGenerator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\FormComponentGenerator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ImportManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\MigrationManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ModelManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ResourceUpdater;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\TableComponentGenerator;

class MakeFilamentCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-crud 
                            {model? : O nome do modelo para gerar o CRUD} 
                            {--fields= : Lista de campos separados por vírgula (nome:tipo:default)}
                            {--relations= : Lista de relações separadas por ponto-e-vírgula (tipo:modelo)}
                            {--softDeletes : Adicionar soft deletes ao modelo}
                            {--no-migrate : Não executar migrações automaticamente}
                            {--no-format : Não formatar o código gerado}
                            {--clean-resources : Limpar todos os recursos existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera um CRUD completo no Filament v3';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Verificar se apenas queremos limpar os recursos
        if ($this->option('clean-resources')) {
            return $this->cleanResources();
        }

        // Verificar se o modelo foi fornecido
        $model = $this->argument('model');
        if (empty($model)) {
            $this->error('Você deve fornecer um nome de modelo.');

            return 1;
        }

        $fields = $this->option('fields') ?? '';
        $relations = $this->option('relations') ?? '';
        $softDeletes = $this->option('softDeletes') ?? false;
        $skipMigrations = $this->option('no-migrate') ?? false;
        $skipCsFixer = $this->option('no-format') ?? false;

        // Informações para depuração
        $this->info('=== INFORMAÇÕES DE ENTRADA ===');
        $this->info("Modelo: {$model}");
        $this->info("Campos: {$fields}");
        $this->info("Relações: {$relations}");
        $this->info("SoftDeletes: " . ($softDeletes ? 'Sim' : 'Não'));
        $this->info("Pular migrações: " . ($skipMigrations ? 'Sim' : 'Não'));
        $this->info("Pular formatação: " . ($skipCsFixer ? 'Sim' : 'Não'));
        $this->info('============================');

        // Inicializar as classes necessárias
        $codeValidator = new CodeValidator();
        $importManager = new ImportManager();
        $formGenerator = new FormComponentGenerator();
        $tableGenerator = new TableComponentGenerator();
        $resourceUpdater = new ResourceUpdater(
            $formGenerator,
            $tableGenerator,
            $importManager,
            $codeValidator,
            $this
        );
        $codeFormatter = new CodeFormatter($this);
        $migrationManager = new MigrationManager($this);
        $modelManager = new ModelManager($this);

        // Inicializar o gerador de CRUD
        $crudGenerator = new CrudGenerator(
            $modelManager,
            $migrationManager,
            $resourceUpdater,
            $codeFormatter,
            $this
        );

        // Gerar o CRUD
        $result = $crudGenerator->generate(
            $model,
            $fields,
            $relations,
            $softDeletes,
            $skipMigrations,
            $skipCsFixer
        );

        return $result ? 0 : 1;
    }

    /**
     * Limpa os recursos existentes
     */
    private function cleanResources(): int
    {
        $this->info('Iniciando limpeza de todos os recursos Filament...');

        // Inicializar as classes necessárias
        $codeValidator = new CodeValidator();
        $importManager = new ImportManager();
        $formGenerator = new FormComponentGenerator();
        $tableGenerator = new TableComponentGenerator();
        $resourceUpdater = new ResourceUpdater(
            $formGenerator,
            $tableGenerator,
            $importManager,
            $codeValidator,
            $this
        );
        $codeFormatter = new CodeFormatter($this);

        // Inicializar o gerador de CRUD com as classes mínimas necessárias
        $crudGenerator = new CrudGenerator(
            new ModelManager($this),
            new MigrationManager($this),
            $resourceUpdater,
            $codeFormatter,
            $this
        );

        // Limpar todos os recursos
        if ($crudGenerator->cleanAllResources()) {
            $this->info('Todos os recursos foram limpos com sucesso!');
        } else {
            $this->error('Ocorreu um erro ao limpar os recursos.');

            return 1;
        }

        return 0;
    }
} 