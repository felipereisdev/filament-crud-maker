## Codebase Patterns

- PHPStan 2.x (used by larastan 3.x) removed `checkGenericClassInNonGenericObjectType` parameter — do not include it in phpstan.neon
- Pest plugin requires `composer config allow-plugins.pestphp/pest-plugin true` before `composer update`
- The codebase has 46 pre-existing PHPStan level 9 errors across all src/ files (missing types, undefined variables, incorrect argument types)
- Filament v4 `make:filament-resource --generate` creates `Schemas/{Model}Form.php` and `Tables/{Model}sTable.php` under the resource directory — check for these before editing inline

## US-001: Foundation: Version constraints, Larastan, and Pest setup
- Updated composer.json: php ^8.3, filament/filament ^4.0, laravel/framework ^11.28|^12.0, orchestra/testbench ^9.0|^10.0, pestphp/pest ^3.0, larastan/larastan ^3.0
- Removed phpunit/phpunit from require-dev
- Added `analyse` script to composer.json, updated `test` script to use pest
- Updated description to "Filament v4"
- Created phpstan.neon with larastan extension, level 9, paths: [src]
- Created tests/Pest.php with TestCase binding
- Created tests/TestCase.php extending Orchestra\Testbench\TestCase
- Deleted tests/MakeFilamentCrudTest.php
- **Files changed:** composer.json, phpstan.neon (new), tests/Pest.php (new), tests/TestCase.php (new), tests/MakeFilamentCrudTest.php (deleted)
- **Learnings for future iterations:**
  - `checkGenericClassInNonGenericObjectType` is not a valid PHPStan 2.x parameter — it was likely removed in the 2.0 upgrade
  - Pest plugin needs explicit allow in composer config
  - There are 48 pre-existing PHPStan errors to fix in later stories (mostly missing iterable value types and undefined variables)
  - phpunit.xml has a deprecation warning about schema — consider migrating with `--migrate-configuration` in a future story

## US-002: Modernize PHP 8.3 syntax across all src/ files
- Converted all switch statements to match expressions across 6 files (10 switches total)
- Added constructor promotion with readonly to 5 classes: CrudGenerator, ResourceUpdater, ModelManager, MigrationManager, CodeFormatter
- Converted ImportManager's `private array $importMap` to `private const array IMPORT_MAP` with `self::IMPORT_MAP` references
- Zero switch statements remain in src/
- **Files changed:** CrudGenerator.php, ModelManager.php, MigrationManager.php, ResourceUpdater.php, CodeFormatter.php, FormComponentGenerator.php, TableComponentGenerator.php, ImportManager.php
- **Learnings for future iterations:**
  - Match expressions work well for switch-to-assignment patterns; for cases with side-effect logic (e.g. foreignId with conditional appending), inline ternaries within match arms keep things clean
  - `str_contains()` is the PHP 8.0+ replacement for `strpos($x, $y) !== false` — used in match arms for readability
  - PHPStan error count dropped from 48 to 47 (one pre-existing error was indirectly resolved by removing property declarations in favor of constructor promotion)

## US-003: Migrate generated code to Filament v4 API
- **ImportManager:** Removed BadgeColumn from IMPORT_MAP; added EditAction, BulkActionGroup, DeleteBulkAction with `Filament\Actions` namespace; updated required imports to use `Filament\Schemas\Schema` instead of `Filament\Forms\Form`, removed standalone `Filament\Forms` and `Filament\Tables` imports; ensured action components are always added to usedComponents before import generation
- **FormComponentGenerator:** Updated `updateFormMethod()` regex to match both `form(Form $form)` and `configure(Schema $schema)` signatures; changed generated output from `$form->schema([...])` to `$schema->components([...])`; simplified `unique()` validation to omit `ignoreRecord: true`
- **TableComponentGenerator:** Changed `getComponentType()` for enum/tags from `BadgeColumn` to `TextColumn` (the `->badge()` modifier was already applied in `generateColumn()`); updated `updateTableMethod()` to use `->recordActions([EditAction::make()])` and `->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])` with direct class references instead of `Tables\Actions\` namespace prefix
- **Files changed:** ImportManager.php, FormComponentGenerator.php, TableComponentGenerator.php
- **Learnings for future iterations:**
  - Filament v4 uses `Filament\Schemas\Schema` instead of `Filament\Forms\Form`, and the method signature changes from `form(Form $form)` to `configure(Schema $schema)`
  - Filament v4 moves actions to `Filament\Actions` namespace (not `Filament\Tables\Actions`) and uses `->recordActions()` / `->toolbarActions()` instead of `->actions()` / `->bulkActions()`
  - BadgeColumn was removed in Filament v4 — use `TextColumn::make()->badge()` instead
  - When adding items to an array before a loop that processes it, ensure the additions come before the loop — ordering matters when mutating input before iteration
  - PHPStan error count remains at 47 (no new errors introduced)

## US-004: Support Filament v4 directory structure (Schemas/ + Tables/)
- **ResourceUpdater:** Refactored `update()` to detect v4 directory structure (`Schemas/` + `Tables/` under resource dir). Extracted field processing into `processFields()` returning separate `$formComponents` and `$tableComponents` arrays. Added `updateV4Structure()` to route updates to Schema and Table files separately. Added `updateSchemaFile()` and `updateTableFile()` for individual file updates. Preserved original inline behavior in `updateInlineResource()` as fallback.
- **ImportManager:** Added `addFormFileImports()` for Schema files (only `Filament\Schemas\Schema` + form component imports). Added `addTableFileImports()` for Table files (table columns, filters, actions, Builder, softDeletes imports). Added private `insertImportsIntoContent()` as a generic namespace-aware import insertion helper that works with any PHP class file.
- **FormComponentGenerator:** Made `static` optional in `updateFormMethod()` regex (`static\s+` → `(?:static\s+)?`) to match `public function configure(Schema $schema)` in v4 Schema files.
- **TableComponentGenerator:** Made `static` optional and added `configure` as alternative method name in `updateTableMethod()` regex to match `public function configure(Table $table)` in v4 Table files.
- **Files changed:** ResourceUpdater.php, ImportManager.php, FormComponentGenerator.php, TableComponentGenerator.php
- **Learnings for future iterations:**
  - Filament v4 `make:filament-resource --generate` creates `{Model}Resource/Schemas/{Model}Form.php` (with `configure(Schema $schema)`) and `{Model}Resource/Tables/{Model}sTable.php` (with `configure(Table $table)`) — these methods are NOT static unlike inline Resource methods
  - The `insertImportsIntoContent()` helper uses a generic `namespace ... class` pattern instead of hardcoded `App\Filament\Resources` namespace — reusable for any PHP file
  - Table filters can use form components (DatePicker, TextInput) inside `->form([])` — these form component imports must go into the Table file, not the Schema file
  - PHPStan error count dropped from 47 to 46 (one pre-existing error resolved by the refactoring)
