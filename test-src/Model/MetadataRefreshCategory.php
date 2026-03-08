<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property string|null $description
 */
class MetadataRefreshCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'metadata_refresh_categories';
}
