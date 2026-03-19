# CRUD Generator for Filament v4

A Laravel package that quickly generates complete CRUD resources for Filament v4, saving development time.

## Installation

You can install the package via composer:

```bash
composer require freis/filament-crud-generator
```

Optionally, you can publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-crud-generator-config"
```

This will publish a file at `config/filament-crud-generator.php`.

You can also publish the PHP CS Fixer configuration file:

```bash
php artisan vendor:publish --tag="php-cs-fixer-config"
```

## Usage

To generate a complete CRUD, use the command:

```bash
php artisan make:filament-crud ModelName --fields=field1:type,field2:type --relations=relationType:RelatedModel:field1:type,field2:type --softDeletes
```

### Available parameters:

- `ModelName`: Name of the model to be created (singular, with the first letter capitalized).
- `--fields`: List of fields and their types, separated by comma.
- `--relations`: List of relations and their fields, in the format `relationType:RelatedModel:field1:type,field2:type;relationType2:RelatedModel2:field1:type`.
- `--softDeletes`: Optional flag to add soft deletes to the model.
- `--no-migrate`: Optional flag to skip running migrations after creation.
- `--no-format`: Optional flag to skip code formatting using PHP CS Fixer.
- `--clean-resources`: Clean all existing resources.

### Examples:

1. Creating a Product model with basic fields:

```bash
php artisan make:filament-crud Product --fields=name:string:required:min=3,description:text,price:decimal:required,active:boolean,image:image:nullable
```

2. Creating a Product model with a category relation:

```bash
php artisan make:filament-crud Product --fields=name:string:required:min=3:max=100,description:text,price:decimal:required,active:boolean --relations=belongsTo:Category
```

3. Creating a model with softDeletes:

```bash
php artisan make:filament-crud Article --fields=title:string:required:min=5,content:html:required,publish_date:date:required --softDeletes
```

4. Creating a model with more complex relations:

```bash
php artisan make:filament-crud Course --fields=name:string:required:min=3:unique,description:markdown:required,price:decimal:required:between=0,9999.99,duration:integer:required,published:boolean:false --relations=belongsTo:Teacher;belongsToMany:Student;hasMany:Lesson
```

## Support

If you encounter any issues or have questions, please open an issue on the GitHub repository.

## License

This package is open-source and available under the MIT license.