<div align="center">

# php-sync-tool

</div>

PHP port of [`db-sync-tool`](https://github.com/jackd248/db-sync-tool) — synchronize databases
(and optionally files) between local and remote systems over SSH/rsync/SFTP, with framework
credential auto-detection for **TYPO3, Symfony, Drupal, WordPress and Laravel**.

📖 **[Read the documentation](https://konradmichalik.github.io/php-sync-tool/)**

## ✨ Features

* Synchronize databases between local and remote systems
* MySQL, MariaDB and PostgreSQL
* Declarative data anonymization for GDPR-safe copies
* Named environments with `init`, `pull` and `push`
* Optional file synchronization over SSH/rsync/SFTP
* Framework credential auto-detection for TYPO3, Symfony, Drupal, WordPress and Laravel

## 🔥 Installation

```bash
composer require konradmichalik/php-sync-tool
```

Or use the standalone PHAR build (`sync-tool.phar`).

### Requirements

- PHP **8.2+**
- the client for your database system on the executing host(s): `mysql` / `mysqldump`,
  or `psql` / `pg_dump` for PostgreSQL
- `gzip`/`gunzip` on the executing host(s)
- `rsync` (default transfer) and optionally `sshpass` for password-based rsync

## 📊 Usage

Set the project up once, then sync by name:

```bash
bin/sync-tool init            # writes .sync-tool/ for this project
bin/sync-tool pull production # that environment's database into this project
bin/sync-tool push staging    # this project's database into that environment
```

Called with no arguments on a terminal, the tool offers every sync it finds and
runs the one you pick.

Explicit configuration works as before:

```bash
bin/sync-tool origin target -f config.yaml
```

See `bin/sync-tool --help` for the full option reference.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under the [GPL-3.0-or-later](LICENSE) license.
