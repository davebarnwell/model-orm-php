<?php

use PHPUnit\Framework\TestCase;

/**
 * Class modelTest
 */
class CategoryTest extends TestCase
{
    private const TEST_DB_NAME = 'categorytest';
    private static ?string $driverName = null;

    /**
     *
     */
    public static function setUpBeforeClass(): void
    {
        // connect and setup db categorytest with table categories
        $dsn  = getenv('MODEL_ORM_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306';
        $user = getenv('MODEL_ORM_TEST_USER') ?: 'root';
        $pass = getenv('MODEL_ORM_TEST_PASS') ?: '';
        try {
            Freshsauce\Model\Model::connectDb($dsn, $user, $pass);
        } catch (PDOException $e) {
            if ($e->getCode() != 0) {
                // throw it on
                throw $e;
            }
        }

        self::$driverName = Freshsauce\Model\Model::driverName();
        $sql_setup = [];
        if (self::$driverName === 'mysql') {
            $sql_setup = [
                'DROP DATABASE IF EXISTS `' . self::TEST_DB_NAME . '`',
                'CREATE DATABASE `' . self::TEST_DB_NAME . '`',
                'USE `' . self::TEST_DB_NAME . '`',
                'CREATE TABLE `categories` (
                 `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                 `name` VARCHAR(120) DEFAULT NULL,
                 `updated_at` TIMESTAMP NULL DEFAULT NULL,
                 `created_at` TIMESTAMP NULL DEFAULT NULL,
                 PRIMARY KEY (`id`)
               ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8',
            ];
        } elseif (self::$driverName === 'pgsql') {
            $sql_setup = [
                'DROP TABLE IF EXISTS "categories"',
                'CREATE TABLE "categories" (
                 "id" SERIAL PRIMARY KEY,
                 "name" VARCHAR(120) NULL,
                 "updated_at" TIMESTAMP NULL,
                 "created_at" TIMESTAMP NULL
               )',
            ];
        } else {
            throw new RuntimeException('Unsupported PDO driver for tests: ' . self::$driverName);
        }

        foreach ($sql_setup as $sql) {
            Freshsauce\Model\Model::execute($sql);
        }

    }

    /**
     *
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$driverName === 'mysql') {
            Freshsauce\Model\Model::execute('DROP DATABASE IF EXISTS `' . self::TEST_DB_NAME . '`');
        } elseif (self::$driverName === 'pgsql') {
            Freshsauce\Model\Model::execute('DROP TABLE IF EXISTS "categories"');
        }
    }

    /**
     * @covers ::save
     */
    public function testCreate(): void
    {
        $_name    = 'Fiction';
        $category = new App\Model\Category(array(
            'name' => $_name
        ));

        $category->save(); // no Id so will insert

        $this->assertEquals($category->name, $_name);
        $this->assertNotEmpty($category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
    }

    /**
     * @covers ::getById
     */
    public function testCreateAndGetById(): void
    {
        $_name    = 'SciFi';
        $category = new App\Model\Category(array(
            'name' => $_name
        ));

        $category->save(); // no Id so will insert

        $this->assertEquals($category->name, $_name);
        $this->assertNotEmpty($category->id);

        // read category back into a new object
        $read_category = App\Model\Category::getById($category->id);
        $this->assertNotNull($read_category);
        $this->assertEquals($read_category->name, $_name);
        $this->assertEquals($read_category->id, $category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
    }

    /**
     * @covers ::fetchOneWhere
     */
    public function testFetchOneWhereReturnsNullWhenMissing(): void
    {
        $missing = App\Model\Category::fetchOneWhere('name = ?', ['__missing__' . uniqid('', true)]);
        $this->assertNull($missing);
    }

    /**
     * @covers ::save
     */
    public function testCreateAndModify(): void
    {
        $_name    = 'Literature';
        $category = new App\Model\Category(array(
            'name' => $_name
        ));

        $category->save(); // no Id so will insert

        $this->assertEquals($category->name, $_name);
        $this->assertNotEmpty($category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);

        $_id         = $category->id;
        $_updated_at = $category->updated_at;

        $_new_name      = 'Literature - great works';
        $category->name = $_new_name;
        sleep(1); // to ensure updated_at time move forward a second at least
        $category->save();

        $this->assertEquals($category->name, $_new_name);
        $this->assertEquals($category->id, $_id);
        $this->assertNotEquals($category->updated_at, $_updated_at);

    }

    /**
     * @covers ::insert
     */
    public function testInsertWithDefaultValuesWhenNoDirtyFields(): void
    {
        $category = new App\Model\Category();
        $category->clearDirtyFields();

        $this->assertTrue($category->insert(false));
        $this->assertNotEmpty($category->id);
    }

    /**
     * @covers ::update
     */
    public function testUpdateWithoutDirtyFieldsReturnsFalse(): void
    {
        $category = new App\Model\Category(array(
            'name' => 'Nonfiction'
        ));
        $category->save();
        $category->clearDirtyFields();

        $this->assertFalse($category->update(false));
    }

    /**
     * @covers ::__callStatic
     * @covers ::fetchAllWhereMatchingSingleField
     */
    public function testFetchAllWhere(): void
    {
        // Create some categories
        $_names = [
            'Sports',
            'Politics',
            'Biography',
            'Cookbooks'
        ];
        foreach ($_names as $_name) {
            $category = new App\Model\Category(array(
                'name' => $_name
            ));
            $category->save(); // no Id so will insert
        }
        $categories = App\Model\Category::find_by_name($_names);
        $this->assertNotEmpty($categories);
        $this->assertContainsOnlyInstancesOf('App\Model\Category', $categories);
        $this->assertCount(count($_names), $categories);
    }

    /**
     * @covers ::__callStatic
     * @covers ::fetchAllWhereMatchingSingleField
     * @covers ::fetchOneWhereMatchingSingleField
     * @covers ::countAllWhere
     */
    public function testDynamicFindersCamelCase(): void
    {
        $_names = [
            'Camel_' . uniqid('a_', true),
            'Camel_' . uniqid('b_', true),
        ];
        foreach ($_names as $_name) {
            $category = new App\Model\Category(array(
                'name' => $_name
            ));
            $category->save();
        }

        $categories = App\Model\Category::findByName($_names);
        $this->assertCount(count($_names), $categories);

        $one = App\Model\Category::findOneByName($_names[0]);
        $this->assertNotNull($one);
        $this->assertEquals($_names[0], $one->name);

        $first = App\Model\Category::firstByName($_names);
        $this->assertNotNull($first);
        $this->assertContains($first->name, $_names);

        $last = App\Model\Category::lastByName($_names);
        $this->assertNotNull($last);
        $this->assertContains($last->name, $_names);

        $count = App\Model\Category::countByName($_names);
        $this->assertEquals(count($_names), $count);
    }
}
