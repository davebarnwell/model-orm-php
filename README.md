Model
=====

PHP Model class which provides, Requires PHP >= 5.3

* table column/property mapping,
* CRUD
* dynamic finders on a database table
* dynamic counters on a database table
* raw database queries with escaped parameters
* helpers for finding one or more records returning rows as instances of the Model class
* throws exception on query error

Usage
=====

With a mysql database as such on your localhost

    CREATE DATABASE categorytest;
    CREATE TABLE `categories` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(120) DEFAULT NULL,
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      `created_at` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

    require_once('model.php');
    db\Model::connectDb('mysql:dbname=categorytest;host=127.0.0.1','root','');    // db connection for all sub-classes

    // minimum model definition
    class Category extends db\Model {
      static protected $_tableName = 'categories'; // database table name
    }

Save & Update records
=====================

    $newCategory = new Category(array(
      'name' => 'test'
    ));
    $newCategory->save();

as Id is not set inserts the data as a new table row, `$newCategory->id` is set that of the row inserted post insert

    $newCategory->name = 'changed name';
    $newCategory->save();

now updates existing record

    new Category(array(
      'name' => 'second test'
    ))->save();
    
explict `->insert()` and `->update()` methods are available, `->save()` is a wrapper around these

All methods call `->validate()` before carrying out their operation, to do your own validation overide this method in your class and throw an Exception on validation error.

Find a single record by primary key
===================================

returns an object for the matching row

    $cat = Category::getById(1);

Delete record(s)
==================

Via an instance method

    $cat = Category::getById(1);
    $cat->delete();

OR a Class method (primary key deletes only)

    Category::deleteById(1);
    
OR  all records matching a where clause

    Category::deleteAllWhere('name = ?',array('changed name'));
    
OR  all records matching a where clause, specifying order and limits with more regular SQL

    Category::deleteAllWhere('name = ? ORDER BY name DESC LIMIT 2',array('changed name'));

Dynamic field name finders & counters
=====================================

Return an array of objects that match the name

    Category::find_by_name('changed name');

Return an array of objects that match the names

    Category::find_by_name(array('changed name','second test'));


Return a count of records that match the name

    Category::count_by_name('changed name');

Return a count of records that match a set of values

    Category::count_by_name(array('changed name','second test'));

First, last & Count
===================

return the first record by ascending primary key as a Catgory object

    Category::first();

return the last record in the table when sorted by ascending primary key as a Catgory object

    Category::last();

return the number of rows in the table

    Catgory::count();

Arbitary Statements
===================

run an arbitary statement returning a PDO statement handle to issue fetch etc... on

    $st = db\Model::execute('SELECT * FROM categoies WHERE id = ? AND id = ? AND id > ?', array(1,2,6));

Find One Or All
===============

custom SQL after the WHERE keyword returning the first match or all matches as Model instances

fetch one Category object with a custom WHERE ... clause

    $cat = Category::fetchOneWhere('id = ? OR name = ?',array(1,'test'));

fetch array of Category objects with a custom WHERE ... clause

    $cat = Category::fetchAllWhere('id = ? OR name = ?',array(1,'second test'));
    
fetch array of Category objects, as above but this time getting the second one if it exists ordered by ascending name

    $cat = Category::fetchAllWhere('id = ? OR name = ? ORDER BY name ASC LIMIT 1,1',array(1,'second test'));

General SQL Helpers
===================

Generate placeholders for an IN clause

    $params = array(1,2,3,4);
    $placeholders = db\Model::createInClausePlaceholders($params);    // returns a string '?,?,?,?
    Category::fetchAllWhere('id IN ('.$placeholders.')',$params);     // use use in a query

Take a date string or unix datetime number and return a string that can be assigned to a TIMESTAMP or DATETIME field
date strings are parsed into a unix date via PHPs incredibly flexible strtotime()

    db\Model::datetimeToMysqldatetime('2012 Sept 13th 12:00');  // returns '2012-09-13 12:00:00'
    db\Model::datetimeToMysqldatetime('next Monday');  // returns next monday midnight in the format 'YYYY-MM-DD HH:MM:SS'
    db\Model::datetimeToMysqldatetime(gmdate());  // returns the current date time in the format 'YYYY-MM-DD HH:MM:SS'
    