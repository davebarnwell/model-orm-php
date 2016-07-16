<?php

/**
 * Assumes a database called categorytest exists
 * with a table like
 * @property int    $id         primary key
 * @property string $name       category name
 * @property string $updated_at mysql datetime string
 * @property string $created_at mysql datetime string
 *
 * @inheritdoc
 */

namespace App\Model;

class Category extends \Freshsauce\Model\Model
{
    static protected $_tableName = 'categories'; // database table name

}
