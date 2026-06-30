# Local sync playground (Docker)

This stack simulates synchronizing a database between two hosts.

| Service | Role | SSH | Database |
|---------|------|-----|----------|
| `db1`   | origin DB (seeded: `person` = 3 rows) | — | `127.0.0.1:33861` |
| `db2`   | target DB (empty: `person` = 0 rows)  | — | `127.0.0.1:33862` |
| `www1`  | origin host (reaches `db1`) | `localhost:2211` | — |
| `www2`  | target host (reaches `db2`), drives the sync | `localhost:2212` | — |
| `proxy` | bastion host for jump-host scenarios | `localhost:2213` | — |

All web nodes mount the repository at `/app` and ship `php`, the MySQL client,
`rsync`, `gzip` and `sshpass`. SSH login is `root` / `root`.

## Start

```bash
cd docker
docker compose up -d --build
```

## Run a sync (RECEIVER: www1 → www2)

```bash
docker compose exec www2 php /app/bin/db-sync-tool \
  -f /app/docker/configs/receiver.yaml -y
```

This dumps `db` on `www1`/`db1`, transfers the gzipped dump to `www2` over
rsync, and imports it into `db2`.

## Verify

```bash
# Target started empty; after the sync it holds the origin's 3 rows.
docker compose exec db2 mysql -udb -pdb db -N -e "SELECT COUNT(*) FROM person;"
```

## Reset

```bash
docker compose down -v   # drops databases; next up re-seeds them
```
