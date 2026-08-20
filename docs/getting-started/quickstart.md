# Quick Start

## The short way

Set the project up once, then sync by name:

```bash
bin/sync-tool init          # asks a few questions, writes .sync-tool/
bin/sync-tool pull prod     # pulls that environment's database into this project
```

`init` detects the framework from the files in the working directory, proposes
the credential file it found, and asks for the first environment. It writes two
files:

```text
.sync-tool/
├── defaults.yaml   # framework plus the `local` block describing this machine
└── prod.yaml       # the "prod" environment
```

From then on `pull <name>` and `push <name>` need nothing else, and calling
`bin/sync-tool` with no arguments offers every sync it can find:

```text
How should this be synchronized?
  [0] pull from prod
  [1] push to prod
```

The picker only appears on a terminal. In a pipeline or with
`--no-interaction`, the tool behaves as it always has and reports that
configuration is missing.

The rest of this page shows the explicit forms, which stay fully supported and
are what you want in CI.

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
