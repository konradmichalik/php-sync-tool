# PostgreSQL

php-sync-tool synchronizes PostgreSQL the same way it synchronizes MySQL and
MariaDB: dump on the origin, transfer the gzipped file, import on the target.
Only the client binaries and a few statement dialects differ.

## Selecting the database system

Set `type` inside the `db` block of an endpoint:

```yaml
origin:
  host: production.example.com
  user: deploy
  db:
    type: postgres
    name: app
    host: 127.0.0.1
    user: app
    password: secret
    port: 5432

target:
  db:
    type: postgres
    name: app
    user: app
    password: secret
```

Accepted values are `mysql`, `mariadb`, `postgres`, `postgresql` and `pgsql`, in
any casing. Without `type`, the tool assumes MySQL, which keeps every existing
configuration working unchanged.

When credentials come from framework auto-detection instead of the config file,
the database system is read from the scheme of the framework's database URL, so
a Symfony `DATABASE_URL=postgresql://…` needs no `type` at all.

## Requirements

`pg_dump` and `psql` must exist on the host that runs the respective step: the
origin host dumps, the target host imports. The tool never talks to PostgreSQL
over a network protocol itself, it drives the official clients.

Mind the client version: `pg_dump` refuses a server newer than itself. A client
of the same major version as the server, or newer, is fine.

## How the password reaches the client

Exactly as careful as on the MySQL side. The tool writes a `.pgpass` file with
mode 0600, points the client at it through `PGPASSFILE`, and removes the file
when the step finishes, including on failure. The password never appears on a
command line and is masked in every log line.

## Differences from MySQL

| Behavior | MySQL | PostgreSQL |
|----------|-------|------------|
| Dump | `mysqldump … \| gzip` | `pg_dump --no-owner --no-privileges --clean --if-exists … \| gzip` |
| Import | `gunzip -c … \| mysql` | `gunzip -c … \| psql -v ON_ERROR_STOP=1` |
| `clear_database` | `DROP TABLE` per table, foreign-key checks disabled | one `DROP TABLE IF EXISTS … CASCADE` |
| `truncate_table` | `TRUNCATE TABLE`, foreign-key checks disabled | `TRUNCATE TABLE … RESTART IDENTITY CASCADE` |
| Table listing | `SHOW TABLES` | `pg_tables` in schema `public` |

The dump carries `--clean --if-exists`, so an import drops and recreates the
objects it contains. That makes a re-import idempotent and makes
`clear_database` unnecessary for most PostgreSQL setups.

Only the `public` schema is considered when tables are listed for
`clear_database` or for a wildcard in `ignore_table`. Table names are treated as
lowercase identifiers, so mixed-case PostgreSQL tables are out of scope.

## Options that do not apply

Two options are MySQL-only. A PostgreSQL run that uses them aborts before
anything is dumped, transferred or imported, rather than ignoring them:

- `where` — `pg_dump` has no equivalent of `mysqldump --where`.
- `additional_mysqldump_options` — passed verbatim to `mysqldump`, meaningless
  for `pg_dump`.

The error names both the system and the option:

```
[ERROR] PostgreSQL does not support: where
```

Everything else works as documented elsewhere: partial dumps via `--tables`,
`ignore_table` including wildcards, `keep_dump`, `post_sql`, lifecycle scripts,
file synchronization, and every sync mode.
