# Configuration

php-sync-tool can be configured via command line arguments or configuration
files (YAML or JSON). Config files are validated against a JSON schema, so typos
in keys are caught early.

## Configuration Methods

| Method | Best For |
|--------|----------|
| Named Environments (`pull` / `push`) | Day-to-day work in a project |
| [Config Files](/configuration/reference) | Complex setups, CI/CD pipelines, reproducibility |
| Named Hosts | Quick, repeated syncs across projects |
| [CLI Arguments](/reference/cli) | One-off syncs, scripting, overrides |

## Quick Example

### Using a Config File

```yaml
# config.yaml
type: TYPO3
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/typo3conf/LocalConfiguration.php
target:
  path: /var/www/local/typo3conf/LocalConfiguration.php
ignore_table:
  - cache_*
  - sys_log
```

```bash
bin/sync-tool -f config.yaml
```

### Using Named Hosts

```bash
bin/sync-tool production local
```

### Using CLI Arguments

```bash
bin/sync-tool \
    --type TYPO3 \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-path /var/www/html/typo3conf/LocalConfiguration.php \
    --target-path /var/www/local/typo3conf/LocalConfiguration.php
```

## Configuration Discovery

When you do not pass `-f`, php-sync-tool resolves configuration from named host
and project definitions:

### Directory Layout

```text
~/.sync-tool/
├── hosts.yaml       # Global host definitions (name → host config)
└── defaults.yaml    # Global defaults, merged into every config (optional)

<project>/.sync-tool/
├── defaults.yaml    # Project defaults, including the `local` block (optional)
├── prod.yaml        # A named environment ("prod")
└── staging.yaml     # A named environment ("staging")
```

### The `local` Block

`defaults.yaml` describes this machine once, under `local`:

```yaml
type: TYPO3
local:
  path: web/typo3conf/LocalConfiguration.php
  dump_dir: var/transfer/
```

`pull <name>` then means "that environment into `local`", and `push <name>` the
reverse. An environment file only has to describe the remote side:

```yaml
# .sync-tool/prod.yaml
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/prod/typo3conf/LocalConfiguration.php
```

An environment can equally be a host from `hosts.yaml`. Without a `local` block,
`pull` and `push` refuse with a message naming the file to add it to.

### Reserved Names

An environment or host cannot be named after a command: `sync`, `pull`, `push`,
`init`, `environments`, `list`, `help` or `completion`. Any other first argument
is understood as a name, so `bin/sync-tool production local` keeps working as
documented.

The project `.sync-tool/` directory is searched from the current working
directory upwards.

### Resolution Order

1. **Explicit file** — `-f config.yaml` is loaded directly.
2. **Extra hosts** — `-o hosts.yaml` merges additional host definitions into the
   global set.
3. **Project config by name** — `bin/sync-tool prod` loads `.sync-tool/prod.yaml`.
4. **Host references** — `bin/sync-tool production local` resolves `production`
   and `local` as host names from `hosts.yaml`.
5. Otherwise an error is raised (no config found).

Global and project `defaults.yaml` are merged in before your named config, so
shared settings can be defined once.

## Key Configuration Sections

### Origin & Target

Every sync needs an **origin** (source) and a **target** (destination). The
presence of a `host` entry makes an endpoint remote:

```yaml
origin:
  host: remote.example.com  # SSH host → remote
  user: ssh_user
  path: /path/to/config
target:
  path: /local/path/to/config  # no host → local
```

The combination of remote/local endpoints determines the
[sync mode](/reference/sync-modes) automatically.

### Framework Type

```yaml
type: TYPO3  # or Symfony, Drupal, WordPress, Laravel
```

If omitted, the tool attempts to detect the framework from the `path` file name.

### Ignore Tables

Exclude tables from the dump (wildcards are expanded against the live database):

```yaml
ignore_table:
  - cache_*
  - sessions
  - sys_log
```

## Next Steps

- [Full Reference](/configuration/reference) — all configuration options
- [Authentication](/configuration/authentication) — SSH keys, agent, passwords
- [Advanced Options](/configuration/advanced) — scripts, links, jump hosts, cleanup
- [File Synchronization](/configuration/file-sync) — syncing files
