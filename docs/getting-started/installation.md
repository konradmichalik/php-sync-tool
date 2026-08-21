# Installation

## Prerequisites

- PHP **8.2** or higher
- [Composer](https://getcomposer.org/) (for the library install)
- `mysql` / `mysqldump` (or `psql` / `pg_dump` for [PostgreSQL](/configuration/postgresql))
  and `gzip` / `gunzip` available on the executing host(s)
- `rsync` for fast transfers (optional — SFTP is used automatically if rsync is missing)
- `sshpass` only if you need password-based rsync

## Composer (Recommended)

Install php-sync-tool into your project from
[Packagist](https://packagist.org/packages/konradmichalik/php-sync-tool):

```bash
composer require konradmichalik/php-sync-tool
```

The executable is then available at `vendor/bin/sync-tool`:

```bash
vendor/bin/sync-tool --help
```

::: tip
Throughout this documentation the command is written as `bin/sync-tool` for
brevity. Use `vendor/bin/sync-tool` for a Composer install, or `sync-tool.phar`
for the standalone build.
:::

## Standalone PHAR

For use outside a Composer project (for example on a deploy host or in CI), grab
the single-file PHAR build. It bundles all dependencies and only needs PHP 8.2+:

```bash
# Make it executable and run it directly
chmod +x sync-tool.phar
./sync-tool.phar --help
```

## Verify Installation

```bash
# View available options
bin/sync-tool --help
```

## Next Steps

- [Quick Start](/getting-started/quickstart) — your first database sync
- [Configuration](/configuration/) — learn about configuration options
