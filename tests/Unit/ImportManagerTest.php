<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ImportManager;

beforeEach(function () {
    $this->manager = new ImportManager;
});

// --- removeDuplicateImports() ---

it('removes duplicate imports keeping first occurrence', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->removeDuplicateImports($content);

    expect(substr_count($result, 'use Filament\Forms\Components\TextInput;'))->toBe(1)
        ->and(substr_count($result, 'use Filament\Tables\Columns\TextColumn;'))->toBe(1);
});

it('preserves non-duplicate imports', function () {
    $content = <<<'PHP'
<?php

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
PHP;

    $result = $this->manager->removeDuplicateImports($content);

    expect($result)
        ->toContain('use Filament\Forms\Components\TextInput;')
        ->toContain('use Filament\Forms\Components\Toggle;')
        ->toContain('use Filament\Tables\Columns\TextColumn;');
});

it('preserves non-import lines when removing duplicates', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->removeDuplicateImports($content);

    expect($result)
        ->toContain('namespace App\Filament\Resources;')
        ->toContain('class ProductResource extends Resource');
});

// --- addRequiredImports() ---

it('adds imports based on used components', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', ['TextInput', 'TextColumn'], false);

    expect($result)
        ->toContain('use Filament\Forms\Components\TextInput;')
        ->toContain('use Filament\Tables\Columns\TextColumn;');
});

it('adds Schema import instead of Form import', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', ['TextInput'], false);

    expect($result)
        ->toContain('use Filament\Schemas\Schema;')
        ->not->toContain('use Filament\Forms\Form;');
});

it('always adds action imports', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', [], false);

    expect($result)
        ->toContain('use Filament\Actions\EditAction;')
        ->toContain('use Filament\Actions\BulkActionGroup;')
        ->toContain('use Filament\Actions\DeleteBulkAction;');
});

it('adds SoftDeletes imports when softDeletes is true', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', [], true);

    expect($result)
        ->toContain('use Illuminate\Database\Eloquent\SoftDeletingScope;')
        ->toContain('use Filament\Tables\Filters\TrashedFilter;');
});

it('injects imports into Filament v5 sub-namespace resource', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\Products;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', ['TextInput'], false);

    // Imports should be injected (namespace matches via sub-namespace regex)
    expect($result)
        ->toContain('use Filament\Forms\Components\TextInput;');
});

it('uses actual sub-namespace for Pages and RelationManagers imports in v5', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\Categories;

use Filament\Resources\Resource;

class CategoryResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Category', [], false);

    expect($result)
        ->toContain('use App\Filament\Resources\Categories\CategoryResource\Pages;')
        ->toContain('use App\Filament\Resources\Categories\CategoryResource\RelationManagers;');
});

it('does not add SoftDeletes imports when softDeletes is false', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', [], false);

    expect($result)
        ->not->toContain('use Illuminate\Database\Eloquent\SoftDeletingScope;')
        ->not->toContain('use Filament\Tables\Filters\TrashedFilter;');
});

it('detects Builder usage and adds import', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

class ProductResource extends Resource
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
PHP;

    $result = $this->manager->addRequiredImports($content, 'Product', [], false);

    expect($result)->toContain('use Illuminate\Database\Eloquent\Builder;');
});

// --- IMPORT_MAP contents ---

it('does not contain BadgeColumn in import map', function () {
    $reflection = new ReflectionClass(ImportManager::class);
    $importMap = $reflection->getConstant('IMPORT_MAP');

    expect($importMap)->not->toHaveKey('BadgeColumn');
});

it('contains EditAction in import map', function () {
    $reflection = new ReflectionClass(ImportManager::class);
    $importMap = $reflection->getConstant('IMPORT_MAP');

    expect($importMap)
        ->toHaveKey('EditAction')
        ->and($importMap['EditAction'])->toBe('Filament\Actions\EditAction');
});

it('contains BulkActionGroup in import map', function () {
    $reflection = new ReflectionClass(ImportManager::class);
    $importMap = $reflection->getConstant('IMPORT_MAP');

    expect($importMap)
        ->toHaveKey('BulkActionGroup')
        ->and($importMap['BulkActionGroup'])->toBe('Filament\Actions\BulkActionGroup');
});

it('contains DeleteBulkAction in import map', function () {
    $reflection = new ReflectionClass(ImportManager::class);
    $importMap = $reflection->getConstant('IMPORT_MAP');

    expect($importMap)
        ->toHaveKey('DeleteBulkAction')
        ->and($importMap['DeleteBulkAction'])->toBe('Filament\Actions\DeleteBulkAction');
});

it('contains new form components in import map', function (string $component, string $expectedPath) {
    $reflection = new ReflectionClass(ImportManager::class);
    $importMap = $reflection->getConstant('IMPORT_MAP');

    expect($importMap)
        ->toHaveKey($component)
        ->and($importMap[$component])->toBe($expectedPath);
})->with([
    ['CodeEditor', 'Filament\Forms\Components\CodeEditor'],
    ['Slider', 'Filament\Forms\Components\Slider'],
    ['ToggleButtons', 'Filament\Forms\Components\ToggleButtons'],
    ['KeyValue', 'Filament\Forms\Components\KeyValue'],
    ['Checkbox', 'Filament\Forms\Components\Checkbox'],
]);

// --- addFormFileImports() ---

it('adds Schema import in form file', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Schemas;

class ProductForm
{
}
PHP;

    $result = $this->manager->addFormFileImports($content, []);

    expect($result)->toContain('use Filament\Schemas\Schema;');
});

it('adds form component imports in form file', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Schemas;

class ProductForm
{
}
PHP;

    $result = $this->manager->addFormFileImports($content, ['TextInput', 'Toggle']);

    expect($result)
        ->toContain('use Filament\Forms\Components\TextInput;')
        ->toContain('use Filament\Forms\Components\Toggle;');
});

it('excludes non-form imports from form file', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Schemas;

class ProductForm
{
}
PHP;

    $result = $this->manager->addFormFileImports($content, ['TextColumn', 'TextInput']);

    expect($result)
        ->toContain('use Filament\Forms\Components\TextInput;')
        ->not->toContain('use Filament\Tables\Columns\TextColumn;');
});

// --- addTableFileImports() ---

it('adds Table import in table file', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Tables;

class ProductsTable
{
}
PHP;

    $result = $this->manager->addTableFileImports($content, [], false);

    expect($result)->toContain('use Filament\Tables\Table;');
});

it('adds action imports in table file', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Tables;

class ProductsTable
{
}
PHP;

    $result = $this->manager->addTableFileImports($content, [], false);

    expect($result)
        ->toContain('use Filament\Actions\EditAction;')
        ->toContain('use Filament\Actions\BulkActionGroup;')
        ->toContain('use Filament\Actions\DeleteBulkAction;');
});

it('adds SoftDeletes imports in table file when enabled', function () {
    $content = <<<'PHP'
<?php

namespace App\Filament\Resources\ProductResource\Tables;

class ProductsTable
{
}
PHP;

    $result = $this->manager->addTableFileImports($content, [], true);

    expect($result)
        ->toContain('use Illuminate\Database\Eloquent\SoftDeletingScope;')
        ->toContain('use Filament\Tables\Filters\TrashedFilter;');
});
