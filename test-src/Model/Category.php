<?php

namespace App\Model;

/**
 * @method static array findByName($match)
 * @method static self|null findOneByName($match)
 * @method static self|null firstByName($match)
 * @method static self|null lastByName($match)
 * @method static int countByName($match)
 * @method static self|null findOneByUpdatedAt($match)
 * Legacy snake_case dynamic methods remain temporarily supported and emit deprecation notices.
 * @property int|null $id primary key
 * @property string|null $name category name
 * @property string|null $updated_at mysql datetime string
 * @property string|null $created_at mysql datetime string
 *
 * @inheritdoc
 */
class Category extends \Freshsauce\Model\Model
{
    protected static $_tableName = 'categories'; // database table name

}
