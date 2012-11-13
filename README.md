Model
=====

PHP Model class which provides, Requires PHP >= 5.3

* table column/property mapping,
* CRUD
* dynamic finders on a database table
* dynamic counters on a database table
* raw database queries with escaped parameters
* helpers for finding one or more records returning rows as instances of the Model class

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

Delete a record
===============

Via an instance method

    $cat = Category::find_by_id(1);
    $cat->delete();

OR a Class method (primary key deletes only)

    Category::deleteById(1);

dynamic field name finders
==========================

    Category::find_by_name('changed name'); // returns null or matching entry as a Category object
    Category::count_by_name(array('changed name','second test')); // counts records with names that match

First & last
============

return the first record by ascending primary key as a Catgory object

    Category::first();

return the last record in the table when sorted by ascending primary key as a Catgory object

    Category::last();

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

