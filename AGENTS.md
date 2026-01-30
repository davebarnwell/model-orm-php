# Repository Guidelines

## Project Structure & Module Organization
- `src/` holds the library source (primary entry: `src/Model/Model.php`) under the `Freshsauce\\` namespace.
- `tests/` contains PHPUnit tests (e.g., `tests/Model/CategoryTest.php`).
- `test-src/` provides example models used by tests (`App\\` namespace).
- Root config files: `composer.json`, `phpunit.xml.dist`, and `docker-compose.yml`.

## Build, Test, and Development Commands
- `composer install` installs dependencies (PHP >= 8.3, `ext-pdo`, PHPUnit 10).
- `vendor/bin/phpunit -c phpunit.xml.dist` runs the test suite.
- `vendor/bin/phpstan analyse -c phpstan.neon` runs static analysis.
- `vendor/bin/php-cs-fixer fix` formats the codebase (use `--dry-run --diff` in CI).
- `docker-compose up -d` starts local MariaDB and PostgreSQL instances (ports 3306 and 5432).

## Coding Style & Naming Conventions
- PHP code uses 4-space indentation and namespaces.
- Class names are `StudlyCaps` (e.g., `Model`, `CategoryTest`); methods are `camelCase`.
- Table config is set via static properties like `static protected $_tableName`.
- Follow PSR-4 autoloading (`Freshsauce\\` -> `src/`, `App\\` -> `test-src/`).

## Testing Guidelines
- PHPUnit 10 is the test runner; tests live in `tests/`.
- Test classes are named `*Test` and extend `PHPUnit\Framework\TestCase`.
- Tests can run against MySQL/MariaDB or PostgreSQL; configure via env vars:
  - `MODEL_ORM_TEST_DSN` (e.g., `mysql:host=127.0.0.1;port=3306` or `pgsql:host=127.0.0.1;port=5432;dbname=categorytest`)
  - `MODEL_ORM_TEST_USER`, `MODEL_ORM_TEST_PASS`
- MySQL tests create/drop `categorytest`; PostgreSQL tests create/drop the `categories` table.

## Commit & Pull Request Guidelines
- Commit messages in this repo are short, descriptive sentences (e.g., “Fix test”, “Scrutinizer adjustments”).
- Prefer imperative or present-tense summaries without a required prefix.
- PRs should include a concise description, testing notes (`vendor/bin/phpunit -c phpunit.xml.dist`), and any DB/setup changes.

## Configuration & Local Setup Tips
- Default test connection assumes MySQL on `127.0.0.1:3306` with `root`/empty password.
- For PostgreSQL, use the Docker defaults (`postgres`/`postgres`) and `dbname=categorytest`.
- Ensure ports `3306` and/or `5432` are free and reachable from PHP.
