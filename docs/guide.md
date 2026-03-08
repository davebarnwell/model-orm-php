# ORM Guide

This guide covers the day-to-day use of `Freshsauce\Model\Model`: how to connect, define models, read and write rows, and use the ORM's lighter-weight features without losing access to raw PDO.

## Mental model

This package is a small active-record layer on top of PDO.

- One model class maps to one table.
- Table columns are discovered from the database at runtime.
- Known columns are exposed as dynamic object properties.
- `save()` decides between `insert()` and `update()` based on whether the primary key currently has a non-`null` value.
- When the helper methods stop being enough, you can run SQL directly through `execute()`.

The package does not include relationships, migrations, or a chainable query builder.

## Installation

```bash
composer require freshsauce/model
```

Requirements:

- PHP `8.3+`
- `ext-pdo`
- A PDO driver such as `pdo_mysql`, `pdo_pgsql`, or `pdo_sqlite`

## Connecting to the database

Set the PDO connection once before using your models:

```php
Freshsauce\Model\Model::connectDb(
    'mysql:host=127.0.0.1;port=3306;dbname=categorytest',
    'root',
    ''
);
```

`connectDb()` accepts the same first three arguments as `new PDO(...)`, plus optional driver options:

```php
Freshsauce\Model\Model::connectDb(
    'pgsql:host=127.0.0.1;port=5432;dbname=categorytest',
    'postgres',
    'postgres',
    [
        PDO::ATTR_TIMEOUT => 5,
    ]
);
```

What `connectDb()` does:

- creates the PDO connection
- forces `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`
- detects the identifier quote character for the current driver
- clears cached prepared statements for the previous connection
- clears cached table metadata so field discovery matches the new connection

By default, the connection is inherited by all subclasses of `Model`.

## Defining a model

The minimum definition is a table name:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
}
```

Optional configuration points:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
    protected static $_primary_column_name = 'id';
    protected static bool $_strict_fields = false;
}
```

Available configuration members:

- `protected static $_tableName`: required; the database table to use
- `protected static $_primary_column_name`: defaults to `id`
- `protected static bool $_strict_fields`: defaults to `false`
- `public static $_db`: only redeclare this when a subclass needs its own isolated connection

Custom primary keys are supported:

```php
class CodedCategory extends Freshsauce\Model\Model
{
    protected static $_tableName = 'coded_categories';
    protected static $_primary_column_name = 'code';
}
```

PostgreSQL schema-qualified tables are also supported:

```php
class ReportingCategory extends Freshsauce\Model\Model
{
    protected static $_tableName = 'reporting.categories';
}
```

## How field mapping works

The ORM reads the table's columns from the database and uses those columns as the model's real fields.

```php
$category = new Category([
    'name' => 'Fiction',
]);
```

Important behavior:

- `hydrate()` only maps known table columns
- known columns missing from the input array are initialised to `null`
- unknown fields are ignored during hydration
- `toArray()` only returns known table columns
- insert and update statements only write known table columns

That last point matters when strict field mode is disabled. Unknown assignments can still exist on the in-memory object, but they will not be persisted unless they match a real table column.

## Creating records

The usual path is `save()`:

```php
$category = new Category([
    'name' => 'Science Fiction',
]);

$category->save();
```

`save()` inserts when the primary key is `null`:

```php
$category->id === null;
```

You can also call `insert()` directly:

```php
$category = new Category([
    'name' => 'Biography',
]);

$category->insert();
```

Notes:

- if `created_at` exists, `insert()` sets it automatically
- if `updated_at` exists, `insert()` sets it automatically
- timestamps are generated in UTC using `Y-m-d H:i:s`
- `insert(false)` disables automatic timestamps
- `insert(false, true)` allows you to include an explicit primary key value
- `insert()` can still succeed when there are no dirty fields by inserting a default-values row

Example with an explicit primary key:

```php
$category = new CodedCategory([
    'code' => 42,
    'name' => 'Meaning',
]);

$category->insert(false, true);
```

## Loading records

Use the lifecycle helpers for common reads:

```php
$category = Category::getById(1);
$first = Category::first();
$last = Category::last();
$count = Category::count();
```

Return values:

- `getById()` returns one model instance or `null`
- `first()` returns one model instance or `null`
- `last()` returns one model instance or `null`
- `count()` returns an integer

