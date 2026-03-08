<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 */
class UntimedCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'untimed_categories';
}
