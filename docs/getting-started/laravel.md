# Laravel

php-sync-tool can automatically detect database credentials from
[Laravel](https://laravel.com/) applications (>= v4.0).

## Configuration File

The tool parses the `.env` file, reading the standard Laravel database
variables:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=secret
```

## Command Line

Example for [receiver mode](/reference/sync-modes#receiver):

```bash
bin/sync-tool \
    --type Laravel \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/laravel/.env \
    --target-path /var/www/local/laravel/.env
```

## Configuration File

```yaml
# config.yaml
type: Laravel
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/laravel/.env
target:
  path: /var/www/local/laravel/.env
```

## Complete Example

```yaml
type: Laravel
origin:
  name: Production
  host: 192.87.33.123
  user: ssh_deploy_user
  path: /var/www/html/laravel/.env
target:
  path: /var/www/local/laravel/.env
ignore_table:
  - jobs
  - failed_jobs
  - sessions
  - cache
  - cache_locks
```

## Next Steps

- [Configuration Reference](/configuration/reference) — all configuration options
- [Sync Modes](/reference/sync-modes) — different synchronization modes
