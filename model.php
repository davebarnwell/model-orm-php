<?php
/**
*  A simple database abstraction layer for PHP 5.3+ with very minor configuration required
*
*  * database table columns are auto detected and made available as public members of the class
*  * provides CRUD, dynamic counters/finders on a database table
*  * uses PDO for data access and exposes PDO if required
*  * class members used to do the magic are preceeded with an underscore, be careful of column names starting with _ in your database!
*  * requires php >=5.3 as uses "Late Static Binding" and namespaces
*
*  BSD Licensed.
*
*  Copyright (c) 2012, Dave Barnwell, www.freshsauce.co.uk, github.com/freshsauce/Model
*  All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without
*  modification, are permitted provided that the following conditions are met:
*
*  * Redistributions of source code must retain the above copyright notice, this
*    list of conditions and the following disclaimer.
*
*  * Redistributions in binary form must reproduce the above copyright notice,
*    this list of conditions and the following disclaimer in the documentation
*    and/or other materials provided with the distribution.
*
*  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
*  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
*  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
*  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
*  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
*  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
*  CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
*  OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
*  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*
*    In your bootstrap
*    db\Model::connectDb('mysql:dbname=testdbname;host=127.0.0.1','testuser','testpassword');
*
*    Extend the model and your set to go
*    class Category extends db\Model {
*      static protected $_tableName = 'categories';   // database table name
*      //static protected $_primary_column_name = 'id'; // define this if your primary key column name is not id
*
*      // add other methods appropriate for your class
*
*    }
*    $category = Category::getById(1);
*/
namespace db;

/**
 * Model ORM 
 *
 * @property created_at optional datatime in table that will automatically get updated on insert
 * @property updated_at optional datatime in table that will automatically get updated on insert/update
 *
 * @package default
 */
class Model
{

  // Class configuration

  public static $_db;  // all models inherit this db connection
                        // but can overide in a sub-class by calling subClass::connectDB(...) sub class must also redeclare public static $_db;

  protected static $_stmt = array(); // prepared statements cache

  protected static $_identifier_quote_character = null;  // character used to quote table & columns names
  private static $_tableColumns = array();             // columns in database table populated dynamically
  // objects public members are created for each table columns dynamically


  // ** OVERIDE THE FOLLOWING as appropriate in your sub-class
  protected static $_primary_column_name = 'id'; // primary key column
  protected static $_tableName = null;           // database table name

  public function __construct(array $data = array()) {
    static::getFieldnames();  // only called once first time an object is created
    if (is_array($data)) {
      $this->hydrate($data);
    }
  }

