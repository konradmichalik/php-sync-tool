# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

`php-sync-tool` synchronizes databases and files between local and remote systems over SSH, rsync and SFTP. It auto-detects framework credentials (TYPO3, Symfony, Drupal, WordPress, Laravel). It ships as a Composer library with a CLI (`bin/sync-tool`) and as a standalone PHAR.

- Package: `konradmichalik/php-sync-tool`, license GPL-3.0-or-later
- PHP `~8.2 || ~8.3 || ~8.4 || ~8.5`, `symfony/console`, `symfony/process` and `symfony/yaml` (`^6.4 || ^7.0 || ^8.0`), `phpseclib/phpseclib`, `opis/json-schema`

## Structure

- `bin/sync-tool`: CLI entry point
- `src/`: PSR-4 namespace `KonradMichalik\SyncTool\`, organized by area: `Command`, `Config`, `Database`, `Recipe` (framework credential detection), `Remote`, `Mode`, `Lifecycle`, `Backup`, `Security`, `Logging`, `Output`, `Update`, `Enum`, `Exception`, `Util`
- `tests/Unit/`, `tests/Integration/`, `tests/Fixture/`: PHPUnit suites (`unit`, `integration`)
- `docker/`: local sync playground with two hosts and separate MariaDB databases, used by the integration tests (see `docker/README.md`)
- `docs/`: VitePress documentation site (`getting-started/`, `configuration/`, `reference/`, `development/`), built via `package.json`
- `box.json`, `.phive/phars.xml`: PHAR build with Box, pinned via PHIVE

## Development commands

```bash
composer install
composer lint            # composer normalize --dry-run, editorconfig-cli (ec), php-cs-fixer --dry-run
composer fix             # same tools, applying fixes
composer sca:php         # phpstan analyse --memory-limit=2G
composer migration       # rector process -c rector.php
php bin/sync-tool --help
```

PHAR build:

```bash
phive install            # installs box into ./tools
composer build:phar      # writes build/sync-tool.phar
```

Documentation site:

```bash
npm install
npm run docs:dev         # also docs:build and docs:preview
```

## Testing

```bash
composer test               # unit tests, no coverage
composer test:coverage      # unit tests, XDEBUG_MODE=coverage, reports to .build/coverage/
composer docker:up          # start the Docker stack
composer test:integration   # integration suite, skipped when the stack is not running
composer test:scenarios     # docker:up plus test:integration
composer docker:down
```

CI (`.github/workflows/tests.yml`) runs `composer test` (unit tests without coverage) on PHP 8.2 to 8.5 against Symfony 6.x and 7.x, and a separate coverage job on PHP 8.4 with Xdebug that uploads `clover.xml`. Lint and static analysis run through the shared reusable workflow `cgl.yml` on PHP 8.4. `docs.yml` deploys the VitePress site to GitHub Pages.

## Code style and static analysis

- PHP CS Fixer uses `konradmichalik/php-cs-fixer-preset` (`.php-cs-fixer.php`), including file headers from `composer.json` and `DocBlockHeaderFixer`. `docker` and `build` are excluded.
- PHPStan level 8 over `src/` and `tests/`, with the Symfony and PHPUnit extensions
- Rector with `LevelSetList::UP_TO_PHP_82`, PHP 8.2 target, over `src/` and `tests/`
- EditorConfig is enforced through `ec` (`.editorconfig`)
- Every PHP file declares `declare(strict_types=1);`. Value objects are readonly.
- Remote commands and SQL are built through the helpers in `src/Security/` (`Shell`, `SqlLiteral`, `TableName`, `LogSanitizer`), not by string interpolation.
- If a CLI option or configuration key changes, update the matching page in `docs/`.

## Git workflow

- Commit format: `<type>: <description>`
- Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`
- No co-author trailers
- One commit per logical change
