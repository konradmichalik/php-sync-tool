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

### Steering the target from outside

A deployment path often carries a branch or release name that the configuration
file cannot know. `--files-target` sets the `target` of the **first** entry for
that run:

```bash
bin/sync-tool -f config.yaml --files-only \
  --files-target /var/www/instances/feature-123/fileadmin
```

Every other entry keeps its configured target. With no `files` entry to apply it
to, the run stops and says so rather than synchronizing nothing.

## Transfer Behavior

- File transfers use the same endpoint roles and modes as the database sync
  (receiver, sender, proxy, etc.), so the direction follows origin → target.
- The default transport is rsync. If no rsync is installed, the tool says so and
  falls back to SFTP; pass `--no-rsync` to choose SFTP explicitly.
- Between two paths on the same machine there is no host to reach, so SFTP is no
  fallback. A database dump is copied with `cp`, but synchronizing directories
  needs rsync and the run stops with a message naming it, because `cp` honors
  neither `exclude` nor rsync's mirroring.
- For proxy mode (remote → remote), files are relayed via the local machine in
  two hops, mirroring the database transfer.

### rsync vs. SFTP fallback differences

The SFTP fallback (`--no-rsync`) transfers the same files and honors
`exclude` patterns, but is not a drop-in replacement for rsync's semantics:

- **No mirroring.** rsync's defaults include `--delete`, so a file removed
  from `origin` is also removed from `target` on the next sync. SFTP has no
  equivalent — it only adds/overwrites files, so stale files already present
  under `target` are never cleaned up. `--no-rsync` behaves like a merge, not
  a mirror.
- **`options` is ignored.** Per-entry `options` (and the global
  `files_options`) are extra flags passed straight to the `rsync` binary —
  they have no effect when the transfer falls back to SFTP.
- **Directory layout.** rsync's behavior depends on whether `origin` ends
  with a trailing slash (a bare path nests the source directory itself
  under `target`, a trailing slash copies its contents into `target`). SFTP
  always maps the origin directory's contents directly into `target`,
  regardless of a trailing slash. Keep this in mind if you switch between
  `--no-rsync` and the default transport for the same entry.

## Examples

### Files-Only Deploy of Assets

```bash
bin/sync-tool -f config.yaml --files-only
```

### Full Environment Refresh (DB + Files)

```bash
bin/sync-tool -f config.yaml --with-files --clear-database
```
