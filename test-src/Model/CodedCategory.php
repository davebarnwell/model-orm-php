<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $code
 * @property string|null $name
 */
class CodedCategory extends \Freshsauce\Model\Model
{
    protected static $_primary_column_name = 'code';

    protected static $_tableName = 'coded_categories';
}
