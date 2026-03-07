Model ORM
=========

[![CI](https://github.com/davebarnwell/model-orm-php/actions/workflows/ci.yml/badge.svg)](https://github.com/davebarnwell/model-orm-php/actions/workflows/ci.yml)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)

`Freshsauce\Model\Model` is a lightweight ORM-style base class for PHP applications that want database-backed models without committing to a large framework. Point it at a table, extend the base class, and you get CRUD operations, dynamic finders, counters, and raw query access with very little setup.

It is designed for projects that value straightforward PHP, direct PDO access, and a small abstraction layer that stays out of the way.

## Why use it?

- Minimal setup: define a model class and table name, then start reading and writing rows.
- PDO-first: use the ORM helpers when they help and drop down to raw SQL when they do not.
- Familiar model flow: create, hydrate, validate, save, update, count, find, and delete.
- Dynamic finders: call methods such as `find_by_name()`, `findOneByName()`, `count_by_name()`, and more.
- Multi-database support: tested against MySQL/MariaDB and PostgreSQL, with SQLite code paths also supported.

## Installation

Install from Composer:

```bash
composer require freshsauce/model
```

Requirements:

- PHP `8.3+`
- `ext-pdo`
- A PDO driver such as `pdo_mysql` or `pdo_pgsql`

Looking for fuller, example-led usage? See [EXAMPLE.md](EXAMPLE.md).

## Quick start

Create a table. This quick-start example uses PostgreSQL syntax:

```sql
CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  name VARCHAR(120) NULL,
  updated_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL
);
```

If you are using MySQL or MariaDB, use `INT AUTO_INCREMENT PRIMARY KEY` for the `id` column instead.

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

Create and save a record:

```php
$category = new Category([
    'name' => 'Sci-Fi',
]);

$category->save();

echo $category->id;
```

Read it back:

```php
$loaded = Category::getById($category->id);
```

Update it:

```php
$loaded->name = 'Science Fiction';
$loaded->save();
```

Delete it:

```php
$loaded->delete();
```

For more end-to-end snippets, see [EXAMPLE.md](EXAMPLE.md).

## What you get

### CRUD helpers

The base model gives you the common record lifecycle methods:

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

Timestamp columns named `created_at` and `updated_at` are populated automatically on insert and update when present.

### Dynamic finders and counters

You can query using snake_case or CamelCase method names:

```php
Category::find_by_name('Science Fiction');
Category::findOne_by_name('Science Fiction');
Category::first_by_name(['Sci-Fi', 'Fantasy']);
Category::lastByName(['Sci-Fi', 'Fantasy']);
Category::count_by_name('Science Fiction');
```

### Custom where clauses

When you need more control, fetch one or many records with SQL fragments:

```php
$one = Category::fetchOneWhere('id = ? OR name = ?', [1, 'Science Fiction']);

$many = Category::fetchAllWhere('name IN (?, ?)', ['Sci-Fi', 'Fantasy']);
```

### Raw statements when needed

If a query does not fit the model helpers, execute SQL directly through PDO:

```php
$statement = Freshsauce\Model\Model::execute(
    'SELECT * FROM categories WHERE id > ?',
    [10]
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
```

## Validation hooks

Override `validate()` in your model to enforce business rules before inserts and updates:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';

    public static function validate()
    {
        return true;
    }
}
```

Throw an exception from `validate()` to block invalid writes.

## Database notes

MySQL/MariaDB example connection:

```php
Freshsauce\Model\Model::connectDb(
    'mysql:host=127.0.0.1;port=3306;dbname=categorytest',
    'root',
    ''
);
```

PostgreSQL example connection:

```php
Freshsauce\Model\Model::connectDb(
    'pgsql:host=127.0.0.1;port=5432;dbname=categorytest',
    'postgres',
    'postgres'
);
```

SQLite is supported in the library code paths, but the automated test suite currently covers MySQL/MariaDB and PostgreSQL.

## Quality

The repository ships with:

- PHPUnit coverage for the core model behavior
- PHPStan static analysis
- PHP-CS-Fixer formatting checks
- GitHub Actions CI for pull requests and pushes
- Automatic `vYY.MM.DD.n` CalVer tags and GitHub releases for merged PRs to `main`/`master`

## Contributing

Development setup, testing commands, pull request expectations, and contribution terms are documented in [CONTRIBUTING.md](CONTRIBUTING.md).
