# Introduction

php-sync-tool is a PHP CLI tool for synchronizing MySQL, MariaDB or PostgreSQL
databases — and optionally files — between systems over SSH. It automatically
extracts database credentials from popular PHP frameworks and supports a range
of sync modes for different use cases.

It is a PHP port of the Python [`db-sync-tool`](https://github.com/konradmichalik/db-sync-tool),
designed to run wherever PHP already runs, without an additional Python runtime.

## Features

- **Database sync** from and to remote systems
  - MySQL (>= 5.5)
  - MariaDB (>= 10.0)
  - [PostgreSQL](/configuration/postgresql)
- **Proxy mode** between two remote systems (two-hop transfer)
- Nine [synchronization modes](/reference/sync-modes) selected automatically from your config
- **Automatic database credential detection** for supported frameworks:
  - [TYPO3](/getting-started/typo3) (>= v7.6)
  - [Symfony](/getting-started/symfony) (>= v2.8)
  - [Drupal](/getting-started/drupal) (>= v8.0)
  - [WordPress](/getting-started/wordpress) (>= v5.0)
  - [Laravel](/getting-started/laravel) (>= v4.0)
- **[File synchronization](/configuration/file-sync)** alongside or instead of the database
- Dump creation (database backup) with optional retention/cleanup
- Lifecycle **scripts** (before / after / error) and post-import SQL
- Structured **logging** (plain or JSON) for CI and log aggregation
- Many more [configuration options](/configuration/)

## Requirements

- PHP **8.2** or higher (`~8.2 || ~8.3 || ~8.4 || ~8.5`)
- `mysql` / `mysqldump` (or `psql` / `pg_dump` for PostgreSQL) and `gzip` / `gunzip`
  on the executing host(s)
- `rsync` for the default transfer method (SFTP is used automatically as a fallback)
- Optionally `sshpass` for password-based rsync
- SSH access to remote systems (for remote syncs)

## How It Works

1. **Resolve configuration** — origin and target are read from a config file, host
   definitions, or CLI arguments.
2. **Detect credentials** — for a supported framework, database credentials are
   parsed from its config file (locally or over SSH).
3. **Export** — a gzip-compressed `mysqldump` (or `pg_dump` for PostgreSQL) is
   created on the origin system.
4. **Transfer** — the dump is moved via rsync (or SFTP fallback, or a local proxy).
5. **Import** — the dump is imported on the target system.
6. **Finalize** — post-import SQL runs, lifecycle scripts fire, and temporary
   files and credential files are cleaned up.

## Next Steps

- [Installation](/getting-started/installation) — get php-sync-tool installed
- [Quick Start](/getting-started/quickstart) — your first database sync
- [Framework Guides](/getting-started/typo3) — framework-specific setup
