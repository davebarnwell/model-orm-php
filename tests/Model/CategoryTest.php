<?php

/**
 * Class modelTest
 */
Class CategoryTest extends \PHPUnit_Framework_TestCase
{


    /**
     *
     */
    public static function setUpBeforeClass() {
        // connect and setup db categorytest with table categories
        try {
            Freshsauce\Model\Model::connectDb('mysql:host='.$_ENV['$DOCKER_IP'].';dbname=unit_test', 'unit_test_user', 'unit_test_password');
        } catch (PDOException $e) {
            if ($e->getCode() != 0) {
                // throw it on
                throw $e;
            }
        }

        $sql_setup = [
            'DROP DATABASE IF EXISTS `categorytest`',
            'CREATE DATABASE `categorytest`',
            'USE `categorytest`',
            'CREATE TABLE `categories` (
             `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
             `name` VARCHAR(120) DEFAULT NULL,
             `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
             `created_at` TIMESTAMP NULL DEFAULT NULL,
             PRIMARY KEY (`id`)
           ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8',
        ];

        foreach ($sql_setup as $sql) {
            Freshsauce\Model\Model::execute($sql);
        }

    }

    /**
     *
     */
    public static function tearDownAfterClass() {
        Freshsauce\Model\Model::execute('DROP DATABASE IF EXISTS `categorytest`');
    }

    /**
     * @covers ::save
     */
    public function testCreate() {
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
    public function testCreateAndGetById() {
        $_name    = 'SciFi';
        $category = new App\Model\Category(array(
            'name' => $_name
        ));

        $category->save(); // no Id so will insert

        $this->assertEquals($category->name, $_name);
        $this->assertNotEmpty($category->id);

        // read category back into a new object
        $read_category = App\Model\Category::getById($category->id);
        $this->assertEquals($read_category->name, $_name);
        $this->assertEquals($read_category->id, $category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
    }

    /**
     * @covers ::save
     */
    public function testCreateAndModify() {
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
     * @covers ::__callStatic
     * @covers ::fetchAllWhereMatchingSingleField
     */
    public function testFetchAllWhere() {
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
}