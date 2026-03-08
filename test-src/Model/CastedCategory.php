<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int|null $id
 * @property string|null $name
 * @property int|null $quantity
 * @property float|null $rating
 * @property bool|null $is_active
 * @property \DateTimeImmutable|null $published_at
 * @property array<mixed>|null $meta_array
 * @property object|null $meta_object
 */
class CastedCategory extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'casted_categories';

    protected static array $_casts = [
        'quantity' => 'integer',
        'rating' => 'float',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'meta_array' => 'array',
        'meta_object' => 'object',
    ];
}
