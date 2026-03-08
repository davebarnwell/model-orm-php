<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $updated_at
 * @property string|null $created_at
 */
class ValidatingCategory extends \Freshsauce\Model\Model
{
    /**
     * @var array<int, string>
     */
    public static array $validationLog = [];

    protected static $_tableName = 'categories';

    protected function validateForSave(): void
    {
        self::$validationLog[] = 'save:' . (string) $this->name;
        if (trim((string) $this->name) === '') {
            throw new \RuntimeException('Name is required');
        }
    }

    protected function validateForInsert(): void
    {
        self::$validationLog[] = 'insert:' . (string) $this->name;
    }

    protected function validateForUpdate(): void
    {
        self::$validationLog[] = 'update:' . (string) $this->name;
    }
}
