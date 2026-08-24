# Coming from db-sync-tool

php-sync-tool is a PHP port of the Python
[`db-sync-tool`](https://github.com/konradmichalik/db-sync-tool) and aims for
feature parity. If you already use the Python tool, your existing config files
and mental model largely carry over. This page highlights what is the same and
what differs.

## Why a PHP Port?

The main motivation is dropping the Python runtime requirement on build and
deploy hosts. Where a project previously needed `pip install db-sync-tool-kmi`
(and `file-sync-tool-kmi` for files), php-sync-tool covers both database and
file synchronization with a single Composer dependency or PHAR — running on the
PHP that is already present.

## What Stays the Same

- **Config file format** — the same YAML/JSON structure with `origin`, `target`,
  `db`, `ignore_table`, `type`, `jump_host`, `console`, `script`, and so on.
- **Sync modes** — the same nine modes, [selected automatically](/reference/sync-modes)
  from the presence and equality of `host` entries.
- **Framework detection** — TYPO3, Symfony, Drupal, WordPress, and Laravel are
  all supported.
- **rsync by default** with automatic SFTP fallback, and SSH host-key
  verification enforced by default.

## What Differs

| Topic | db-sync-tool (Python) | php-sync-tool (PHP) |
|-------|-----------------------|---------------------|
| Runtime | Python 3.10+ | PHP 8.2+ |
| Install | `pip install db-sync-tool-kmi` | `composer require konradmichalik/php-sync-tool` or PHAR |
| Command | `db_sync_tool` | `bin/sync-tool` (or `vendor/bin/sync-tool`, `sync-tool.phar`) |
| Config directory | `~/.db-sync-tool/` | `~/.sync-tool/` |
| Project config dir | `.db-sync-tool/` | `.sync-tool/` |
| rsync toggle | `--use-rsync` (opt in) | rsync is default; `--no-rsync` opts out to SFTP |
| Shell completion | built-in (`--install-completion`) | `sync-tool completion <shell>`, from Symfony Console |
| Interactive selection | interactive prompts / discovery | the sync picker on a terminal, `environments` without one |

### Command Migration

```bash
# db-sync-tool (Python)
db_sync_tool -f config.yaml -y

# php-sync-tool (PHP)
bin/sync-tool -f config.yaml -y
```

Named-host syncs work the same way, only the config directory name changes from
`~/.db-sync-tool/` to `~/.sync-tool/`:

```bash
bin/sync-tool production local
```

### Jump Hosts

php-sync-tool tunnels through jump hosts using the system SSH client
(`ssh -J` / ProxyJump). The `jump_host` block accepts `host`, `user`,
`password`, `ssh_key`, and `port`. See
[Advanced Options → Jump Host](/configuration/advanced#jump-host).

## Not Currently Included

A few Python-tool conveniences are intentionally out of scope, as they add
little value for non-interactive CI/deploy automation:

- Interactive host/config discovery prompts beyond the sync picker
- SFTP transfer progress callbacks

Explicit config files, host arguments, and [`@hostname` links](/configuration/advanced#linking-hosts)
cover the same needs in an automation-friendly way.

## Getting Help

- [GitHub Issues](https://github.com/konradmichalik/php-sync-tool/issues)
- [Configuration Reference](/configuration/reference)
