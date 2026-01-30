<?php

namespace App\Model;

/**
 * @method static array find_by_name($match)
 * @property int|null $id primary key
 * @property string|null $name category name
 * @property string|null $updated_at mysql datetime string
 * @property string|null $created_at mysql datetime string
 *
 * @inheritdoc
 */
class Category extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'categories'; // database table name

}
