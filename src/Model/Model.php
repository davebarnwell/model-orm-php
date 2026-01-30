<?php

namespace Freshsauce\Model;

/**
 * Model ORM
 *
 *
 *  A simple database abstraction layer for PHP 8.3+ with very minor configuration required
 *
 * database table columns are auto detected and made available as public members of the class
 * provides CRUD, dynamic counters/finders on a database table
 * uses PDO for data access and exposes PDO if required
 * class members used to do the magic are preceeded with an underscore, be careful of column names starting with _ in your database!
 * requires php >=8.3
 *
 *
 * @property string $created_at optional datatime in table that will automatically get updated on insert
 * @property string $updated_at optional datatime in table that will automatically get updated on insert/update
 *
 * @package default
 */

/**
 * Class Model
 *
 * @property string|null $created_at optional datetime in table that will automatically get updated on insert
 * @property string|null $updated_at optional datetime in table that will automatically get updated on insert/update
 *
 * @package Freshsauce\Model
 */
class Model
{
    // Class configuration

    /**
     * @var \PDO|null
     */
    public static $_db; // all models inherit this db connection
    // but can overide in a sub-class by calling subClass::connectDB(...)
    // sub class must also redeclare public static $_db;

    /**
     * @var \PDOStatement[]
     */
    protected static $_stmt = array(); // prepared statements cache

    /**
     * @var string|null
     */
    protected static $_identifier_quote_character; // character used to quote table & columns names

    /**
     * @var array
     */
    private static $_tableColumns = array(); // columns in database table populated dynamically
    // objects public members are created for each table columns dynamically

    /**
     * @var \stdClass all data is stored here
     */
    protected $data;

    /**
     * @var \stdClass whether a field value has changed (become dirty) is stored here
     */
    protected $dirty;

    /**
     * @var string primary key column name, set as appropriate in your sub-class
     */
    protected static $_primary_column_name = 'id'; // primary key column

    /**
     * @var string database table name, set as appropriate in your sub-class
     */
    protected static $_tableName = '_the_db_table_name_'; // database table name

