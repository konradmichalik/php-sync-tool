# Symfony

php-sync-tool can automatically detect database credentials from
[Symfony](https://symfony.com/) applications (>= v2.8).

## Configuration Files

| Symfony Version | Config File | Source |
|-----------------|-------------|--------|
| >= 3.4 | `.env` | `DATABASE_URL` environment variable, or the individual `DATABASE_*` variables |
| <= 2.8 | `parameters.yml` | `database_*` parameters |

For `.env`, the tool parses the first non-comment `DATABASE_URL` line, for
example:

```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname"
```

Any file name other than `parameters.yml` is read this way. When `path` points
to a `.env.local`, the files next to it are layered the way Symfony loads them,
from highest to lowest priority: `.env.<APP_ENV>.local`, `.env.<APP_ENV>`,
`.env.local` and `.env`. `APP_ENV` is read from `.env.local`, or from `.env`
when the first one does not set it. An `APP_ENV` that is set only in the
server's environment is not seen. Files from the repository, usually `.env` and
`.env.<APP_ENV>`, fill every value the others leave unset.

### Individual variables

Without a `DATABASE_URL`, the tool reads the individual variables instead, for
projects that wire each Doctrine connection field to its own variable:

```dotenv
DATABASE_DB=dbname
DATABASE_HOST=127.0.0.1
DATABASE_PORT=3306
DATABASE_USER=user
DATABASE_PASSWORD=password
```

In one file, a `DATABASE_URL` line takes precedence, even an empty one, so
remove or comment out an unused `DATABASE_URL`. Across layered files, the
highest file that sets either style decides. The port defaults to `3306`
when `DATABASE_PORT` is missing. These variables name no driver, so the database
is treated as MySQL or MariaDB and `db.type` does not change that. Use a
`DATABASE_URL` for PostgreSQL.

## Command Line

Example for [receiver mode](/reference/sync-modes#receiver):

```bash
bin/sync-tool \
    --type Symfony \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/shared/.env \
    --target-path /var/www/local/project/.env
```

## Configuration File

### Using .env (Symfony >= 3.4)

```yaml
# config.yaml
type: Symfony
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/project/shared/.env
target:
  path: /var/www/local/project/.env
```

### Using parameters.yml (Symfony <= 2.8)

```yaml
# config.yaml
type: Symfony
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/project/app/config/parameters.yml
target:
  path: /var/www/local/project/app/config/parameters.yml
```

## Complete Example

```yaml
type: Symfony
origin:
  name: Production
  host: 192.87.33.123
  user: ssh_deploy_user
  path: /var/www/html/project/shared/.env
target:
  path: /var/www/local/project/.env
ignore_table:
  - sessions
  - messenger_messages
```

## Next Steps

- [Configuration Reference](/configuration/reference) — all configuration options
- [Sync Modes](/reference/sync-modes) — different synchronization modes
