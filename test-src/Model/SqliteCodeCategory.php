<?php

namespace App\Model;

/**
 * @property int|null $code
 * @property string|null $name
 */
class SqliteCodeCategory extends \Freshsauce\Model\Model
{
    public static $_db;

    protected static $_primary_column_name = 'code';

    protected static $_tableName = 'code_categories';
}