    /**
     * Model constructor.
     *
     * @param array $data
     */
    public function __construct($data = array())
    {
        static::getFieldnames(); // only called once first time an object is created
        $this->clearDirtyFields();
        if (is_array($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * check if this object has data attached
     *
     * @return bool
     */
    public function hasData()
    {
        return is_object($this->data);
    }


    /**
     * Returns true if data present else throws an Exception
     *
     * @return bool
     * @throws \Exception
     */
    public function dataPresent()
    {
        if (!$this->hasData()) {
            throw new \Exception('No data');
        }

        return true;
    }

    /**
     * Set field in data object if doesnt match a native object member
     * Initialise the data store if not an object
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if (!$this->hasData()) {
            $this->data = new \stdClass();
        }
        $this->data->$name = $value;
        $this->markFieldDirty($name);
    }

    /**
     * Mark the field as dirty, so it will be set in inserts and updates
     *
     * @param string $name
     */
    public function markFieldDirty(string $name): void
    {
        $this->dirty->$name = true; // field became dirty
    }

    /**
     * Return true if filed is dirty else false
     *
     * @param string $name
     *
     * @return bool
     */
    public function isFieldDirty($name)
    {
        return isset($this->dirty->$name) && ($this->dirty->$name == true);
    }

    /**
     * resets what fields have been considered dirty ie. been changed without being saved to the db
     */
    public function clearDirtyFields(): void
    {
        $this->dirty = new \stdClass();
    }

    /**
     * Try and get the object member from the data object
     * if it doesnt match a native object member
     *
     * @param string $name
     *
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        if (!$this->hasData()) {
            throw new \Exception("data property=$name has not been initialised", 1);
        }

        if (property_exists($this->data, $name)) {
            return $this->data->$name;
        }

        $trace = debug_backtrace();
        $file = $trace[0]['file'] ?? 'unknown';
        $line = $trace[0]['line'] ?? 0;
        throw new \Exception(
            'Undefined property via __get(): ' . $name .
            ' in ' . $file .
            ' on line ' . $line,
            1
        );
    }

    /**
     * Test the existence of the object member from the data object
     * if it doesnt match a native object member
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        if ($this->hasData() && property_exists($this->data, $name)) {
            return true;
        }

        return false;
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
     * @param array  $driverOptions
     *
     * @throws \Exception
     */
    public static function connectDb(string $dsn, string $username, string $password, array $driverOptions = array()): void
    {
        static::$_db = new \PDO($dsn, $username, $password, $driverOptions);
        static::$_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); // Set Errorhandling to Exception
        static::_setup_identifier_quote_character();
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc).
     *
     * @return void
     * @throws \Exception
     */
    public static function _setup_identifier_quote_character()
    {
        if (is_null(static::$_identifier_quote_character)) {
            static::$_identifier_quote_character = static::_detect_identifier_quote_character();
        }
    }

    /**
     * Return the correct character used to quote identifiers (table
     * names, column names etc) by looking at the driver being used by PDO.
     *
     * @return string
     * @throws \Exception
     */
    protected static function _detect_identifier_quote_character()
    {
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
     * @return string
     * @throws \Exception
     */
    protected static function getDriverName()
    {
        $db = static::$_db;
        if (!$db) {
            throw new \Exception('No database connection setup');
        }
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new \Exception('Unable to determine database driver');
        }
        return $driver;
    }

    /**
     * Public accessor for the current PDO driver name.
     *
     * @return string
     * @throws \Exception
     */
    public static function driverName()
    {
        return static::getDriverName();
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     *
     * @param string $identifier
     *
     * @return string
     */
    protected static function _quote_identifier($identifier)
    {
        $class = get_called_class();
        $parts = explode('.', $identifier);
        $parts = array_map(array(
            $class,
            '_quote_identifier_part'
        ), $parts);
        return join('.', $parts);
    }


    /**
     * This method performs the actual quoting of a single
     * part of an identifier, using the identifier quote
     * character specified in the config (or autodetected).
     *
     * @param string $part
     *
     * @return string
     */
    protected static function _quote_identifier_part($part)
    {
        if ($part === '*') {
            return $part;
        }
        static::_setup_identifier_quote_character();
        $quote = static::$_identifier_quote_character;
        if ($quote === null) {
            throw new \Exception('Identifier quote character not set');
        }
        $escaped = str_replace($quote, $quote . $quote, $part);
        return $quote . $escaped . $quote;
    }

    /**
     * Get and cache on first call the column names assocaited with the current table
     *
     * @return array of column names for the current table
     */
    protected static function getFieldnames()
    {
        $class = get_called_class();
        if (!isset(self::$_tableColumns[$class])) {
            $driver = static::getDriverName();
            if ($driver === 'pgsql') {
                list($schema, $table) = static::splitTableName(static::$_tableName);
                $st = static::execute(
                    'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
                    array($schema, $table)
                );
                self::$_tableColumns[$class] = $st->fetchAll(\PDO::FETCH_COLUMN);
            } elseif ($driver === 'sqlite' || $driver === 'sqlite2') {
                $st = static::execute('PRAGMA table_info(' . static::_quote_identifier(static::$_tableName) . ')');
                self::$_tableColumns[$class] = $st->fetchAll(\PDO::FETCH_COLUMN, 1);
            } else {
                $st                          = static::execute('DESCRIBE ' . static::_quote_identifier(static::$_tableName));
                self::$_tableColumns[$class] = $st->fetchAll(\PDO::FETCH_COLUMN);
            }
        }
        return self::$_tableColumns[$class];
    }

    /**
     * Split a table name into schema and table, defaulting schema to public.
     *
     * @param string $tableName
     *
     * @return string[]
     */
    protected static function splitTableName($tableName)
    {
        $parts = explode('.', $tableName, 2);
        if (count($parts) === 2) {
            return $parts;
        }
        return array('public', $tableName);
    }

    /**
     * Given an associative array of key value pairs
     * set the corresponding member value if associated with a table column
     * ignore keys which dont match a table column name
     *
     * @return void
     */
    public function hydrate(array $data): void
    {
        foreach (static::getFieldnames() as $fieldname) {
            if (isset($data[$fieldname])) {
                $this->$fieldname = $data[$fieldname];
            } elseif (!isset($this->$fieldname)) { // PDO pre populates fields before calling the constructor, so dont null unless not set
                $this->$fieldname = null;
            }
        }
    }

    /**
     * set all members to null that are associated with table columns
     *
     * @return void
     */
    public function clear()
    {
        foreach (static::getFieldnames() as $fieldname) {
            $this->$fieldname = null;
        }
        $this->clearDirtyFields();
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return static::getFieldnames();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $a = array();
        foreach (static::getFieldnames() as $fieldname) {
            $a[$fieldname] = $this->$fieldname;
        }
        return $a;
    }

    /**
     * Get the record with the matching primary key
     *
     * @param int|string $id
     *
     * @return static
     */
    public static function getById(int|string $id): static
    {
        return static::fetchOneWhere(static::_quote_identifier(static::$_primary_column_name) . ' = ?', array($id));
    }

    /**
     * Get the first record in the table
     *
     * @return static
     */
    public static function first(): static
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' ASC');
    }

    /**
     * Get the last record in the table
     *
     * @return static
     */
    public static function last(): static
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' DESC');
    }

    /**
     * Find records with the matching primary key
     *
     * @param string $id
     *
     * @return object[] of objects for matching records
     */
    public static function find($id)
    {
        $find_by_method = 'find_by_' . (static::$_primary_column_name);
        return static::$find_by_method($id);
    }

    /**
     * handles calls to non-existant static methods, used to implement dynamic finder and counters ie.
     *  find_by_name('tom')
     *  find_by_title('a great book')
     *  count_by_name('tom')
     *  count_by_title('a great book')
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed int|object[]|object
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        // Note: value of $name is case sensitive.
        if (preg_match('/^find_by_/', $name) == 1) {
            // it's a find_by_{fieldname} dynamic method
            $fieldname = substr($name, 8); // remove find by
            $match     = $arguments[0];
            return static::fetchAllWhereMatchingSingleField($fieldname, $match);
        } elseif (preg_match('/^findOne_by_/', $name) == 1) {
            // it's a findOne_by_{fieldname} dynamic method
            $fieldname = substr($name, 11); // remove findOne_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'ASC');
        } elseif (preg_match('/^first_by_/', $name) == 1) {
            // it's a first_by_{fieldname} dynamic method
            $fieldname = substr($name, 9); // remove first_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'ASC');
        } elseif (preg_match('/^last_by_/', $name) == 1) {
            // it's a last_by_{fieldname} dynamic method
            $fieldname = substr($name, 8); // remove last_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'DESC');
        } elseif (preg_match('/^count_by_/', $name) == 1) {
            // it's a count_by_{fieldname} dynamic method
            $fieldname = substr($name, 9); // remove find by
            $match     = $arguments[0];
            if (is_array($match)) {
                return static::countAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
            } else {
                return static::countAllWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
            }
        }
        throw new \Exception(__CLASS__ . ' not such static method[' . $name . ']');
    }

    /**
     * find one match based on a single field and match criteria
     *
     * @param string       $fieldname
     * @param string|array $match
     * @param string       $order ASC|DESC
     *
     * @return object of calling class
     */
    public static function fetchOneWhereMatchingSingleField($fieldname, $match, $order)
    {
        if (is_array($match)) {
            return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ') ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, $match);
        } else {
            return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ? ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, array($match));
        }
    }


    /**
     * find multiple matches based on a single field and match criteria
     *
     * @param string       $fieldname
     * @param string|array $match
     *
     * @return object[] of objects of calling class
     */
    public static function fetchAllWhereMatchingSingleField($fieldname, $match)
    {
        if (is_array($match)) {
            return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
        } else {
            return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
        }
    }

    /**
     * for a given array of params to be passed to an IN clause return a string placeholder
     *
     * @param array $params
     *
     * @return string
     */
    public static function createInClausePlaceholders($params)
    {
        return implode(',', array_fill(0, count($params), '?'));
    }

    /**
     * returns number of rows in the table
     *
     * @return int
     */
    public static function count()
    {
        $st = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName));
        return (int)$st->fetchColumn(0);
    }

    /**
     * returns an integer count of matching rows
     *
     * @param string $SQLfragment conditions, grouping to apply (to right of WHERE keyword)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     *
     * @return integer count of rows matching conditions
     */
    public static function countAllWhere($SQLfragment = '', $params = array())
    {
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st          = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment, $params);
        return (int)$st->fetchColumn(0);
    }

    /**
     * if $SQLfragment is not empty prefix with the WHERE keyword
     *
     * @param string $SQLfragment
     *
     * @return string
     */
    protected static function addWherePrefix($SQLfragment)
    {
        return $SQLfragment ? ' WHERE ' . $SQLfragment : $SQLfragment;
    }


    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @param bool   $limitOne    if true the first match will be returned
     *
     * @return array|static object[]|object of objects of calling class
     */
    public static function fetchWhere($SQLfragment = '', $params = array(), $limitOne = false): array|static
    {
        $class       = get_called_class();
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st          = static::execute(
            'SELECT * FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment . ($limitOne ? ' LIMIT 1' : ''),
            $params
        );
        $st->setFetchMode(\PDO::FETCH_ASSOC);
        if ($limitOne) {
            $instance = new $class($st->fetch());
            $instance->clearDirtyFields();
            return $instance;
        }
        $results = [];
        while ($row = $st->fetch()) {
            $instance = new $class($row);
            $instance->clearDirtyFields();
            $results[] = $instance;
        }
        return $results;
    }

    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     *
     * @return array object[] of objects of calling class
     */
    public static function fetchAllWhere($SQLfragment = '', $params = array()): array
    {
        /** @var array $results */
        $results = static::fetchWhere($SQLfragment, $params, false);
        return $results;
    }

    /**
     * returns an object of the sub-class which matches the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     *
     * @return static object of calling class
     */
    public static function fetchOneWhere($SQLfragment = '', $params = array()): static
    {
        /** @var static $result */
        $result = static::fetchWhere($SQLfragment, $params, true);
        return $result;
    }

    /**
     * Delete a record by its primary key
     *
     * @return boolean indicating success
     */
    public static function deleteById(int|string $id): bool
    {
        $limitClause = static::supportsDeleteLimit() ? ' LIMIT 1' : '';
        $st = static::execute(
            'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ?' . $limitClause,
            array($id)
        );
        return ($st->rowCount() == 1);
    }

    /**
     * Delete the current record
     *
     * @return boolean indicating success
     */
    public function delete()
    {
        return self::deleteById($this->{static::$_primary_column_name});
    }

    /**
     * Delete records based on an SQL conditions
     *
     * @param string $where  SQL fragment of conditions
     * @param array  $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     *
     * @return \PDOStatement
     */
    public static function deleteAllWhere($where, $params = array())
    {
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
    public static function validate()
    {
        return true;
    }

    /**
     * insert a row into the database table, and update the primary key field with the one generated on insert
     *
     * @param boolean $autoTimestamp      true by default will set updated_at & created_at fields if present
     * @param boolean $allowSetPrimaryKey if true include primary key field in insert (ie. you want to set it yourself)
     *
     * @return boolean indicating success
     */
    public function insert($autoTimestamp = true, $allowSetPrimaryKey = false)
    {
        $pk      = static::$_primary_column_name;
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
        $set = $this->setString(!$allowSetPrimaryKey);
        $driver = static::getDriverName();
        if ($driver === 'pgsql') {
            if (count($set['columns']) === 0) {
                $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) . ' DEFAULT VALUES RETURNING ' . static::_quote_identifier(static::$_primary_column_name);
                $st = static::execute($query);
            } else {
                $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) .
                    ' (' . implode(', ', $set['columns']) . ')' .
                    ' VALUES (' . implode(', ', $set['values']) . ')' .
                    ' RETURNING ' . static::_quote_identifier(static::$_primary_column_name);
                $st = static::execute($query, $set['params']);
            }
            $insertedId = $st->fetchColumn();
            if ($insertedId !== false) {
                if ($allowSetPrimaryKey !== true) {
                    $this->{static::$_primary_column_name} = $insertedId;
                }
                $this->clearDirtyFields();
                return true;
            }
            return false;
        }

        $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) . ' SET ' . $set['sql'];
        $st    = static::execute($query, $set['params']);
        if ($st->rowCount() == 1) {
            if ($allowSetPrimaryKey !== true) {
                $db = static::$_db;
                if (!$db) {
                    throw new \Exception('No database connection setup');
                }
                $this->{static::$_primary_column_name} = $db->lastInsertId();
            }
            $this->clearDirtyFields();
        }
        return ($st->rowCount() == 1);
    }

    /**
     * update the current record
     *
     * @param boolean $autoTimestamp true by default will set updated_at field if present
     *
     * @return boolean indicating success
     */
    public function update($autoTimestamp = true)
    {
        if ($autoTimestamp && in_array('updated_at', static::getFieldnames())) {
            $this->updated_at = gmdate('Y-m-d H:i:s');
        }
        $this->validate();
        $set             = $this->setString();
        $limitClause     = static::supportsUpdateLimit() ? ' LIMIT 1' : '';
        $query           = 'UPDATE ' . static::_quote_identifier(static::$_tableName) . ' SET ' . $set['sql'] . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ?' . $limitClause;
        $set['params'][] = $this->{static::$_primary_column_name};
        $st              = static::execute(
            $query,
            $set['params']
        );
        if ($st->rowCount() == 1) {
            $this->clearDirtyFields();
        }
        return ($st->rowCount() == 1);
    }

    /**
     * execute
     * convenience function for setting preparing and running a database query
     * which also uses the statement cache
     *
     * @param string $query  database statement with parameter place holders as PDO driver
     * @param array  $params array of parameters to replace the placeholders in the statement
     *
     * @return \PDOStatement handle
     */
    public static function execute($query, $params = array())
    {
        $st = static::_prepare($query);
        $st->execute($params);
        return $st;
    }

    /**
     * prepare an SQL query via PDO
     *
     * @param string $query
     *
     * @return \PDOStatement
     */
    protected static function _prepare($query)
    {
        $db = static::$_db;
        if (!$db) {
            throw new \Exception('No database connection setup');
        }
        if (!isset(static::$_stmt[$query])) {
            // cache prepared query if not seen before
            static::$_stmt[$query] = $db->prepare($query);
        }
        return static::$_stmt[$query]; // return cache copy
    }

    /**
     * call update if primary key field is present, else call insert
     *
     * @return boolean indicating success
     */
    public function save()
    {
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
     *
     * @return array ['sql' => string, 'params' => mixed[] ]
     */
    protected function setString($ignorePrimary = true)
    {
        // escapes and builds mysql SET string returning false, empty string or `field` = 'val'[, `field` = 'val']...
        /**
         * @var array $fragments individual SQL assignments
         */
        $fragments = array();
        /**
         * @var array $params values in order to insert into SQl assignment fragments
         */
        $params = [];
        /**
         * @var array $columns column list for INSERT
         */
        $columns = [];
        /**
         * @var array $values placeholder list for INSERT
         */
        $values = [];
        $hasData = $this->hasData();
        foreach (static::getFieldnames() as $field) {
            if ($ignorePrimary && $field == static::$_primary_column_name) {
                continue;
            }
            if (!$hasData || !property_exists($this->data, $field) || !$this->isFieldDirty($field)) {
                continue;
            }
            $columns[] = static::_quote_identifier($field);
            if ($this->$field === null) {
                // if empty set to NULL
                $fragments[] = static::_quote_identifier($field) . ' = NULL';
                $values[] = 'NULL';
            } else {
                // Just set value normally as not empty string with NULL allowed
                $fragments[] = static::_quote_identifier($field) . ' = ?';
                $values[] = '?';
                $params[]    = $this->$field;
            }
        }
        $sqlFragment = implode(", ", $fragments);
        return [
            'sql'    => $sqlFragment,
            'params' => $params,
            'columns' => $columns,
            'values' => $values
        ];
    }

    /**
     * Determine whether the driver supports LIMIT on UPDATE.
     *
     * @return bool
     * @throws \Exception
     */
    protected static function supportsUpdateLimit()
    {
        $driver = static::getDriverName();
        return ($driver === 'mysql' || $driver === 'sqlite' || $driver === 'sqlite2');
    }

    /**
     * Determine whether the driver supports LIMIT on DELETE.
     *
     * @return bool
     * @throws \Exception
     */
    protected static function supportsDeleteLimit()
    {
        $driver = static::getDriverName();
        return ($driver === 'mysql' || $driver === 'sqlite' || $driver === 'sqlite2');
    }

    /**
     * convert a date string or timestamp into a string suitable for assigning to a SQl datetime or timestamp field
     *
     * @param int|string $dt a date string or a unix timestamp
     *
     * @return string
     */
    public static function datetimeToMysqldatetime(int|string $dt): string
    {
        $timestamp = (is_string($dt)) ? strtotime($dt) : $dt;
        if ($timestamp === false) {
            $timestamp = 0;
        }
        return date('Y-m-d H:i:s', (int) $timestamp);
    }
}
