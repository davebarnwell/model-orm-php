<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $created_on
 * @property string|null $modified_on
 */
class CustomTimestampCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'custom_timestamp_categories';

    protected static ?string $_created_at_column = 'created_on';

    protected static ?string $_updated_at_column = 'modified_on';
}
