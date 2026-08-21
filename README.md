# php-sync-tool

[![Tests](https://github.com/konradmichalik/php-sync-tool/actions/workflows/tests.yml/badge.svg)](https://github.com/konradmichalik/php-sync-tool/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/konradmichalik/php-sync-tool)](https://packagist.org/packages/konradmichalik/php-sync-tool)
[![License](https://img.shields.io/github/license/konradmichalik/php-sync-tool)](LICENSE)

PHP port of [`db-sync-tool`](https://github.com/jackd248/db-sync-tool) — synchronize databases
(and optionally files) between local and remote systems over SSH/rsync/SFTP, with framework
credential auto-detection for **TYPO3, Symfony, Drupal, WordPress and Laravel**.

📖 **[Read the documentation](https://konradmichalik.github.io/php-sync-tool/)**

## ✨ Features

- Synchronize databases between local and remote systems
- MySQL, MariaDB and PostgreSQL
- Declarative data anonymization for GDPR-safe copies
- Named environments with `init`, `pull` and `push`
- Optional file synchronization over SSH/rsync/SFTP
- Framework credential auto-detection for TYPO3, Symfony, Drupal, WordPress and Laravel

## 🔥 Installation

```bash
composer require konradmichalik/php-sync-tool
```

Or use the standalone PHAR build (`sync-tool.phar`), see
[Installation](https://konradmichalik.github.io/php-sync-tool/getting-started/installation).

> [!IMPORTANT]
> Needs PHP **8.2+**, the client for your database system on the executing host(s)
> (`mysql`/`mysqldump`, or `psql`/`pg_dump` for PostgreSQL), `gzip`/`gunzip`, and `rsync`
> (optionally `sshpass` for password-based rsync).

## 🚀 Quick start

```bash
bin/sync-tool init            # writes .sync-tool/ for this project
bin/sync-tool pull production # that environment's database into this project
```

Called with no arguments on a terminal, the tool offers every sync it finds and runs the one
you pick. See the [CLI Reference](https://konradmichalik.github.io/php-sync-tool/reference/cli)
for explicit configuration files, named hosts and the full option list.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under the [GPL-3.0-or-later](LICENSE) license.
