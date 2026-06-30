# Contributing

## Development

```bash
composer install
composer lint         # composer-normalize + editorconfig + php-cs-fixer (dry-run)
composer sca          # PHPStan (level 8)
composer test         # unit tests
composer fix          # apply all fixers
```

## Building the PHAR

The standalone PHAR is built with [Box](https://github.com/box-project/box),
pinned via [PHIVE](https://phar.io):

```bash
phive install            # installs box into ./tools
composer build:phar      # writes build/sync-tool.phar
```

## Local sync playground (Docker)

A ready-to-run stack simulates a sync between two hosts (`www1` → `www2`) with
separate MariaDB databases:

```bash
cd docker
docker compose up -d --build
docker compose exec www2 php /app/bin/sync-tool -f /app/docker/configs/receiver.yaml -y
docker compose exec db2 mariadb -udb -pdb db -N -e "SELECT COUNT(*) FROM person;"
```

See [`docker/README.md`](docker/README.md) for details. The integration test
suite (`composer test:integration`) drives this stack and is skipped when it is
not running.
