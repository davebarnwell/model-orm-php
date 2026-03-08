# Roadmap

This roadmap tracks the improvement work for `freshsauce/model`.

Current status:

1. Phase 1 is complete.
2. Phase 2 is complete.
3. Phase 3 is complete.
4. Phase 4 remains optional and has not been started.

The sequencing remains intentional:

1. Fix correctness issues before expanding the API.
2. Improve developer ergonomics without turning the library into a heavyweight ORM.
3. Tighten quality and portability before considering broader feature growth.
4. Add optional features only where they preserve the package's lightweight position.

## Principles

- Keep PDO-first escape hatches intact.
- Prefer additive changes with low migration cost.
- Tighten behavior with tests before changing public APIs.
- Avoid feature growth that pushes the library toward framework-scale complexity.

## Phase 1: Core correctness and safety

Status: completed

Outcome:

- Replaced the broken serialization path with `__serialize()` / `__unserialize()` behavior and covered round-trip cases with tests.
- Made persisted-state detection explicit so zero-like primary key values do not trigger incorrect inserts.
- Changed invalid dynamic finder field resolution to fail fast with model-level exceptions.
- Defined empty-array matching behavior across collection, singular, and count helpers without generating invalid SQL.
- Introduced a library exception hierarchy and replaced generic exceptions in core failure paths.

Goal: remove known edge-case bugs and make failure modes explicit.

Priority: high

### Milestone 1.1: Serialization works correctly

Problem:
`Model::__sleep()` returns table field names, but model state lives in `$data` and `$dirty`. Serializing a model currently emits warnings and drops state.

Tasks:

- Replace `__sleep()` with `__serialize()` and `__unserialize()`.
- Preserve hydrated values and dirty-state behavior after unserialization.
- Decide whether deserialized models should retain dirty flags or reset to clean.
- Add PHPUnit coverage for round-trip serialization of new and persisted models.

Acceptance criteria:

- `serialize($model)` produces no warnings.
- `unserialize(serialize($model))` preserves field values.
- Dirty-state behavior after round-trip is documented and tested.

### Milestone 1.2: Persisted-state detection is explicit

Problem:
`save()` uses truthiness on the primary key. A value like `0` is treated as "new" and triggers insert instead of update.

Tasks:

- Introduce a dedicated persisted-state check based on `null` rather than truthiness.
- Review insert/update/delete behavior for zero-like primary key values.
- Add tests for integer `0`, string `'0'`, and non-default primary key names.

Acceptance criteria:

- `save()` updates when a record has a zero-like primary key value.
- Insert behavior remains unchanged for `null` primary keys.

### Milestone 1.3: Dynamic finder failures are model-level errors

Problem:
Unknown dynamic fields fall through to raw SQL execution and surface as PDO errors instead of clear model exceptions.

Tasks:

- Make `resolveFieldName()` fail fast when a field does not map to a real column.
- Add a dedicated exception for unknown fields or invalid dynamic methods.
- Add tests for invalid `findBy...`, `findOneBy...`, and `countBy...` calls.

Acceptance criteria:

- Invalid dynamic finders throw a predictable library exception before query execution.
- Error messages identify the requested field and model class.

### Milestone 1.4: Empty-array query behavior is defined

Problem:
Helpers that build `IN (...)` clauses do not define behavior for empty arrays.

Tasks:

- Define expected behavior for empty-array matches across:
  - `findBy...`
  - `findOneBy...`
  - `firstBy...`
  - `lastBy...`
  - `countBy...`
  - `fetchAllWhereMatchingSingleField()`
  - `fetchOneWhereMatchingSingleField()`
- Implement that behavior without generating invalid SQL.
- Add regression tests for each public entry point.

Acceptance criteria:

- Empty arrays never produce invalid SQL.
- Collection methods return empty results.
- Singular methods return `null`.
- Count methods return `0`.

### Milestone 1.5: Replace generic exceptions with library exceptions