`find($id)` behaves differently from `getById()`:

```php
$rows = Category::find(1);
```

`find()` returns an array of model instances, even when matching by the primary key.

## Updating records

Update a loaded model and call `save()`:

```php
$category = Category::getById(1);

if ($category !== null) {
    $category->name = 'Modern Fiction';
    $category->save();
}
```

You can also call `update()` directly:

```php
$category->name = 'Memoir';
$category->update();
```

Update behavior:

- `updated_at` is refreshed automatically when that column exists
- `update(false)` disables automatic timestamp updates
- only dirty known fields are included in the SQL `SET` clause
- `update()` returns `false` when there is nothing dirty to write
- a no-op update still returns `true` when the row exists and the database reports zero changed rows

`save()` uses update when the primary key has any non-`null` value, including `0` and `'0'`.

## Deleting records

Delete through an instance:

```php
$category = Category::getById(1);
$category?->delete();
```

Delete by primary key:

```php
Category::deleteById(1);
```

Delete by condition:

```php
Category::deleteAllWhere('name = ?', ['Fiction']);
```

Notes:

- `deleteById()` returns `true` only when exactly one row was deleted
- `deleteById()` returns `false` when no row matches
- `deleteAllWhere()` returns the raw `PDOStatement`
- `deleteAllWhere()` expects only the condition fragment, not the `WHERE` keyword

## Query helpers

For custom reads without dropping to raw SQL, use the `fetch...` helpers.

### `fetchAllWhere()` and `fetchOneWhere()`

```php
$many = Category::fetchAllWhere(
    'name IN (?, ?)',
    ['Fiction', 'Fantasy']
);

$one = Category::fetchOneWhere(
    'id = ? OR name = ?',
    [1, 'Fiction']
);
```

Rules:

- pass only the SQL fragment that belongs to the right of `WHERE`
- use PDO placeholders and a matching params array
- `fetchOneWhere()` returns `null` when nothing matches
- `fetchAllWhere()` returns an array of model instances

### Existence and counting helpers

```php
$hasRows = Category::exists();
$hasFiction = Category::existsWhere('name = ?', ['Fiction']);
$matchingCount = Category::countAllWhere('name = ?', ['Fiction']);
```

### Ordered reads

```php
$alphabetical = Category::fetchAllWhereOrderedBy('name', 'ASC');
$latest = Category::fetchOneWhereOrderedBy('id', 'DESC');
```

Rules:

- `orderByField` must resolve to a real model field
- direction must be `ASC` or `DESC`
- `fetchAllWhereOrderedBy()` accepts an optional limit as the fifth argument

Example with conditions and a limit:

```php
$recent = Category::fetchAllWhereOrderedBy(
    'id',
    'DESC',
    'name <> ?',
    ['Archived'],
    10
);
```

### `pluck()`

```php
$names = Category::pluck('name', '', [], 'name', 'ASC', 10);
```

`pluck()` returns an array of scalar column values instead of model objects.

## Dynamic finder and counter methods

Dynamic static methods are supported for simple single-column matching.

Preferred camelCase forms:

```php
Category::findByName('Fiction');
Category::findOneByName('Fiction');
Category::firstByName(['Fiction', 'Fantasy']);
Category::lastByName(['Fiction', 'Fantasy']);
Category::countByName('Fiction');
```

Field names are resolved against real table columns, so this also works with snake_case columns:

```php
Category::findOneByUpdatedAt('2026-03-08 12:00:00');
```

Behavior:

- scalar input generates `= ?`
- array input generates `IN (?, ?, ...)`
- empty arrays short-circuit without running SQL
- unknown fields throw `UnknownFieldException`
- unsupported dynamic method names throw `InvalidDynamicMethodException`

Legacy snake_case methods still work for now:

```php
Category::find_by_name('Fiction');
Category::count_by_name('Fiction');
```

Those calls emit `E_USER_DEPRECATED`. New code should use camelCase.

## Raw SQL and PDO access

Use `execute()` when you need full SQL control:

```php
$statement = Freshsauce\Model\Model::execute(
    'SELECT * FROM categories WHERE id > ?',
    [10]
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
```

Notes:

- `execute()` returns a `PDOStatement`
- statements are prepared through PDO and cached by connection plus SQL string
- statement caching stays isolated per connection, including subclasses with separate `$_db` properties

