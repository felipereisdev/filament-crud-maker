# Filament CRUD Maker

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel 11/12/13](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Filament v4/v5](https://img.shields.io/badge/Filament-v4%20%7C%20v5-FFAA00?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDJMMiAyMmgyMEwxMiAyeiIgZmlsbD0id2hpdGUiLz48L3N2Zz4=)](https://filamentphp.com)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-4695D4)](https://phpstan.org)
[![License: MIT + Commons Clause](https://img.shields.io/badge/License-MIT%20+%20Commons%20Clause-blue.svg)](LICENSE.md)

A Laravel package that generates **complete CRUD resources** for Filament admin panels with a single Artisan command. Define your fields, relationships, and validations — the generator creates your Model, Migration, and fully configured Filament Resource with smart form components, table columns, filters, and actions.

**Key highlights:**

- **One command, full CRUD** — Model, Migration, Resource, Schema, and Table files generated at once
- **Interactive wizard** — Run the command without `--fields` and be guided step-by-step through field and relationship setup
- **30+ field types** — From simple text inputs to rich editors, file uploads, color pickers, key-value pairs, and more
- **Smart component mapping** — Each field type maps to the most appropriate Filament form component, table column, and filter
- **Relationship support** — `belongsTo`, `belongsToMany`, `hasOne`, `hasMany`, `morphTo`, `morphOne`, and `morphMany`
- **Soft delete filter** — `TrashedFilter` is automatically added to the table when `--softDeletes` is used
- **Built-in validation** — Apply rules like `required`, `min`, `max`, `email`, `unique`, `between`, and more directly in the command
- **Configurable namespaces** — Customize model and resource namespaces via the published config file
- **Automatic code formatting** — Generated files are formatted with Laravel Pint (PSR-12)
- **Filament v4/v5 compatible** — Supports both `form(Form $form)` and `form(Schema $schema)` signatures, separate Schemas/Tables directory structure (both Mode A and Mode B), and inline resources. Schema and Table files are detected independently

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.3 |
| Laravel | ^11.28 \| ^12.0 \| ^13.0 |
| Filament | ^4.0 \| ^5.0 |

## Installation

Install the package via Composer:

```bash
composer require freis/filament-crud-maker
```

The service provider is auto-discovered. Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-crud-maker-config"
```

This publishes `config/filament-crud-maker.php` where you can customize namespaces, auto-migration, and auto-formatting behavior.

You can also publish the Laravel Pint configuration:

```bash
php artisan vendor:publish --tag="pint-config"
```

## Quick Start

Generate a complete Product CRUD with fields, validations, and a category relationship in one command:

```bash
php artisan make:filament-crud Product \
  --fields="name:string:required:min=3:max=100,description:textarea,price:decimal:required,active:boolean:false,image:image:nullable" \
  --relations="belongsTo:Category" \
  --softDeletes
```

This creates:
- `app/Models/Product.php` with fillable fields, casts, and relationships
- `database/migrations/xxxx_create_products_table.php` with all columns and foreign keys
- `app/Filament/Resources/ProductResource.php` (or separate Schema/Table files for v4)
- `app/Models/Category.php` and its migration/resource (if they don't exist)

---

## Usage

### Interactive Mode

If you omit `--fields`, the command will detect that you are in an interactive terminal and offer a step-by-step wizard:

```bash
php artisan make:filament-crud Product
```

```
No --fields provided. Use interactive wizard? (yes/no) [yes]:
--- Field Wizard (leave name empty to finish) ---
Field name (empty to finish): name
Field type [string]: string
Nullable? (yes/no) [no]: no
Field name (empty to finish): price
Field type [string]: decimal
Nullable? (yes/no) [no]: no
Field name (empty to finish):
Add relationships? (yes/no) [no]: yes
Relation type [hasOne]: belongsTo
Related model name: Category
Add another relationship? (yes/no) [no]: no
Add soft deletes? (yes/no) [no]: yes
```

The wizard collects the same information as the CLI flags and assembles it into the standard format. Non-interactive invocations (piped, CI, `--no-interaction`) are unaffected.

### Command Syntax

```
php artisan make:filament-crud {model} [options]
```

| Argument / Option | Description |
|---|---|
| `model` | Name of the model (singular, PascalCase). Example: `Product`, `BlogPost` |
| `--fields=` | Comma-separated list of fields. See [Field Format](#field-format) |
| `--relations=` | Semicolon-separated list of relationships. See [Relationships](#relationships) |
| `--softDeletes` | Add `SoftDeletes` trait and `softDeletes()` migration column |
| `--no-migrate` | Skip running migrations after generation |
| `--no-format` | Skip Laravel Pint auto-formatting |
| `--clean-resources` | Clean and regenerate all existing Filament resources |

### Field Format

Fields follow the format:

```
name:type[:default][:validation...]
```

| Segment | Required | Description |
|---|---|---|
| `name` | Yes | The database column / field name (snake_case) |
| `type` | Yes | The field type (see [Supported Field Types](#supported-field-types)) |
| `default` | No | Default value. Use `true`/`false` for booleans, numbers for numeric types, or a plain string for text/select types (e.g. `active`, `expense`). Applied to both the form component and the migration column. |
| `validation` | No | One or more validation rules. Rules with values use `=` syntax (e.g. `min=3`) |

**Examples:**

```bash
# Simple field
name:string

# Field with default value
active:boolean:false

# Field with validations
email:string:required:email:unique

# Field with default and validations
price:decimal:0:required:min=0

# Field with between validation
score:integer:required:between=0,100

# Select field with string default
type:select:expense:required

# String field with column length
name:string:required:max=100
```

---

## Supported Field Types

### Text & String

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `string` | `TextInput` | `TextColumn` (searchable, sortable) | `string` |
| `text` | `TextInput` | `TextColumn` (searchable, sortable) | `text` |
| `textarea` | `Textarea` | `TextColumn` (limit 50, tooltip) | `text` |
| `longtext` | `Textarea` | `TextColumn` (limit 50, tooltip) | `longText` |

### Numeric

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `integer` | `TextInput` (numeric, step 1) | `TextColumn` (numeric) | `integer` |
| `bigInteger` | `TextInput` (numeric, step 1) | `TextColumn` (numeric) | `bigInteger` |
| `decimal` | `TextInput` (numeric, decimal) | `TextColumn` (numeric/money*) | `decimal(10, 2)` |
| `float` | `TextInput` (numeric, decimal) | `TextColumn` (numeric) | `float(8, 2)` |
| `double` | `TextInput` (numeric, decimal) | `TextColumn` (numeric) | `double` |

> \* Fields named `price`, `preco`, or `valor` automatically use `->money('BRL')` formatting.

### Boolean

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `boolean` | `Toggle` | `ToggleColumn` | `boolean` |
| `checkbox` | `Checkbox` | `ToggleColumn` | `boolean` |

### Date & Time

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `date` | `DatePicker` | `TextColumn` (date format) | `date` |
| `datetime` | `DateTimePicker` | `TextColumn` (dateTime format) | `datetime` |
| `time` | `TimePicker` | `TextColumn` (time format) | `time` |

### Selection

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `select` | `Select` (with `->options([ /* TODO */ ])`) | `TextColumn` (badge, searchable, sortable) | `string` |
| `enum` | `Select` (with `->options([ /* TODO */ ])`) | `TextColumn` (badge) | `string` |
| `foreignId` | `Select` (with `->relationship()`) | `TextColumn` (relationship) | `foreignId` |
| `checkboxes` | `CheckboxList` (with `->options([ /* TODO */ ])`) | `TextColumn` | `json` |
| `radio` | `Radio` (with `->options([ /* TODO */ ])`) | `TextColumn` (badge, searchable, sortable) | `string` |
| `toggleButtons` | `ToggleButtons` (with `->options([ /* TODO */ ])`) | `TextColumn` (badge) | `string` |

### File & Media

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `file` | `FileUpload` (`disk: public`, `dir: uploads`) | `TextColumn` | `string` |
| `image` | `FileUpload` (image, 16:9 crop, `disk: public`, `dir: images`) | `ImageColumn` (circular) | `string` |

### Rich Content

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `richtext` / `editor` | `RichEditor` | `TextColumn` (limit 50, tooltip) | `longText` |
| `markdown` | `MarkdownEditor` | `TextColumn` (limit 50, tooltip) | `text` |

### Special

| Type | Form Component | Table Column | Migration Type |
|---|---|---|---|
| `color` | `ColorPicker` | `ColorColumn` | `string` |
| `tags` | `TagsInput` | `TextColumn` (badge) | `json` |
| `code` / `json` | `CodeEditor` | `TextColumn` (limit 50, tooltip) | `longText` / `json` |
| `slider` / `range` | `Slider` | `TextColumn` (numeric) | `integer` |
| `keyvalue` | `KeyValue` | `TextColumn` (limit 50, tooltip) | `json` |

---

## Validation Rules

Apply validation rules to fields using the `rule` or `rule=value` syntax after the field type.

| Rule | Syntax | Form Component Method | Description |
|---|---|---|---|
| Required | `required` | `->required()` | Field must be filled |
| Minimum | `min=N` | `->minLength(N)` or `->minValue(N)` | Min length (text) or min value (numeric) |
| Maximum | `max=N` | `->maxLength(N)` or `->maxValue(N)` | Max length (text) or max value (numeric). For `string` fields, also sets the column length in the migration: `$table->string('name', N)` |
| Between | `between=X,Y` | `->minValue(X)->maxValue(Y)` | Value must be between X and Y. The comma inside the value is handled correctly and won't split the field |
| Email | `email` | `->email()` | Must be a valid email address |
| URL | `url` | `->url()` | Must be a valid URL |
| Phone | `tel` | `->tel()` | Phone number input |
| Password | `password` | `->password()` | Masked password input |
| Confirmed | `confirmed` | `->confirmed()` | Requires a confirmation field |
| Nullable | `nullable` | `->nullable()` | Field can be null (also applied to migration) |
| Unique | `unique` | `->unique()` | Value must be unique (also applied to migration) |
| Exists | `exists=table,column` | `->exists('table', 'column')` | Value must exist in another table |

**Example combining multiple rules:**

```bash
--fields="email:string:required:email:unique,age:integer:required:min=18:max=120"
```

---

## Relationships

Define relationships using the `--relations` option. Multiple relationships are separated by semicolons (`;`).

### Format

```
relationType:RelatedModel[:field1:type,field2:type]
```

You can optionally specify fields for the related model. If the related model doesn't exist, it will be created automatically along with its migration and Filament resource.

### Supported Relationship Types

| Type | Description | Automatic Actions |
|---|---|---|
| `belongsTo` | Many-to-one (e.g. Product belongs to Category) | Adds `foreignId` column + `constrained()->onDelete('cascade')` to migration |
| `belongsToMany` | Many-to-many (e.g. Course has many Students) | Creates pivot table with foreign keys and unique constraint. Generates `Select::make()->multiple()->relationship()` in the form. Also generates the inverse relationship on the related model |
| `hasOne` | One-to-one (e.g. User has one Profile) | Adds relationship method to model. Automatically adds `foreignId` + `constrained()` FK column to the related model's migration (uses a separate alter migration when the target table already exists or has custom fields to avoid FK ordering issues). Alter migrations generate the FK column as **nullable** for safety with existing data. Also updates the related model's `$fillable` array and adds the inverse `belongsTo` relationship method |
| `hasMany` | One-to-many (e.g. Course has many Lessons) | Adds relationship method to model. Automatically adds `foreignId` + `constrained()` FK column to the related model's migration (uses a separate alter migration when the target table already exists or has custom fields to avoid FK ordering issues). Alter migrations generate the FK column as **nullable** for safety with existing data. Also updates the related model's `$fillable` array and adds the inverse `belongsTo` relationship method |
| `morphTo` | Polymorphic inverse (e.g. Comment belongs to Post or Video) | Adds `$table->morphs('{morphName}')` to migration; the second segment is the morph name |
| `morphOne` | Polymorphic one-to-one (e.g. Post has one Image) | Adds relationship method. Morph name can be explicit (`morphOne:Attachment:attachable`) or auto-derived (`{snake(relatedModel)}able`) |
| `morphMany` | Polymorphic one-to-many (e.g. Post has many Comments) | Adds relationship method. Morph name can be explicit (`morphMany:Attachment:attachable`) or auto-derived (`{snake(relatedModel)}able`) |

**Polymorphic examples:**

```bash
# Comment model that can belong to Post or Video
--relations="morphTo:commentable"

# Post model that morphs one Image and many Comments
--relations="morphOne:Image;morphMany:Comment"

# Attachment model with morphTo, and parent models with explicit morph name
# Child: --relations="morphTo:attachable"
# Parent: --relations="morphMany:Attachment:attachable"
```

For `morphTo`, the second segment is the **morph name** (e.g. `commentable`), not a model class. The generator adds `$table->morphs('commentable')` to the migration and `$this->morphTo()` to the model.

For `morphOne` and `morphMany`, the second segment is the **related model class**. You can optionally provide the **morph name** as a third segment to match the `morphTo` side exactly:

```bash
# Explicit morph name (recommended when the morph name differs from the auto-derived one)
--relations="morphMany:Attachment:attachable"

# Auto-derived morph name: {snake(relatedModel)}able (e.g. imageable, commentable)
--relations="morphOne:Image"
```

When using `morphTo:attachable` on the child model, make sure the parent's `morphMany` or `morphOne` uses the same morph name (`attachable`), either explicitly or by auto-derivation.

### Examples

**Single relationship:**

```bash
--relations="belongsTo:Category"
```

**Multiple relationships:**

```bash
--relations="belongsTo:Category;hasMany:Comment;belongsToMany:Tag"
```

**Relationship with fields for the related model:**

```bash
--relations="belongsTo:Category:name:string:required,slug:string:unique;hasMany:Review:rating:integer:required,comment:text"
```

> **Pivot tables** for `belongsToMany` relationships are automatically created with the correct naming convention (alphabetical order), foreign keys, and a unique constraint on the pair.

---

## Practical Examples

### 1. Blog Post with basic fields

```bash
php artisan make:filament-crud Post \
  --fields="title:string:required:min=5:max=200,slug:string:unique,content:richtext:required,excerpt:textarea:nullable,published_at:datetime:nullable,is_featured:boolean:false"
```

### 2. E-commerce Product with relationships

```bash
php artisan make:filament-crud Product \
  --fields="name:string:required:min=3,description:markdown:required,price:decimal:required:min=0,sku:string:unique,stock:integer:0:required:min=0,active:boolean:true,image:image:nullable" \
  --relations="belongsTo:Category;belongsTo:Brand;belongsToMany:Tag" \
  --softDeletes
```

### 3. Course platform with complex relations

```bash
php artisan make:filament-crud Course \
  --fields="name:string:required:min=3:unique,description:markdown:required,price:decimal:required:between=0,9999.99,duration:integer:required,color:color:nullable,published:boolean:false" \
  --relations="belongsTo:Teacher:name:string:required,email:string:email;belongsToMany:Student;hasMany:Lesson:title:string:required,content:richtext,order:integer:0"
```

### 4. Settings page with special fields

```bash
php artisan make:filament-crud Setting \
  --fields="key:string:required:unique,value:text:nullable,type:select,metadata:json:nullable,tags:tags:nullable,config:keyvalue:nullable"
```

---

## Configuration

After publishing, the config file is located at `config/filament-crud-maker.php`:

```php
return [
    // Namespace where models will be created
    // All model file paths and import statements are derived from this value
    'model_namespace' => 'App\\Models',

    // Namespace where Filament Resources will be created
    // All resource file paths and import statements are derived from this value
    'resource_namespace' => 'App\\Filament\\Resources',

    // Run migrations automatically after generation (override with --no-migrate)
    'auto_migrate' => true,

    // Format generated code with Laravel Pint (override with --no-format)
    'auto_format' => true,
];
```

Both `model_namespace` and `resource_namespace` are fully respected by the generator — all file path resolution and `use` import statements are derived from these values. If you use a non-standard namespace (e.g. a DDD structure), update these keys and the generator will place and import files correctly.

## What Gets Generated

For each model, the generator creates or updates:

| File | Location | Description |
|---|---|---|
| **Model** | `app/Models/{Model}.php` | Eloquent model with `$fillable`, `$casts`, relationship methods, and optionally `SoftDeletes` |
| **Migration** | `database/migrations/xxxx_create_{table}_table.php` | Migration with all column types, foreign keys, defaults, nullable/unique constraints |
| **Resource** | `app/Filament/Resources/{Model}Resource.php` | Filament resource entry point |
| **Schema** | `.../{Model}Resource/Schemas/{Model}Form.php` | Form schema with all field components (Filament v4 structure) |
| **Table** | `.../{Model}Resource/Tables/{PluralModel}Table.php` | Table with columns, filters, edit actions, and bulk delete (Filament v4 structure) |

**Automatic features:**

- **Fillable fields** — All defined fields are added to the model's `$fillable` array
- **Type casts** — Boolean, date, datetime, integer, decimal, and JSON fields get proper Eloquent casts
- **Options placeholder** — Selection components (`Select`, `Radio`, `ToggleButtons`, `CheckboxList`) include `->options([ /* TODO */ ])` with a TODO comment for discoverability; `foreignId` and `belongsToMany` use `->relationship()` instead. `SelectFilter` for non-relationship fields also includes the TODO placeholder
- **Smart imports** — Only the required Filament component classes are imported, with no duplicates. Existing imports not managed by the generator (e.g. `BackedEnum`, `Heroicon`, Page classes) are preserved
- **Relationship return types** — Generated relationship methods include proper return type hints (e.g. `: HasMany`, `: BelongsTo`)
- **Table filters** — Boolean fields get ternary filters; date/numeric fields get range filters; foreign keys get select filters
- **Table actions** — Edit action and bulk delete are automatically configured
- **Code formatting** — All generated files are formatted with Laravel Pint (PSR-12)

---

## Flags & Options

### `--softDeletes`

Adds the `SoftDeletes` trait to the model and a `softDeletes()` column to the migration. The generated table automatically includes a `TrashedFilter` so administrators can view, restore, and permanently delete soft-deleted records from the Filament UI.

### `--no-migrate`

Skips running `php artisan migrate` after file generation. Useful when you want to review or adjust the migration before running it. You can also disable auto-migration globally in the config file.

### `--no-format`

Skips Laravel Pint auto-formatting. Useful in CI environments or when you prefer to format manually. You can also disable auto-formatting globally in the config file.

### `--clean-resources`

Cleans and regenerates all existing Filament resources in your application. This re-processes imports and formatting across all resource files.

```bash
php artisan make:filament-crud --clean-resources
```

---

## Development

### Setup

```bash
git clone https://github.com/felipereisdev/filament-crud-maker.git
cd filament-crud-maker
composer install
```

### Commands

```bash
# Run tests
composer test

# Run static analysis (PHPStan level 9)
composer analyse

# Format code (PSR-12)
composer format
```

### Running a single test

```bash
vendor/bin/pest --filter test_method_name
```

---

## License

This package is open-source software licensed under the [MIT license](LICENSE.md).
