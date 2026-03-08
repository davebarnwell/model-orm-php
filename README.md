Model ORM for PHP
=================

[![CI](https://github.com/davebarnwell/model-orm-php/actions/workflows/ci.yml/badge.svg)](https://github.com/davebarnwell/model-orm-php/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/davebarnwell/model-orm-php?display_name=tag)](https://github.com/davebarnwell/model-orm-php/releases)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)

`Freshsauce\Model\Model` gives you the sweet spot between raw PDO and a full framework ORM: fast setup, familiar model-style workflows, and complete freedom to drop to SQL whenever you want.

If you want database-backed PHP models without pulling in a heavyweight stack, this library is built for that job.

## When it fits

Use this library when you want:

- Active-record style models without adopting a full framework ORM
- Direct access to SQL and PDO when convenience helpers stop helping
- A small API surface that stays easy to understand in legacy or custom PHP apps

Skip it if you need relationship graphs, migrations, or a chainable query builder comparable to framework ORMs.

## Why teams pick it

- Lightweight by design: point a model at a table and start reading and writing records.
- PDO-first: keep the convenience methods, keep full access to SQL, keep control.
- Framework-agnostic: use it in custom apps, legacy codebases, small services, or greenfield projects.
- Productive defaults: CRUD helpers, dynamic finders, counters, hydration, and timestamp handling are ready out of the box.
- Practical opt-ins: transaction helpers, configurable timestamp columns, and attribute casting stay lightweight but cover common app needs.
- Portable across databases: exercised against MySQL/MariaDB, PostgreSQL, and SQLite.

## Install in minutes

```bash
composer require freshsauce/model
```

Requirements:

- PHP `8.3+`
- `ext-pdo`
- A PDO driver such as `pdo_mysql` or `pdo_pgsql`

## Quick start

Create a table. This example uses PostgreSQL syntax:

```sql
CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  name VARCHAR(120) NULL,
  updated_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL
);
```

If you are using MySQL or MariaDB, use `INT AUTO_INCREMENT PRIMARY KEY` for `id` instead.

Connect and define a model:

```php
require_once 'vendor/autoload.php';

Freshsauce\Model\Model::connectDb(
    'pgsql:host=127.0.0.1;port=5432;dbname=categorytest',
    'postgres',
    'postgres'
);

class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
}
```

Create, read, update, and delete records:

```php
$category = new Category([
    'name' => 'Sci-Fi',
]);

$category->save();

$loaded = Category::getById($category->id);
$loaded->name = 'Science Fiction';
$loaded->save();
$loaded->delete();
```

That is the core promise of the library: minimal ceremony, direct results.

## Documentation

Use the docs based on how much detail you need:

- [docs/guide.md](docs/guide.md) for setup, model definition, CRUD, querying, validation, strict fields, and database notes
- [docs/api-reference.md](docs/api-reference.md) for method-by-method behavior and return types
- [EXAMPLE.md](EXAMPLE.md) for shorter copy-paste examples

## What you get

### Full record lifecycle helpers

The base model gives you the methods most applications reach for first:

- `save()`
- `insert()`
- `update()`
- `delete()`
- `deleteById()`
- `deleteAllWhere()`
- `getById()`
- `first()`
- `last()`
- `count()`
- `transaction()`
- `beginTransaction()`
- `commit()`
- `rollBack()`

If your table includes `created_at` and `updated_at`, they are populated automatically on insert and update.

Timestamps are generated in UTC using the `Y-m-d H:i:s` format. SQLite stores those values as text, while MySQL/MariaDB and PostgreSQL accept them in timestamp-style columns.

### Transactions without leaving the model

Use the built-in transaction helper when several writes should succeed or fail together:

```php
Category::transaction(function (): void {
    $first = new Category(['name' => 'Sci-Fi']);
    $first->save();

    $second = new Category(['name' => 'Fantasy']);
    $second->save();
});
```

If you need lower-level control, the model also exposes `beginTransaction()`, `commit()`, and `rollBack()` as thin wrappers around the current PDO connection.

### Timestamp columns can be configured per model

The default convention remains `created_at` and `updated_at`, but models can now opt into different column names or disable automatic timestamps entirely:

```php
class AuditLog extends Freshsauce\Model\Model
{
    protected static $_tableName = 'audit_logs';
    protected static ?string $_created_at_column = 'created_on';
    protected static ?string $_updated_at_column = 'modified_on';
}

class LegacyCategory extends Freshsauce\Model\Model
{
    protected static $_tableName = 'legacy_categories';
    protected static bool $_auto_timestamps = false;
}
```

### Attribute casting

Cast common fields to application-friendly PHP types:

```php
class Product extends Freshsauce\Model\Model
{
    protected static $_tableName = 'products';

    protected static array $_casts = [
        'stock' => 'integer',
        'price' => 'float',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'tags' => 'array',
        'settings' => 'object',
    ];
}
```

Supported cast types are `integer`, `float`, `boolean`, `datetime`, `array`, and `object`.

`datetime` casts assume stored strings are UTC wall-time values. If you do not want implicit timezone conversion by the database, prefer `DATETIME`-style columns or ensure the connection session timezone is UTC before using `TIMESTAMP` columns.

### Dynamic finders and counters

Build expressive queries straight from method names:

```php
Category::findByName('Science Fiction');
Category::findOneByName('Science Fiction');
Category::firstByName(['Sci-Fi', 'Fantasy']);
Category::lastByName(['Sci-Fi', 'Fantasy']);
Category::countByName('Science Fiction');
```

Legacy snake_case dynamic methods still work during the transition, but they are deprecated and emit `E_USER_DEPRECATED` notices.

If you still have legacy calls such as `find_by_name()`, treat them as migration work rather than the preferred API.

### Focused query helpers

For common read patterns that do not justify raw SQL:

```php
Category::exists();
Category::existsWhere('name = ?', ['Science Fiction']);

$ordered = Category::fetchAllWhereOrderedBy('name', 'ASC');
$latest = Category::fetchOneWhereOrderedBy('id', 'DESC');

$names = Category::pluck('name', '', [], 'name', 'ASC', 10);
```

### Flexible SQL when convenience methods stop helping

Use targeted where clauses:

```php
$one = Category::fetchOneWhere('id = ? OR name = ?', [1, 'Science Fiction']);

$many = Category::fetchAllWhere('name IN (?, ?)', ['Sci-Fi', 'Fantasy']);
```

Or run raw SQL directly through PDO:

```php
$statement = Freshsauce\Model\Model::execute(
    'SELECT * FROM categories WHERE id > ?',
    [10]
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
```

If you change a table schema at runtime and need the model to see the new columns without reconnecting, call `YourModel::refreshTableMetadata()`.

### Validation hooks

Use instance-aware hooks when writes need application rules:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';

    protected function validateForSave(): void
    {
        if (trim((string) $this->name) === '') {
            throw new RuntimeException('Name is required');
        }
    }
}
```

Use `validateForInsert()` or `validateForUpdate()` when the rules differ by operation.

The legacy static `validate()` method still works for backward compatibility, but new code should prefer the instance hooks.

### Strict field mode

Unknown properties are permissive by default for compatibility. If you want typo-safe writes, enable strict field mode:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
    protected static bool $_strict_fields = true;
}
```

You can also opt in at runtime with `Category::useStrictFields(true)`.

### Predictable exceptions

The library now throws model-specific exceptions for common failure modes instead of generic `Exception` objects.

- `Freshsauce\Model\Exception\ConnectionException` for missing database connections
- `Freshsauce\Model\Exception\UnknownFieldException` for invalid model fields and dynamic finder columns
- `Freshsauce\Model\Exception\InvalidDynamicMethodException` for unsupported dynamic static methods
- `Freshsauce\Model\Exception\MissingDataException` for invalid access to uninitialized model data

## Database support

MySQL or MariaDB:

```php
Freshsauce\Model\Model::connectDb(
    'mysql:host=127.0.0.1;port=3306;dbname=categorytest',
    'root',
    ''
);
```

PostgreSQL:

```php
Freshsauce\Model\Model::connectDb(
    'pgsql:host=127.0.0.1;port=5432;dbname=categorytest',
    'postgres',
    'postgres'
);
```

SQLite is supported in the library and covered by the automated test suite.

Schema-qualified table names such as `reporting.categories` are supported for PostgreSQL models.

## Built for real projects

The repository includes:

- PHPUnit coverage for core model behavior
- PHPStan static analysis
- PHP-CS-Fixer formatting checks
- GitHub Actions CI for pushes and pull requests
- Automatic `vYY.MM.DD.n` CalVer tags and GitHub releases for pushes to `main`

## Learn more

- Need fuller ORM docs? Start with [docs/guide.md](docs/guide.md) and [docs/api-reference.md](docs/api-reference.md).
- Want to see planned improvements? See [ROADMAP.md](ROADMAP.md).
- Want fuller usage examples? See [EXAMPLE.md](EXAMPLE.md).
- Want to contribute? See [CONTRIBUTING.md](CONTRIBUTING.md).
