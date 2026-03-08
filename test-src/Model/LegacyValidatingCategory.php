<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $updated_at
 * @property string|null $created_at
 */
class LegacyValidatingCategory extends \Freshsauce\Model\Model
{
    public static int $validateCalls = 0;

    protected static $_tableName = 'categories';

    public static function validate(): bool
    {
        self::$validateCalls++;

        return true;
    }
}
