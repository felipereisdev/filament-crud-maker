<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\FormComponentGenerator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ImportManager;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ResourceUpdater;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\TableComponentGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->updater = new ResourceUpdater(
        new FormComponentGenerator,
        new TableComponentGenerator,
        new ImportManager,
        new CodeValidator,
    );
});

// --- resolveResourceDir ---

it('resolves resource dir for Filament v5 Mode B (CategoryResource subdirectory)', function () {
    $base = app_path('Filament/Resources');
    $model = 'Category';
    $plural = Str::plural($model); // Categories

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$plural}/{$model}Resource");
});

it('resolves resource dir for Filament v5 Mode A (Schemas/Tables inside plural directory)', function () {
    $base = app_path('Filament/Resources');
    $model = 'Category';
    $plural = Str::plural($model);

    // Mode B does not exist
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    // Mode A: plural directory exists with Schemas
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Schemas")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$plural}");
});

it('resolves resource dir for Filament v5 Mode A with Tables directory', function () {
    $base = app_path('Filament/Resources');
    $model = 'Post';
    $plural = Str::plural($model);

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Schemas")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Tables")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$plural}");
});

it('resolves resource dir for Filament v5 Mode A with Pages directory', function () {
    $base = app_path('Filament/Resources');
    $model = 'Post';
    $plural = Str::plural($model);

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Schemas")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Tables")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Pages")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$plural}");
});

it('prioritizes Mode B over Mode A when both exist', function () {
    $base = app_path('Filament/Resources');
    $model = 'Category';
    $plural = Str::plural($model);

    // Mode B exists
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$plural}/{$model}Resource");
});

it('resolves resource dir for Filament v4 (CategoryResource at base)', function () {
    $base = app_path('Filament/Resources');
    $model = 'Category';
    $plural = Str::plural($model);

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Schemas")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Tables")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Pages")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$model}Resource")->andReturn(true);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBe("{$base}/{$model}Resource");
});

it('returns null when no resource directory matches', function () {
    $base = app_path('Filament/Resources');
    $model = 'Category';
    $plural = Str::plural($model);

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$model}Resource")->andReturn(false);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBeNull();
});

it('does not false-positive on bare plural directory without Schemas/Tables/Pages', function () {
    $base = app_path('Filament/Resources');
    $model = 'Post';
    $plural = Str::plural($model);

    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Schemas")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Tables")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/Pages")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$model}Resource")->andReturn(false);

    $result = $this->updater->resolveResourceDir($model);
    expect($result)->toBeNull();
});

// --- Table file path pluralization ---

it('resolves table file path using plural model name for regular models', function () {
    $base = app_path('Filament/Resources');
    $model = 'Post';
    $plural = Str::plural($model); // Posts

    File::shouldReceive('exists')->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(true);

    // The table path should be PostsTable.php, not PostsTable.php — just verify the plural
    expect(Str::plural('Post'))->toBe('Posts')
        ->and(Str::plural('Post').'Table.php')->toBe('PostsTable.php');
});

it('uses Str::plural for irregular plurals in table path (Category → Categories)', function () {
    expect(Str::plural('Category').'Table.php')->toBe('CategoriesTable.php');
});

it('uses Str::plural for irregular plurals in table path (Person → People)', function () {
    expect(Str::plural('Person').'Table.php')->toBe('PeopleTable.php');
});

it('uses Str::plural for table path (Attachment → Attachments)', function () {
    expect(Str::plural('Attachment').'Table.php')->toBe('AttachmentsTable.php');
});

it('uses Str::plural for table path (Transaction → Transactions)', function () {
    expect(Str::plural('Transaction').'Table.php')->toBe('TransactionsTable.php');
});
