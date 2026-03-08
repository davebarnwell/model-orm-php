<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 */
class SchemaQualifiedCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'orm_phase3.schema_categories';
}
