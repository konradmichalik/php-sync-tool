# Configuration Reference

Complete reference for `config.yaml` (or `config.json`). Configuration is
validated against a JSON schema; unknown values in known objects are ignored,
but wrong types are rejected.

## Root Keys

| Key | Type | Description |
|-----|------|-------------|
| `type` | enum | Framework: `TYPO3`, `Symfony`, `Drupal`, `WordPress`, `Laravel`. Optional if `db` credentials are given. |
| `origin` | object | Source endpoint (see [Client Object](#client-object)). |
| `target` | object | Destination endpoint (see [Client Object](#client-object)). |
| `ignore_table` | array | Tables to exclude from the dump (wildcards supported). |
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
| `post_sql` | array | SQL statements to execute after import. |
| `protect` | boolean | Refuse to use this endpoint as an import target without confirmation. |
| `console` | object | Custom command paths (`php`, `mysql`, `mysqldump`). |
| `script` | object | Lifecycle commands: `before`, `after`, `error`. |
| `db` | object | Manual database credentials (see [Database Object](#database-object)). |
| `jump_host` | object | SSH jump host (see [Jump Host Object](#jump-host-object)). |

### Database Object

Under `origin.db` / `target.db`:

| Key | Type | Description |
|-----|------|-------------|
| `name` | string | Database name. |
| `host` | string | Database host. |
| `user` | string | Database user. |
| `password` | string | Database password. |
| `port` | number | Database port (default: 3306, PostgreSQL: 5432). |
| `ssl_disabled` | boolean | Disable TLS for the MySQL connection (useful for DDEV). |
| `type` | enum | Database system: `mysql`, `mariadb`, `postgres` (default: `mysql`). See [PostgreSQL](/configuration/postgresql). |

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
