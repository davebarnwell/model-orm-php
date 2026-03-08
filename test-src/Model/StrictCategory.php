<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $updated_at
 * @property string|null $created_at
 */
class StrictCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'categories';

    protected static bool $_strict_fields = true;
}
