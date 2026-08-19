# CLI Reference

Complete command line reference for php-sync-tool.

## Basic Usage

```bash
bin/sync-tool [OPTIONS] [ORIGIN] [TARGET]
```

## Arguments

| Argument | Description |
|----------|-------------|
| `ORIGIN` | Origin host name from the host file |
| `TARGET` | Target host name from the host file |

Both are optional. When omitted, configuration comes from `-f` or discovery.

## Configuration Options

| Option | Short | Description |
|--------|-------|-------------|
| `--config-file` | `-f` | Path to a configuration file |
| `--host-file` | `-o` | Additional hosts file to merge |
| `--type` | `-t` | Framework: `TYPO3`, `Symfony`, `Drupal`, `WordPress`, `Laravel` |

## Output & Logging Options

| Option | Short | Description |
|--------|-------|-------------|
| `--output` | | Output mode: `interactive`, `ci`, `json`, `quiet` (default: `interactive`) |
| `--mute` | `-m` | Mute console output |
| `--log-file` | `-l` | Write log output to a file |
| `--json-log` | | Format log output as JSON lines |

### Progress Display

In `interactive` mode a single live line on **stderr** tracks the whole run, so piped
stdout stays clean. The bar counts the planned steps: the dump, the dump transfer, the
import, an `after_dump` import, each `post_sql` statement and each `files` entry. The
running phase and the rsync percentage appear as fields on the same line, and log lines
are printed above it.

The rsync percentage needs `--info=progress2`, which rsync added in 3.1. On an older
rsync (macOS still ships 2.6.9) the transfer runs without it, and the SFTP fallback
(`--no-rsync`) reports no percentage either. In both cases the step count still advances.

The line clears itself when the run ends. `--output ci`, `--output json`, `--quiet` and
`--mute` switch it off entirely, and without a TTY it degrades to plain log lines.

Interactive runs stay compact: apart from the header, the line and the result, nothing
is printed. Add `-v` for what the tool is doing and `-vv` for the commands it runs. Both
are printed above the live line. The verbosity threshold applies to `interactive` only:
`--output ci` and `--output json` have no live line and keep emitting every message, and
the `--log-file` always records everything.

## Execution Options

| Option | Short | Description |
|--------|-------|-------------|
| `--yes` | `-y` | Skip the import confirmation prompt |
| `--dry-run` | | Resolve and report without export, transfer, or import |
| `--reverse` | `-r` | Swap origin and target |
| `--force-password` | | Force interactive password authentication |

## Database Dump Options

| Option | Short | Description |
|--------|-------|-------------|
| `--import-file` | `-i` | Import from an existing dump file |
| `--dump-name` | | Custom dump file name |
| `--keep-dump` | | Keep the dump and skip the import |
| `--clear-database` | | Drop all tables before import |
| `--tables` | | Comma-separated list of tables to sync |
| `--where` | | WHERE clause for mysqldump |
| `--additional-mysqldump-options` | | Extra mysqldump options |
| `--target-after-dump` | | Additional dump to import on the target after the main import |

## Transfer Options

| Option | Short | Description |
|--------|-------|-------------|
| `--no-rsync` | | Disable rsync and use the SFTP fallback |
| `--use-rsync-options` | | Additional rsync options |

## File Transfer Options

| Option | Short | Description |
|--------|-------|-------------|
| `--with-files` | | Enable file synchronization alongside the database |
| `--files-only` | | Synchronize only files, skip the database |

## Origin Endpoint Overrides

Override individual origin settings from the CLI (no short forms):

| Option | Description |
|--------|-------------|
| `--origin-path` | Path to the framework credential file |
| `--origin-name` | Informative name for the origin |
| `--origin-host` | SSH host |
| `--origin-user` | SSH user |
| `--origin-password` | SSH password |
| `--origin-key` | Path to the SSH private key |
| `--origin-port` | SSH port |
| `--origin-dump-dir` | Directory for the dump file |
| `--origin-keep-dumps` | Dump retention count |
| `--origin-db-name` | Database name |
| `--origin-db-host` | Database host |
| `--origin-db-user` | Database user |
| `--origin-db-password` | Database password |
| `--origin-db-port` | Database port |

## Target Endpoint Overrides

The same set of overrides is available for the target:

| Option | Description |
|--------|-------------|
| `--target-path` | Path to the framework credential file |
| `--target-name` | Informative name for the target |
| `--target-host` | SSH host |
| `--target-user` | SSH user |
| `--target-password` | SSH password |
| `--target-key` | Path to the SSH private key |
| `--target-port` | SSH port |
| `--target-dump-dir` | Directory for the dump file |
| `--target-keep-dumps` | Dump retention count |
| `--target-db-name` | Database name |
| `--target-db-host` | Database host |
| `--target-db-user` | Database user |
| `--target-db-password` | Database password |
| `--target-db-port` | Database port |

## Standard Options

php-sync-tool is built on Symfony Console, which also provides the standard
global options `--help` (`-h`), `--quiet` (`-q`), `--verbose` (`-v`/`-vv`/`-vvv`),
`--version` (`-V`), and `--no-interaction` (`-n`).

## Examples

### Basic Sync with a Config File

```bash
bin/sync-tool -f config.yaml
```

### Named-Host Sync

```bash
bin/sync-tool production local
```

### Dry Run

```bash
bin/sync-tool -f config.yaml --dry-run
```

### Skip Confirmation

```bash
bin/sync-tool -f config.yaml -y
```

### Import from a Dump File

```bash
bin/sync-tool -f config.yaml -i /path/to/dump.sql.gz
```

### Keep the Dump Without Importing

```bash
bin/sync-tool -f config.yaml --keep-dump
```

### Sync Specific Tables

```bash
bin/sync-tool -f config.yaml --tables=users,orders
```

### Partial Sync with a WHERE Clause

```bash
bin/sync-tool -f config.yaml --tables=orders --where="created_at > '2024-01-01'"
```

### Clear the Database Before Import

```bash
bin/sync-tool -f config.yaml --clear-database
```

### Sync Database and Files

```bash
bin/sync-tool -f config.yaml --with-files
```

### Files Only

```bash
bin/sync-tool -f config.yaml --files-only
```

### Force SFTP (No rsync)

```bash
bin/sync-tool -f config.yaml --no-rsync
```

### CI Mode (No Prompts)

```bash
bin/sync-tool -f config.yaml -y --output=ci
```

### JSON Logging

```bash
bin/sync-tool -f config.yaml -l /var/log/sync.log --json-log
```
