<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\MigrationManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->manager = new MigrationManager;
});

// --- Field type mapping ---

it('maps string field to $table->string()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->string('title')");
    });

    $this->manager->updateMigration('Post', ['title:string']);
});

it('maps boolean field to $table->boolean()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->boolean('active')");
    });

    $this->manager->updateMigration('Post', ['active:boolean']);
});

it('maps integer field to $table->integer()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->integer('views')");
    });

    $this->manager->updateMigration('Post', ['views:integer']);
});

it('maps textarea to $table->text()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->text('body')");
    });

    $this->manager->updateMigration('Post', ['body:textarea']);
});

it('maps datetime to $table->dateTime()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->dateTime('published_at')");
    });

    $this->manager->updateMigration('Post', ['published_at:datetime']);
});

it('maps richtext to $table->longText()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->longText('content')");
    });

    $this->manager->updateMigration('Post', ['content:richtext']);
});

it('maps keyvalue to $table->json()', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->json('metadata')");
    });

    $this->manager->updateMigration('Post', ['metadata:keyvalue']);
});

// --- Modifiers ---

it('adds nullable() modifier when field is nullable', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->string('subtitle')->nullable()");
    });

    $this->manager->updateMigration('Post', ['subtitle:string:nullable']);
});

it('adds unique() modifier when field is unique', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->string('slug')->unique()");
    });

    $this->manager->updateMigration('Post', ['slug:string:unique']);
});

it('adds default value when provided', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->integer('sort_order')->default(0)");
    });

    $this->manager->updateMigration('Post', ['sort_order:integer:0']);
});

it('adds string default value for select type with string default', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->string('type')->default('expense')");
    });

    $this->manager->updateMigration('Post', ['type:select:expense:required']);
});

it('applies max=N as string column length for string type', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->string('name', 50)");
    });

    $this->manager->updateMigration('Post', ['name:string:required:max=50']);
});

it('does not apply max=N as length for non-string types', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        // Should be integer without a length argument
        return str_contains($content, "\$table->integer('count')")
            && ! str_contains($content, "\$table->integer('count', 10)");
    });

    $this->manager->updateMigration('Post', ['count:integer:max=10']);
});

// --- Relations ---

it('adds foreignId with constrained for belongsTo relations', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->foreignId('user_id')->constrained()->onDelete('cascade')");
    });

    $this->manager->updateMigration('Post', [], ['belongsTo:User']);
});

it('adds morphs() column for morphTo relations', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->morphs('commentable')");
    });

    $this->manager->updateMigration('Post', [], ['morphTo:commentable']);
});

// --- Soft deletes ---

it('adds softDeletes() column when softDeletes is true', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, '$table->softDeletes()');
    });

    $this->manager->updateMigration('Post', [], [], true);
});

it('does not duplicate softDeletes() when already present', function () {
    $migrationContent = migrationStub(withSoftDeletes: true);
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return substr_count($content, 'softDeletes') === 1;
    });

    $this->manager->updateMigration('Post', [], [], true);
});

// --- Pivot tables ---

it('generates pivot table for belongsToMany relation with alphabetical column order', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        // pivot table name must be alphabetically sorted: post_tag (not tag_post)
        return str_contains($content, "Schema::create('post_tag'")
            && str_contains($content, "\$table->foreignId('post_id')")
            && str_contains($content, "\$table->foreignId('tag_id')");
    });

    $this->manager->updateMigration('Post', [], ['belongsToMany:Tag']);
});

// --- Decimal precision ---

it('maps decimal field to $table->decimal() with explicit precision', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->decimal('amount', 10, 2)");
    });

    $this->manager->updateMigration('Post', ['amount:decimal']);
});

// --- Float precision ---

it('maps float field to $table->float() with explicit precision', function () {
    $migrationContent = migrationStub();
    $migrationFile = database_path('migrations/2024_01_01_000000_create_posts_table.php');

    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($migrationContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "\$table->float('weight', 8, 2)");
    });

    $this->manager->updateMigration('Post', ['weight:float']);
});

// --- shouldUseAlterMigration ---

it('shouldUseAlterMigration returns true when table exists in DB', function () {
    Schema::shouldReceive('hasTable')->with('transactions')->andReturn(true);

    expect($this->manager->shouldUseAlterMigration('Transaction'))->toBeTrue();
});

it('shouldUseAlterMigration returns true when migration file not found', function () {
    Schema::shouldReceive('hasTable')->with('transactions')->andReturn(false);
    File::shouldReceive('glob')->andReturn([]);

    expect($this->manager->shouldUseAlterMigration('Transaction'))->toBeTrue();
});

it('shouldUseAlterMigration returns true when migration has custom fields', function () {
    $migrationFile = database_path('migrations/2024_01_01_000000_create_transactions_table.php');
    $content = <<<'PHP'
    <?php
    return new class extends Migration {
        public function up(): void {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }
    };
    PHP;

    Schema::shouldReceive('hasTable')->with('transactions')->andReturn(false);
    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($content);

    expect($this->manager->shouldUseAlterMigration('Transaction'))->toBeTrue();
});

it('shouldUseAlterMigration returns false for bare scaffold migration', function () {
    $migrationFile = database_path('migrations/2024_01_01_000000_create_transactions_table.php');
    $content = <<<'PHP'
    <?php
    return new class extends Migration {
        public function up(): void {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
    };
    PHP;

    Schema::shouldReceive('hasTable')->with('transactions')->andReturn(false);
    File::shouldReceive('glob')->andReturn([$migrationFile]);
    File::shouldReceive('get')->with($migrationFile)->andReturn($content);

    expect($this->manager->shouldUseAlterMigration('Transaction'))->toBeFalse();
});

// --- createAlterMigration ---

it('createAlterMigration generates correct file content', function () {
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($path, '_add_budget_id_to_transactions_table.php')
            && str_contains($content, "Schema::table('transactions'")
            && str_contains($content, "\$table->foreignId('budget_id')->constrained()->onDelete('cascade')")
            && str_contains($content, "\$table->dropForeign(['budget_id'])")
            && str_contains($content, "\$table->dropColumn('budget_id')");
    });

    $result = $this->manager->createAlterMigration('Transaction', 'Budget');
    expect($result)->toBeTrue();
});

// --- Error handling ---

it('returns false when migration file is not found', function () {
    File::shouldReceive('glob')->andReturn([]);

    $result = $this->manager->updateMigration('Post', ['title:string']);

    expect($result)->toBeFalse();
});

// --- Helpers ---

function migrationStub(bool $withSoftDeletes = false): string
{
    $softDeleteLine = $withSoftDeletes ? "\n            \$table->softDeletes();" : '';

    return <<<PHP
    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('posts', function (Blueprint \$table) {
                \$table->id();{$softDeleteLine}
                \$table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('posts');
        }
    };
    PHP;
}
