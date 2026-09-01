# Coming from db-sync-tool

php-sync-tool succeeds the Python
[`db-sync-tool`](https://github.com/konradmichalik/db-sync-tool) by the same
author. It is a different tool with its own roadmap, not a translation kept in
lockstep with the original. What it does keep is the part that lives in your
repositories: the configuration files.

## Why a Successor?

The Python tool required a Python runtime wherever a sync ran, which on a PHP
build or deploy host meant provisioning a second toolchain and a
`pip install db-sync-tool-kmi` (plus `file-sync-tool-kmi` for files). This tool
covers database and file synchronization in one Composer dependency or PHAR,
running on the PHP that is already there.

Starting fresh also made room for things the Python tool never had: named
environments with [`init`, `pull` and `push`](/getting-started/quickstart), an
interactive sync picker, [PostgreSQL support](/configuration/postgresql), and
[declarative anonymization](/configuration/anonymization) for GDPR-safe copies.

## What Is Guaranteed

**Your configuration files keep working.** A YAML or JSON config written for
db-sync-tool runs unchanged:

```bash
sync-tool -f config.yaml
```

That promise covers:

- The **file format** and every key in it: `origin`, `target`, `db`,
  `ignore_table`, `type`, `jump_host`, `console`, `script` and the rest of the
  [configuration reference](/configuration/reference).
- **Renamed keys**, under their old names too. `additional_mysqldump_options`
  is still read as `additional_dump_options`.
- The **nine sync modes**, [derived the same way](/reference/sync-modes) from
  the presence and equality of `host` entries.
- **Framework detection** for TYPO3, Symfony, Drupal, WordPress and Laravel.
- The **directory names** `~/.db-sync-tool/` and `.db-sync-tool/`, read as a
  fallback when their `.sync-tool` counterparts are absent. This is a
  transitional convenience rather than a second supported layout, so the tool
  prints a notice when it falls back, and another one when an old directory is
  being ignored because a `.sync-tool` directory exists beside it. Rename the
  directory when convenient, and never keep both.

Everything outside that list is free to differ, and does.

## What Differs

| Topic | db-sync-tool (Python) | php-sync-tool (PHP) |
|-------|-----------------------|---------------------|
| Runtime | Python 3.10+ | PHP 8.2+ |
| Install | `pip install db-sync-tool-kmi` | `composer require konradmichalik/php-sync-tool` or PHAR |
| Command | `db_sync_tool` | `sync-tool` (`vendor/bin/sync-tool`, `sync-tool.phar`) |
| Config directory | `~/.db-sync-tool/` | `~/.sync-tool/` (old name read as fallback) |
| Project config dir | `.db-sync-tool/` | `.sync-tool/` (old name read as fallback) |
| rsync toggle | `--use-rsync` (opt in) | rsync is the default, `--no-rsync` opts out to SFTP |
| Shell completion | built-in (`--install-completion`) | `sync-tool completion <shell>`, from Symfony Console |
| Interactive selection | interactive prompts / discovery | the sync picker on a terminal, `environments` without one |
| Databases | MySQL / MariaDB | MySQL / MariaDB / [PostgreSQL](/configuration/postgresql) |

The command-line flags carry over where they exist here, so `-f`, `-y` and the
endpoint overrides read the same:

```bash
# db-sync-tool (Python)
db_sync_tool -f config.yaml -y

# php-sync-tool (PHP)
sync-tool -f config.yaml -y
```

Named-host syncs are unchanged:

```bash
sync-tool production local
```

### Replacing file-sync-tool

`file_sync_tool` was a second command with its own flags. Its work is a run of
this tool with `--files-only`, reading the same `files` block:

```bash
# file-sync-tool (Python)
file_sync_tool -f config.yaml --files-target /var/www/target/fileadmin

# php-sync-tool (PHP)
sync-tool -f config.yaml --files-only --files-target /var/www/target/fileadmin
```

`--files-target` behaves as it did there: it sets the target of the first entry.
`--files-origin`, `--files-exclude` and `--files-option` have no counterpart;
those values belong in the `files` block.

### Jump Hosts

php-sync-tool tunnels through jump hosts using the system SSH client
(`ssh -J` / ProxyJump). The `jump_host` block accepts `host`, `user`,
`password`, `ssh_key` and `port`. See
[Advanced Options → Jump Host](/configuration/advanced#jump-host).

## Deliberately Absent

These exist in the Python tool and are not planned here. They serve interactive
exploration, which explicit config files, host arguments and
[`@hostname` links](/configuration/advanced#linking-hosts) cover in a way that
also works unattended:

- Interactive host and config discovery prompts beyond the sync picker
- SFTP transfer progress callbacks

The Python tool's feature list is not this tool's roadmap. Features land here
because they earn their place, so expect the two to keep diverging. If
something you rely on is missing, [open an issue](https://github.com/konradmichalik/php-sync-tool/issues).

## Getting Help

- [GitHub Issues](https://github.com/konradmichalik/php-sync-tool/issues)
- [Configuration Reference](/configuration/reference)
