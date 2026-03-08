# Roadmap

This roadmap now tracks only the remaining optional backlog for `freshsauce/model`.

Phases 1 through 3 are complete, and Phase 4.1 through 4.3 have been delivered.

## Remaining item: 4.4 Composite keys or relationship support

Why this is last:

- This is where complexity rises sharply.
- It should only happen if the maintainer wants the library to move beyond lightweight active-record usage.

Recommendation:

- Do not start here by default.
- Re-evaluate only after clear real-world demand.
- Preserve the package's lightweight, PDO-first position if any further expansion happens.

## Out of scope unless demand changes

- Full relationship mapping
- Eager/lazy loading systems
- A chainable query builder comparable to framework ORMs
- Migration tooling
- Schema management

Those features would change the character of the package more than they would improve the current design.
