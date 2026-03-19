# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel PHP package (`freis/filament-crud-generator`) that generates complete CRUD resources for Filament v4/v5 admin panels. It creates Models, Migrations, and Filament Resources with customizable fields, validations, and relationships via a single Artisan command.

Documentation and code are in English.

## Commands

```bash
# Install dependencies
composer install

# Run tests
composer test          # runs vendor/bin/pest

# Run static analysis
composer analyse       # runs vendor/bin/phpstan analyse

# Format code
composer cs-fix        # runs php-cs-fixer with PSR-12 rules

# Run a single test
vendor/bin/pest --filter test_method_name
```

## Architecture

The package follows a Command + Manager pattern with single-responsibility classes:

1. **`MakeFilamentCrud`** (`src/Commands/MakeFilamentCrud.php`) — Artisan command entry point. Parses CLI arguments (`--fields`, `--relations`, `--softDeletes`, etc.) and delegates to `CrudGenerator`.

2. **`CrudGenerator`** (`src/Commands/FilamentCrud/CrudGenerator.php`) — Orchestrator. Coordinates the full generation workflow: model creation → relationship setup → migration → Filament resource creation → code formatting.

3. **Specialized Managers** (all in `src/Commands/FilamentCrud/`):
   - `ModelManager` — Creates/updates Eloquent Models (fillable, casts, relationship methods)
   - `MigrationManager` — Creates/updates migrations and pivot tables
   - `ResourceUpdater` — Modifies Filament Resource files with form/table components
   - `FormComponentGenerator` — Maps field types → Filament form components (TextInput, Toggle, DatePicker, etc.)
   - `TableComponentGenerator` — Maps field types → Filament table columns
   - `ImportManager` — Manages PHP `use` statements, prevents duplicates
   - `CodeFormatter` — Wraps PHP CS Fixer integration
   - `CodeValidator` — Validates generated code syntax (bracket balancing)

4. **Service Provider** (`src/Providers/FilamentCrudGeneratorServiceProvider.php`) — Registers the command and publishes config. Auto-discovered via `composer.json` extra.

5. **Config** (`src/config/filament-crud-generator.php`) — Default namespaces for models (`App\Models`) and resources (`App\Filament\Resources`), auto-migrate/auto-format toggles.

## Key Dependencies

- **PHP ^8.3**, **Laravel ^11.28|^12/^13**, **Filament ^4.0/^5.0**
- **PHP-CS-Fixer ^3.0** for code formatting
- **Pest ^3.0** for testing
- **Larastan ^3.0** (PHPStan + Laravel) for static analysis at level 9
- **Orchestra Testbench** for package testing within a Laravel environment

=== phpstan/core rules ===

## PHPStan Static Analysis

- You must run `vendor/bin/phpstan analyse --memory-limit=512M` before finalizing changes to ensure your code passes static analysis at level 9.
- Fix all PHPStan errors in the files you modified. Do not ignore errors or add them to the baseline without approval.
- The project uses Larastan (Laravel-specific PHPStan extension) with level 9 strictness.
- When PHPStan reports errors, fix the root cause (add proper type hints, return types, null checks, etc.) rather than suppressing with `@phpstan-ignore` annotations.
- Avoid generic PHPDoc types like `array`, `object`, or `mixed`. Use specific types such as `array<int, string>`, `array{key: value}`, `Collection<int, User>`, etc.

## IMPORTANT

- THE CODE COMMENT'S SHOULD BE ALWAYS IN ENGLISH