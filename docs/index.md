---
layout: home

hero:
  name: php-sync-tool
  text: Database & File Synchronization for PHP
  tagline: Sync MySQL/MariaDB databases and files between systems over SSH/rsync/SFTP — with automatic framework credential detection
  image:
    src: /logo.svg
    alt: php-sync-tool
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started/
    - theme: alt
      text: View on GitHub
      link: https://github.com/konradmichalik/php-sync-tool

features:
  - icon: 🔄
    title: Database Sync
    details: Synchronize MySQL/MariaDB databases between local and remote systems in nine modes (receiver, sender, proxy, dump, import, sync).
    link: /reference/sync-modes
  - icon: 🔐
    title: Auto Credential Detection
    details: Automatically extract database credentials from TYPO3, Symfony, Drupal, WordPress, and Laravel — no manual DB config needed.
    link: /getting-started/
  - icon: 📁
    title: File Synchronization
    details: Sync files alongside (or instead of) the database with --with-files / --files-only, including exclude patterns.
    link: /configuration/file-sync
  - icon: 📦
    title: Composer or PHAR
    details: Install via Composer as a library dependency, or ship the single-file PHAR. PHP 8.2+, no Python runtime required.
    link: /getting-started/installation
  - icon: 🛡️
    title: Secure by Default
    details: SSH host-key verification is enforced, credentials are masked in logs, and protected hosts guard against accidental overwrites.
    link: /configuration/authentication
  - icon: ⚡
    title: Fast Transfers
    details: gzip-compressed mysqldump with rsync transfer by default, automatic SFTP fallback, and two-hop proxy for isolated networks.
    link: /configuration/advanced
---

## Quick Example

```bash
# Install via Composer
composer require konradmichalik/php-sync-tool

# Sync a database from production to local using a config file
bin/sync-tool -f config.yaml
```

## Supported Frameworks

Point `path` at a framework's config file and credentials are detected automatically:

| Framework | Version | Config File |
|-----------|---------|-------------|
| [TYPO3](/getting-started/typo3) | 7.6 – 12.x | `LocalConfiguration.php` / `AdditionalConfiguration.php` or `.env` |
| [TYPO3](/getting-started/typo3) | ≥ 13 | `config/system/settings.php` / `additional.php` or `.env` |
| [Symfony](/getting-started/symfony) | ≥ 3.4 | `.env` (`DATABASE_URL`) |
| [Symfony](/getting-started/symfony) | ≤ 2.8 | `parameters.yml` |
| [Drupal](/getting-started/drupal) | ≥ 8.0 | `settings.php` (via drush) |
| [WordPress](/getting-started/wordpress) | ≥ 5.0 | `wp-config.php` |
| [Laravel](/getting-started/laravel) | ≥ 4.0 | `.env` |

## About This Project

php-sync-tool is a PHP port of the Python
[`db-sync-tool`](https://github.com/konradmichalik/db-sync-tool), built for
teams that want database synchronization without a Python runtime on their
build and deploy hosts. It targets feature parity with the original while
integrating naturally into Composer-based PHP projects. See
[Coming from db-sync-tool](/getting-started/from-db-sync-tool) if you already
use the Python tool.
