# Quick Start

This guide shows how to run a database sync without relying on framework
credential detection. For framework projects, see the
[framework guides](/getting-started/typo3).

## Command Line

Most sync details can be declared as CLI arguments. Here is an example for
[receiver mode](/reference/sync-modes#receiver) (remote → local) using manual
database credentials:

```bash
bin/sync-tool \
    --origin-host prod.example.com \
    --origin-user deploy \
    --origin-db-name remote_db \
    --origin-db-user db_user \
    --origin-db-password db_password \
    --target-db-name local_db \
    --target-db-user root \
    --target-db-password root
```

## Configuration File

For reusable, readable setups, put the sync details in a config file. YAML and
JSON are both supported.

### Using YAML (Recommended)

```bash
bin/sync-tool -f config.yaml
```

```yaml
# config.yaml
origin:
  host: prod.example.com
  user: deploy
  db:
    name: remote_db
    host: localhost
    user: db_user
    password: db_password
target:
  db:
    name: local_db
    host: localhost
    user: root
    password: root
```

### Using JSON

```bash
bin/sync-tool -f config.json
```

```json
{
  "origin": {
    "host": "prod.example.com",
    "user": "deploy",
    "db": {
      "name": "remote_db",
      "host": "localhost",
      "user": "db_user",
      "password": "db_password"
    }
  },
  "target": {
    "db": {
      "name": "local_db",
      "host": "localhost",
      "user": "root",
      "password": "root"
    }
  }
}
```

## Named Hosts

For workflows you repeat often, define hosts once in `~/.sync-tool/hosts.yaml`
and reference them by name — no `-f` needed:

```bash
# Sync from the "production" host to the "local" host
bin/sync-tool production local
```

See [Configuration → Overview](/configuration/) for how host and project config
discovery works.

## Common Options

| Option | Short | Description |
|--------|-------|-------------|
| `--config-file` | `-f` | Path to a configuration file |
| `--dry-run` | | Resolve and report the plan without exporting/transferring/importing |
| `--yes` | `-y` | Skip the import confirmation prompt |
| `--output` | | Output mode: `interactive`, `ci`, `json`, `quiet` |

See the [CLI Reference](/reference/cli) for the full option list.

## A Safe First Run

Preview exactly what would happen before touching any database:

```bash
bin/sync-tool -f config.yaml --dry-run
```

## Next Steps

- [Framework Guides](/getting-started/typo3) — automatic credential detection
- [Configuration](/configuration/) — full configuration options
- [Sync Modes](/reference/sync-modes) — how the mode is chosen
