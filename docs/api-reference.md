# API Reference

This reference documents the public API of `Freshsauce\Model\Model` as implemented in [`src/Model/Model.php`](../src/Model/Model.php).

## Class configuration

These are the static members you are expected to override in subclasses.

### `protected static $_tableName`

Required. The database table for the model.

```php
protected static $_tableName = 'categories';
```

### `protected static $_primary_column_name`

Optional. Defaults to `id`.

```php
protected static $_primary_column_name = 'code';
```

### `protected static bool $_strict_fields`

Optional. Defaults to `false`. When enabled, unknown assignments throw `UnknownFieldException`.

### `protected static bool $_auto_timestamps`

Optional. Defaults to `true`. Set to `false` to disable built-in automatic timestamp handling for the model.

### `protected static ?string $_created_at_column`

Optional. Defaults to `created_at`. Set to a different column name to customise insert timestamps, or `null` to disable created-at writes.

### `protected static ?string $_updated_at_column`

Optional. Defaults to `updated_at`. Set to a different column name to customise insert/update timestamps, or `null` to disable updated-at writes.

### `protected static array $_casts`

Optional. Field cast map. Supported cast types are `integer`, `float`, `boolean`, `datetime`, `array`, and `object`.

For `datetime`, string values are interpreted as UTC wall-time values. Prefer `DATETIME`-style columns, or ensure the connection session timezone is UTC when using database types that perform timezone conversion.

### `public static $_db`

Inherited shared PDO connection. Redeclare this in a subclass only when that subclass needs an isolated connection.

## Construction and state

### `__construct(array $data = [])`

Initialises the model and hydrates known columns from the provided array.

### `hasData(): bool`

Returns whether the internal data object exists.

### `dataPresent(): bool`

Returns `true` when data is present, otherwise throws `MissingDataException`.

### `hydrate(array $data): void`

Maps known table columns from the input array onto the model. Unknown keys are ignored.

### `clear(): void`

Sets all known columns to `null` and resets dirty tracking.

### `toArray()`

Returns an associative array of known table columns and their current values.

### `markFieldDirty(string $name): void`

Marks a field dirty manually.

### `isFieldDirty(string $name): bool`

Returns whether a field is dirty.

### `clearDirtyFields(): void`

Clears the dirty-field tracking state.

### `__sleep()`

Serialises the `data` and `dirty` properties.

### `__serialize(): array`

Returns a normalised serialisable payload containing `data` and `dirty`.

### `__unserialize(array $data): void`

Restores serialised model state.

## Property access

### `__set(string $name, mixed $value): void`

Assigns a value to the model.

Behavior:

- in strict mode, resolves the name against real fields first
- creates the internal data object on first assignment
- applies configured attribute casts before storing the value
- marks the field as dirty

### `__get(string $name): mixed`

Returns a field value from the internal data store.

When a field is configured in `$_casts`, the returned value is the cast PHP value.

Throws:

- `MissingDataException` when data has not been initialised
- `UnknownFieldException` when the field is not present in the current data object

### `__isset(string $name): bool`

Returns whether the current data object contains the field.

## Connection and database helpers

### `connectDb(string $dsn, string $username, string $password, array $driverOptions = []): void`

Creates and stores the PDO connection for the current model class hierarchy.

Notes:

- sets `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`
- clears cached prepared statements for the previous connection
- clears cached column metadata

### `driverName(): string`

Returns the active PDO driver name.

### `refreshTableMetadata(): void`

Clears the cached table-column list for the current model class.

Use this after runtime schema changes.

### `execute(string $query, array $params = []): PDOStatement`

Prepares and executes a statement, returning the `PDOStatement`.

### `beginTransaction(): bool`

Begins a transaction on the current model connection.

### `commit(): bool`

Commits the current transaction on the current model connection.

### `rollBack(): bool`

Rolls back the current transaction on the current model connection.

### `transaction(callable $callback): mixed`

Runs the callback inside a transaction and returns the callback result.

Behavior:

- begins and commits a transaction when no transaction is active
- rolls back automatically when the callback throws
- reuses an already-open outer transaction instead of nesting another one

### `datetimeToMysqldatetime(int|string $dt): string`

Converts a Unix timestamp or date string into `Y-m-d H:i:s`.

Invalid date strings are converted as timestamp `0`.

### `createInClausePlaceholders(array $params): string`

Returns a comma-separated placeholder string for `IN (...)` clauses.

Examples:

- `[1, 2, 3]` -> `?,?,?`
- `[]` -> `NULL`

## Record lifecycle

### `save(): bool`

Calls `update()` when the primary key is non-`null`; otherwise calls `insert()`.

Primary key values `0` and `'0'` count as existing primary keys and therefore use the update path.

### `insert(bool $autoTimestamp = true, bool $allowSetPrimaryKey = false): bool`

Inserts the current model as a new row.

Behavior:

- auto-fills the configured created/update timestamp columns when enabled and the fields exist
- runs `validateForSave()` and `validateForInsert()`
- clears dirty flags on success
- updates the model's primary key from the database when the key is generated by the database
- supports default-values inserts when there are no dirty fields

