# TYPO3

php-sync-tool can automatically detect database credentials from
[TYPO3](https://typo3.org/) applications (>= v7.6).

## Configuration Files

Point `path` at one of the following. The tool detects the framework from the
file name and reads the credentials (over SSH for remote systems):

| File | TYPO3 Version | Notes |
|------|---------------|-------|
| `LocalConfiguration.php` | v7.6 – v12.x | Standard configuration |
| `AdditionalConfiguration.php` | v7.6 – v12.x | Override configuration |
| `config/system/settings.php` | v13+ | New location (Composer mode) |
| `config/system/additional.php` | v13+ | New override location |
| `.env` | v9+ | Environment-based configuration |

::: tip TYPO3 v13 Changes
In TYPO3 v13 the configuration files were relocated:
- `typo3conf/LocalConfiguration.php` → `config/system/settings.php`
- `typo3conf/AdditionalConfiguration.php` → `config/system/additional.php`

Both old and new paths are supported.
:::

PHP configuration files are read by evaluating them with the PHP CLI and
serializing the resolved connection settings, so computed values are handled
correctly.

## Command Line

Example for [receiver mode](/reference/sync-modes#receiver):

```bash
bin/sync-tool \
    --type TYPO3 \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/typo3conf/LocalConfiguration.php \
    --target-path /var/www/local/typo3conf/LocalConfiguration.php
```

## Configuration File

### TYPO3 v7.6 – v12.x

```yaml
# config.yaml
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
target:
  path: /var/www/local/typo3conf/LocalConfiguration.php
```

### TYPO3 v13+

```yaml
# config.yaml
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/config/system/settings.php
target:
  path: /var/www/local/config/system/settings.php
```

## .env Support

Credentials can also be parsed from a `.env` file. Point `path` at it — the tool
reads the default TYPO3 connection variables:

```dotenv
TYPO3_CONF_VARS__DB__Connections__Default__dbname=db
TYPO3_CONF_VARS__DB__Connections__Default__host=db
TYPO3_CONF_VARS__DB__Connections__Default__port=3306
TYPO3_CONF_VARS__DB__Connections__Default__user=db
TYPO3_CONF_VARS__DB__Connections__Default__password=db
```

## Complete Example

```yaml
type: TYPO3
origin:
  name: Production
  host: 192.87.33.123
  user: ssh_deploy_user
  path: /var/www/html/shared/typo3conf/LocalConfiguration.php
target:
  path: /var/www/local/typo3conf/LocalConfiguration.php
ignore_table:
  - cache_*
  - cf_*
  - sys_log
  - sys_history
  - be_sessions
  - fe_sessions
```

## Next Steps

- [Configuration Reference](/configuration/reference) — all configuration options
- [Advanced Options](/configuration/advanced) — scripts, logging, cleanup
