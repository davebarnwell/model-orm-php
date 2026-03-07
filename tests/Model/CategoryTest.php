<?php

use PHPUnit\Framework\TestCase;

/**
 * Class modelTest
 */
class CategoryTest extends TestCase
{
    private const TEST_DB_NAME = 'categorytest';
    private const SQLITE_SEQUENCE_TABLE = 'sqlite_sequence';
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
        } elseif (self::$driverName === 'sqlite') {
            $sql_setup = [
                'DROP TABLE IF EXISTS `categories`',
                'CREATE TABLE `categories` (
                 `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                 `name` VARCHAR(120) NULL,
                 `updated_at` TEXT NULL,
                 `created_at` TEXT NULL
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

    public function setUp(): void
    {
        if (self::$driverName === 'mysql') {
            Freshsauce\Model\Model::execute('TRUNCATE TABLE `categories`');
        } elseif (self::$driverName === 'pgsql') {
            Freshsauce\Model\Model::execute('TRUNCATE TABLE "categories" RESTART IDENTITY');
        } elseif (self::$driverName === 'sqlite') {
            Freshsauce\Model\Model::execute('DELETE FROM `categories`');
            Freshsauce\Model\Model::execute(
                'DELETE FROM `' . self::SQLITE_SEQUENCE_TABLE . '` WHERE `name` = ?',
                ['categories']
            );
        }
    }

    private function createCategory(string $name, array $data = []): App\Model\Category
    {
        $category = new App\Model\Category(array_merge(['name' => $name], $data));
        $category->save();

        return $category;
    }

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

    public function testCreateAndGetById(): void
    {
        $_name    = 'SciFi';
        $category = $this->createCategory($_name);

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

    public function testFetchOneWhereReturnsNullWhenMissing(): void
    {
        $missing = App\Model\Category::fetchOneWhere('name = ?', ['__missing__' . uniqid('', true)]);
        $this->assertNull($missing);
    }

    public function testCreateAndModify(): void
    {
        $_name    = 'Literature';
        $category = $this->createCategory($_name);

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

    public function testInsertWithDefaultValuesWhenNoDirtyFields(): void
    {
        $category = new App\Model\Category();
        $category->clearDirtyFields();

        $this->assertTrue($category->insert(false));
        $this->assertNotEmpty($category->id);
    }

    public function testUpdateWithoutDirtyFieldsReturnsFalse(): void
    {
        $category = $this->createCategory('Nonfiction');
        $category->clearDirtyFields();

        $this->assertFalse($category->update(false));
    }

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
        $categories = App\Model\Category::findByName($_names);
        $this->assertNotEmpty($categories);
        $this->assertContainsOnlyInstancesOf('App\Model\Category', $categories);
        $this->assertCount(count($_names), $categories);
    }

    public function testModelStateHelpers(): void
    {
        $category = new App\Model\Category();
        $category->clearDirtyFields();

        $this->assertTrue($category->hasData());
        $this->assertTrue($category->dataPresent());
        $this->assertTrue(isset($category->name));
        $this->assertNull($category->name);
        $this->assertFalse($category->isFieldDirty('name'));

        $category->name = 'History';
        $this->assertTrue($category->isFieldDirty('name'));

        $data = $category->toArray();
        $this->assertSame('History', $data['name']);
        $this->assertSame(['id', 'name', 'updated_at', 'created_at'], $category->__sleep());

        $category->clear();
        $this->assertNull($category->name);
        $this->assertFalse($category->isFieldDirty('name'));
    }

    public function testMagicGetThrowsForMissingDataAndUnknownField(): void
    {
        $reflection = new ReflectionClass(App\Model\Category::class);
        /** @var App\Model\Category $categoryWithoutConstructor */
        $categoryWithoutConstructor = $reflection->newInstanceWithoutConstructor();

        $this->assertFalse($categoryWithoutConstructor->hasData());

        try {
            $categoryWithoutConstructor->dataPresent();
            $this->fail('Expected missing data exception.');
        } catch (Exception $exception) {
            $this->assertSame('No data', $exception->getMessage());
        }

        $category = new App\Model\Category();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Undefined property via __get(): unknown_field');
        $category->__get('unknown_field');
    }

    public function testRecordLifecycleHelpers(): void
    {
        $first = $this->createCategory('Alpha');
        $second = $this->createCategory('Beta');
        $third = $this->createCategory('Gamma');

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);
        $this->assertNotNull($third->id);

        $this->assertSame(3, App\Model\Category::count());
        $this->assertSame((int) $first->id, (int) App\Model\Category::first()?->id);
        $this->assertSame((int) $third->id, (int) App\Model\Category::last()?->id);

        /** @var App\Model\Category[] $found */
        $found = App\Model\Category::find((int) $second->id);
        $this->assertCount(1, $found);
        $this->assertSame((int) $second->id, (int) $found[0]->id);

        $matching = App\Model\Category::fetchAllWhere('name IN (?, ?)', ['Alpha', 'Gamma']);
        $this->assertCount(2, $matching);

        $this->assertTrue($third->delete());
        $this->assertTrue(App\Model\Category::deleteById((int) $second->id));
        $this->assertFalse(App\Model\Category::deleteById(999999));

        $statement = App\Model\Category::deleteAllWhere('name = ?', ['Alpha']);
        $this->assertSame(1, $statement->rowCount());
        $this->assertSame(0, App\Model\Category::count());
    }

    public function testDynamicFindersCamelCase(): void
    {
        $_names = [
            'Camel_' . uniqid('a_', true),
            'Camel_' . uniqid('b_', true),
        ];
        foreach ($_names as $_name) {
            $this->createCategory($_name);
        }

        $categories = App\Model\Category::findByName($_names);
        $this->assertCount(count($_names), $categories);

        $one = App\Model\Category::findOneByName($_names[0]);
        $this->assertNotNull($one);
        $this->assertSame($_names[0], $one->name);

        $first = App\Model\Category::firstByName($_names);
        $this->assertNotNull($first);
        $this->assertContains($first->name, $_names);

        $last = App\Model\Category::lastByName($_names);
        $this->assertNotNull($last);
        $this->assertContains($last->name, $_names);

        $count = App\Model\Category::countByName($_names);
        $this->assertSame(count($_names), $count);
    }

    public function testDynamicFindersCamelCaseResolveSnakeCaseColumns(): void
    {
        $category = $this->createCategory('Timestamp_' . uniqid('', true));
        $one = App\Model\Category::findOneByUpdatedAt($category->updated_at);
        $this->assertNotNull($one);
        $this->assertSame((int) $category->id, (int) $one->id);
    }

    public function testDynamicFindersSnakeCaseEmitDeprecation(): void
    {
        $_names = [
            'Snake_' . uniqid('a_', true),
            'Snake_' . uniqid('b_', true),
        ];
        foreach ($_names as $_name) {
            $this->createCategory($_name);
        }

        $this->assertSame(count($_names), $this->captureUserDeprecation(
            'Dynamic snake_case model methods are deprecated. Use countByName instead of count_by_name.',
            static fn () => App\Model\Category::__callStatic('count_by_name', [$_names])
        ));

        /** @var App\Model\Category[] $categories */
        $categories = $this->captureUserDeprecation(
            'Dynamic snake_case model methods are deprecated. Use findByName instead of find_by_name.',
            static fn () => App\Model\Category::__callStatic('find_by_name', [$_names])
        );
        $this->assertCount(count($_names), $categories);

        /** @var App\Model\Category|null $one */
        $one = $this->captureUserDeprecation(
            'Dynamic snake_case model methods are deprecated. Use findOneByName instead of findOne_by_name.',
            static fn () => App\Model\Category::__callStatic('findOne_by_name', [$_names[0]])
        );
        $this->assertNotNull($one);
        $this->assertSame($_names[0], $one->name);

        /** @var App\Model\Category|null $first */
        $first = $this->captureUserDeprecation(
            'Dynamic snake_case model methods are deprecated. Use firstByName instead of first_by_name.',
            static fn () => App\Model\Category::__callStatic('first_by_name', [$_names])
        );
        $this->assertNotNull($first);
        $this->assertContains($first->name, $_names);

        /** @var App\Model\Category|null $last */
        $last = $this->captureUserDeprecation(
            'Dynamic snake_case model methods are deprecated. Use lastByName instead of last_by_name.',
            static fn () => App\Model\Category::__callStatic('last_by_name', [$_names])
        );
        $this->assertNotNull($last);
        $this->assertContains($last->name, $_names);
    }

    public function testInsertAllowsExplicitPrimaryKey(): void
    {
        $category = new App\Model\Category([
            'id' => 999,
            'name' => 'With explicit id',
        ]);

        $this->assertTrue($category->insert(false, true));
        $this->assertSame(999, (int) $category->id);
        $this->assertSame(999, (int) App\Model\Category::getById(999)?->id);
    }

    public function testUtilityHelpers(): void
    {
        $this->assertSame('?,?,?', App\Model\Category::createInClausePlaceholders([1, 2, 3]));
        $this->assertSame('1970-01-01 00:00:00', App\Model\Category::datetimeToMysqldatetime('not-a-date'));
        $this->assertSame('2023-11-14 22:13:20', App\Model\Category::datetimeToMysqldatetime(1700000000));
    }

    public function testUnknownDynamicMethodThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Freshsauce\Model\Model not such static method[doesNotExist]');

        App\Model\Category::__callStatic('doesNotExist', ['value']);
    }

    private function captureUserDeprecation(string $expectedMessage, callable $callback): mixed
    {
        $result = null;
        $captured = null;

        set_error_handler(static function (int $severity, string $message) use (&$captured): bool {
            if ($severity !== E_USER_DEPRECATED) {
                return false;
            }
            $captured = $message;
            return true;
        });

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame($expectedMessage, $captured);

        return $result;
    }
}
