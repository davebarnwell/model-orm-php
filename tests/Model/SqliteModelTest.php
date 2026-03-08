<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\SkippedTestSuiteError;

class SqliteModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            throw new SkippedTestSuiteError('The pdo_sqlite extension is required to run SQLite-specific tests.');
        }

        App\Model\SqliteCategory::connectDb('sqlite::memory:', '', '');
        App\Model\SqliteCategory::execute(
            'CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NULL,
                updated_at TEXT NULL,
                created_at TEXT NULL
            )'
        );
    }

    protected function setUp(): void
    {
        App\Model\SqliteCategory::execute('DELETE FROM `categories`');
        $this->resetSqliteSequenceIfPresent();
    }

    private function resetSqliteSequenceIfPresent(): void
    {
        try {
            App\Model\SqliteCategory::execute('DELETE FROM sqlite_sequence WHERE name = ?', ['categories']);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'no such table: sqlite_sequence') === false) {
                throw $e;
            }
        }
    }

    public function testSqliteInsertWithDirtyFields(): void
    {
        /** @var App\Model\SqliteCategory $category */
        $category = new App\Model\SqliteCategory([
            'name' => 'SQLite Fiction',
        ]);

        $this->assertTrue($category->save());
        $this->assertSame('SQLite Fiction', $category->name);
        $this->assertSame('1', (string) $category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);

        /** @var App\Model\SqliteCategory|null $reloaded */
        $reloaded = App\Model\SqliteCategory::getById((int) $category->id);

        $this->assertNotNull($reloaded);
        $this->assertSame('SQLite Fiction', $reloaded->name);
    }

    public function testPreparedStatementsStayBoundToTheirOwnConnection(): void
    {
        App\Model\IsolatedConnectionCategoryA::connectDb('sqlite::memory:', '', '');
        App\Model\IsolatedConnectionCategoryB::connectDb('sqlite::memory:', '', '');

        App\Model\IsolatedConnectionCategoryA::execute(
            'CREATE TABLE items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NULL
            )'
        );
        App\Model\IsolatedConnectionCategoryB::execute(
            'CREATE TABLE items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NULL
            )'
        );

        App\Model\IsolatedConnectionCategoryA::execute('INSERT INTO `items` (`name`) VALUES (?)', ['from-a']);
        App\Model\IsolatedConnectionCategoryB::execute('INSERT INTO `items` (`name`) VALUES (?)', ['from-b']);

        /** @var App\Model\IsolatedConnectionCategoryA|null $fromA */
        $fromA = App\Model\IsolatedConnectionCategoryA::fetchOneWhere('`id` = ?', [1]);
        /** @var App\Model\IsolatedConnectionCategoryB|null $fromB */
        $fromB = App\Model\IsolatedConnectionCategoryB::fetchOneWhere('`id` = ?', [1]);

        $this->assertNotNull($fromA);
        $this->assertNotNull($fromB);
        $this->assertSame('from-a', $fromA->name);
        $this->assertSame('from-b', $fromB->name);
    }
}
