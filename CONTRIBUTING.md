# Contributing

Thanks for contributing to `model-orm-php`.

## Setup
- PHP 8.3+ with PDO and the driver you intend to test (`pdo_mysql` or `pdo_pgsql`).
- Install dependencies: `composer install`.
- Start local DBs (optional): `docker-compose up -d`.

## Tests
- Run: `vendor/bin/phpunit -c phpunit.xml.dist`.
- Use env overrides to target a DB:
  - `MODEL_ORM_TEST_DSN` (e.g., `mysql:host=127.0.0.1;port=3306` or `pgsql:host=127.0.0.1;port=5432;dbname=categorytest`)
  - `MODEL_ORM_TEST_USER`, `MODEL_ORM_TEST_PASS`

## Static Analysis
- Run: `vendor/bin/phpstan analyse -c phpstan.neon` (use `--debug` if your environment blocks parallel workers).

## Formatting
- Format: `vendor/bin/php-cs-fixer fix`.
- Check only: `vendor/bin/php-cs-fixer fix --dry-run --diff`.

## Code Style
- Keep changes minimal and consistent with existing conventions (4-space indent, `StudlyCaps` classes, `camelCase` methods).
- Update or add tests for behavior changes.

## Pull Requests
- Explain the change and include test results.
- Note any DB/schema assumptions and compatibility impacts.
