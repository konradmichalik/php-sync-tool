# Configuration Reference

Complete reference for `config.yaml` (or `config.json`). Configuration is
validated against a JSON schema: wrong types are rejected, and so is a key the
tool does not know, so a misspelled `ignore_tabel` is reported instead of quietly
doing nothing. Keys beginning with `x` or `.` are left to you, which keeps YAML
anchor blocks such as `.defaults: &defaults` usable.

## The configuration is executable input

A configuration file decides which machine this tool logs into, which binaries it
runs there and which SQL it executes. Several keys are passed to a shell or a
database by design:

| Key | Goes to |
|-----|---------|
| `script` (`before`, `after`, `error`) | your local shell, as a command |
| `console.*` | the endpoint, as the path of the binary to run |
| `additional_mysqldump_options`, `use_rsync_options`, `files_options` | the command line of `mysqldump` / `rsync`, as options |
| `post_sql`, `anonymize.*.value`, `where` | the database, as SQL |

Treat a configuration file the way you treat a `Makefile` or a CI pipeline
definition: review one before you run it, and give the repository that holds it
the same protection as the code it syncs. Values are quoted so that a *database*
password or a path cannot become a command, but a key documented as a command
stays a command.

## Root Keys

| Key | Type | Description |
|-----|------|-------------|
| `type` | enum | Framework: `TYPO3`, `Symfony`, `Drupal`, `WordPress`, `Laravel`. Optional if `db` credentials are given. |
| `origin` | object | Source endpoint (see [Client Object](#client-object)). |
| `target` | object | Destination endpoint (see [Client Object](#client-object)). |
| `ignore_table` | array | Tables to exclude from the dump. A `table*` entry is expanded against the origin. |
| `truncate_tables` | array | Tables to empty on the target before the import. A `table*` entry is expanded against the target. |
| `log_file` | string | Path to a log file. |
| `json_log` | boolean | Write the log file as JSON lines. |
| `ssh_strict_host_key_checking` | boolean | Toggle SSH host-key verification (default: enabled). |
| `files` | array | File-transfer entries (see [File Synchronization](/configuration/file-sync)). |

## Client Object

Both `origin` and `target` accept the same structure:

| Key | Type | Description |
|-----|------|-------------|
| `name` | string | Informative name for logging (e.g. `Production`). |
| `host` | string | SSH host. Its presence makes the endpoint remote. |
| `user` | string | SSH user. |
| `port` | number | SSH port (default: 22). |
| `password` | string | SSH password (prefer `ssh_key` or an agent). |
| `ssh_key` | string | Path to an SSH private key. |
| `path` | string | Path to the framework config file for credential detection. |
| `link` | string | Reference to a host definition, e.g. `@prod`. |
| `dump_dir` | string | Directory for temporary dump files (default: `/tmp/`). |
| `keep_dumps` | number | Retention: keep only the N most recent dumps in `dump_dir`. |
| `after_dump` | string | Additional SQL file to import after the main import. |
| `post_sql` | array | SQL statements to execute after import, in one batch. |
| `protect` | boolean | Refuse to use this endpoint as an import target without confirmation. |
| `console` | object | Custom binary paths, keyed by the binary they replace (`php`, `mysql`, `mysqldump`, `mariadb`, `mariadb-dump`, `psql`, `pg_dump`). |
| `script` | object | Lifecycle commands: `before`, `after`, `error`. They run on the machine driving the sync, not on the endpoint. `scripts` is accepted as well. |
| `db` | object | Manual database credentials (see [Database Object](#database-object)). |
| `jump_host` | object | SSH jump host (see [Jump Host Object](#jump-host-object)). |
| `anonymize` | object | Masking rules per table and column, target only (see [Data Anonymization](/configuration/anonymization)). |

### Database Object

Under `origin.db` / `target.db`:

| Key | Type | Description |
|-----|------|-------------|
| `name` | string | Database name. |
| `host` | string | Database host. |
| `user` | string | Database user. |
| `password` | string | Database password. |
| `port` | number | Database port (default: 3306, PostgreSQL: 5432). |
| `ssl_disabled` | boolean | Turn TLS off entirely for the MySQL connection. |
| `ssl_skip_verify` | boolean | Keep TLS on but do not verify the server certificate. |
| `ssl_ca` | string | Path to the CA certificate. |
| `ssl_capath` | string | Directory of trusted CA certificates in PEM format. |
| `ssl_cert` | string | Path to the client certificate. |
| `ssl_key` | string | Path to the client key. |
| `ssl_cipher` | string | Allowed cipher list. |
| `type` | enum | Database system: `mysql`, `mariadb`, `postgres` (default: `mysql`). See [PostgreSQL](/configuration/postgresql). |

The `ssl_*` keys configure a MySQL or MariaDB client and are written into the same
temporary credential file as the password, so no path reaches the process list. A
`postgres` endpoint that sets any of them aborts the run rather than connecting
without them: `psql` takes its TLS settings from the environment instead.

::: tip DDEV
DDEV 1.25 moved to a Trixie image whose MySQL server presents a self-signed
certificate. `ssl_skip_verify: true` keeps the connection encrypted and only skips
verification, which is the narrower fix than `ssl_disabled: true`.
:::

### Jump Host Object

Under `origin.jump_host` / `target.jump_host`:

| Key | Type | Description |
|-----|------|-------------|
| `host` | string | Jump host address. |
| `user` | string | SSH user (defaults to the endpoint user). |
| `port` | number | SSH port (defaults to the endpoint port). |
| `password` | string | SSH password for the jump host. |
| `ssh_key` | string | SSH private key for the jump host. |

## Full Example

```yaml
# Framework for credential detection (optional if db is given)
type: TYPO3

# Tables excluded from the dump (wildcards supported)
ignore_table:
  - cache_*
  - sys_log

# Optional logging
log_file: /var/log/php-sync-tool.log
json_log: false

origin:
  name: Production
  host: prod.example.com
  user: deploy
  port: 22
  ssh_key: /home/user/.ssh/id_ed25519
  path: /var/www/html/typo3conf/LocalConfiguration.php
  dump_dir: /var/backups/db/
  keep_dumps: 5

target:
  name: Local
  path: /var/www/local/typo3conf/LocalConfiguration.php
  protect: false
  post_sql:
    - "UPDATE be_users SET password = '' WHERE 1;"
  script:
    before: php artisan down
    after: php artisan up
```

## Dump Files and Retention

Generated dumps are named `sync-tool_<database>_<YYYY-MM-DD_HH-MM-SS>.sql.gz`.
Two things follow from that name:

- **`keep_dumps` only ever deletes dumps the tool wrote itself.** Retention globs
  on the `sync-tool_` prefix, so other `.sql` and `.gz` files in `dump_dir` are
  left alone. That matters because the default `dump_dir` is `/tmp/`, which is
  rarely yours exclusively.
- **A dump written with `--dump-name` is yours, not ours.** It carries no prefix
  and is therefore never pruned automatically.
- **Retention goes by modification time**, not by the name, so a restored or
  re-transferred dump counts as recent.

The timestamp is precise to the second, so repeated runs within the same minute
produce distinct files instead of overwriting each other.

A log file that `log_file` points at is created with owner-only permissions
(`0600`), because it records the hosts, users, databases and commands of the run.
An existing file keeps whatever permissions it has.

## Common Configurations

### Receiver Mode (Remote → Local)

```yaml
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
target:
  path: /var/www/local/typo3conf/LocalConfiguration.php
```

### Sender Mode (Local → Remote)

```yaml
type: TYPO3
origin:
  path: /var/www/local/typo3conf/LocalConfiguration.php
target:
  host: staging.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
```

### Manual Database Credentials

```yaml
origin:
  host: prod.example.com
  user: deploy
  db:
    name: production_db
    host: localhost
    user: db_user
    password: db_password
    port: 3306
target:
  db:
    name: local_db
    host: localhost
    user: root
    password: root
```

## File Formats

Configuration can be written in YAML or JSON:

::: code-group

```yaml [config.yaml]
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
target:
  path: /var/www/local/LocalConfiguration.php
```

```json [config.json]
{
  "type": "TYPO3",
  "origin": {
    "host": "prod.example.com",
    "user": "deploy",
    "path": "/var/www/html/LocalConfiguration.php"
  },
  "target": {
    "path": "/var/www/local/LocalConfiguration.php"
  }
}
```

:::
