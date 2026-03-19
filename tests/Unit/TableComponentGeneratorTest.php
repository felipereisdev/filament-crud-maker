<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\TableComponentGenerator;

beforeEach(function () {
    $this->generator = new TableComponentGenerator;
});

// --- Column type mapping ---

it('generates TextColumn for string type', function () {
    $result = $this->generator->generateColumn('name', 'string');
    expect($result)
        ->toContain("TextColumn::make('name')")
        ->toContain('->searchable()->sortable()');
});

it('generates TextColumn for text type', function () {
    $result = $this->generator->generateColumn('title', 'text');
    expect($result)
        ->toContain("TextColumn::make('title')")
        ->toContain('->searchable()->sortable()');
});

it('generates ToggleColumn for boolean type', function () {
    $result = $this->generator->generateColumn('is_active', 'boolean');
    expect($result)->toContain("ToggleColumn::make('is_active')");
});

it('generates ColorColumn for color type', function () {
    $result = $this->generator->generateColumn('hex_color', 'color');
    expect($result)->toContain("ColorColumn::make('hex_color')");
});

it('generates ImageColumn with circular for image type', function () {
    $result = $this->generator->generateColumn('avatar', 'image');
    expect($result)
        ->toContain("ImageColumn::make('avatar')")
        ->toContain('->circular()');
});

// --- Badge behavior (select, enum and tags) ---

it('generates TextColumn with badge and searchable for select type', function () {
    $result = $this->generator->generateColumn('status', 'select');
    expect($result)
        ->toContain("TextColumn::make('status')")
        ->toContain('->badge()')
        ->toContain('->searchable()->sortable()');
});

it('generates TextColumn with badge for enum type', function () {
    $result = $this->generator->generateColumn('status', 'enum');
    expect($result)
        ->toContain("TextColumn::make('status')")
        ->toContain('->badge()')
        ->not->toContain('BadgeColumn');
});

it('generates TextColumn with badge for tags type', function () {
    $result = $this->generator->generateColumn('keywords', 'tags');
    expect($result)
        ->toContain("TextColumn::make('keywords')")
        ->toContain('->badge()')
        ->not->toContain('BadgeColumn');
});

// --- Price and numeric formatting ---

it('generates money BRL for price fields', function () {
    $result = $this->generator->generateColumn('price', 'decimal');
    expect($result)->toContain("->money('BRL')");
});

it('generates money BRL for preco fields', function () {
    $result = $this->generator->generateColumn('preco', 'float');
    expect($result)->toContain("->money('BRL')");
});

it('generates money BRL for valor fields', function () {
    $result = $this->generator->generateColumn('valor_total', 'double');
    expect($result)->toContain("->money('BRL')");
});

it('generates numeric(2) for non-price decimal fields', function () {
    $result = $this->generator->generateColumn('weight', 'decimal');
    expect($result)->toContain('->numeric(2)');
});

it('generates numeric(0) for integer fields', function () {
    $result = $this->generator->generateColumn('quantity', 'integer');
    expect($result)->toContain('->numeric(0)');
});

it('generates numeric(0) for bigInteger fields', function () {
    $result = $this->generator->generateColumn('views', 'bigInteger');
    expect($result)->toContain('->numeric(0)');
});

// --- Long text limit and tooltip ---

it('generates limit and tooltip for textarea type', function () {
    $result = $this->generator->generateColumn('description', 'textarea');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for longtext type', function () {
    $result = $this->generator->generateColumn('body', 'longtext');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for markdown type', function () {
    $result = $this->generator->generateColumn('content', 'markdown');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for richtext type', function () {
    $result = $this->generator->generateColumn('content', 'richtext');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for editor type', function () {
    $result = $this->generator->generateColumn('content', 'editor');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for code type', function () {
    $result = $this->generator->generateColumn('snippet', 'code');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for json type', function () {
    $result = $this->generator->generateColumn('metadata', 'json');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates limit and tooltip for keyvalue type', function () {
    $result = $this->generator->generateColumn('settings', 'keyvalue');
    expect($result)
        ->toContain('->limit(50)')
        ->toContain('->tooltip(');
});

it('generates numeric TextColumn for slider type', function () {
    $result = $this->generator->generateColumn('volume', 'slider');
    expect($result)
        ->toContain("TextColumn::make('volume')")
        ->toContain('->numeric()');
});

it('generates numeric TextColumn for range type', function () {
    $result = $this->generator->generateColumn('age_range', 'range');
    expect($result)
        ->toContain("TextColumn::make('age_range')")
        ->toContain('->numeric()');
});

it('generates TextColumn with badge for toggleButtons type', function () {
    $result = $this->generator->generateColumn('status', 'toggleButtons');
    expect($result)
        ->toContain("TextColumn::make('status')")
        ->toContain('->badge()');
});

it('generates ToggleColumn for checkbox type', function () {
    $result = $this->generator->generateColumn('agreed', 'checkbox');
    expect($result)->toContain("ToggleColumn::make('agreed')");
});

// --- ForeignId relationship column ---

