# File Synchronization

Beyond databases, php-sync-tool can synchronize files between the same
origin/target endpoints, using the same transport (rsync, with SFTP fallback,
and two-hop proxy support). This replaces the need for a separate file-sync
tool.

## Enabling File Sync

File sync is opt-in via CLI flags:

| Flag | Effect |
|------|--------|
| `--with-files` | Sync files **in addition to** the database |
| `--files-only` | Sync **only** files, skip the database |

```bash
# Database and files
bin/sync-tool -f config.yaml --with-files

# Files only
bin/sync-tool -f config.yaml --files-only
```

## Configuring Paths

Define file entries as a top-level `files` list. Each entry maps an `origin`
path to a `target` path and may exclude patterns:

```yaml
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
target:
  path: /var/www/local/typo3conf/LocalConfiguration.php

files:
  - origin: /var/www/html/fileadmin
    target: /var/www/local/fileadmin
    exclude:
      - "_processed_"
      - "*.log"
  - origin: /var/www/html/uploads
    target: /var/www/local/uploads
```

### Entry Keys

| Key | Type | Description |
|-----|------|-------------|
| `origin` | string | Source directory (on the origin endpoint). |
| `target` | string | Destination directory (on the target endpoint). |
| `exclude` | array | Patterns to exclude from the transfer. |
| `options` | string | Extra transfer options for this entry. |

## Transfer Behavior

- File transfers use the same endpoint roles and modes as the database sync
  (receiver, sender, proxy, etc.), so the direction follows origin → target.
- The default transport is rsync. If rsync is unavailable, transfers fall back
  to SFTP; pass `--no-rsync` to force SFTP explicitly.
- For proxy mode (remote → remote), files are relayed via the local machine in
  two hops, mirroring the database transfer.

## Examples

### Files-Only Deploy of Assets

```bash
bin/sync-tool -f config.yaml --files-only
```

### Full Environment Refresh (DB + Files)

```bash
bin/sync-tool -f config.yaml --with-files --clear-database
```
