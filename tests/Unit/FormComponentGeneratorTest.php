<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;
use Freis\FilamentCrudGenerator\Commands\FilamentCrud\FormComponentGenerator;

beforeEach(function () {
    $this->generator = new FormComponentGenerator();
});

// --- Field type → component mapping ---

it('generates TextInput for string type', function () {
    $result = $this->generator->generate('name', 'string');
    expect($result)->toBe("TextInput::make('name')");
});

it('generates TextInput for text type', function () {
    $result = $this->generator->generate('title', 'text');
    expect($result)->toBe("TextInput::make('title')");
});

it('generates Textarea for textarea type', function () {
    $result = $this->generator->generate('description', 'textarea');
    expect($result)->toBe("Textarea::make('description')");
});

it('generates Textarea for longtext type', function () {
    $result = $this->generator->generate('body', 'longtext');
    expect($result)->toBe("Textarea::make('body')");
});

it('generates Toggle for boolean type', function () {
    $result = $this->generator->generate('is_active', 'boolean');
    expect($result)->toBe("Toggle::make('is_active')");
});

it('generates DatePicker for date type', function () {
    $result = $this->generator->generate('birth_date', 'date');
    expect($result)->toBe("DatePicker::make('birth_date')");
});

it('generates DateTimePicker for datetime type', function () {
    $result = $this->generator->generate('published_at', 'datetime');
    expect($result)->toBe("DateTimePicker::make('published_at')");
});

it('generates TimePicker for time type', function () {
    $result = $this->generator->generate('start_time', 'time');
    expect($result)->toBe("TimePicker::make('start_time')");
});

it('generates Select for select type', function () {
    $result = $this->generator->generate('status', 'select');
    expect($result)->toBe("Select::make('status')");
});

it('generates Select for enum type', function () {
    $result = $this->generator->generate('role', 'enum');
    expect($result)->toBe("Select::make('role')");
});

it('generates Select with relationship for foreignId type', function () {
    $result = $this->generator->generate('category_id', 'foreignId');
    expect($result)->toBe("Select::make('category_id')->relationship('category', 'name')");
});

it('generates CheckboxList for checkboxes type', function () {
    $result = $this->generator->generate('permissions', 'checkboxes');
    expect($result)->toBe("CheckboxList::make('permissions')");
});

it('generates Radio for radio type', function () {
    $result = $this->generator->generate('gender', 'radio');
    expect($result)->toBe("Radio::make('gender')");
});

it('generates ColorPicker for color type', function () {
    $result = $this->generator->generate('hex_color', 'color');
    expect($result)->toBe("ColorPicker::make('hex_color')");
});

it('generates FileUpload for file type', function () {
    $result = $this->generator->generate('document', 'file');
    expect($result)->toBe("FileUpload::make('document')");
});

it('generates FileUpload with image options for image type', function () {
    $result = $this->generator->generate('avatar', 'image');
    expect($result)->toBe("FileUpload::make('avatar')->image()->imageResizeMode('cover')->imageCropAspectRatio('16:9')");
});

it('generates RichEditor for richtext type', function () {
    $result = $this->generator->generate('content', 'richtext');
    expect($result)->toBe("RichEditor::make('content')");
});

it('generates RichEditor for editor type', function () {
    $result = $this->generator->generate('content', 'editor');
    expect($result)->toBe("RichEditor::make('content')");
});

it('generates MarkdownEditor for markdown type', function () {
    $result = $this->generator->generate('readme', 'markdown');
    expect($result)->toBe("MarkdownEditor::make('readme')");
});

it('generates TagsInput for tags type', function () {
    $result = $this->generator->generate('keywords', 'tags');
    expect($result)->toBe("TagsInput::make('keywords')");
});

it('generates numeric TextInput for decimal type', function () {
    $result = $this->generator->generate('amount', 'decimal');
    expect($result)->toBe("TextInput::make('amount')->numeric()->inputMode('decimal')");
});

it('generates numeric TextInput for float type', function () {
    $result = $this->generator->generate('weight', 'float');
    expect($result)->toBe("TextInput::make('weight')->numeric()->inputMode('decimal')");
});

it('generates numeric TextInput for double type', function () {
    $result = $this->generator->generate('latitude', 'double');
    expect($result)->toBe("TextInput::make('latitude')->numeric()->inputMode('decimal')");
});

it('generates integer TextInput for integer type', function () {
    $result = $this->generator->generate('quantity', 'integer');
    expect($result)->toBe("TextInput::make('quantity')->numeric()->inputMode('numeric')->step(1)");
});

it('generates integer TextInput for bigInteger type', function () {
    $result = $this->generator->generate('views', 'bigInteger');
    expect($result)->toBe("TextInput::make('views')->numeric()->inputMode('numeric')->step(1)");
});

it('generates TextInput for unknown type', function () {
    $result = $this->generator->generate('field', 'unknown_type');
    expect($result)->toBe("TextInput::make('field')");
});

// --- Validation rules ---

it('applies required validation', function () {
    $result = $this->generator->generate('name', 'string', ['required' => true]);
    expect($result)->toContain('->required()');
});