it('generates relation.name TextColumn for foreignId type', function () {
    $result = $this->generator->generateColumn('category_id', 'foreignId');
    expect($result)->toContain("TextColumn::make('category.name')");
});

// --- Searchable and sortable ---

it('applies searchable and sortable for string types', function () {
    $result = $this->generator->generateColumn('name', 'string');
    expect($result)->toContain('->searchable()->sortable()');
});

it('applies searchable and sortable for enum types', function () {
    $result = $this->generator->generateColumn('status', 'enum');
    expect($result)->toContain('->searchable()->sortable()');
});

it('applies only sortable for numeric types', function () {
    $result = $this->generator->generateColumn('amount', 'integer');
    expect($result)
        ->toContain('->sortable()')
        ->not->toContain('->searchable()');
});

it('applies only sortable for date types', function () {
    $result = $this->generator->generateColumn('created_at', 'date');
    expect($result)
        ->toContain('->sortable()')
        ->not->toContain('->searchable()');
});

it('applies only sortable for datetime types', function () {
    $result = $this->generator->generateColumn('updated_at', 'datetime');
    expect($result)
        ->toContain('->sortable()')
        ->not->toContain('->searchable()');
});

it('does not apply searchable or sortable for boolean type', function () {
    $result = $this->generator->generateColumn('is_active', 'boolean');
    expect($result)
        ->not->toContain('->searchable()')
        ->not->toContain('->sortable()');
});

// --- Date and time formatting ---

it('generates date column for date type', function () {
    $result = $this->generator->generateColumn('birth_date', 'date');
    expect($result)->toContain('->date()');
});

it('generates dateTime column for datetime type', function () {
    $result = $this->generator->generateColumn('published_at', 'datetime');
    expect($result)->toContain('->dateTime()');
});

it('generates time column for time type', function () {
    $result = $this->generator->generateColumn('start_time', 'time');
    expect($result)->toContain('->time()');
});

// --- Filter generation ---

it('generates TernaryFilter for boolean type', function () {
    $result = $this->generator->generateFilter('is_active', 'boolean');
    expect($result)->toContain("TernaryFilter::make('is_active')");
});

it('generates SelectFilter with relationship for foreignId type', function () {
    $result = $this->generator->generateFilter('user_id', 'foreignId');
    expect($result)
        ->toContain("SelectFilter::make('user_id')")
        ->toContain("->relationship('user', 'name')");
});

it('generates SelectFilter for enum type', function () {
    $result = $this->generator->generateFilter('status', 'enum');
    expect($result)->toContain("SelectFilter::make('status')");
});

it('generates SelectFilter for select type', function () {
    $result = $this->generator->generateFilter('category', 'select');
    expect($result)->toContain("SelectFilter::make('category')");
});

it('generates date range Filter for date type', function () {
    $result = $this->generator->generateFilter('created_at', 'date');
    expect($result)
        ->toContain("Filter::make('created_at')")
        ->toContain('DatePicker::make')
        ->toContain('created_at_from')
        ->toContain('created_at_until');
});

it('generates date range Filter for datetime type', function () {
    $result = $this->generator->generateFilter('published_at', 'datetime');
    expect($result)
        ->toContain("Filter::make('published_at')")
        ->toContain('DatePicker::make');
});

it('generates numeric range Filter for decimal type', function () {
    $result = $this->generator->generateFilter('amount', 'decimal');
    expect($result)
        ->toContain("Filter::make('amount')")
        ->toContain('TextInput::make')
        ->toContain('amount_from')
        ->toContain('amount_until')
        ->toContain('->numeric()');
});

it('generates numeric range Filter for integer type', function () {
    $result = $this->generator->generateFilter('quantity', 'integer');
    expect($result)
        ->toContain("Filter::make('quantity')")
        ->toContain('TextInput::make');
});

// --- Text fields only generate filter for status/type/category ---

it('generates SelectFilter for string field named status', function () {
    $result = $this->generator->generateFilter('status', 'string');
    expect($result)->toContain("SelectFilter::make('status')");
});

it('generates SelectFilter for string field named type', function () {
    $result = $this->generator->generateFilter('type', 'string');
    expect($result)->toContain("SelectFilter::make('type')");
});

it('generates SelectFilter for string field named category', function () {
    $result = $this->generator->generateFilter('category', 'string');
    expect($result)->toContain("SelectFilter::make('category')");
});

it('returns null for regular string field filter', function () {
    $result = $this->generator->generateFilter('name', 'string');
    expect($result)->toBeNull();
});

it('returns null for regular text field filter', function () {
    $result = $this->generator->generateFilter('description', 'text');
    expect($result)->toBeNull();
});

it('returns null for unknown type filter', function () {
    $result = $this->generator->generateFilter('field', 'unknown');
    expect($result)->toBeNull();
});

it('generates TernaryFilter for checkbox type', function () {
    $result = $this->generator->generateFilter('agreed', 'checkbox');
    expect($result)->toContain("TernaryFilter::make('agreed')");
});

