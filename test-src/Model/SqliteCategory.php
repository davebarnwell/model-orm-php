<?php

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $updated_at
 * @property string|null $created_at
 */
class SqliteCategory extends \Freshsauce\Model\Model
{
    public static $_db;

    protected static $_tableName = 'categories';
}
