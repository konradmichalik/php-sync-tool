# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Data anonymization as configuration. An `anonymize` block on the target names
  the columns to mask per table, with four strategies: `null`, a `static` value,
  `hash`, and `email` (the address is hashed into the reserved `example.invalid`
  domain, so no primary-key column is needed). Masking runs as its own phase
  after every import step and before `post_sql`, a failing statement aborts the
  sync, and the statements are emitted in the dialect of the configured database
  system. See the
  [anonymization documentation](https://konradmichalik.github.io/php-sync-tool/configuration/anonymization).

- PostgreSQL support. `db.type` selects the database system (`mysql`, `mariadb`,
  `postgres`), and it is also read from the scheme of a framework database URL,
  so a Symfony `DATABASE_URL=postgresql://…` needs no extra configuration.
  Dumps run through `pg_dump` and imports through `psql`; the password is passed
  in a `.pgpass` file with mode 0600 that is removed after the run. `where` and
  `additional_mysqldump_options` are MySQL-only and now abort a PostgreSQL run
  with a clear message instead of being ignored. See the
  [PostgreSQL documentation](https://konradmichalik.github.io/php-sync-tool/configuration/postgresql).

- Live progress output in `interactive` mode, built on
  [`konradmichalik/php-progress`](https://github.com/konradmichalik/php-progress):
  one bar tracks the whole run and counts every planned step (dump, dump
  transfer, import, `after_dump`, each `post_sql` statement, each `files`
  entry), with the running phase and the rsync percentage as fields on the same
  line. The line is written to stderr, so piped stdout stays clean, and
  `--output ci|json`, `--quiet` and `--mute` switch it off. The percentage
  requires rsync 3.1 (`--info=progress2`); older rsync builds and the SFTP
  fallback advance the step count without one.
- File synchronization now supports the SFTP fallback (`--no-rsync`) for
  directories, including recursive transfer and `exclude` patterns — not
  just the database dump.

### Changed

- Every database command now goes through a `DatabaseDriver`. The commands
  emitted for MySQL and MariaDB are unchanged.

- Interactive output is compact by default. Progress phases and executed commands
  are no longer printed unless `-v` (phases) or `-vv` (commands) is passed. The
  `ci` and `json` output modes and the log file are unaffected.

- Local-to-local and same-host (`SYNC_REMOTE`) dump transfers now use
  `rsync` instead of `cp`, unifying with how file synchronization already
  handled these cases. The copied data is the same, but this now requires
  the `rsync` binary to be present, and file permissions on the copy follow
  rsync's `--chmod` defaults rather than a plain `cp`.

### Fixed

- Log output no longer masks long options that merely contain `-p`, so
  `pg_dump --no-privileges` stays readable in `-vv` output. Attached passwords
  such as `-psecret` are still masked.

### Internal

- Replaced the duplicated transfer-mechanism branching in `Sync` and
  `FileSync` with a shared `TransferStrategy` interface and resolver
  (`Remote\Transfer\*`), removing `SftpTransfer`, `ProxyTransfer` and
  `SftpDirection`.

## [0.1.0] - 2026-07-01

Initial release — a PHP port of the Python
[`db-sync-tool`](https://github.com/konradmichalik/db-sync-tool), targeting
feature parity with 3.0.3.

### Added

- Database synchronization between local and remote systems over SSH, with
  rsync (default), SFTP fallback (`--no-rsync`) and two-hop proxy transfer.
- Sync modes: receiver, sender, proxy, remote-to-remote, local, and dump/import-only.
- Optional file synchronization (`--with-files` / `--files-only`).
- Framework credential auto-detection for TYPO3, Symfony, Drupal, WordPress and Laravel.
- Jump-host tunnelling via the system SSH client (`ssh -J` / ProxyJump).
- Strict SSH host-key verification by default (`ssh_strict_host_key_checking` to opt out).
- Interactive **import confirmation** before any write; skipped in non-interactive
  contexts and via `--yes` / `-y`.
- `protect: true` to refuse a host as a sync target entirely.
- Data anonymization / GDPR masking via `post_sql`, partial sync (`--tables`,
  `--where`), `clear_database`, dump retention (`keep_dumps`) and lifecycle scripts.
- Log files with optional JSON-lines output; secrets are sanitized from all log messages.
- Output modes (`--output interactive|ci|json|quiet`), `--dry-run`, and a standalone PHAR build.

### Security

- Passwords are passed through MySQL defaults-extra-files (chmod 0600, removed after use),
  never on the command line, and are masked in logs.
