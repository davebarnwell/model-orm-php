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

Summary:

- Fixed serialization, zero-like primary key handling, invalid dynamic finder failures, and empty-array query behavior.
- Replaced generic exceptions with a small library exception hierarchy.
- Added regression coverage for the above edge cases.

## Phase 2: API ergonomics and typing

Status: completed

Summary:

- Added instance-aware validation hooks with legacy compatibility.
- Added optional strict field handling and focused query helpers.
- Tightened typing, static analysis, and public documentation around the preferred API.

## Phase 3: Quality, portability, and maintenance

Status: completed

Summary:

- Expanded cross-driver integration coverage for connection sharing, custom keys, metadata refresh, timestamp behavior, and PostgreSQL schema-qualified tables.
- Added `refreshTableMetadata()` and made UTC timestamp behavior explicit.
- Normalized no-op update handling while preserving single-row primary key update expectations.

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