  /**
   * set the db connection for this and all sub-classes to use
   * if a sub class overrides $_db it can have it's own db connection if required
   * params are as new PDO(...)
   * set PDO to throw exceptions on error
   *
   * @param string $dsn
   * @param string $username
   * @param string $password
   * @param string $driverOptions
   * @return void
   */
  public static function connectDb($dsn, $username, $password, $driverOptions = array()) {
    static::$_db = new \PDO($dsn, $username, $password, $driverOptions);
    static::$_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); // Set Errorhandling to Exception
    static::_setup_identifier_quote_character();
  }

  /**
   * Detect and initialise the character used to quote identifiers
   * (table names, column names etc).
   *
   * @return void
   */
  public static function _setup_identifier_quote_character() {
    if (is_null(static::$_identifier_quote_character)) {
      static::$_identifier_quote_character = static::_detect_identifier_quote_character();
    }
  }

  /**
   * Return the correct character used to quote identifiers (table
   * names, column names etc) by looking at the driver being used by PDO.
   *
   * @return string
   */
  protected static function _detect_identifier_quote_character() {
    switch (static::getDriverName()) {
      case 'pgsql':
      case 'sqlsrv':
      case 'dblib':
      case 'mssql':
      case 'sybase':
          return '"';
      case 'mysql':
      case 'sqlite':
      case 'sqlite2':
      default:
          return '`';
    }
  }
  
  /**
   * return the driver name for the current database connection
   *
   * @return string (driver name as returned by PDO)
   */
  protected static function getDriverName() {
    if (!static::$_db) {
      throw new Exception('No database connection setup');
    }
    return static::$_db->getAttribute(\PDO::ATTR_DRIVER_NAME);
  }

  /**
   * Quote a string that is used as an identifier
   * (table names, column names etc). This method can
   * also deal with dot-separated identifiers eg table.column
   *
   * @param string $identifier
   * @return string
   */
  protected static function _quote_identifier($identifier) {
    $class = get_called_class();
    $parts = explode('.', $identifier);
    $parts = array_map(array($class, '_quote_identifier_part'), $parts);
    return join('.', $parts);
  }


  /**
   * This method performs the actual quoting of a single
   * part of an identifier, using the identifier quote
   * character specified in the config (or autodetected).
   *
   * @param string $part
   * @return string
   */
  protected static function _quote_identifier_part($part) {
    if ($part === '*') {
      return $part;
    }
    return static::$_identifier_quote_character . $part . static::$_identifier_quote_character;
  }

  /**
   * Get and cache on first call the column names assocaited with the current table
   *
   * @return array of column names for the current table
   */
  protected static function getFieldnames() {
    $class = get_called_class();
    if (!isset(self::$_tableColumns[$class])) {
      $st = static::execute('DESCRIBE ' . static::_quote_identifier(static::$_tableName));
      self::$_tableColumns[$class] = $st->fetchAll(\PDO::FETCH_COLUMN);
    }
    return self::$_tableColumns[$class];
  }

  /**
   * Given an associative array of key value pairs
   * set the corresponding member value if associated with a table column
   * ignore keys which dont match a table column name
   *
   * @param associative array $data
   * @return void
   */
  public function hydrate($data) {
    foreach (static::getFieldnames() as $fieldname) {
      if (isset($data[$fieldname])) {
        $this->$fieldname = $data[$fieldname];
      } else if (!isset($this->$fieldname)) { // PDO pre populates fields before calling the constructor, so dont null unless not set
        $this->$fieldname = null;
      }
    }
  }
  
  /**
   * set all members to null that are associated with table columns
   *
   * @return void
   */
  public function clear() {
    foreach (static::getFieldnames() as $fieldname) {
      $this->$fieldname = null;
    }
  }

  public function __sleep() {
    return static::getFieldnames();
  }

  public function toArray() {
    $a = array();
    foreach (static::getFieldnames() as $fieldname) {
      $a[$fieldname] = $this->$fieldname;
    }
    return $a;
  }
  
  /**
   * Get the record with the matching primary key
   *
   * @param string $id
   * @return Object
   */
  static public function getById($id) {
    return static::fetchOneWhere(static::_quote_identifier(static::$_primary_column_name) . ' = ?', array($id));
  }
  
  /**
   * Get the first record in the table
   *
   * @return Object
   */
  static public function first() {
    return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' ASC');
  }
  
  /**
   * Get the last record in the table
   *
   * @return Object
   */
  static public function last() {
    return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' DESC');
  }
  
  /**
   * Find records with the matching primary key
   *
   * @param string $id
   * @return array of objects for matching records
   */
  static public function find($id) {
    $find_by_method = 'find_by_' . (static::$_primary_column_name);
    static::$find_by_method($id);
  }

  /**
   * handles calls to non-existant static methods, used to implement dynamic finder and counters ie.
   *  find_by_name('tom')
   *  find_by_title('a great book')
   *  count_by_name('tom')
   *  count_by_title('a great book')
   *
   * @param string $name
   * @param string $arguments
   * @return same as ::fetchAllWhere() or ::countAllWhere()
   */
  static public function __callStatic($name, $arguments) {
    // Note: value of $name is case sensitive.
    if (preg_match('/^find_by_/', $name) == 1) {
      // it's a find_by_{fieldname} dynamic method
      $fieldname = substr($name, 8); // remove find by
      $match = $arguments[0];
      if (is_array($match)) {
        return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
      } else {
        return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
      }
    } else if (preg_match('/^findOne_by_/', $name) == 1) {
      // it's a findOne_by_{fieldname} dynamic method
      $fieldname = substr($name, 11); // remove findOne_by_
      $match = $arguments[0];
      if (is_array($match)) {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
      } else {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
      }
    } else if (preg_match('/^first_by_/', $name) == 1) {
      // it's a first_by_{fieldname} dynamic method
      $fieldname = substr($name, 9); // remove first_by_
      $match = $arguments[0];
      if (is_array($match)) {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ') ORDER BY ' . static::_quote_identifier($fieldname) . ' ASC', $match);
      } else {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ? ORDER BY ' . static::_quote_identifier($fieldname) . ' ASC', array($match));
      }
    } else if (preg_match('/^last_by_/', $name) == 1) {
      // it's a last_by_{fieldname} dynamic method
      $fieldname = substr($name, 8); // remove last_by_
      $match = $arguments[0];
      if (is_array($match)) {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ') ORDER BY ' . static::_quote_identifier($fieldname) . ' DESC', $match);
      } else {
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ? ORDER BY ' . static::_quote_identifier($fieldname) . ' DESC', array($match));
      }
    } else if (preg_match('/^count_by_/', $name) == 1) {
      // it's a count_by_{fieldname} dynamic method
      $fieldname = substr($name, 9); // remove find by
      $match = $arguments[0];
      if (is_array($match)) {
        return static::countAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
      } else {
        return static::countAllWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
      }
    }
    throw new \Exception(__CLASS__ . ' not such static method[' . $name . ']');
  }

  /**
   * for a given array of params to be passed to an IN clause return a string placeholder
   *
   * @param array $params
   * @return string
   */
  static public function createInClausePlaceholders($params) {
    return implode(',', array_fill(0, count($params), '?')); // ie. returns ? [, ?]...
  }
  
  /**
   * returns number of rows in the table
   *
   * @return int
   */
  static public function count() {
    $st = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName));
    return $st->fetchColumn();
  }

  /**
   * run a SELECT count(*) FROM WHERE ...
   * returns an integer count of matching rows
   *
   * @param string $SQLfragment conditions, grouping to apply (to right of WHERE keyword)
   * @param array $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
   * @return integer count of rows matching conditions
   */
  static public function countAllWhere($SQLfragment = '', $params = array()) {
    if ($SQLfragment) {
      $SQLfragment = ' WHERE ' . $SQLfragment;
    }
    $st = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment, $params);
    return $st->fetchColumn();
  }

  /**
   * run a SELECT * FROM WHERE ...
   * returns an array of objects of the sub-class
   *
   * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
   * @param array $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
   * @return array of objects of calling class
   */
  static public function fetchAllWhere($SQLfragment = '', $params = array()) {
    $class = get_called_class();
    if ($SQLfragment) {
      $SQLfragment = ' WHERE ' . $SQLfragment;
    }
    $st = static::execute('SELECT * FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment, $params);
    // $st->debugDumpParams();
    $st->setFetchMode(\PDO::FETCH_CLASS, $class);
    return $st->fetchAll();
  }
  
  /**
   * run a SELECT * FROM WHERE ... LIMIT 1
   * returns an object of the sub-class
   *
   * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
   * @param array $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
   * @return an object of calling class
   */
  static public function fetchOneWhere($SQLfragment = '', $params = array()) {
    $class = get_called_class();
    if ($SQLfragment) {
      $SQLfragment = ' WHERE ' . $SQLfragment;
    }
    $st = static::execute('SELECT * FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment . ' LIMIT 1', $params);
    $st->setFetchMode(\PDO::FETCH_CLASS, $class);
    return $st->fetch();
  }
  
  /**
   * Delete a record by its primary key
   *
   * @return boolean indicating success
   */
  static public function deleteById($id) {
    $st = static::execute(
      'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ? LIMIT 1',
      array($id)
    );
    return ($st->rowCount() == 1);
  }
  
  /**
   * Delete the current record
   *
   * @return boolean indicating success
   */
  public function delete() {
    return self::deleteById($this->{static::$_primary_column_name});
  }

  /**
   * Delete records based on an SQL conditions
   *
   * @param string $where SQL fragment of conditions
   * @param array $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
   * @return PDO statement handle
   */
  static public function deleteAllWhere($where, $params = array()) {
    $st = static::execute(
      'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . $where,
      $params
    );
    return $st;
  }

  /**
   * do any validation in this function called before update and insert
   * should throw errors on validation failure.
   *
   * @return boolean true or throws exception on error
   */
  static public function validate() {
    return true;
  }
  
  /**
   * insert a row into the database table, and update the primary key field with the one generated on insert
   *
   * @param boolean $autoTimestamp true by default will set updated_at & created_at fields if present
   * @param boolean $allowSetPrimaryKey, if true include primary key field in insert (ie. you want to set it yourself)
   * @return boolean indicating success
   */
  public function insert($autoTimestamp = true, $allowSetPrimaryKey = false) {
    $pk = static::$_primary_column_name;
    $timeStr = gmdate('Y-m-d H:i:s');
    if ($autoTimestamp && in_array('created_at', static::getFieldnames())) {
      $this->created_at = $timeStr;
    }
    if ($autoTimestamp && in_array('updated_at', static::getFieldnames())) {
      $this->updated_at = $timeStr;
    }
    $this->validate();
    if ($allowSetPrimaryKey !== true) {
      $this->$pk = null; // ensure id is null
    }
    $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) . ' SET ' . $this->setString(!$allowSetPrimaryKey);
    $st = static::execute($query);
    if ($st->rowCount() == 1) {
      $this->{static::$_primary_column_name} = static::$_db->lastInsertId();
    }
    return ($st->rowCount() == 1);
  }

    /**
     * update the current record
     *
     * @param boolean $autoTimestamp true by default will set updated_at field if present
     * @return boolean indicating success
     */
  public function update($autoTimestamp = true) {
    if ($autoTimestamp && in_array('updated_at', static::getFieldnames())) {
      $this->updated_at = gmdate('Y-m-d H:i:s');
    }
    $this->validate();
    $query = 'UPDATE ' . static::_quote_identifier(static::$_tableName) . ' SET ' . $this->setString() . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ? LIMIT 1';
    $st = static::execute(
      $query,
      array(
        $this->{static::$_primary_column_name}
      )
    );
    return ($st->rowCount() == 1);
  }

  /**
   * execute
   * connivence function for setting preparing and running a database query
   * which also uses the statement cache
   *
   * @param string $query database statement with parameter place holders as PDO driver
   * @param array $params array of parameters to replace the placeholders in the statement
   * @return PDO statement handle
   */
  public static function execute($query, $params = array()) {
    $st = static::_prepare($query);
    $st->execute($params);
    return $st;
  }

  /**
   * prepare an SQL query via PDO
   *
   * @param string $query
   * @return a PDO prepared statement
   */
  protected static function _prepare($query) {
    if (!isset(static::$_stmt[$query])) {
      // cache prepared query if not seen before
      static::$_stmt[$query] = static::$_db->prepare($query);
    }
    return static::$_stmt[$query]; // return cache copy
  }

  /**
   * call update if primary key field is present, else call insert
   *
   * @return boolean indicating success
   */
  public function save() {
    if ($this->{static::$_primary_column_name}) {
      return $this->update();
    } else {
      return $this->insert();
    }
  }
  
  /**
   * Create an SQL fragment to be used after the SET keyword in an SQL UPDATE
   * escaping parameters as necessary.
   * by default the primary key is not added to the SET string, but passing $ignorePrimary as false will add it
   *
   * @param boolean $ignorePrimary
   * @return string
   */
  protected function setString($ignorePrimary = true) {
    // escapes and builds mysql SET string returning false, empty string or `field` = 'val'[, `field` = 'val']...
    $fragments = array();
    foreach (static::getFieldnames() as $field) {
      if ($ignorePrimary && $field == static::$_primary_column_name) continue;
      if (isset($this->$field)) {
        if ($this->$field === null) {
          // if empty set to NULL
          $fragments[] = static::_quote_identifier($field) . ' = NULL';
        } else {
          // Just set value normally as not empty string with NULL allowed
          $fragments[] = static::_quote_identifier($field) . ' = ' . static::$_db->quote($this->$field);
        }
      }
    }
    $sqlFragment = implode(", ", $fragments);
    return $sqlFragment;
  }
  
  /**
   * convert a date string or timestamp into a string suitable for assigning to a SQl datetime or timestamp field
   *
   * @param string|int $dt a date string or a unix timestamp
   * @return string
   */
  static function datetimeToMysqldatetime($dt) {
    $dt = (is_string($dt)) ? strtotime($dt) : $dt;
    return date('Y-m-d H:i:s', $dt);
  }
}


