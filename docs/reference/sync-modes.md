# Sync Modes

php-sync-tool selects a synchronization mode automatically based on the origin
and target configuration — specifically whether each endpoint is remote (has a
`host`), whether both point at the same host, and whether an import file is
given. You never set the mode explicitly.

## Overview

| Mode | Origin | Target | Description |
|------|--------|--------|-------------|
| [Receiver](#receiver) | Remote | Local | Pull a database from a remote system |
| [Sender](#sender) | Local | Remote | Push a database to a remote system |
| [Proxy](#proxy) | Remote | Remote | Transfer between two remotes via local |
| [Dump Local](#dump-local) | Local | Local (same) | Create a local backup |
| [Dump Remote](#dump-remote) | Remote | Remote (same) | Create a remote backup |
| [Import Local](#import-local) | — | Local | Import a dump file locally |
| [Import Remote](#import-remote) | — | Remote | Import a dump file remotely |
| [Sync Local](#sync-local) | Local | Local (diff path) | Copy between two local databases |
| [Sync Remote](#sync-remote) | Remote | Remote (same host, diff path) | Copy within one remote system |

The `--import-file` (`-i`) option selects the import modes. The dump modes are
selected when origin and target resolve to the **same** host/database with no
transfer needed.

## Receiver {#receiver}

Pull a database dump from a remote system (origin) to your local system
(target). **This is the default and most common mode.**

![Sync mode receiver](/images/sm-receiver.png)

Origin has a `host`, target does not:

```yaml
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
target:
  path: /var/www/local/LocalConfiguration.php
```

**Use cases:** pulling production data to local development, local backups of
remote databases.

## Sender {#sender}

Send a database dump from your local system (origin) to a remote system
(target).

![Sync mode sender](/images/sm-sender.png)

Target has a `host`, origin does not:

```yaml
origin:
  path: /var/www/local/LocalConfiguration.php
target:
  host: staging.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
```

**Use cases:** pushing local data to staging, deploying database changes to test
environments.

::: warning
Be careful when sending to remote systems. Consider `protect: true` on
production hosts.
:::

## Proxy {#proxy}

Transfer a database between two remote systems using your local machine as a
relay (two hops). Useful when origin and target cannot connect directly.

![Sync mode proxy](/images/sm-proxy.png)

Both origin and target have a `host` (different hosts):

```yaml
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
target:
  host: staging.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
```

**Flow:** dump on origin → transfer to local → transfer to target → import on
target.

**Use cases:** syncing isolated environments, cross-datacenter transfers.

## Dump Local {#dump-local}

Create a database dump on your local system, without transfer or import.

![Sync mode dump local](/images/sm-dump-local.png)

Origin and target are both local and resolve to the same system:

```yaml
origin:
  path: /var/www/local/LocalConfiguration.php
  dump_dir: /var/backups/
target:
  path: /var/www/local/LocalConfiguration.php
```

**Use cases:** local backups, snapshots before risky operations.

## Dump Remote {#dump-remote}

Create a database dump on a remote system, without transfer or import.

![Sync mode dump remote](/images/sm-dump-remote.png)

The same `host` in both origin and target:

```yaml
origin:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
  dump_dir: /var/backups/
target:
  host: prod.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
```

**Use cases:** remote backup creation, scheduled backups.

## Import Local {#import-local}

Import an existing dump file into a local database. Selected by `-i`:

```bash
bin/sync-tool -f config.yaml -i /path/to/dump.sql.gz
```

```yaml
target:
  path: /var/www/local/LocalConfiguration.php
```

**Use cases:** restoring from backup, importing a shared dump.

## Import Remote {#import-remote}

Import an existing dump file into a remote database. Selected by `-i` with a
remote target:

```bash
bin/sync-tool -f config.yaml -i /path/to/dump.sql.gz
```

```yaml
target:
  host: staging.example.com
  user: deploy
  path: /var/www/html/LocalConfiguration.php
```

**Use cases:** restoring remote systems, deploying database snapshots.

## Sync Local {#sync-local}

Copy a database between two different local paths/databases.

![Sync mode sync local](/images/sm-sync-local.png)

No `host` entries, but different `path` (or database):

```yaml
origin:
  path: /var/www/project-a/LocalConfiguration.php
target:
  path: /var/www/project-b/LocalConfiguration.php
```

**Use cases:** syncing between local projects, testing migrations locally.

## Sync Remote {#sync-remote}

Copy a database between two paths on the **same** remote system.

![Sync mode sync remote](/images/sm-sync-remote.png)

Same `host`, different `path` (or database):

```yaml
origin:
  host: server.example.com
  user: deploy
  path: /var/www/live/LocalConfiguration.php
target:
  host: server.example.com
  user: deploy
  path: /var/www/staging/LocalConfiguration.php
```

**Use cases:** copying production to staging on the same server, refreshing a
test environment.
