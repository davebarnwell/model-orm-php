Model ORM
=========

[![Build Status](https://scrutinizer-ci.com/g/freshsauce/model-orm-php/badges/build.png?b=master)](https://scrutinizer-ci.com/g/freshsauce/model-orm-php/build-status/master)

PHP Model class which provides:

* table column/property mapping
* CRUD operations
* dynamic finders and counters
* raw queries with escaped parameters
* results as instances of the Model class
* exceptions on query error

Requirements & support
----------------------

* PHP >= 8.3
* PDO with MySQL/MariaDB or PostgreSQL drivers
* SQLite is supported in code paths but not covered by tests

Usage
=====

With a MySQL database on localhost:

```sql
CREATE DATABASE categorytest;
CREATE TABLE `categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
```

```php
require_once('vendor/autoload.php');
Freshsauce\Model\Model::connectDb('mysql:dbname=categorytest;host=127.0.0.1', 'root', '');

// minimum model definition
class Category extends Freshsauce\Model\Model {
  static protected $_tableName = 'categories';
}
```

PostgreSQL schema and connection:

```sql
CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  name VARCHAR(120) NULL,
  updated_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL
);
```

```php
Freshsauce\Model\Model::connectDb('pgsql:host=127.0.0.1;port=5432;dbname=categorytest', 'postgres', 'postgres');
```

Testing
=======

Run PHPUnit with optional environment overrides for the DB connection:

```
MODEL_ORM_TEST_DSN=mysql:host=127.0.0.1;port=3306
MODEL_ORM_TEST_USER=root
MODEL_ORM_TEST_PASS=

vendor/bin/phpunit -c phpunit.xml.dist
```

Static analysis:

```
vendor/bin/phpstan analyse -c phpstan.neon
```

Save & Update records
=====================

```php
$newCategory = new Category(array(
  'name' => 'test'
));
$newCategory->save();
```

as Id is not set inserts the data as a new table row, `$newCategory->id` is set that of the row inserted post insert

```php
$newCategory->name = 'changed name';
$newCategory->save();
```

Now updates existing record

```php
new Category(array(
  'name' => 'second test'
))->save();
```

Explicit `->insert()` and `->update()` methods are available. `->save()` is a wrapper around these.
If no dirty fields are present, `insert()` uses DEFAULT VALUES and `update()` returns false.

All methods call `->validate()` before carrying out their operation. To add your own validation, override this method and throw an Exception on validation error.

Find a single record by primary key
===================================

Returns an object for the matching row, or null if not found.

```php
$cat = Category::getById(1);
```

Delete record(s)
==================

Via an instance method

```php
$cat = Category::getById(1);
$cat?->delete();
```

OR a Class method (primary key deletes only)

```php
Category::deleteById(1);
```
    
OR  all records matching a where clause

```php
Category::deleteAllWhere('name = ?', array('changed name'));
```
    
OR  all records matching a where clause, specifying order and limits with more regular SQL

```php
Category::deleteAllWhere('name = ? ORDER BY name DESC LIMIT 2', array('changed name'));
```

Dynamic field name finders & counters
=====================================

Return an object for the first matching the name

```php
Category::findOne_by_name('changed name');
```

CamelCase alternatives are also supported:

```php
Category::findOneByName('changed name');
```

Return an object for the first match from the names

```php
Category::findOne_by_name(array('changed name', 'second test'));
```

Return an array of objects that match the name

```php
Category::find_by_name('changed name');
```

```php
Category::findByName('changed name');
```

Return an array of objects that match the names

```php
Category::find_by_name(array('changed name', 'second test'));
```

Return the first record by ascending field 'name' as a Category object

```php
Category::first_by_name('john');  // can also pass an array of values to match
```

Return the last record in the table when sorted by ascending field 'name' as a Category object

```php
Category::last_by_name('john');   // can also pass an array of values to match
```

Return a count of records that match the name

```php
Category::count_by_name('changed name');
```

Return a count of records that match a set of values

```php
Category::count_by_name(array('changed name', 'second test'));
```

First, last & Count
===================

return the first record by ascending primary key as a Category object (or null)

```php
Category::first();
```

return the last record in the table when sorted by ascending primary key as a Category object (or null)

```php
Category::last();
```

Return the number of rows in the table

```php
Category::count();
```

Arbitrary Statements
====================

run an arbitrary statement returning a PDO statement handle to issue fetch etc... on

```php
$st = Freshsauce\Model\Model::execute(
  'SELECT * FROM categories WHERE id = ? AND id = ? AND id > ?',
  array(1, 2, 6)
);
```

Find One Or All
===============

Custom SQL after the WHERE keyword returning the first match or all matches as Model instances.
`fetchOneWhere()` returns null when no rows match.

fetch one Category object with a custom WHERE ... clause

```php
$cat = Category::fetchOneWhere('id = ? OR name = ?', array(1, 'test'));
```

Fetch array of Category objects with a custom WHERE ... clause

```php
$cat = Category::fetchAllWhere('id = ? OR name = ?', array(1, 'second test'));
```
    
Fetch array of Category objects, as above but this time getting the second one if it exists ordered by ascending name

```php
$cat = Category::fetchAllWhere('id = ? OR name = ? ORDER BY name ASC LIMIT 1,1', array(1, 'second test'));
```

General SQL Helpers
===================

Generate placeholders for an IN clause

```php
$params = array(1, 2, 3, 4);
$placeholders = Freshsauce\Model\Model::createInClausePlaceholders($params); // returns '?,?,?,?'
Category::fetchAllWhere('id IN (' . $placeholders . ')', $params);
```

Take a date string or unix datetime number and return a string that can be assigned to a TIMESTAMP or DATETIME field
date strings are parsed into a unix date via PHP's incredibly flexible strtotime()

```php
Freshsauce\Model\Model::datetimeToMysqldatetime('2012 Sept 13th 12:00');
Freshsauce\Model\Model::datetimeToMysqldatetime('next Monday');
Freshsauce\Model\Model::datetimeToMysqldatetime(gmdate());
```
