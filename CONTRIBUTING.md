# Contributing

Thanks for contributing to `model-orm-php`.

## Ground rules

- Be respectful and constructive in issues, pull requests, and review discussion.
- Keep changes focused. Small, well-scoped pull requests are easier to review and safer to merge.
- Preserve backward compatibility where practical, or clearly call out breaking changes.

## Development setup

Requirements:

- PHP `8.3+`
- `ext-pdo`
- A PDO driver for the database you want to test against, typically `pdo_mysql` or `pdo_pgsql`
- Composer

Install dependencies:

```bash
composer install
```

Optional local databases:

```bash
docker-compose up -d
```

## Running checks

Run the test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

Override the database connection with environment variables when needed:

```bash
MODEL_ORM_TEST_DSN=mysql:host=127.0.0.1;port=3306
MODEL_ORM_TEST_USER=root
MODEL_ORM_TEST_PASS=
```

Or for PostgreSQL:

```bash
MODEL_ORM_TEST_DSN=pgsql:host=127.0.0.1;port=5432;dbname=categorytest
MODEL_ORM_TEST_USER=postgres
MODEL_ORM_TEST_PASS=postgres
```

Run static analysis:

```bash
vendor/bin/phpstan analyse -c phpstan.neon
```

Run formatting:

```bash
vendor/bin/php-cs-fixer fix
```

Check formatting only:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Coding expectations

- Follow the existing project style: 4-space indentation, `StudlyCaps` class names, and `camelCase` methods.
- Keep the library framework-agnostic and PDO-centered.
- Add or update tests for behavior changes, especially around cross-database behavior.
- Prefer clear, direct code over clever abstractions.

## Pull requests

Before opening a pull request:

- Make sure tests pass locally for the database you changed or relied on.
- Run PHPStan and the formatting check.
- Update documentation when the public behavior or setup changes.

When opening a pull request:

- Explain the user-visible problem and the change you made.
- Note database-specific assumptions or compatibility impacts.
- Include the commands you ran to validate the change.

## Contribution terms

By submitting code, documentation, or any other contribution to this repository, you represent that:

- You have the right to submit the contribution.
- The contribution is your own original work, or you have sufficient rights to provide it under the project license.
- You grant the project permission to use, modify, distribute, and relicense the contribution under the repository's existing license and future versions of that license as needed for project maintenance.

If these terms do not work for you, do not submit the contribution until the terms are clarified with the maintainers.
