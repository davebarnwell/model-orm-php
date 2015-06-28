<?php
// Assumes database
//   CREATE DATABASE categorytest;
//   CREATE TABLE `categories` (
//     `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
//     `name` varchar(120) DEFAULT NULL,
//     `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
//     `created_at` timestamp NULL DEFAULT NULL,
//     PRIMARY KEY (`id`)
//   ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
require_once('model.php');
db\Model::connectDb('mysql:dbname=categorytest;host=127.0.0.1', 'root', '');

// Assumes a database called categorytest exists
// with a table like
//
//
class Category extends db\Model {
  static protected $_tableName = 'categories'; // database table name
}

try {


  echo "Create name=test\n";
  echo "================\n";
  $newCategory = new Category(array(
    'name' => 'test'
  ));
  $newCategory->save(); // no Id so will insert
  var_dump($newCategory);
  echo "------------------------------------------------------\n\n";

  echo "Change name to=new name\n";
  echo "=======================\n";
  $newCategory->name = 'new name';
  $newCategory->save(); // has id now so will update
  var_dump($newCategory);
  echo "------------------------------------------------------\n\n";

  echo "Fetch last object into a new Object\n";
  echo "===================================\n";
  $category = Category::getById($newCategory->id); // read  category into a new object
  var_dump($category);
  echo "------------------------------------------------------\n\n";
  
  echo "Fetch All\n";
  echo "=========\n";
  $categories = Category::fetchAllWhere('1=1');
  var_dump($categories);
  echo "------------------------------------------------------\n\n";

} catch (Exception $e) {
  echo "ERROR\n";
  echo "*****\n";
  var_dump($e);
}

