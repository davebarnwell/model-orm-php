# Examples

This document shows example-led usage of `Freshsauce\Model\Model` for common application flows.

## Minimal model definition

Define a model by extending the base class and setting the table name:

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

## Create a record

```php
$category = new Category([
    'name' => 'Fiction',
]);

$category->save();

echo $category->id;
echo $category->created_at;
echo $category->updated_at;
```

`save()` inserts when the primary key is `null` and updates when the primary key is present.

## Load a record by primary key

```php
$category = Category::getById(1);

if ($category !== null) {
    echo $category->name;
}
```

## Update an existing record

```php
$category = Category::getById(1);

if ($category !== null) {
    $category->name = 'Modern Fiction';
    $category->save();
}
```

## Insert explicitly

If you want to call the insert path directly:

```php
$category = new Category([
    'name' => 'Biography',
]);

$category->insert();
```

## Update explicitly

If you already know the object exists and want to call update directly:

```php
$category = Category::getById(1);

if ($category !== null) {
    $category->name = 'Memoir';
    $category->update();
}
```

## First, last, and count

```php
$first = Category::first();
$last = Category::last();
$count = Category::count();
```

## Dynamic finders

Preferred camelCase methods:

```php
$all = Category::findByName('Fiction');
$one = Category::findOneByName('Fiction');
$first = Category::firstByName(['Fiction', 'Fantasy']);
$last = Category::lastByName(['Fiction', 'Fantasy']);
$count = Category::countByName('Fiction');
```

Legacy snake_case dynamic methods still work during the transition, but they are deprecated and should be migrated.

## Focused query helpers

Check existence:

```php
$hasCategories = Category::exists();
$hasFiction = Category::existsWhere('name = ?', ['Fiction']);
```

Fetch ordered results:

```php
$alphabetical = Category::fetchAllWhereOrderedBy('name', 'ASC');
$latest = Category::fetchOneWhereOrderedBy('id', 'DESC');
```

Pluck one column:

```php
$names = Category::pluck('name', '', [], 'name', 'ASC', 10);
```

## Custom WHERE clauses

Fetch a single matching record:

```php
$category = Category::fetchOneWhere(
    'id = ? OR name = ?',
    [1, 'Fiction']
);
```

Fetch all matching records:

```php
$categories = Category::fetchAllWhere(
    'name IN (?, ?)',
    ['Fiction', 'Fantasy']
);
```

## Delete records

Delete via an instance:

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
Category::deleteAllWhere(
    'name = ?',
    ['Fiction']
);
```

Delete with a richer SQL fragment that works across MySQL/MariaDB and PostgreSQL:

```php
Category::deleteAllWhere(
    'id IN (SELECT id FROM categories WHERE name = ? ORDER BY name DESC LIMIT 2)',
    ['Fiction']
);
```

## Raw SQL

Drop down to PDO statements when needed:

```php
$statement = Freshsauce\Model\Model::execute(
    'SELECT * FROM categories WHERE id > ?',
    [10]
);

$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
```

## Validation

Use instance-aware hooks to enforce model rules before insert and update:

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

Override `validateForInsert()` or `validateForUpdate()` when the rules are operation-specific.

The legacy static `validate()` method remains supported for backward compatibility.

## Strict field mode

Enable strict field mode when you want unknown assignments to fail immediately instead of being silently ignored on persistence:

```php
class Category extends Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';
    protected static bool $_strict_fields = true;
}
```

Or enable it temporarily:

```php
Category::useStrictFields(true);
```

## MySQL example connection

```php
Freshsauce\Model\Model::connectDb(
    'mysql:host=127.0.0.1;port=3306;dbname=categorytest',
    'root',
    ''
);
```

## PostgreSQL example connection

```php
Freshsauce\Model\Model::connectDb(
    'pgsql:host=127.0.0.1;port=5432;dbname=categorytest',
    'postgres',
    'postgres'
);
```