Problem:
The model currently throws generic `\Exception` in many places, which makes calling code and tests less precise.

Tasks:

- Introduce a small exception hierarchy under `Freshsauce\Model\Exception\`.
- Replace generic throws for:
  - missing connection
  - unknown field
  - invalid dynamic method
  - missing data access
  - identifier quoting setup failures
- Keep exception names narrow and practical.

Acceptance criteria:

- Core failure modes throw specific exception classes.
- Existing messages remain readable.
- Public docs mention the main exception types users should expect.

## Phase 2: API ergonomics and typing

Status: completed

Outcome:

- Added instance-aware validation hooks while preserving the legacy validation path for compatibility.
- Added optional strict field handling so unknown assignments can fail loudly when enabled.
- Added focused query helpers for existence checks, ordered fetches, and plucking values without introducing a full query builder.
- Tightened typing with `declare(strict_types=1);` and refreshed static-analysis-friendly signatures and docs.
- Updated public documentation to lead with the preferred camelCase API and newer validation/strict-mode behavior.

Goal: make the library easier to use correctly while keeping the current lightweight style.

Priority: medium

### Milestone 2.1: Validation becomes instance-aware

Problem:
`validate()` is declared static, but it is invoked from instance writes. That makes record-aware validation awkward.

Tasks:

- Change validation to an instance hook, or introduce `validateForInsert()` and `validateForUpdate()` instance hooks.
- Preserve backward compatibility where practical, or provide a clear migration path.
- Add tests covering validation against current field values.

Acceptance criteria:

- Validation can inspect instance state directly.
- Validation behavior for insert vs update is documented.

### Milestone 2.2: Strict field handling is available

Problem:
Unknown fields can be assigned silently via `__set()`, but are ignored during persistence. That hides typos.

Tasks:

- Add an optional strict mode that rejects assignment to unknown fields.
- Decide whether strict mode is global, per model, or opt-in at runtime.
- Keep the current permissive mode available for legacy integrations.
- Add tests for strict and permissive behavior.

Acceptance criteria:

- Strict mode raises a clear exception on unknown field assignment.
- Default behavior remains stable unless the user opts in.

### Milestone 2.3: Add focused query helpers

Problem:
Many common query patterns require manual SQL fragments, which works but is unnecessarily error-prone.

Tasks:

- Add a small set of helpers with clear value:
  - `exists()`
  - `existsWhere()`
  - `pluck(string $field, ... )`
  - `orderBy(string $field, string $direction)` via new fetch helpers, not a full query builder
  - `limit(int $n)` support in helper methods where it keeps the API simple
- Avoid introducing a chainable query-builder unless a later need is proven.

Acceptance criteria:

- Common "check existence", "single column list", and "ordered fetch" cases need less handwritten SQL.
- The API remains smaller than a full query-builder abstraction.

### Milestone 2.4: Tighten types and static analysis

Problem:
The library runs on PHP 8.3+ but still carries loose typing in several public and protected methods.

Tasks:

- Add `declare(strict_types=1);` to source and tests.
- Add explicit parameter and return types where missing.
- Improve PHPDoc for dynamic methods and arrays.
- Reduce `phpstan.neon` ignores where feasible.

Acceptance criteria:

- PHPStan remains green at the current level.
- Public APIs are more explicit and easier to consume from IDEs.

### Milestone 2.5: Refresh documentation around modern usage

Problem:
The docs still present deprecated snake_case dynamic methods in examples and do not explain newer behavior clearly enough.

Tasks:

- Update `README.md` and `EXAMPLE.md` to lead with camelCase dynamic methods only.
- Add migration notes for deprecated snake_case methods.
- Document strict mode, validation hooks, and exception behavior once shipped.
- Add a short "when to use this library" section to reinforce scope boundaries.

Acceptance criteria:

- Public docs reflect the preferred API.
- Deprecated behavior is documented as transitional, not primary.

## Phase 3: Quality, portability, and maintenance

Status: completed

Outcome:

- Expanded integration coverage for inherited connections, custom primary keys, metadata refresh, timestamp opt-out behavior, timestampless tables, and PostgreSQL schema-qualified tables.
- Added `refreshTableMetadata()` so cached column metadata can be refreshed without reconnecting.
- Normalized update success behavior for no-op writes while preserving the invariant that primary-key updates must not affect multiple rows.
- Made UTC timestamp behavior explicit in code and documentation.

Goal: make the library easier to maintain and safer across supported databases.

Priority: medium

### Milestone 3.1: Expand edge-case test coverage

Tasks:

- Add regression tests for every Phase 1 fix.
- Add tests for multiple model subclasses sharing and isolating connections.
- Add tests for schema-qualified PostgreSQL table names.
- Add tests for custom primary key column names.
- Add tests for timestamp opt-out behavior.

Acceptance criteria:

- New fixes are guarded by tests.
- Cross-driver behavior is better documented in test form.

### Milestone 3.2: Review statement and metadata caching behavior

Problem:
Statement caching is now keyed by connection, which is good, but metadata and caching behavior should stay predictable as the library grows.

Tasks:

- Audit cache invalidation rules for reconnection and subclass-specific connections.
- Decide whether table column metadata should be refreshable without reconnecting.
- Add tests around reconnect and metadata refresh behavior.

Acceptance criteria:

- Reconnection cannot leak stale prepared statements.
- Metadata caching behavior is documented and tested.

### Milestone 3.3: Normalize exception and timestamp behavior across drivers

Tasks:

- Verify `rowCount()` assumptions for update/delete across supported drivers.
- Review timestamp formatting consistency for MySQL, PostgreSQL, and SQLite.
- Ensure identifier quoting and schema discovery stay correct for each supported driver.

Acceptance criteria:

- Driver-specific behavior is explicit where unavoidable.
- Public docs do not imply unsupported guarantees.

## Phase 4: Optional feature expansion

Goal: add features that help real applications, but only if they fit the package's lightweight position.

Priority: lower

Phases 1 through 3 are complete, so this is now the remaining backlog.

### Candidate 4.1: Transaction helpers

Possible scope:

- `transaction(callable $callback)`
- pass through `beginTransaction()`, `commit()`, `rollBack()` wrappers

Why:
This adds practical value without changing the core model shape.

### Candidate 4.2: Configurable timestamp columns

Possible scope:

- opt-in timestamp column names
- disable automatic timestamps per model

Why:
The current `created_at` / `updated_at` convention is convenient but rigid.

### Candidate 4.3: Attribute casting

Possible scope:

- integer
- float
- boolean
- datetime
- JSON array/object

Why:
Casting improves ergonomics substantially without requiring relationships or a large query layer.

### Candidate 4.4: Composite keys or relationship support

Why this is last:
This is where complexity rises sharply. It should only happen if the maintainer wants the library to move beyond lightweight active-record usage.

Recommendation:

- Do not start here by default.
- Re-evaluate only after the earlier phases have shipped and real user demand is clear.

## Suggested issue order

If this work is split into GitHub issues, the most practical order is:

1. Add transaction helpers.
2. Add configurable timestamp column support.
3. Add attribute casting.
4. Re-evaluate whether composite keys or relationship support are warranted.

## Suggested release strategy

- Release 1: Phase 1 correctness and exception work. Shipped.
- Release 2: Phase 2 ergonomics, typing, and documentation updates. Shipped.
- Release 3: Phase 3 portability and maintenance hardening. Shipped.
- Release 4: optional feature work only if it still fits the package scope.

## Out of scope unless demand changes

- Full relationship mapping
- Eager/lazy loading systems
- A chainable query builder comparable to framework ORMs
- Migration tooling
- Schema management

Those features would change the character of the package more than they would improve the current design.
