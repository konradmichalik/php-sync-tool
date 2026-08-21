# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Named environments. `sync-tool init` asks a few questions and writes
  `.sync-tool/defaults.yaml` (framework plus a `local` block describing this
  machine) and one file per environment. `sync-tool pull <name>` then syncs that
  environment into the project and `sync-tool push <name>` the other way, with
  the `local` block as the opposite side. Called with no arguments on a terminal,
  the tool offers every sync it can find and runs the one you pick; without a
  terminal it reports missing configuration as before. Every existing invocation
  keeps working, including `sync-tool prod` and `sync-tool production local`.
  Environment and host names cannot collide with a command name (`sync`, `pull`,
  `push`, `init`, `list`, `help`, `completion`).

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

- A configuration key the tool does not know is now an error instead of being
  ignored. The schema only listed a handful of keys and accepted anything else, so
  `ignore_tabel` or `keep_dump: 3` on an endpoint validated cleanly and then did
  nothing at all. Every key the code reads is in the schema now, including the
  legacy singular spellings (`ignore_table`, `truncate_table`). Keys starting with
  `x` or `.` stay free for the author, so YAML anchor blocks keep working.

- The full set of MySQL TLS options is configurable per endpoint: `ssl_skip_verify`,
  `ssl_ca`, `ssl_capath`, `ssl_cert`, `ssl_key` and `ssl_cipher` join the existing
  `ssl_disabled`. They are written into the same temporary credential file as the
  password, so no certificate path reaches the process list. `ssl_skip_verify` is
  the narrow fix for DDEV 1.25, whose Trixie image serves a self-signed
  certificate. A `postgres` endpoint that sets any of them now aborts instead of
  ignoring them and connecting without TLS.

- Database client binaries follow the endpoint's `db.type`. A `mariadb` endpoint
  is addressed through `mariadb-dump` and `mariadb` rather than the `mysqldump`
  and `mysql` symlinks that MariaDB 11 deprecated, and a `postgres` endpoint
  through `pg_dump` and `psql`. The default `mysql` type is unchanged, so
  existing configurations keep their commands.


- The sync mode is derived from three axes (direction, operation, whether both
  endpoints share a host) instead of nine overlapping cases. The nine mode names
  in the output are unchanged.

- Every database command now goes through a `DatabaseDriver`. The commands
  emitted for MySQL and MariaDB are unchanged.

- Interactive output is compact by default. Progress phases and executed commands
  are no longer printed unless `-v` (phases) or `-vv` (commands) is passed. The
  `ci` and `json` output modes and the log file are unaffected.

- A successful interactive run is two lines: a heading naming the tool and both
  endpoints, and the live progress line, which now stays on screen and becomes the
  result with the spinner turning into a status icon. The separate success block
  and the framed definition list are gone. The sync mode moved onto the heading
  behind `-v`, as the label alone, because the two endpoints already state the
  direction; `ci` and `json` keep reporting the full mode string. Without a TTY the
  confirmation is printed as one plain line, and failures keep their error block.

- Local-to-local and same-host (`SYNC_REMOTE`) dump transfers now use
  `rsync` instead of `cp`, unifying with how file synchronization already
  handled these cases. The copied data is the same, but this now requires
  the `rsync` binary to be present, and file permissions on the copy follow
  rsync's `--chmod` defaults rather than a plain `cp`.

### Security

- SQL from configuration is no longer expanded by the shell. `post_sql`, an
  `anonymize` `static` value and `where` were passed to `mysql` inside a
  double-quoted argument, which left `$(…)` and `$VAR` live: a config file
  became a command-execution path. They are single-quoted now. An SSH key path
  and an `sshpass` password are quoted the same way in `rsync` commands.

- `rsync` transfers honour `ssh_strict_host_key_checking` instead of always
  passing `StrictHostKeyChecking=no`. The command channel already verified host
  keys while the channel carrying the dump did not. Transfers to a host missing
  from `known_hosts` now fail rather than proceeding unauthenticated.

- Credential files are never briefly world-readable. The remote one is written
  under `umask 077` instead of being created with the shell's default mode and
  chmod'ed afterwards, and the local one is created empty, restricted to 0600 and
  only then filled. On a shared host, `/tmp` is readable by every other user.