it('applies min validation as minLength for strings', function () {
    $result = $this->generator->generate('name', 'string', ['min' => 3]);
    expect($result)->toContain('->minLength(3)');
});

it('applies min validation as minValue for numeric types', function () {
    $result = $this->generator->generate('age', 'integer', ['min' => 0]);
    expect($result)->toContain('->minValue(0)');
});

it('applies max validation as maxLength for strings', function () {
    $result = $this->generator->generate('name', 'string', ['max' => 255]);
    expect($result)->toContain('->maxLength(255)');
});

it('applies max validation as maxValue for numeric types', function () {
    $result = $this->generator->generate('price', 'decimal', ['max' => 9999]);
    expect($result)->toContain('->maxValue(9999)');
});

it('applies email validation', function () {
    $result = $this->generator->generate('email', 'string', ['email' => true]);
    expect($result)->toContain('->email()');
});

it('applies nullable validation', function () {
    $result = $this->generator->generate('bio', 'string', ['nullable' => true]);
    expect($result)->toContain('->nullable()');
});

it('applies unique validation without ignoreRecord', function () {
    $result = $this->generator->generate('slug', 'string', ['unique' => true]);
    expect($result)->toContain('->unique()')
        ->not->toContain('ignoreRecord');
});

it('applies between validation', function () {
    $result = $this->generator->generate('rating', 'integer', ['between' => '1,5']);
    expect($result)->toContain('->minValue(1)->maxValue(5)');
});

it('applies url validation', function () {
    $result = $this->generator->generate('website', 'string', ['url' => true]);
    expect($result)->toContain('->url()');
});

it('applies tel validation', function () {
    $result = $this->generator->generate('phone', 'string', ['tel' => true]);
    expect($result)->toContain('->tel()');
});

it('applies password validation', function () {
    $result = $this->generator->generate('password', 'string', ['password' => true]);
    expect($result)->toContain('->password()');
});

it('applies confirmed validation', function () {
    $result = $this->generator->generate('password', 'string', ['confirmed' => true]);
    expect($result)->toContain('->confirmed()');
});

it('applies exists validation', function () {
    $result = $this->generator->generate('category_id', 'string', ['exists' => 'categories,id']);
    expect($result)->toContain("->exists('categories', 'id')");
});

// --- Default values ---

it('applies string default value', function () {
    $result = $this->generator->generate('status', 'string', [], 'active');
    expect($result)->toContain("->default('active')");
});

it('applies boolean true default value', function () {
    $result = $this->generator->generate('is_active', 'boolean', [], 'true');
    expect($result)->toContain('->default(true)');
});

it('applies boolean false default value', function () {
    $result = $this->generator->generate('is_active', 'boolean', [], 'false');
    expect($result)->toContain('->default(false)');
});

it('applies numeric default value', function () {
    $result = $this->generator->generate('quantity', 'integer', [], '10');
    expect($result)->toContain('->default(10)');
});

// --- getComponentType() ---

it('returns correct component type for each field type', function (string $fieldType, string $expectedComponent) {
    expect($this->generator->getComponentType($fieldType))->toBe($expectedComponent);
})->with([
    ['string', 'TextInput'],
    ['text', 'TextInput'],
    ['textarea', 'Textarea'],
    ['longtext', 'Textarea'],
    ['boolean', 'Toggle'],
    ['date', 'DatePicker'],
    ['datetime', 'DateTimePicker'],
    ['time', 'TimePicker'],
    ['select', 'Select'],
    ['enum', 'Select'],
    ['foreignId', 'Select'],
    ['checkboxes', 'CheckboxList'],
    ['radio', 'Radio'],
    ['color', 'ColorPicker'],
    ['file', 'FileUpload'],
    ['image', 'FileUpload'],
    ['richtext', 'RichEditor'],
    ['editor', 'RichEditor'],
    ['markdown', 'MarkdownEditor'],
    ['tags', 'TagsInput'],
    ['unknown', 'TextInput'],
]);

// --- updateFormMethod() ---

it('inserts fields into form method with Form signature', function () {
    $content = <<<'PHP'
<?php

class ProductResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }
}
PHP;

    $fields = [
        "TextInput::make('name')->required()",
        "Toggle::make('is_active')",
    ];

    $validator = new CodeValidator();
    $result = $this->generator->updateFormMethod($content, $fields, $validator);

    expect($result)
        ->toContain('$schema')
        ->toContain('->components([')
        ->toContain("TextInput::make('name')->required()")
        ->toContain("Toggle::make('is_active')");
});

it('inserts fields into configure method with Schema signature', function () {
    $content = <<<'PHP'
<?php

class ProductForm
{
    public function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
PHP;

    $fields = [
        "TextInput::make('name')",
    ];

    $validator = new CodeValidator();
    $result = $this->generator->updateFormMethod($content, $fields, $validator);

    expect($result)
        ->toContain('->components([')
        ->toContain("TextInput::make('name')");
});

it('returns content unchanged when form fields are empty', function () {
    $content = '<?php class Test { public static function form(Form $form): Form { return $form->schema([]); } }';
    $validator = new CodeValidator();

    $result = $this->generator->updateFormMethod($content, [], $validator);
    expect($result)->toBe($content);
});
