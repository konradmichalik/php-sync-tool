# Drupal

php-sync-tool can automatically detect database credentials from
[Drupal](https://www.drupal.org/) applications (>= v8.0).

## How It Works

The tool reads the active database connection from the Drupal installation via
[`drush`](https://www.drush.org/latest/commands/core_status/). Point `path` at
the Drupal `settings.php` (or its installation directory).

## Prerequisites

- `drush` installed and accessible on the system where the Drupal site lives
- PHP CLI available

## Command Line

Example for [receiver mode](/reference/sync-modes#receiver):

```bash
bin/sync-tool \
    --type Drupal \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/drupal/sites/default/settings.php \
    --target-path /var/www/local/drupal/sites/default/settings.php
```

## Configuration File

```yaml
# config.yaml
type: Drupal
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/drupal/sites/default/settings.php
target:
  path: /var/www/local/drupal/sites/default/settings.php
```

## Complete Example

```yaml
type: Drupal
origin:
  name: Production
  host: 192.87.33.123
  user: ssh_deploy_user
  path: /var/www/html/drupal/sites/default/settings.php
target:
  path: /var/www/local/drupal/sites/default/settings.php
ignore_table:
  - cache_*
  - sessions
  - watchdog
  - flood
```

## Next Steps

- [Configuration Reference](/configuration/reference) — all configuration options
- [Sync Modes](/reference/sync-modes) — different synchronization modes