- Configured paths are one shell argument each in `rsync` commands. Origin and
  target were interpolated raw, so a path carrying `$(…)` ran on the machine
  driving the sync. Dump retention quotes `dump_dir` the same way and appends the
  glob star outside the quotes. (A remote path is still handed to the remote
  shell by `rsync` itself, so spaces there remain rsync's own topic.)

- A path reaches the TYPO3 credential reader as an escaped PHP literal. It was
  interpolated into a single-quoted string inside `php -r`, where a quote in the
  path closed the literal and had the rest of it run as PHP on the endpoint.

- Failure messages are masked like log output. A failing remote command was
  quoted back verbatim, which for the credential-file write meant the base64
  encoded database password, and for a client invocation its `-p` argument.

- A log file this tool creates starts out at 0600. It names hosts, users,
  databases and the commands run against them. An existing file keeps its mode.

- A `console` override is quoted as one shell argument. It is documented as the
  path of a binary and lands in a command that runs on the endpoint, which made it
  the last configuration key able to smuggle a command of its own onto a remote
  host. An ordinary path is unaffected; a wrapper that needs several arguments
  belongs in a script whose path is configured here.

### Fixed

- A dump or import that fails halfway through no longer reports success.
  `mysqldump … | gzip > dump.gz` and `gunzip -c dump.gz | mysql` report the exit
  status of the *last* stage, so wrong credentials, a killed query or a full disk
  left a valid but useless archive behind and the run continued to transfer and
  import it — on an already cleared target. Both pipelines now fail when either
  stage fails (`Security\Shell::strictPipe()`).

- A command that fails without saying anything on stderr is a failure. The local
  runner only raised an error when the exit status *and* stderr agreed, so a
  silent non-zero exit passed as success and the sync continued on bad state. The
  message now names the exit status and the (masked) command.

- `keep_dumps` deletes the oldest dumps rather than an arbitrary selection. The
  listing sorted formatted timestamps numerically, which compares the year on
  GNU `stat` and nothing at all on BSD, so retention on macOS dropped whichever
  dumps happened to be listed last. Both formats report epoch seconds now.

- A passphrase-protected or malformed SSH key reports what is wrong with which
  file instead of surfacing as a phpseclib stack trace.

- `sync-tool init` no longer claims to have written files it could not write.

- A `script` block finally runs. Every example in the documentation writes
  `script`, the schema accepted `script`, and the code read `scripts`, so
  documented lifecycle commands were silently skipped. Both spellings work now,
  and the documentation says what has always been true: the commands run on the
  machine driving the sync, not on the endpoint they are written under.

- `truncate_tables` accepts `table*` wildcards, expanded against the target
  before truncating, the way `ignore_table` already worked against the origin.
  It was also missing from the configuration reference entirely.

- The `console` block now actually overrides the database client binaries. Only
  its `php` entry was ever read; `mysql` and `mysqldump` were documented but
  ignored.

- Anonymization no longer skipped when credentials come from framework
  auto-detection. Resolving credentials rebuilds the endpoint, and that copy
  dropped its `anonymize` rules, so masking silently did not run for exactly the
  `path`-based configuration the documentation recommends. Unmasked production
  data reached the target without a warning, and the progress bar counted the
  step regardless.

- `keep_dumps` no longer deletes files it did not create. Retention listed
  everything in `dump_dir` and removed any `.sql` or `.gz` beyond the limit,
  which with the default `dump_dir` of `/tmp/` could hit other tools' files. Dumps
  now carry a `sync-tool_` prefix and retention globs on it.

- Dump filenames are precise to the second, so two runs within the same minute no
  longer write to the same file.

- A `password` in a host definition is no longer silently dropped, so a named
  host can carry SSH password authentication like any other endpoint.
- The sync mode line no longer swaps the descriptions of `IMPORT_LOCAL` and
  `IMPORT_REMOTE`.

- Log output no longer masks long options that merely contain `-p`, so
  `pg_dump --no-privileges` stays readable in `-vv` output. Attached passwords
  such as `-psecret` are still masked.

### Internal

- Replaced the duplicated transfer-mechanism branching in `Sync` and
  `FileSync` with a shared `TransferStrategy` interface and resolver
  (`Remote\Transfer\*`), removing `SftpTransfer`, `ProxyTransfer` and
  `SftpDirection`.

- `RunnerFactory` reuses one runner per endpoint for the lifetime of a run. A
  sync asks for the same endpoint in several phases, and each miss cost a full
  SSH handshake; on a measured local run that was roughly a quarter of the total
  time, and it grows with the round-trip.

- Masking and `post_sql` each run as one batched invocation instead of one per
  statement, removing a round trip per statement. Both now report a single
  progress step.

- Dump transfers use a leaner `rsync` option set than directory syncs: no `-z` on
  already-compressed bytes and no `--delete` or `--iconv` without a directory to
  walk. The restrictive file mode is kept.

- A reflection-based test asserts that the wither methods on `SyncConfig` and
  `ClientConfig` carry over every constructor property, so a field forgotten in a
  hand-written copy fails the suite instead of disappearing at runtime.

- Removed the unused `MysqlCredentials::legacyArguments()`, which built
  `-u'user' -p'password'` command lines, and folded the remaining one-line helper
  into `MysqlDriver`. The duplicated SQL literal escaping in both drivers moved to
  `Security\SqlLiteral`.

- The local `rsync` version is read once per run instead of once per transferred
  entry. Every file entry used to spawn `rsync --version` to decide whether a
  progress percentage is available.

- Directories the SFTP fallback creates locally use the same restrictive mode as
  the `rsync` path (`0770` rather than `0777` minus umask); the tree can hold a
  production copy.

- Every `table*` wildcard in `ignore_table` and `truncate_tables` is resolved by a
  single query instead of one per pattern. A TYPO3 `truncate_tables` list of ten
  cache patterns paid for ten round trips to the endpoint before the first table
  was touched. A table matched by two patterns is now listed once.

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
