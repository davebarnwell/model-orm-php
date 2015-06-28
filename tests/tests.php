<?php
require_once(dirname(__DIR__) . '/model.php');
require_once(__DIR__ . '/category.php');

Class modelTest extends \PHPUnit_Framework_TestCase {
  
  
    public static function setUpBeforeClass() {
        // connect and setup db categorytest with table categories
        try {
            db\Model::connectDb('mysql:host=127.0.0.1;dbname=test', 'root', '');
        } catch (PDOException $e) {
            if ($e->getCode() != 0) {
                // throw it on
                throw $e;
            }
        }

        db\Model::execute('DROP DATABASE IF EXISTS `categorytest`');
        db\Model::execute('CREATE DATABASE `categorytest`');
        db\Model::execute('USE `categorytest`');
        db\Model::execute('CREATE TABLE `categories` (
             `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
             `name` varchar(120) DEFAULT NULL,
             `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
             `created_at` timestamp NULL DEFAULT NULL,
             PRIMARY KEY (`id`)
           ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8');
    }

    public static function tearDownAfterClass() {
        db\Model::execute('DROP DATABASE IF EXISTS `categorytest`');
    }
  
    /**
     * @covers ::save
     */
    public function testCreate() {
        $_name = 'Fiction';
        $category = new Category(array(
          'name' => $_name
        ));

        $category->save(); // no Id so will insert
        
        $this->assertEquals($category->name, $_name);
        $this->assertObjectHasAttribute('id', $category);
        $this->assertNotEmpty($category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
    }
    
    /**
     * @covers ::getById
     */
    public function testCreateAndGetById() {
        $_name = 'SciFi';
        $category = new Category(array(
          'name' => $_name
        ));

        $category->save(); // no Id so will insert
        
        $this->assertEquals($category->name, $_name);
        $this->assertObjectHasAttribute('id', $category);
        $this->assertNotEmpty($category->id);
        
        // read category back into a new object
        $read_category = Category::getById($category->id);
        $this->assertEquals($read_category->name, $_name);
        $this->assertEquals($read_category->id, $category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
    }

    /**
     * @covers ::save
     */
    public function testCreateAndModify() {
        $_name = 'Literature';
        $category = new Category(array(
          'name' => $_name
        ));

        $category->save(); // no Id so will insert
        
        $this->assertEquals($category->name, $_name);
        $this->assertObjectHasAttribute('id', $category);
        $this->assertNotEmpty($category->id);
        $this->assertNotEmpty($category->created_at);
        $this->assertNotEmpty($category->updated_at);
        
        $_id = $category->id;
        $_updated_at = $category->updated_at;
        
        $_new_name = 'Literature - great works';
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
        $_names = ['Sports', 'Politics', 'Biography', 'Cookbooks'];
        foreach ($_names as $_name) {
            $category = new Category(array(
              'name' => $_name
            ));
            $category->save(); // no Id so will insert
        }
        $categories = Category::find_by_name($_names);
        $this->assertNotEmpty($categories);
        $this->assertContainsOnlyInstancesOf('Category', $categories);
        $this->assertCount(count($_names), $categories);
    }
}