<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property string|null $code
 * @property string|null $name
 */
class SqliteStringCodeCategory extends \Freshsauce\Model\Model
{
    public static $_db;

    protected static $_primary_column_name = 'code';

    protected static $_tableName = 'string_code_categories';
}
