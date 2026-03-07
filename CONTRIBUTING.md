# Contributing to Model ORM

Thanks for contributing to `model-orm-php`. The project aims to stay small, practical, and dependable across supported databases, so focused changes and clear validation matter.

## Principles

- Keep changes focused and easy to review.
- Preserve backward compatibility where practical, or call out breaking behavior clearly.
- Favor direct, readable PDO-centered code over extra abstraction.
- Update tests and docs when public behavior changes.

## Local setup

Requirements:

- PHP `8.3+`
- `ext-pdo`
- A PDO driver for the database you want to test against, usually `pdo_mysql` or `pdo_pgsql`
- Composer

Install dependencies:

```bash
composer install
```

Start optional local databases:

```bash
docker-compose up -d
```

Default local ports:

- MySQL/MariaDB on `127.0.0.1:3306`
- PostgreSQL on `127.0.0.1:5432`

## Running checks

Run the test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

Run static analysis:

```bash
vendor/bin/phpstan analyse -c phpstan.neon
```

Run the formatter:

```bash
vendor/bin/php-cs-fixer fix
```

Check formatting without changing files:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Database configuration for tests

Override the test connection with environment variables when needed.

MySQL or MariaDB:

```bash
MODEL_ORM_TEST_DSN=mysql:host=127.0.0.1;port=3306
MODEL_ORM_TEST_USER=root
MODEL_ORM_TEST_PASS=
```

PostgreSQL:

```bash
MODEL_ORM_TEST_DSN=pgsql:host=127.0.0.1;port=5432;dbname=categorytest
MODEL_ORM_TEST_USER=postgres
MODEL_ORM_TEST_PASS=postgres
```

## Coding expectations

- Follow the existing project style: 4-space indentation, `StudlyCaps` class names, and `camelCase` methods.
- Keep the library framework-agnostic.
- Add or adjust tests for behavior changes, especially cross-database behavior.
- Prefer small, well-scoped pull requests over mixed changes.

## Pull requests

Before opening a pull request:

- Run PHPUnit for the database setup you changed or relied on.
- Run PHPStan and the formatting check.
- Update documentation when API behavior, setup, or migration guidance changes.

When opening a pull request:

- Describe the problem and the change in user-facing terms.
- Note any database-specific assumptions or compatibility impacts.
- Include the commands you ran to validate the change.

## Contribution terms

By submitting code, documentation, or other contributions to this repository, you represent that:

- You have the right to submit the contribution.
- The contribution is your own original work, or you have sufficient rights to provide it under the project license.
- You grant the project permission to use, modify, distribute, and relicense the contribution under the repository's existing license and future versions of that license as needed for project maintenance.

If these terms do not work for you, do not submit the contribution until the terms are clarified with the maintainers.
