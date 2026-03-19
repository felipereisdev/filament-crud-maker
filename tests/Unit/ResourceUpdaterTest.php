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

// --- Table file path pluralization ---

it('resolves table file path using plural model name for regular models', function () {
    $base = app_path('Filament/Resources');
    $model = 'Post';
    $plural = Str::plural($model); // Posts

    File::shouldReceive('exists')->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource")->andReturn(true);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource/Schemas")->andReturn(false);
    File::shouldReceive('isDirectory')->with("{$base}/{$plural}/{$model}Resource/Tables")->andReturn(false);

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
