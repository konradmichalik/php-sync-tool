# CLI Reference

Complete command line reference for php-sync-tool.

## Commands

| Command | Purpose |
|---------|---------|
| `sync` | Synchronize, the default when no command is named |
| `pull <environment>` | That environment's database into this project |
| `push <environment>` | This project's database into that environment |
| `init` | Ask a few questions and write `.sync-tool/` for this project |
| `environments` | List the synchronizations this project is configured for |

`pull`, `push`, `init` and `environments` are described under [Named Environments](#named-environments).
Every option in this reference applies to `pull` and `push` as well, apart from the
`ORIGIN` and `TARGET` arguments, which the environment name replaces.

## Basic Usage

```bash
bin/sync-tool [OPTIONS] [ORIGIN] [TARGET]
```

Called with no arguments on a terminal, the tool offers every sync it can find
and runs the one you pick. Without a terminal it reports that configuration is
missing, exactly as before.

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
import, an `after_dump` import, the `post_sql` block and each `files` entry. The
running phase and the rsync percentage appear as fields on the same line, and log lines
are printed above it.

The rsync percentage needs `--info=progress2`, which rsync added in 3.1. On an older
rsync (macOS still ships 2.6.9) the transfer runs without it, and the SFTP fallback
(`--no-rsync`) reports no percentage either. In both cases the step count still advances.

The line stays on screen when the run ends and becomes the result: the leading spinner
turns into a status icon, the bar fills, and the label reports the outcome. Above it sits
a heading naming the tool and both endpoints. Together they are the whole default output
of a successful run:

```
php-sync-tool  remote (www1) ➔ local
✔  Synchronization complete  ██████████████████  100%  4/4  (0:07)
```

Nothing else is printed, and there is no separate success block on top of the line.
`--output ci`, `--output json`, `--quiet` and `--mute` switch the live line off
entirely; without a TTY it degrades to plain log lines and the confirmation is printed
as one plain line instead. A failure keeps its own error block, because an error is
worth interrupting for.

Add `-v` for what the tool is doing and `-vv` for the commands it runs; both are printed
above the live line. `-v` also names the sync mode on the heading:

```
php-sync-tool  RECEIVER  remote (www1) ➔ local
```

Only the mode label appears there, not its `(REMOTE ➔ LOCAL)` description: the two
endpoints on the same line already state the direction. `--output ci` and `--output json`
report the full mode string, since a log consumer has no line to read it off.

The verbosity threshold applies to `interactive` only: `--output ci` and `--output json`
have no live line and keep emitting every message, and the `--log-file` always records
everything.

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
| `--additional-dump-options` | | Extra options for the dump binary |
| `--backup-before-import` | | Dump the target database before it is overwritten |
| `--no-check-dump` | | Import without checking the dump for content first |
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
| `--files-target` | | Target path of the first `files` entry, overriding the configuration |

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

## Named Environments

```bash
bin/sync-tool init                 # set the project up
bin/sync-tool init -e staging      # add another environment to it
bin/sync-tool environments         # list what is configured
bin/sync-tool pull production      # production → this project
bin/sync-tool push staging         # this project → staging
```

An environment is a host from `hosts.yaml` or a file in `.sync-tool/`. The other
side comes from the `local` block in `.sync-tool/defaults.yaml`. See
[Configuration](/configuration/#the-local-block).

`init` options:

| Option | Short | Description |
|--------|-------|-------------|
| `--environment` | `-e` | Name of the environment to set up (default: `production`) |
| `--force` | | Overwrite existing files without asking |

Run in a project that already carries a `defaults.yaml`, `init` keeps it and only
adds the environment: the framework and this machine are asked about once, not
again for every environment. `--force` rewrites the defaults from fresh answers.

`init` needs a terminal; it refuses to run without one rather than writing files
from defaults. `environments` needs none, which is what makes it usable from a
script or a Makefile.

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