it('generates SelectFilter for toggleButtons type', function () {
    $result = $this->generator->generateFilter('status', 'toggleButtons');
    expect($result)->toContain("SelectFilter::make('status')");
});

it('generates numeric range Filter for slider type', function () {
    $result = $this->generator->generateFilter('volume', 'slider');
    expect($result)
        ->toContain("Filter::make('volume')")
        ->toContain('TextInput::make');
});

it('generates numeric range Filter for range type', function () {
    $result = $this->generator->generateFilter('age_range', 'range');
    expect($result)
        ->toContain("Filter::make('age_range')")
        ->toContain('TextInput::make');
});

it('returns null for code type filter', function () {
    $result = $this->generator->generateFilter('snippet', 'code');
    expect($result)->toBeNull();
});

it('returns null for json type filter', function () {
    $result = $this->generator->generateFilter('metadata', 'json');
    expect($result)->toBeNull();
});

it('returns null for keyvalue type filter', function () {
    $result = $this->generator->generateFilter('settings', 'keyvalue');
    expect($result)->toBeNull();
});

// --- getComponentType() for columns ---

it('returns correct column component type', function (string $fieldType, string $expectedComponent) {
    expect($this->generator->getComponentType($fieldType, 'column'))->toBe($expectedComponent);
})->with([
    ['boolean', 'ToggleColumn'],
    ['image', 'ImageColumn'],
    ['color', 'ColorColumn'],
    ['icon', 'IconColumn'],
    ['enum', 'TextColumn'],
    ['tags', 'TextColumn'],
    ['string', 'TextColumn'],
    ['integer', 'TextColumn'],
    ['date', 'TextColumn'],
    ['checkbox', 'ToggleColumn'],
    ['code', 'TextColumn'],
    ['json', 'TextColumn'],
    ['keyvalue', 'TextColumn'],
    ['slider', 'TextColumn'],
    ['range', 'TextColumn'],
    ['toggleButtons', 'TextColumn'],
]);

it('does not return BadgeColumn for any field type', function () {
    $fieldTypes = ['string', 'text', 'boolean', 'enum', 'tags', 'date', 'integer', 'decimal', 'color', 'image'];
    foreach ($fieldTypes as $type) {
        expect($this->generator->getComponentType($type, 'column'))->not->toBe('BadgeColumn');
    }
});

// --- getComponentType() for filters ---

it('returns correct filter component type', function (string $fieldType, string $expectedComponent) {
    expect($this->generator->getComponentType($fieldType, 'filter'))->toBe($expectedComponent);
})->with([
    ['boolean', 'TernaryFilter'],
    ['select', 'SelectFilter'],
    ['enum', 'SelectFilter'],
    ['foreignId', 'SelectFilter'],
    ['date', 'Filter'],
    ['datetime', 'Filter'],
    ['integer', 'Filter'],
    ['decimal', 'Filter'],
    ['slider', 'Filter'],
    ['range', 'Filter'],
    ['checkbox', 'TernaryFilter'],
    ['toggleButtons', 'SelectFilter'],
    ['code', ''],
    ['json', ''],
    ['keyvalue', ''],
    ['string', ''],
]);

// --- updateTableMethod() ---

it('generates recordActions and toolbarActions in table method', function () {
    $content = <<<'PHP'
<?php

class ProductResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }
}
PHP;

    $columns = ["TextColumn::make('name')"];
    $filters = [];
    $validator = new CodeValidator;

    $result = $this->generator->updateTableMethod($content, $columns, $filters, $validator);

    expect($result)
        ->toContain('->columns([')
        ->toContain("TextColumn::make('name')")
        ->toContain('->recordActions([')
        ->toContain('EditAction::make()')
        ->toContain('->toolbarActions([')
        ->toContain('BulkActionGroup::make([')
        ->toContain('DeleteBulkAction::make()');
});

it('includes filters when provided', function () {
    $content = <<<'PHP'
<?php

class ProductResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }
}
PHP;

    $columns = ["TextColumn::make('name')"];
    $filters = ["TernaryFilter::make('is_active')"];
    $validator = new CodeValidator;

    $result = $this->generator->updateTableMethod($content, $columns, $filters, $validator);

    expect($result)
        ->toContain('->filters([')
        ->toContain("TernaryFilter::make('is_active')");
});

it('supports configure method with Table signature', function () {
    $content = <<<'PHP'
<?php

class ProductsTable
{
    public function configure(Table $table): Table
    {
        return $table->columns([]);
    }
}
PHP;

    $columns = ["TextColumn::make('name')"];
    $validator = new CodeValidator;

    $result = $this->generator->updateTableMethod($content, $columns, [], $validator);

    expect($result)
        ->toContain('->columns([')
        ->toContain("TextColumn::make('name')");
});

it('returns content unchanged when columns and filters are empty', function () {
    $content = '<?php class Test { public static function table(Table $table): Table { return $table->columns([]); } }';
    $validator = new CodeValidator;

    $result = $this->generator->updateTableMethod($content, [], [], $validator);
    expect($result)->toBe($content);
});
