# php-sync-tool

> PHP port of [`db-sync-tool`](https://github.com/jackd248/db-sync-tool) — synchronize databases
> (and optionally files) between local and remote systems over SSH/rsync/SFTP, with framework
> credential auto-detection for **TYPO3, Symfony, Drupal, WordPress and Laravel**.

Target: **feature parity** with the Python tool `3.0.3`.

## Status

🚧 Work in progress — see `~/.claude/plans/plane-die-umsetzung-einer-parallel-pie.md` for the
implementation plan and the migration checklist in `db-sync-tool/PHP_MIGRATION_REQUIREMENTS.md`.

## Requirements

- PHP **8.2+**
- `mysql` / `mysqldump`, `gzip`/`gunzip` on the executing host(s)
- `rsync` (default transfer) and optionally `sshpass` for password-based rsync

## Installation

```bash
composer require move-elevator/php-sync-tool
```

Or use the standalone PHAR build (`db-sync-tool.phar`).

## Usage

```bash
bin/db-sync-tool origin target -f config.yaml
```

See `bin/db-sync-tool --help` for the full option reference.

## Development

```bash
composer install
composer lint         # composer-normalize + editorconfig + php-cs-fixer (dry-run)
composer sca          # PHPStan (level 8)
composer test         # unit tests
composer fix          # apply all fixers
```

## Local sync playground (Docker)

A ready-to-run stack simulates a sync between two hosts (`www1` → `www2`) with
separate MariaDB databases:

```bash
cd docker
docker compose up -d --build
docker compose exec www2 php /app/bin/db-sync-tool -f /app/docker/configs/receiver.yaml -y
docker compose exec db2 mariadb -udb -pdb db -N -e "SELECT COUNT(*) FROM person;"
```

See [`docker/README.md`](docker/README.md) for details. The integration test
suite (`composer test:integration`) drives this stack and is skipped when it is
not running.

## License

MIT
