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

Synchronize databases by running the command:

```bash
bin/sync-tool origin target -f config.yaml
```

See `bin/sync-tool --help` for the full option reference.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under the [GPL-3.0-or-later](LICENSE) license.
