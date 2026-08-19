# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Live progress output in `interactive` mode, built on
  [`konradmichalik/php-progress`](https://github.com/konradmichalik/php-progress):
  rsync transfers render a real percentage, every other long-running phase
  (dump, import, additional dump, post-import SQL) renders a spinner. The line
  is written to stderr, so piped stdout stays clean, and `--output ci|json`,
  `--quiet` and `--mute` switch it off. The percentage requires rsync 3.1
  (`--info=progress2`); older rsync builds and the SFTP fallback fall back to a
  spinner or no line at all.

- File synchronization now supports the SFTP fallback (`--no-rsync`) for
  directories, including recursive transfer and `exclude` patterns — not
  just the database dump.

### Changed

- Local-to-local and same-host (`SYNC_REMOTE`) dump transfers now use
  `rsync` instead of `cp`, unifying with how file synchronization already
  handled these cases. The copied data is the same, but this now requires
  the `rsync` binary to be present, and file permissions on the copy follow
  rsync's `--chmod` defaults rather than a plain `cp`.

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