Set `$allowSetPrimaryKey` to `true` to include the current primary key value in the insert.

### `update(bool $autoTimestamp = true): bool`

Updates the current row by primary key.

Behavior:

- auto-fills the configured update timestamp column when enabled and the field exists
- runs `validateForSave()` and `validateForUpdate()`
- updates only dirty known fields
- returns `false` when there are no dirty fields to write
- treats zero changed rows as success when the target row still exists

### `delete()`

Deletes the current row by primary key. Returns the result of `deleteById()`.

### `deleteById(int|string $id): bool`

Deletes one row by primary key. Returns `true` only when one row was removed.

### `deleteAllWhere(string $where, array $params = []): PDOStatement`

Deletes rows matching a condition fragment. Returns the raw statement.

Pass only the expression that belongs after `WHERE`.

## Reading records

### `getById(int|string $id): ?static`

Returns one model instance for the matching primary key, or `null`.

### `first(): ?static`

Returns the row with the lowest primary key value, or `null`.

### `last(): ?static`

Returns the row with the highest primary key value, or `null`.

### `find($id)`

Returns an array of model instances matching the primary key value.

This is intentionally different from `getById()`, which returns a single instance or `null`.

### `count(): int`

Returns the total row count for the model table.

### `exists(): bool`

Returns whether the table contains at least one row.

## Conditional reads

### `fetchWhere(string $SQLfragment = '', array $params = [], bool $limitOne = false): array|static|null`

Base helper for conditional reads.

Pass only the fragment that belongs after `WHERE`.

### `fetchAllWhere(string $SQLfragment = '', array $params = []): array`

Returns all matching rows as model instances.

### `fetchOneWhere(string $SQLfragment = '', array $params = []): ?static`

Returns the first matching row as a model instance, or `null`.

### `fetchAllWhereOrderedBy(string $orderByField, string $direction = 'ASC', string $SQLfragment = '', array $params = [], ?int $limit = null): array`

Returns all matching rows ordered by a real model field.

Constraints:

- `$orderByField` must resolve to a real model field
- `$direction` must be `ASC` or `DESC`
- `$limit`, when provided, must be greater than `0`

### `fetchOneWhereOrderedBy(string $orderByField, string $direction = 'ASC', string $SQLfragment = '', array $params = []): ?static`

Returns the first matching row using explicit ordering.

### `pluck(string $fieldname, string $SQLfragment = '', array $params = [], ?string $orderByField = null, string $direction = 'ASC', ?int $limit = null): array`

Returns one column from matching rows as a plain array.

Both `$fieldname` and `$orderByField` must resolve to real model fields.

### `countAllWhere(string $SQLfragment = '', array $params = []): int`

Returns the number of rows matching the condition fragment.

### `existsWhere(string $SQLfragment = '', array $params = []): bool`

Returns whether at least one row matches the condition fragment.

## Dynamic static methods

The model supports a dynamic method family through `__callStatic()`.

Preferred forms:

- `findBy<Field>($match)`
- `findOneBy<Field>($match)`
- `firstBy<Field>($match)`
- `lastBy<Field>($match)`
- `countBy<Field>($match)`

Examples:

```php
Category::findByName('Fiction');
Category::findOneByUpdatedAt('2026-03-08 12:00:00');
Category::countByName(['Fiction', 'Fantasy']);
```

Rules:

- field names are resolved against real columns
- camelCase field names can map to snake_case columns
- scalar matches become `= ?`
- array matches become `IN (...)`
- empty arrays short-circuit without querying the database

Legacy snake_case dynamic methods remain supported temporarily:

- `find_by_name($match)`
- `findOne_by_name($match)`
- `first_by_name($match)`
- `last_by_name($match)`
- `count_by_name($match)`

Those methods emit `E_USER_DEPRECATED`.

### `fetchOneWhereMatchingSingleField(string $fieldname, mixed $match, string $order): ?static`

Returns one row for a single-column match.

### `fetchAllWhereMatchingSingleField(string $fieldname, mixed $match): array`

Returns all rows for a single-column match.

## Validation extension points

### `validate(): bool`

Legacy static validation hook. Returns `true` by default.

### `validateForSave(): void`

Shared instance-level validation hook for both insert and update. By default it calls `static::validate()`.

### `validateForInsert(): void`

Insert-specific validation hook. No-op by default.

### `validateForUpdate(): void`

Update-specific validation hook. No-op by default.

Preferred customisation is to override the instance methods, not the legacy static method.

## Strict field controls

### `useStrictFields(bool $strict = true): void`

Enables or disables strict field mode for the current model class.

### `strictFieldsEnabled(): bool`

Returns whether strict field mode is currently enabled.

## Exceptions raised by the ORM

The API can raise these ORM-specific exceptions:

- `Freshsauce\Model\Exception\ConfigurationException`
- `Freshsauce\Model\Exception\ConnectionException`
- `Freshsauce\Model\Exception\InvalidDynamicMethodException`
- `Freshsauce\Model\Exception\MissingDataException`
- `Freshsauce\Model\Exception\ModelException`
- `Freshsauce\Model\Exception\UnknownFieldException`

PDO exceptions still bubble up for database-level errors.
