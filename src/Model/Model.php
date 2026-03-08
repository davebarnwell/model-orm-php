<?php

declare(strict_types=1);

namespace Freshsauce\Model;

use Freshsauce\Model\Exception\ConfigurationException;
use Freshsauce\Model\Exception\ConnectionException;
use Freshsauce\Model\Exception\InvalidDynamicMethodException;
use Freshsauce\Model\Exception\MissingDataException;
use Freshsauce\Model\Exception\ModelException;
use Freshsauce\Model\Exception\UnknownFieldException;

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
 *
 * @phpstan-consistent-constructor
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
     * @var array<int, array<string, \PDOStatement>>
     */
    protected static $_stmt = array(); // prepared statements cache keyed by PDO connection and SQL

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
     * @var \stdClass|null all data is stored here
     */
    protected $data;

    /**
     * @var \stdClass|null whether a field value has changed (become dirty) is stored here
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
     * @var bool whether writes to unknown fields should throw immediately
     */
    protected static bool $_strict_fields = false;

    /**
     * @var bool whether built-in automatic timestamp handling is enabled
     */
    protected static bool $_auto_timestamps = true;

    /**
     * @var string|null column name to auto-populate on insert, or null to disable
     */
    protected static ?string $_created_at_column = 'created_at';

    /**
     * @var string|null column name to auto-populate on insert/update, or null to disable
     */
    protected static ?string $_updated_at_column = 'updated_at';

    /**
     * @var array<string, string> field cast map
     */
    protected static array $_casts = [];

    /**
     * Model constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
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
    public function hasData(): bool
    {
        return is_object($this->data);
    }


    /**
     * Returns true if data is present else throws MissingDataException
     *
     * @return bool
     * @throws MissingDataException
     */
    public function dataPresent()
    {
        if (!$this->hasData()) {
            throw new MissingDataException('No data');
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
    public function __set(string $name, mixed $value): void
    {
        $this->assignAttribute($name, $value);
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
    public function isFieldDirty(string $name): bool
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
     * @throws MissingDataException
     * @throws UnknownFieldException
     */
    public function __get(string $name): mixed
    {
        $data = $this->data;
        if (!$data instanceof \stdClass) {
            throw new MissingDataException("data property=$name has not been initialised", 1);
        }

        if (property_exists($data, $name)) {
            return $data->$name;
        }

        $trace = debug_backtrace();
        $file = $trace[0]['file'] ?? 'unknown';
        $line = $trace[0]['line'] ?? 0;
        throw new UnknownFieldException(
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
    public function __isset(string $name): bool
    {
        $data = $this->data;
        if ($data instanceof \stdClass && property_exists($data, $name)) {
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
     * @throws \PDOException
     * @throws ModelException
     */
    public static function connectDb(string $dsn, string $username, string $password, array $driverOptions = array()): void
    {
        $previousDb = static::$_db;
        if ($previousDb instanceof \PDO) {
            unset(static::$_stmt[spl_object_id($previousDb)]);
        }
        static::$_db = new \PDO($dsn, $username, $password, $driverOptions);
        static::$_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); // Set Errorhandling to Exception
        static::$_identifier_quote_character = null;
        self::$_tableColumns = array();
        static::_setup_identifier_quote_character();
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc).
     *
     * @return void
     * @throws ModelException
     */
    public static function _setup_identifier_quote_character(): void
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
     * @throws ModelException
     */
    protected static function _detect_identifier_quote_character(): string
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
     * @throws ConnectionException
     * @throws ConfigurationException
     */
    protected static function getDriverName(): string
    {
        $db = static::$_db;
        if (!$db) {
            throw new ConnectionException('No database connection setup');
        }
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new ConfigurationException('Unable to determine database driver');
        }
        return $driver;
    }

    /**
     * Public accessor for the current PDO driver name.
     *
     * @return string
     * @throws ConnectionException
     * @throws ConfigurationException
     */
    public static function driverName(): string
    {
        return static::getDriverName();
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names, etc). This method can
     * also deal with dot-separated identifiers eg table.column
     *
     * @param string $identifier
     *
     * @return string
     */
    protected static function _quote_identifier(string $identifier): string
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
     * character specified in the config (or autodetect).
     *
     * @param  string  $part
     *
     * @return string
     * @throws ModelException
     */
    protected static function _quote_identifier_part(string $part): string
    {
        if ($part === '*') {
            return $part;
        }
        static::_setup_identifier_quote_character();
        $quote = static::$_identifier_quote_character;
        if ($quote === null) {
            throw new ConfigurationException('Identifier quote character not set');
        }
        $escaped = str_replace($quote, $quote . $quote, $part);
        return $quote . $escaped . $quote;
    }

    /**
     * Get and cache on the first call the column names associated with the current table
     *
     * @return array of column names for the current table
     * @throws \PDOException
     * @throws ModelException
     */
    protected static function getFieldnames(): array
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
     * Refresh cached table metadata for the current model class.
     *
     * @return void
     */
    public static function refreshTableMetadata(): void
    {
        unset(self::$_tableColumns[static::class]);
    }

    /**
     * Split a table name into schema and table, defaulting schema to public.
     *
     * @param  string  $tableName
     *
     * @return string[]
     */
    protected static function splitTableName(string $tableName): array
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
     * ignore keys which don't match a table column name
     *
     * @param  array  $data
     *
     * @return void
     * @throws \PDOException
     * @throws ModelException
     */
    public function hydrate(array $data): void
    {
        foreach (static::getFieldnames() as $fieldname) {
            if (array_key_exists($fieldname, $data)) {
                $this->assignAttribute($fieldname, $data[$fieldname]);
            } elseif (!isset($this->$fieldname)) { // PDO pre populates fields before calling the constructor, so dont null unless not set
                $this->assignAttribute($fieldname, null);
            }
        }
    }

    /**
     * set all members to null that are associated with table columns
     *
     * @return void
     * @throws \PDOException
     * @throws ModelException
     */
    public function clear(): void
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
        return array('data', 'dirty');
    }

    /**
     * @return array{data: \stdClass, dirty: \stdClass}
     */
    public function __serialize(): array
    {
        return array(
            'data' => $this->normaliseSerializedState(isset($this->data) ? $this->data : null),
            'dirty' => $this->normaliseSerializedState(isset($this->dirty) ? $this->dirty : null),
        );
    }

    /**
     * @param array{data?: mixed, dirty?: mixed} $data
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->data = $this->normaliseSerializedState($data['data'] ?? null);
        $this->dirty = $this->normaliseSerializedState($data['dirty'] ?? null);
    }

    /**
     * @return array
     * @throws \PDOException
     * @throws ModelException
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
     * @return static|null
     */
    public static function getById(int|string $id): ?static
    {
        return static::fetchOneWhere(static::_quote_identifier(static::$_primary_column_name) . ' = ?', array($id));
    }

    /**
     * Get the first record in the table
     *
     * @return static|null
     */
    public static function first(): ?static
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' ASC');
    }

    /**
     * Get the last record in the table
     *
     * @return static|null
     */
    public static function last(): ?static
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' DESC');
    }

    /**
     * Find records with the matching primary key
     *
     * @param int|string $id
     *
     * @return object[] of objects for matching records
     */
    public static function find($id)
    {
        return static::fetchAllWhereMatchingSingleField(static::resolveFieldName(static::$_primary_column_name), $id);
    }

    /**
     * handles calls to non-existent static methods, used to implement dynamic finder and counters ie.
     *  findByName('tom')
     *  findByTitle('a great book')
     *  countByName('tom')
     *  countByTitle('a great book')
     * snake_case dynamic methods remain temporarily supported and trigger a deprecation warning.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed int|object[]|object
     * @throws InvalidDynamicMethodException
     * @throws \PDOException
     * @throws ModelException
     */
    public static function __callStatic($name, $arguments)
    {
        $match = $arguments[0] ?? null;
        $dynamicMethod = static::parseDynamicStaticMethod($name);
        if (is_array($dynamicMethod)) {
            if ($dynamicMethod['deprecated']) {
                static::triggerSnakeCaseDynamicMethodDeprecation($name);
            }
            return static::dispatchDynamicStaticMethod($dynamicMethod['operation'], $dynamicMethod['fieldname'], $match);
        }
        throw new InvalidDynamicMethodException(__CLASS__ . ' not such static method[' . $name . ']');
    }

    /**
     * Parse supported dynamic static finder/counter names.
     *
     * @param string $name
     *
     * @return array{operation: string, fieldname: string, deprecated: bool}|null
     */
    protected static function parseDynamicStaticMethod(string $name): ?array
    {
        $camelCasePrefixes = array(
            'findOneBy' => 'findOne',
            'findBy' => 'findAll',
            'firstBy' => 'first',
            'lastBy' => 'last',
            'countBy' => 'count',
        );
        foreach ($camelCasePrefixes as $prefix => $operation) {
            if (str_starts_with($name, $prefix)) {
                $fieldname = substr($name, strlen($prefix));
                if ($fieldname === '') {
                    return null;
                }
                return array(
                    'operation' => $operation,
                    'fieldname' => $fieldname,
                    'deprecated' => false,
                );
            }
        }

        $snakeCasePrefixes = array(
            'findOne_by_' => 'findOne',
            'find_by_' => 'findAll',
            'first_by_' => 'first',
            'last_by_' => 'last',
            'count_by_' => 'count',
        );
        foreach ($snakeCasePrefixes as $prefix => $operation) {
            if (str_starts_with($name, $prefix)) {
                $fieldname = substr($name, strlen($prefix));
                if ($fieldname === '') {
                    return null;
                }
                return array(
                    'operation' => $operation,
                    'fieldname' => $fieldname,
                    'deprecated' => true,
                );
            }
        }

        return null;
    }

    /**
     * Execute a parsed dynamic static method.
     *
     * @param string $operation
     * @param string $fieldname
     * @param mixed  $match
     *
     * @return mixed
     * @throws InvalidDynamicMethodException
     * @throws \PDOException
     * @throws ModelException
     */
    protected static function dispatchDynamicStaticMethod(string $operation, string $fieldname, $match)
    {
        $resolvedFieldname = static::resolveFieldName($fieldname);

        return match ($operation) {
            'findAll' => static::fetchAllWhereMatchingSingleField($resolvedFieldname, $match),
            'findOne' => static::fetchOneWhereMatchingSingleField($resolvedFieldname, $match, 'ASC'),
            'first' => static::fetchOneWhereMatchingSingleField($resolvedFieldname, $match, 'ASC'),
            'last' => static::fetchOneWhereMatchingSingleField($resolvedFieldname, $match, 'DESC'),
            'count' => static::countByField($resolvedFieldname, $match),
            default => throw new InvalidDynamicMethodException(static::class . ' not such static method operation[' . $operation . ']'),
        };
    }

    /**
     * Warn when a deprecated snake_case dynamic method is used.
     *
     * @param string $name
     *
     * @return void
     */
    protected static function triggerSnakeCaseDynamicMethodDeprecation(string $name): void
    {
        $replacement = static::snakeCaseDynamicMethodToCamelCase($name);
        $message = 'Dynamic snake_case model methods are deprecated. Use ' . $replacement . ' instead of ' . $name . '.';
        trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Convert a snake_case dynamic method name to the camelCase replacement.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function snakeCaseDynamicMethodToCamelCase(string $name): string
    {
        $prefixMap = array(
            'findOne_by_' => 'findOneBy',
            'find_by_' => 'findBy',
            'first_by_' => 'firstBy',
            'last_by_' => 'lastBy',
            'count_by_' => 'countBy',
        );
        foreach ($prefixMap as $prefix => $replacementPrefix) {
            if (str_starts_with($name, $prefix)) {
                $fieldname = substr($name, strlen($prefix));
                return $replacementPrefix . static::snakeToStudly($fieldname);
            }
        }

        return $name;
    }

    /**
     * Resolve a dynamic field name from snake_case or CamelCase to an actual column name.
     *
     * @param string $fieldname
     *
     * @return string
     * @throws UnknownFieldException
     * @throws \PDOException
     * @throws ModelException
     */
    protected static function resolveFieldName(string $fieldname): string
    {
        $fieldnames = static::getFieldnames();
        if (in_array($fieldname, $fieldnames, true)) {
            return $fieldname;
        }
        foreach ($fieldnames as $field) {
            if (strcasecmp($field, $fieldname) === 0) {
                return $field;
            }
        }
        $snake = static::camelToSnake($fieldname);
        if (in_array($snake, $fieldnames, true)) {
            return $snake;
        }
        foreach ($fieldnames as $field) {
            if (strcasecmp($field, $snake) === 0) {
                return $field;
            }
        }
        throw new UnknownFieldException('Unknown field [' . $fieldname . '] for model ' . static::class);
    }

    /**
     * Convert CamelCase or mixedCase to snake_case.
     *
     * @param string $fieldname
     *
     * @return string
     */
    protected static function camelToSnake(string $fieldname): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $fieldname);
        return strtolower($snake ?? $fieldname);
    }

    /**
     * Convert snake_case to StudlyCase for dynamic method generation.
     *
     * @param string $fieldname
     *
     * @return string
     */
    protected static function snakeToStudly(string $fieldname): string
    {
        $parts = explode('_', $fieldname);
        $parts = array_map(static fn ($part) => ucfirst(strtolower($part)), $parts);

        return implode('', $parts);
    }

    /**
     * Count records for a field with either a single value or an array of values.
     *
     * @param string $fieldname
     * @param mixed  $match
     *
     * @return int
     */
    protected static function countByField(string $fieldname, mixed $match): int
    {
        if (static::isEmptyMatchList($match)) {
            return 0;
        }
        if (is_array($match)) {
            return static::countAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
        }
        return static::countAllWhere(static::_quote_identifier($fieldname) . ' = ?', array($match));
    }

    /**
     * find one match based on a single field and match criteria
     *
     * @param string $fieldname
     * @param mixed  $match
     * @param string $order ASC|DESC
     *
     * @return static|null object of calling class
     */
    public static function fetchOneWhereMatchingSingleField(string $fieldname, mixed $match, string $order): ?static
    {
        if (static::isEmptyMatchList($match)) {
            return null;
        }
        if (is_array($match)) {
            return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ') ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, $match);
        } else {
            return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ? ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, array($match));
        }
    }


    /**
     * find multiple matches based on a single field and match criteria
     *
     * @param string $fieldname
     * @param mixed  $match
     *
     * @return object[] of objects of calling class
     */
    public static function fetchAllWhereMatchingSingleField(string $fieldname, mixed $match): array
    {
        if (static::isEmptyMatchList($match)) {
            return array();
        }
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
    public static function createInClausePlaceholders(array $params): string
    {
        if (count($params) === 0) {
            return 'NULL';
        }
        return implode(',', array_fill(0, count($params), '?'));
    }

    /**
     * returns number of rows in the table
     *
     * @return int
     */
    public static function count(): int
    {
        $st = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName));
        return (int)$st->fetchColumn(0);
    }

    /**
     * returns true when the table contains at least one row
     *
     * @return bool
     */
    public static function exists(): bool
    {
        $st = static::execute(
            'SELECT 1 FROM ' . static::_quote_identifier(static::$_tableName) . ' LIMIT 1'
        );

        return $st->fetchColumn(0) !== false;
    }

    /**
     * returns an integer count of matching rows
     *
     * @param string $SQLfragment conditions, grouping to apply (to right of WHERE keyword)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standard PDO syntax)
     *
     * @return integer count of rows matching conditions
     */
    public static function countAllWhere(string $SQLfragment = '', array $params = []): int
    {
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st          = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment, $params);
        return (int)$st->fetchColumn(0);
    }

    /**
     * returns true when at least one row matches the conditions
     *
     * @param string $SQLfragment
     * @param array<int, mixed> $params
     *
     * @return bool
     */
    public static function existsWhere(string $SQLfragment = '', array $params = []): bool
    {
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $sql         = 'SELECT 1 FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment . ' LIMIT 1';
        $st          = static::execute($sql, $params);

        return $st->fetchColumn(0) !== false;
    }

    /**
     * if $SQLfragment is not empty prefix with the WHERE keyword
     *
     * @param string $SQLfragment
     *
     * @return string
     */
    protected static function addWherePrefix(string $SQLfragment): string
    {
        return $SQLfragment ? ' WHERE ' . $SQLfragment : $SQLfragment;
    }

    /**
     * Build ORDER BY / LIMIT clauses for helper queries.
     *
     * @param string|null $orderByField
     * @param string $direction
     * @param int|null $limit
     *
     * @return string
     * @throws ConfigurationException
     * @throws UnknownFieldException
     * @throws \PDOException
     * @throws ModelException
     */
    protected static function buildOrderAndLimitClause(?string $orderByField = null, string $direction = 'ASC', ?int $limit = null): string
    {
        $suffix = '';
        if ($orderByField !== null) {
            $suffix .= ' ORDER BY ' . static::_quote_identifier(static::resolveFieldName($orderByField)) . ' ' . static::normaliseOrderDirection($direction);
        }
        if ($limit !== null) {
            if ($limit < 1) {
                throw new ConfigurationException('Limit must be greater than zero.');
            }
            $suffix .= ' LIMIT ' . $limit;
        }

        return $suffix;
    }

    /**
     * @param string $direction
     *
     * @return string
     * @throws ConfigurationException
     */
    protected static function normaliseOrderDirection(string $direction): string
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new ConfigurationException('Unsupported order direction [' . $direction . ']. Use ASC or DESC.');
        }

        return $direction;
    }


    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standard PDO syntax)
     * @param bool   $limitOne    if true the first match will be returned
     *
     * @return array|static|null object[]|object of objects of calling class
     */
    protected static function fetchWhereWithSuffix(string $SQLfragment = '', array $params = [], bool $limitOne = false, string $suffix = ''): array|static|null
    {
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st          = static::execute(
            'SELECT * FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment . $suffix . ($limitOne ? ' LIMIT 1' : ''),
            $params
        );
        $st->setFetchMode(\PDO::FETCH_ASSOC);
        if ($limitOne) {
            $row = $st->fetch();
            if ($row === false) {
                return null;
            }
            if (!is_array($row)) {
                throw new ConfigurationException('Expected associative row data from PDO.');
            }
            return static::newInstanceFromDatabaseRow($row);
        }
        $results = [];
        while ($row = $st->fetch()) {
            if (!is_array($row)) {
                throw new ConfigurationException('Expected associative row data from PDO.');
            }
            $results[] = static::newInstanceFromDatabaseRow($row);
        }
        return $results;
    }

    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array<int, mixed> $params optional params to be escaped and injected into the SQL query (standard PDO syntax)
     * @param bool $limitOne if true the first match will be returned
     *
     * @return array|static|null
     */
    public static function fetchWhere(string $SQLfragment = '', array $params = [], bool $limitOne = false): array|static|null
    {
        return static::fetchWhereWithSuffix($SQLfragment, $params, $limitOne);
    }

    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standard PDO syntax)
     *
     * @return array object[] of objects of calling class
     */
    public static function fetchAllWhere(string $SQLfragment = '', array $params = []): array
    {
        /** @var array $results */
        $results = static::fetchWhere($SQLfragment, $params, false);
        return $results;
    }

    /**
     * returns an object of the sub-class which matches the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standard PDO syntax)
     *
     * @return static|null object of calling class
     */
    public static function fetchOneWhere(string $SQLfragment = '', array $params = []): ?static
    {
        /** @var static $result */
        $result = static::fetchWhere($SQLfragment, $params, true);
        return $result;
    }

    /**
     * Fetch all matching rows ordered by a real model field.
     *
     * @param string $orderByField
     * @param string $direction
     * @param string $SQLfragment
     * @param array<int, mixed> $params
     * @param int|null $limit
     *
     * @return array<int, static>
     */
    public static function fetchAllWhereOrderedBy(
        string $orderByField,
        string $direction = 'ASC',
        string $SQLfragment = '',
        array $params = [],
        ?int $limit = null
    ): array {
        $suffix = static::buildOrderAndLimitClause($orderByField, $direction, $limit);

        /** @var array<int, static> $results */
        $results = static::fetchWhereWithSuffix($SQLfragment, $params, false, $suffix);
        return $results;
    }

    /**
     * Fetch the first matching row using an explicit model-field ordering.
     *
     * @param string $orderByField
     * @param string $direction
     * @param string $SQLfragment
     * @param array<int, mixed> $params
     *
     * @return static|null
     */
    public static function fetchOneWhereOrderedBy(
        string $orderByField,
        string $direction = 'ASC',
        string $SQLfragment = '',
        array $params = []
    ): ?static {
        /** @var static|null $result */
        $result = static::fetchWhereWithSuffix(
            $SQLfragment,
            $params,
            true,
            static::buildOrderAndLimitClause($orderByField, $direction)
        );

        return $result;
    }

    /**
     * Return a single column from matching rows.
     *
     * @param string $fieldname
     * @param string $SQLfragment
     * @param array<int, mixed> $params
     * @param string|null $orderByField
     * @param string $direction
     * @param int|null $limit
     *
     * @return array<int, mixed>
     */
    public static function pluck(
        string $fieldname,
        string $SQLfragment = '',
        array $params = [],
        ?string $orderByField = null,
        string $direction = 'ASC',
        ?int $limit = null
    ): array {
        $query = 'SELECT ' . static::_quote_identifier(static::resolveFieldName($fieldname)) .
            ' FROM ' . static::_quote_identifier(static::$_tableName) .
            static::addWherePrefix($SQLfragment) .
            static::buildOrderAndLimitClause($orderByField, $direction, $limit);
        $st = static::execute($query, $params);

        /** @var array<int, mixed> $values */
        $values = $st->fetchAll(\PDO::FETCH_COLUMN, 0);
        return $values;
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
     * @param array  $params optional params to be escaped and injected into the SQL query (standard PDO syntax)
     *
     * @return \PDOStatement
     */
    public static function deleteAllWhere(string $where, array $params = []): \PDOStatement
    {
        $st = static::execute(
            'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . $where,
            $params
        );
        return $st;
    }

    /**
     * Legacy static validation hook kept for backward compatibility.
     *
     * @return boolean true or throws exception on error
     */
    public static function validate(): bool
    {
        return true;
    }

    /**
     * Shared instance-aware validation hook for insert and update.
     *
     * @return void
     */
    protected function validateForSave(): void
    {
        static::validate();
    }

    /**
     * Instance-aware validation hook that runs after validateForSave() on insert.
     *
     * @return void
     */
    protected function validateForInsert(): void
    {
    }

    /**
     * Instance-aware validation hook that runs after validateForSave() on update.
     *
     * @return void
     */
    protected function validateForUpdate(): void
    {
    }

    /**
     * @return void
     */
    protected function runInsertValidation(): void
    {
        $this->validateForSave();
        $this->validateForInsert();
    }

    /**
     * @return void
     */
    protected function runUpdateValidation(): void
    {
        $this->validateForSave();
        $this->validateForUpdate();
    }

    /**
     * Return a UTC timestamp string suitable for the built-in timestamp columns.
     *
     * @return string
     */
    protected static function currentTimestamp(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Begin a transaction on the current model connection.
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return static::requireDb()->beginTransaction();
    }

    /**
     * Commit the current transaction on the model connection.
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return static::requireDb()->commit();
    }

    /**
     * Roll back the current transaction on the model connection.
     *
     * @return bool
     */
    public static function rollBack(): bool
    {
        return static::requireDb()->rollBack();
    }

    /**
     * Run work inside a transaction, reusing any outer transaction when already active.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public static function transaction(callable $callback): mixed
    {
        $db = static::requireDb();
        if ($db->inTransaction()) {
            return $callback();
        }

        $db->beginTransaction();

        try {
            $result = $callback();
            $db->commit();

            return $result;
        } catch (\Throwable $throwable) {
            try {
                $db->rollBack();
            } catch (\PDOException) {
                // Ignore rollback failures when the callback already closed the transaction.
            }

            throw $throwable;
        }
    }

    /**
     * insert a row into the database table, and update the primary key field with the one generated on insert
     *
     * @param  boolean  $autoTimestamp  true by default will set updated_at & created_at fields if present
     * @param  boolean  $allowSetPrimaryKey  if true include primary key field in insert (ie. you want to set it yourself)
     *
     * @return boolean indicating success
     * @throws \PDOException
     * @throws ModelException
     */
    public function insert(bool $autoTimestamp = true, bool $allowSetPrimaryKey = false): bool
    {
        $pk      = static::$_primary_column_name;
        $timeStr = static::currentTimestamp();
        $this->applyAutomaticInsertTimestamps($timeStr, $autoTimestamp);
        $this->runInsertValidation();
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

        if (count($set['columns']) === 0) {
            if ($driver === 'sqlite' || $driver === 'sqlite2') {
                $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) . ' DEFAULT VALUES';
                $st = static::execute($query);
            } else {
                $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) . ' () VALUES ()';
                $st = static::execute($query);
            }
        } else {
            $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) .
                ' (' . implode(', ', $set['columns']) . ')' .
                ' VALUES (' . implode(', ', $set['values']) . ')';
            $st    = static::execute($query, $set['params']);
        }
        if ($st->rowCount() == 1) {
            if ($allowSetPrimaryKey !== true) {
                $db = static::$_db;
                if (!$db) {
                    throw new ConnectionException('No database connection setup');
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
    public function update(bool $autoTimestamp = true): bool
    {
        $this->applyAutomaticUpdateTimestamp($autoTimestamp);
        $this->runUpdateValidation();
        $set             = $this->setString();
        if ($set['sql'] === '') {
            return false;
        }
        $limitClause     = static::supportsUpdateLimit() ? ' LIMIT 1' : '';
        $query           = 'UPDATE ' . static::_quote_identifier(static::$_tableName) . ' SET ' . $set['sql'] . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ?' . $limitClause;
        $set['params'][] = $this->{static::$_primary_column_name};
        $st              = static::execute(
            $query,
            $set['params']
        );
        if ($this->updateSucceeded($st)) {
            $this->clearDirtyFields();
            return true;
        }
        return false;
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
    public static function execute(string $query, array $params = []): \PDOStatement
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
    protected static function _prepare(string $query): \PDOStatement
    {
        $db = static::requireDb();
        $connectionId = spl_object_id($db);
        if (!isset(static::$_stmt[$connectionId])) {
            static::$_stmt[$connectionId] = array();
        }
        if (!isset(static::$_stmt[$connectionId][$query])) {
            // cache prepared query if not seen before
            static::$_stmt[$connectionId][$query] = $db->prepare($query);
        }
        return static::$_stmt[$connectionId][$query]; // return cache copy
    }

    /**
     * call update if primary key field is present, else call insert
     *
     * @return boolean indicating success
     */
    public function save(): bool
    {
        if ($this->hasPrimaryKeyValue()) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Enable or disable strict field assignment for the current model class.
     *
     * @param bool $strict
     *
     * @return void
     */
    public static function useStrictFields(bool $strict = true): void
    {
        static::$_strict_fields = $strict;
    }

    /**
     * @return bool
     */
    public static function strictFieldsEnabled(): bool
    {
        return static::$_strict_fields;
    }

    /**
     * @param mixed $state
     *
     * @return \stdClass
     */
    protected function normaliseSerializedState($state): \stdClass
    {
        if ($state instanceof \stdClass) {
            return $state;
        }
        if (is_array($state)) {
            return (object) $state;
        }

        return new \stdClass();
    }

    /**
     * @return bool
     */
    protected function hasPrimaryKeyValue(): bool
    {
        $primaryKey = static::$_primary_column_name;
        $data = $this->data;
        if (!$data instanceof \stdClass || !property_exists($data, $primaryKey)) {
            return false;
        }

        return $this->$primaryKey !== null;
    }

    /**
     * Determine whether an update succeeded even when the driver reports zero changed rows.
     *
     * @param \PDOStatement $statement
     *
     * @return bool
     */
    protected function updateSucceeded(\PDOStatement $statement): bool
    {
        $count = $statement->rowCount();

        if ($count === 1) {
            return true;
        }

        if ($count === 0) {
            return static::existsWhere(
                static::_quote_identifier(static::$_primary_column_name) . ' = ?',
                [$this->{static::$_primary_column_name}]
            );
        }

        throw new ModelException(
            sprintf(
                'Update affected %d rows for %s; expected at most one row.',
                $count,
                static::class
            )
        );
    }

    /**
     * @param mixed $match
     *
     * @return bool
     */
    protected static function isEmptyMatchList($match): bool
    {
        return is_array($match) && $match === array();
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
    protected function setString(bool $ignorePrimary = true): array
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
        $data = $this->data;
        foreach (static::getFieldnames() as $field) {
            if ($ignorePrimary && $field == static::$_primary_column_name) {
                continue;
            }
            if (!$data instanceof \stdClass || !property_exists($data, $field) || !$this->isFieldDirty($field)) {
                continue;
            }
            $columns[] = static::_quote_identifier($field);
            $value = $this->prepareAttributeForDatabase($field, $this->$field);
            if ($value === null) {
                // if empty set to NULL
                $fragments[] = static::_quote_identifier($field) . ' = NULL';
                $values[] = 'NULL';
            } else {
                // Just set value normally as not empty string with NULL allowed
                $fragments[] = static::_quote_identifier($field) . ' = ?';
                $values[] = '?';
                $params[]    = $value;
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
     * @throws ConnectionException
     */
    protected static function supportsUpdateLimit(): bool
    {
        $driver = static::getDriverName();
        return ($driver === 'mysql');
    }

    /**
     * Determine whether the driver supports LIMIT on DELETE.
     *
     * @return bool
     * @throws ConnectionException
     */
    protected static function supportsDeleteLimit(): bool
    {
        $driver = static::getDriverName();
        return ($driver === 'mysql');
    }

    /**
     * Hydrate a model instance from a database row and clear dirty tracking.
     *
     * @param array<string, mixed> $row
     *
     * @return static
     */
    protected static function newInstanceFromDatabaseRow(array $row): static
    {
        $instance = new static();
        $instance->hydrateFromDatabase($row);
        $instance->clearDirtyFields();

        return $instance;
    }

    /**
     * Hydrate known fields from database results so casts are applied on read.
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    protected function hydrateFromDatabase(array $data): void
    {
        foreach (static::getFieldnames() as $fieldname) {
            if (array_key_exists($fieldname, $data)) {
                $this->assignAttribute($fieldname, $data[$fieldname], true, true);
            } elseif (!isset($this->$fieldname)) {
                $this->assignAttribute($fieldname, null, true, true);
            }
        }
    }

    /**
     * Assign a value to the model, optionally treating it as database input.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $markDirty
     * @param bool $fromDatabase
     *
     * @return void
     */
    protected function assignAttribute(string $name, mixed $value, bool $markDirty = true, bool $fromDatabase = false): void
    {
        if (static::strictFieldsEnabled()) {
            $name = static::resolveFieldName($name);
        }
        if (!$this->hasData()) {
            $this->data = new \stdClass();
        }
        $this->data->$name = $fromDatabase
            ? $this->castAttributeFromDatabase($name, $value)
            : $this->castAttributeForAssignment($name, $value);
        if ($markDirty) {
            $this->markFieldDirty($name);
        }
    }

    /**
     * @return \PDO
     */
    protected static function requireDb(): \PDO
    {
        $db = static::$_db;
        if (!$db instanceof \PDO) {
            throw new ConnectionException('No database connection setup');
        }

        return $db;
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castAttributeForAssignment(string $field, mixed $value): mixed
    {
        return $this->castAttributeValue($field, $value, false);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castAttributeFromDatabase(string $field, mixed $value): mixed
    {
        return $this->castAttributeValue($field, $value, true);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param bool $fromDatabase
     *
     * @return mixed
     */
    protected function castAttributeValue(string $field, mixed $value, bool $fromDatabase): mixed
    {
        $castType = static::normaliseCastType(static::$_casts[$field] ?? null);
        if ($castType === null || $value === null) {
            return $value;
        }

        return match ($castType) {
            'integer' => $this->castIntegerValue($field, $value),
            'float' => $this->castFloatValue($field, $value),
            'boolean' => $this->castBooleanValue($value),
            'datetime' => $this->castDateTimeValue($field, $value),
            'array' => $this->castArrayValue($field, $value, $fromDatabase),
            'object' => $this->castObjectValue($field, $value, $fromDatabase),
            default => $value,
        };
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function prepareAttributeForDatabase(string $field, mixed $value): mixed
    {
        $castType = static::normaliseCastType(static::$_casts[$field] ?? null);
        if ($castType === null || $value === null) {
            return $value;
        }

        return match ($castType) {
            'datetime' => $this->formatDateTimeValue($field, $value),
            'array', 'object' => $this->encodeJsonValue($field, $value),
            default => $value,
        };
    }

    /**
     * @param string|null $castType
     *
     * @return string|null
     */
    protected static function normaliseCastType(?string $castType): ?string
    {
        return match ($castType) {
            null => null,
            'int', 'integer' => 'integer',
            'float', 'double', 'real' => 'float',
            'bool', 'boolean' => 'boolean',
            'datetime' => 'datetime',
            'array', 'json', 'json_array' => 'array',
            'object', 'json_object' => 'object',
            default => throw new ConfigurationException('Unsupported cast type [' . $castType . '] for model ' . static::class),
        };
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    protected function castBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $normalised = strtolower(trim($value));
            if (in_array($normalised, ['1', 'true', 't', 'yes', 'y', 'on'], true)) {
                return true;
            }
            if (in_array($normalised, ['0', 'false', 'f', 'no', 'n', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return int
     */
    protected function castIntegerValue(string $field, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) || is_float($value) || is_bool($value)) {
            return (int) $value;
        }

        throw new ModelException('Unable to cast [' . $field . '] to integer for model ' . static::class);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return float
     */
    protected function castFloatValue(string $field, mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value) || is_string($value) || is_bool($value)) {
            return (float) $value;
        }

        throw new ModelException('Unable to cast [' . $field . '] to float for model ' . static::class);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return \DateTimeImmutable
     */
    protected function castDateTimeValue(string $field, mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
        }
        if (is_string($value)) {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        }

        throw new ModelException('Unable to cast [' . $field . '] to datetime for model ' . static::class);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return string
     */
    protected function formatDateTimeValue(string $field, mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        throw new ModelException('Unable to format [' . $field . '] datetime value for model ' . static::class);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param bool $fromDatabase
     *
     * @return array<mixed>
     */
    protected function castArrayValue(string $field, mixed $value, bool $fromDatabase): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }
        if (is_string($value)) {
            $decoded = $this->decodeJsonValue($field, $value, true);
            if (!is_array($decoded)) {
                throw new ModelException('Expected JSON array for [' . $field . '] on model ' . static::class);
            }

            return $decoded;
        }
        if (!$fromDatabase && is_object($value)) {
            return get_object_vars($value);
        }

        throw new ModelException('Unable to cast [' . $field . '] to array for model ' . static::class);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param bool $fromDatabase
     *
     * @return object
     */
    protected function castObjectValue(string $field, mixed $value, bool $fromDatabase): object
    {
        if (is_object($value)) {
            return $value;
        }
        if (is_array($value)) {
            return (object) $value;
        }
        if (is_string($value)) {
            $decoded = $this->decodeJsonValue($field, $value, false);
            if (!is_object($decoded)) {
                throw new ModelException('Expected JSON object for [' . $field . '] on model ' . static::class);
            }

            return $decoded;
        }

        throw new ModelException('Unable to cast [' . $field . '] to object for model ' . static::class);
    }

    /**
     * @param string $field
     * @param string $value
     * @param bool $assoc
     *
     * @return mixed
     */
    protected function decodeJsonValue(string $field, string $value, bool $assoc): mixed
    {
        try {
            return json_decode($value, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ModelException(
                'Unable to decode JSON for [' . $field . '] on model ' . static::class . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return string
     */
    protected function encodeJsonValue(string $field, mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ModelException(
                'Unable to encode JSON for [' . $field . '] on model ' . static::class . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * @param string $timestamp
     * @param bool $autoTimestamp
     *
     * @return void
     */
    protected function applyAutomaticInsertTimestamps(string $timestamp, bool $autoTimestamp): void
    {
        if (!$autoTimestamp || !static::automaticTimestampsEnabled()) {
            return;
        }

        $createdColumn = static::createdTimestampColumn();
        if ($createdColumn !== null && in_array($createdColumn, static::getFieldnames(), true)) {
            $this->$createdColumn = $timestamp;
        }

        $updatedColumn = static::updatedTimestampColumn();
        if ($updatedColumn !== null && in_array($updatedColumn, static::getFieldnames(), true)) {
            $this->$updatedColumn = $timestamp;
        }
    }

    /**
     * @param bool $autoTimestamp
     *
     * @return void
     */
    protected function applyAutomaticUpdateTimestamp(bool $autoTimestamp): void
    {
        if (!$autoTimestamp || !static::automaticTimestampsEnabled()) {
            return;
        }

        $updatedColumn = static::updatedTimestampColumn();
        if ($updatedColumn !== null && in_array($updatedColumn, static::getFieldnames(), true)) {
            $this->$updatedColumn = static::currentTimestamp();
        }
    }

    /**
     * @return bool
     */
    protected static function automaticTimestampsEnabled(): bool
    {
        return static::$_auto_timestamps;
    }

    /**
     * @return string|null
     */
    protected static function createdTimestampColumn(): ?string
    {
        return static::$_created_at_column;
    }

    /**
     * @return string|null
     */
    protected static function updatedTimestampColumn(): ?string
    {
        return static::$_updated_at_column;
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
