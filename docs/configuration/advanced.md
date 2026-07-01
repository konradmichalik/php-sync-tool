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

- `before` — runs before the endpoint's export/import work
- `after` — runs after it completes
- `error` — runs if the sync fails

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

Production databases pulled into a development environment routinely contain
personal data (names, emails, addresses, hashed passwords). Under the GDPR you
must not keep that data in a non-production system without cause. `post_sql`
runs on the target **after** the import, so it is the natural place to mask
personal data before it is ever used:

```yaml
target:
  path: /var/www/html/.env
  post_sql:
    # Overwrite direct identifiers with deterministic, non-personal values
    - "UPDATE users SET email = CONCAT('user', id, '@example.test'), name = CONCAT('User ', id), phone = NULL;"
    # Neutralize credentials so no real password hash leaves production
    - "UPDATE users SET password = '$2y$10$abcdefghijklmnopqrstuv';"
    # Drop rows that have no place in a dev database at all
    - "TRUNCATE TABLE payment_tokens;"
    - "DELETE FROM audit_log WHERE created_at < NOW() - INTERVAL 30 DAY;"
```

For reusable, multi-line masking, keep the statements in a file and apply it
with [after-dump import](#after-dump-import) or a lifecycle
[script](#lifecycle-scripts) instead of inlining every rule.

::: tip
Combine this with [Partial Sync](#partial-sync) (`--tables` / `--where`) to leave
bulky or sensitive tables behind entirely, rather than copying and then masking
them.
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

Override the paths to required binaries per endpoint:

```yaml
origin:
  console:
    php: /usr/local/bin/php
    mysql: /usr/local/mysql/bin/mysql
    mysqldump: /usr/local/mysql/bin/mysqldump
```

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
