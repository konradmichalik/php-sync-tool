# Testing

php-sync-tool ships with a unit test suite and a Docker-backed integration
suite, plus a full static-analysis and code-style toolchain.

## Prerequisites

```bash
composer install
```

Integration tests additionally require Docker (Compose) for the sync playground.

## Unit Tests

Fast, isolated tests that need no external services:

```bash
# Run the unit suite
composer test

# With coverage (requires Xdebug)
composer test:coverage
```

`composer test` runs PHPUnit against the `unit` test suite.

## Integration Tests

End-to-end sync scenarios run against a Docker stack (web nodes, MariaDB
databases, and a proxy/bastion for jump-host and proxy modes):

```bash
# Bring the stack up, then run the integration suite
composer test:scenarios
```

This is equivalent to:

```bash
composer docker:up          # build and start the containers
composer test:integration   # run the integration test suite
composer docker:down         # tear the stack down (-v removes volumes)
```

Integration tests are skipped in CI (no Docker) and are meant to be run locally
with the stack up.

## Static Analysis & Code Style

The project uses the same CGL toolchain as its sibling packages:

```bash
# PHPStan (level 8)
composer sca

# Check code style, composer.json normalization, editorconfig
composer lint

# Auto-fix style, normalization, editorconfig
composer fix

# Rector (dry-run via CI; process locally)
composer migration
```

::: tip Before Committing
Run the full battery — `composer test`, `composer sca`, `composer lint`, and a
Rector dry-run — and verify cross-version Symfony compatibility
(`^6.4 || ^7.0 || ^8.0`). A warm php-cs-fixer cache can mask issues, so use
`--using-cache=no` when in doubt.
:::

## Test Structure

```text
tests/
├── Unit/          # Fast, isolated unit tests
├── Integration/   # Docker-backed end-to-end sync scenarios
└── Fixture/       # Framework config fixtures used by tests

docker/            # Compose stack + sync configs for integration tests
```

## Continuous Integration

GitHub Actions runs:

| Workflow | Description |
|----------|-------------|
| Tests | PHPUnit unit suite across the supported PHP and Symfony matrix |
| CGL | Code style, PHPStan, Rector, composer normalize/unused, editorconfig |
| Docs | Builds and deploys this documentation to GitHub Pages |