## Validation hooks

The preferred extension points are instance methods:

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

    protected function validateForInsert(): void
    {
        // insert-only rules
    }

    protected function validateForUpdate(): void
    {
        // update-only rules
    }
}
```

Validation order:

- insert path: `validateForSave()`, then `validateForInsert()`
- update path: `validateForSave()`, then `validateForUpdate()`

The legacy static `validate()` method is still called by default through `validateForSave()`, so older models continue to work.

## Strict field mode

Strict field mode changes assignment behavior from permissive to fail-fast.

Per model:

```php
class StrictCategory extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
    protected static bool $_strict_fields = true;
}
```

At runtime:

```php
Category::useStrictFields(true);
```

What changes when strict mode is on:

- `__set()` resolves the requested property name against real fields
- unknown fields throw `UnknownFieldException`
- camelCase field names such as `updatedAt` are normalised to real columns such as `updated_at`

What happens when strict mode is off:

- unknown fields can be assigned to the in-memory model
- those fields are not written by `insert()` or `update()`
- this keeps older code working, but it can hide typos

## Runtime schema changes

Field names are cached per model class after the first lookup. If the table schema changes while the process is still running, refresh the metadata cache manually:

```php
Category::refreshTableMetadata();
```

Use this after operations such as adding a new column at runtime.

## Multiple connections

All subclasses share the inherited connection unless a subclass redeclares `public static $_db`.

Example:

```php
class ReportingCategory extends Freshsauce\Model\Model
{
    public static $_db;
    protected static $_tableName = 'categories';
}
```

That lets one model family use a different database connection without affecting the default shared connection.

## Object state helpers

The model tracks both data and dirty fields.

Useful instance helpers:

- `hasData()`: whether the model currently has a data container
- `dataPresent()`: same check, but throws `MissingDataException` when absent
- `markFieldDirty($name)`: manually mark a field dirty
- `isFieldDirty($name)`: check whether a field will be written on save
- `clearDirtyFields()`: reset dirty tracking
- `clear()`: set all known columns to `null` and clear dirty flags
- `toArray()`: export known columns as an associative array

Serialisation is supported:

- `serialize()` and `unserialize()` preserve values
- dirty state is preserved across serialisation round-trips

## Timestamp behavior

Automatic timestamp handling is convention-based:

- `created_at` is filled on insert when the column exists
- `updated_at` is filled on insert and update when the column exists
- timestamps are generated in UTC with `gmdate('Y-m-d H:i:s')`
- models without those columns save normally

If you need custom timestamp columns, that is currently outside the built-in feature set.

## Exceptions

The ORM raises library-specific exceptions for common failure modes:

- `ConnectionException`: no database connection is configured
- `ConfigurationException`: unsupported order direction, invalid limit, or other setup errors
- `UnknownFieldException`: invalid model property or unresolved dynamic finder field
- `InvalidDynamicMethodException`: unsupported dynamic static method name
- `MissingDataException`: access to model data before initialisation
- `ModelException`: general ORM-specific failure

PDO exceptions still surface for underlying database errors.

## Utility helpers

There are a few small helpers worth knowing about:

- `createInClausePlaceholders([1, 2, 3])` returns `?,?,?`
- `createInClausePlaceholders([])` returns `NULL`
- `datetimeToMysqldatetime($value)` converts a timestamp or date string to `Y-m-d H:i:s`

`datetimeToMysqldatetime()` treats invalid date strings as Unix epoch `0`, formatting the result in the PHP default timezone.

## Database-specific notes

MySQL and MariaDB:

- tested in the main integration suite
- support `LIMIT 1` on `UPDATE` and `DELETE` statements used by `update()` and `deleteById()`

PostgreSQL:

- tested in the main integration suite
- supports schema-qualified table names such as `reporting.categories`
- uses `RETURNING` on inserts to capture generated primary keys

SQLite:

- supported and covered by dedicated tests
- stores automatic timestamps as text by default in the test schema
- uses `DEFAULT VALUES` when inserting rows with no dirty fields

## Suggested reading order

If you are new to the package:

1. Read the [README](../README.md) for the overview and quick start.
2. Use this guide while building your first model.
3. Keep the [API reference](./api-reference.md) open for exact method behavior.
4. Check [EXAMPLE.md](../EXAMPLE.md) for shorter copy-paste examples.
