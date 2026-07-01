# Symfony

php-sync-tool can automatically detect database credentials from
[Symfony](https://symfony.com/) applications (>= v2.8).

## Configuration Files

| Symfony Version | Config File | Source |
|-----------------|-------------|--------|
| >= 3.4 | `.env` | `DATABASE_URL` environment variable |
| <= 2.8 | `parameters.yml` | `database_*` parameters |

For `.env`, the tool parses the first non-comment `DATABASE_URL` line, for
example:

```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname"
```

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
