<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\ModelManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->manager = new ModelManager;
});

// --- buildCastsArray ---

it('builds correct cast types for boolean fields', function () {
    $casts = $this->manager->buildCastsArray(['active:boolean', 'verified:checkbox']);
    expect($casts)->toBe(['active' => 'boolean', 'verified' => 'boolean']);
});

it('builds correct cast types for numeric fields', function () {
    $casts = $this->manager->buildCastsArray([
        'count:integer',
        'big:bigInteger',
        'volume:slider',
        'level:range',
        'price:decimal',
        'ratio:float',
        'score:double',
    ]);
    expect($casts)->toBe([
        'count' => 'integer',
        'big' => 'integer',
        'volume' => 'integer',
        'level' => 'integer',
        'price' => 'decimal:2',
        'ratio' => 'float',
        'score' => 'double',
    ]);
});

it('builds correct cast types for date fields', function () {
    $casts = $this->manager->buildCastsArray(['born_at:date', 'created_at:datetime']);
    expect($casts)->toBe(['born_at' => 'date', 'created_at' => 'datetime']);
});

it('builds correct cast types for json fields', function () {
    $casts = $this->manager->buildCastsArray(['data:json', 'labels:tags', 'meta:keyvalue']);
    expect($casts)->toBe(['data' => 'array', 'labels' => 'array', 'meta' => 'array']);
});

it('returns empty array for string fields', function () {
    $casts = $this->manager->buildCastsArray(['name:string', 'title:text', 'body:longtext']);
    expect($casts)->toBe([]);
});

it('returns empty array when fields array is empty', function () {
    $casts = $this->manager->buildCastsArray([]);
    expect($casts)->toBe([]);
});

it('builds string cast for enum fields', function () {
    $casts = $this->manager->buildCastsArray(['status:enum']);
    expect($casts)->toBe(['status' => 'string']);
});

it('skips fields without a colon separator', function () {
    $casts = $this->manager->buildCastsArray(['invalid', 'active:boolean']);
    expect($casts)->toBe(['active' => 'boolean']);
});

// --- $fillable merge ---

it('merges fillable fields with existing fillable array', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Post extends Model
        {
            protected $fillable = ['title'];
        }
        PHP;

    $modelPath = app_path('Models/Post.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, "'title'") && str_contains($content, "'body'");
    });

    $this->manager->updateModel('Post', ['body:string'], []);
});

// --- Polymorphic relationships ---

it('generates a morphTo method using the morph name as the method name', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Comment extends Model
        {
        }
        PHP;

    $modelPath = app_path('Models/Comment.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, 'public function commentable()')
            && str_contains($content, '$this->morphTo()');
    });

    $this->manager->updateModel('Comment', [], ['morphTo:commentable']);
});

it('generates a morphOne method with the related-model-derived morph name', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Post extends Model
        {
        }
        PHP;

    $modelPath = app_path('Models/Post.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, 'public function image()')
            && str_contains($content, '$this->morphOne(')
            && str_contains($content, "'imageable'");
    });

    $this->manager->updateModel('Post', [], ['morphOne:Image']);
});

it('generates a morphMany method with the related-model-derived morph name', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Post extends Model
        {
        }
        PHP;

    $modelPath = app_path('Models/Post.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, 'public function comments()')
            && str_contains($content, '$this->morphMany(')
            && str_contains($content, "'commentable'");
    });

    $this->manager->updateModel('Post', [], ['morphMany:Comment']);
});

it('derives morph name from related model for morphMany (Attachment on Transaction)', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Transaction extends Model
        {
        }
        PHP;

    $modelPath = app_path('Models/Transaction.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        return str_contains($content, 'public function attachments()')
            && str_contains($content, '$this->morphMany(')
            && str_contains($content, "'attachmentable'");
    });

    $this->manager->updateModel('Transaction', [], ['morphMany:Attachment']);
});

it('does not duplicate existing fillable entries', function () {
    $modelContent = <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Model;

        class Post extends Model
        {
            protected $fillable = ['title', 'body'];
        }
        PHP;

    $modelPath = app_path('Models/Post.php');
    File::shouldReceive('exists')->with($modelPath)->andReturn(true);
    File::shouldReceive('get')->with($modelPath)->andReturn($modelContent);
    File::shouldReceive('put')->once()->withArgs(function (string $path, string $content) {
        // Should appear exactly once
        return substr_count($content, "'body'") === 1;
    });

    $this->manager->updateModel('Post', ['body:string'], []);
});
