# Advanced Options

Configuration for lifecycle scripts, post-import SQL, logging, cleanup, jump
hosts, and more.

## Lifecycle Scripts

Run custom commands at different stages of the sync, per endpoint:

```yaml
origin:
  script:
    before: /var/www/scripts/pre-export.sh
    after: /var/www/scripts/post-export.sh
    error: /var/www/scripts/export-error.sh

target:
  script:
    before: php artisan down
    after: php artisan up && php artisan cache:clear
    error: php artisan up
```

- `before` — runs before the export/import work starts
- `after` — runs after it completes
- `error` — runs if the sync fails

Every block runs on the machine driving the sync, in the order root, `origin`,
`target`, and not on the endpoint it is written under. `scripts` is accepted as
an alias for `script`.

## Post-Import SQL

Run SQL statements on the target after the import — useful for anonymizing data
or resetting environment-specific values:

```yaml
target:
  post_sql:
    - "UPDATE sys_domain SET hidden = 1;"
    - "UPDATE be_users SET email = CONCAT('test-', uid, '@example.com');"
```

## Data Anonymization (GDPR/DSGVO)

Masking personal data is configuration, not a hand-written recipe. Declare the
rules per table and column and the tool applies them after the import:

```yaml
target:
  anonymize:
    fe_users:
      email: email
      password: hash
```

See [Data Anonymization](/configuration/anonymization) for the available
strategies, the phase order and the PostgreSQL dialect. `post_sql` remains the
place for everything masking cannot express, such as deleting rows outright.

::: tip
Combine either with [Partial Sync](#partial-sync) (`--tables` / `--where`) to
leave bulky or sensitive tables behind entirely, rather than copying and then
masking them.
:::

## After-Dump Import

Import an additional SQL file after the main import completes:

```yaml
target:
  after_dump: /path/to/additional.sql
```

Or from the CLI:

```bash
bin/sync-tool -f config.yaml --target-after-dump /path/to/additional.sql
```

## Logging

Write a log file, optionally as structured JSON lines for aggregation systems:

```yaml
log_file: /var/log/php-sync-tool.log
json_log: true
```

Equivalent CLI flags:

```bash
bin/sync-tool -f config.yaml -l /var/log/sync.log --json-log
```

Secrets (passwords, credentials) are sanitized from every logged message.

## Dump Directory

Change where temporary dump files are written (default: `/tmp/`):

```yaml
origin:
  dump_dir: /var/backups/db/
target:
  dump_dir: /home/user/dumps/
```

::: warning
Use a unique directory per project to avoid conflicts with dump retention.
:::

## Dump Retention

Keep only the N most recent dumps in `dump_dir`:

```yaml
origin:
  dump_dir: /var/backups/db/
  keep_dumps: 5   # keep the last 5, delete older ones
```

::: danger
Retention deletes older dump files in `dump_dir`. Point it at a dedicated
directory used only for this project's dumps.
:::

## Linking Hosts

For projects with several config files, define hosts once and reference them
with the `@name` syntax. Hosts live in `~/.sync-tool/hosts.yaml` (or an extra
file passed with `-o`):

```yaml
# hosts.yaml
prod:
  host: prod.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php

staging:
  host: staging.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
```

```yaml
# config.yaml
origin:
  link: "@prod"
target:
  link: "@staging"
```

```bash
bin/sync-tool -f config.yaml -o hosts.yaml
```

## Import Confirmation

Any run that writes to a target database asks for confirmation before it starts:

```
This overwrites the remote (prod.example.com) database. Continue? (yes/no) [no]:
```

The prompt only appears on an interactive terminal. Non-interactive contexts
(CI pipelines, Deployer, cron) proceed automatically, as does `--dry-run`. Skip
it explicitly with `--yes` / `-y`:

```bash
bin/sync-tool -f config.yaml --yes
```

## Protect Host

Prevent accidental imports into a critical system entirely. When an endpoint
marked `protect: true` is used as the **target**, the sync is refused with an
error before any change is made — regardless of `--yes`:

```yaml
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
  protect: true
```

Mark every production host `protect: true` so it can only ever be a sync
**source**, never overwritten.

## Backup Before Import

An import replaces what the target holds. `backup_before_import` dumps the target
first, while it is still intact, so there is something to go back to:

```yaml
backup_before_import: true
```

Or for a single run:

```bash
bin/sync-tool -f config.yaml --backup-before-import
```

The file lands in the target's `dump_dir` as
`sync-tool_backup_<database>_<timestamp>.sql.gz` and holds the whole database,
even when the sync itself is a partial one. It is written before `clear_database`,
before `truncate_table` and before the import.

::: warning
The backup counts as one of this tool's dumps, so `keep_dumps` retention can
delete it like any other. Give a target you rely on this for either no
`keep_dumps` or enough room for both files.
:::

## Dump Check

Before the import, the tool checks that the dump it is about to load has content
at all, so that an empty or truncated file cannot silently clear a database. That
check is on by default and can be turned off for a run:

```bash
bin/sync-tool -f config.yaml --no-check-dump
```

```yaml
check_dump: false
```

## Reverse Origin and Target

Swap origin and target for a single run:

```bash
bin/sync-tool -f config.yaml --reverse
```

## Jump Host

Reach servers that are only accessible through a bastion. Tunnelling uses the
system SSH client (`ssh -J` / ProxyJump):

```yaml
origin:
  host: internal.server.local
  user: app_user
  path: /var/www/html/config.php
  jump_host:
    host: bastion.example.com   # public bastion
    user: bastion_user          # optional (defaults to endpoint user)
    port: 22                    # optional (defaults to endpoint port)
    ssh_key: /home/user/.ssh/bastion_key  # optional
```

## Console Commands

Override the paths to required binaries per endpoint. The key is the binary it
replaces, so it depends on the endpoint's `db.type`:

```yaml
origin:
  console:
    php: /usr/local/bin/php
    mysql: /usr/local/mysql/bin/mysql
    mysqldump: /usr/local/mysql/bin/mysqldump
```

Which names apply follows the database system, because MariaDB 11 deprecated the
`mysql` and `mysqldump` symlinks:

| `db.type` | client | dump |
|-----------|--------|------|
| `mysql` | `mysql` | `mysqldump` |
| `mariadb` | `mariadb` | `mariadb-dump` |
| `postgres` | `psql` | `pg_dump` |

An endpoint left at the default `mysql` type keeps using the `mysql` names, so
existing configurations are unaffected. Set `db.type: mariadb` to address a
MariaDB server by its own binaries.

## SSH Port

```yaml
origin:
  host: prod.example.com
  user: deploy
  port: 2222   # default: 22
```

## Naming Hosts

Descriptive names improve log readability:

```yaml
origin:
  name: Production
  host: prod.example.com
target:
  name: Local Dev
  path: /var/www/local/config.php
```

## Clear Database

Drop all target tables before importing for a clean slate:

```bash
bin/sync-tool -f config.yaml --clear-database
```

## Partial Sync

Sync specific tables, optionally with a `WHERE` clause:

```bash
# Only selected tables
bin/sync-tool -f config.yaml --tables=users,orders

# A subset of rows
bin/sync-tool -f config.yaml --tables=orders --where="created_at > '2024-01-01'"
```
