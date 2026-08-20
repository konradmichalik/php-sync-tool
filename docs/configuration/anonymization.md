# Data Anonymization

A production database pulled into a development environment routinely carries
personal data: names, addresses, mail addresses, password hashes. Under the GDPR
that data has no place in a non-production system without a reason. The
`anonymize` block masks it as part of the sync, so the plaintext never sits in
the copy waiting for someone to remember a cleanup step.

## Configuration

Rules live on the **target** client, keyed by table and column:

```yaml
target:
  db:
    name: app
  anonymize:
    fe_users:
      email: email
      password: hash
      name:
        strategy: static
        value: 'Redacted'
    sys_log:
      details: 'null'
```

A column takes either the strategy name directly, or an object when the strategy
needs a parameter.

::: warning Quote `null`
`details: null` is a YAML null and reads as "no strategy configured", which the
tool rejects. Write `details: 'null'` with quotes.
:::

## Strategies

| Strategy | Parameter | What lands in the column |
|----------|-----------|--------------------------|
| `null` | — | `NULL` |
| `static` | `value` (required) | the configured value, the same for every row |
| `hash` | — | the MD5 of the previous value |
| `email` | — | the MD5 of the previous address plus `@example.invalid` |

The `email` strategy hashes the existing address rather than numbering rows,
which means it needs no primary-key column and works the same whether the table
calls it `id`, `uid` or nothing at all. The result stays unique per distinct
address, so uniqueness constraints and joins on the address survive, and
`example.invalid` is reserved by RFC 2606 and can never receive mail.

`hash` is the right choice for anything that must stay distinguishable without
being readable, password hashes above all: the copy keeps a value in the column,
but it is no longer the hash that protects the production account.

## When it runs

Masking is its own phase, between the import and `post_sql`:

```
dump → transfer → import → after_dump → anonymize → post_sql
```

That order is deliberate. It runs after every import step, so rows brought in by
[after-dump import](/configuration/advanced#after-dump-import) are masked too,
and before `post_sql`, so your own statements already see masked data.

Every rule is applied in one database invocation. A failing statement aborts the
sync, and because the whole block goes over in a single call there is no window
in which some columns are masked and others are not. Reporting success while
leaving a copy full of plaintext behind would defeat the point of the feature.

Masking is independent of how the target's credentials were obtained. It applies
just the same when they come from
[framework auto-detection](/getting-started/) via `path` as when the `db` block
is written out in full.

## Only on the target

`anonymize` on the `origin` is rejected during validation. Masking rewrites rows
in place, and on the origin that would rewrite the system being copied from.

## What masking cannot do

The strategies cover replacing values in existing rows. Everything else stays
the job of [`post_sql`](/configuration/advanced#post-import-sql):

```yaml
target:
  anonymize:
    fe_users:
      email: email
  post_sql:
    # Drop rows that have no place in a development database at all
    - "TRUNCATE TABLE sys_refindex;"
    - "DELETE FROM sys_log WHERE tstamp < UNIX_TIMESTAMP() - 2592000;"
```

For tables that should never be copied in the first place, `ignore_table` or a
[partial sync](/configuration/advanced#partial-sync) is cheaper than copying and
then masking.

## PostgreSQL

The same rules work unchanged. The tool emits the dialect of the configured
database system, so `hash` becomes `md5(col)` and `email` becomes
`md5(col) || '@example.invalid'`. See [PostgreSQL](/configuration/postgresql).
