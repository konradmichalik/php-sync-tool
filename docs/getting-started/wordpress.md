# WordPress

php-sync-tool can automatically detect database credentials from
[WordPress](https://wordpress.org) applications (>= v5.0).

## Configuration File

The tool parses `wp-config.php`, reading the standard database constants:

```php
define( 'DB_NAME', 'database_name' );
define( 'DB_USER', 'database_user' );
define( 'DB_PASSWORD', 'database_password' );
define( 'DB_HOST', 'localhost' );
```

## Command Line

Example for [receiver mode](/reference/sync-modes#receiver):

```bash
bin/sync-tool \
    --type WordPress \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/wordpress/wp-config.php \
    --target-path /var/www/local/wordpress/wp-config.php
```

## Configuration File

```yaml
# config.yaml
type: WordPress
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/wordpress/wp-config.php
target:
  path: /var/www/local/wordpress/wp-config.php
```

## Complete Example

```yaml
type: WordPress
origin:
  name: Production
  host: 192.87.33.123
  user: ssh_deploy_user
  path: /var/www/html/wordpress/wp-config.php
target:
  path: /var/www/local/wordpress/wp-config.php
ignore_table:
  - wp_sessions
```

## Next Steps

- [Configuration Reference](/configuration/reference) — all configuration options
- [Sync Modes](/reference/sync-modes) — different synchronization modes
