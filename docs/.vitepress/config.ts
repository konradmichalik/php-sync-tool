import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'php-sync-tool',
  description: 'Synchronize MySQL/MariaDB databases and files between systems over SSH/rsync/SFTP with framework credential auto-detection',

  base: '/php-sync-tool/',

  srcExclude: ['superpowers/**'],

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/php-sync-tool/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#777bb3' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'php-sync-tool' }],
    ['meta', { property: 'og:description', content: 'PHP database & file synchronization tool with framework credential auto-detection' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/getting-started/' },
      { text: 'Configuration', link: '/configuration/' },
      { text: 'Reference', link: '/reference/sync-modes' },
      {
        text: 'Links',
        items: [
          { text: 'Packagist', link: 'https://packagist.org/packages/konradmichalik/php-sync-tool' },
          { text: 'db-sync-tool (Python)', link: 'https://github.com/konradmichalik/db-sync-tool' },
        ]
      }
    ],

    sidebar: {
      '/getting-started/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/getting-started/' },
            { text: 'Installation', link: '/getting-started/installation' },
            { text: 'Quick Start', link: '/getting-started/quickstart' },
            { text: 'Coming from db-sync-tool', link: '/getting-started/from-db-sync-tool' }
          ]
        },
        {
          text: 'Framework Guides',
          items: [
            { text: 'TYPO3', link: '/getting-started/typo3' },
            { text: 'Symfony', link: '/getting-started/symfony' },
            { text: 'WordPress', link: '/getting-started/wordpress' },
            { text: 'Drupal', link: '/getting-started/drupal' },
            { text: 'Laravel', link: '/getting-started/laravel' }
          ]
        }
      ],
      '/configuration/': [
        {
          text: 'Configuration',
          items: [
            { text: 'Overview', link: '/configuration/' },
            { text: 'Config File Reference', link: '/configuration/reference' },
            { text: 'Authentication', link: '/configuration/authentication' },
            { text: 'Advanced Options', link: '/configuration/advanced' },
            { text: 'File Synchronization', link: '/configuration/file-sync' },
            { text: 'PostgreSQL', link: '/configuration/postgresql' },
            { text: 'Data Anonymization', link: '/configuration/anonymization' }
          ]
        }
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Sync Modes', link: '/reference/sync-modes' },
            { text: 'CLI Reference', link: '/reference/cli' }
          ]
        }
      ],
      '/development/': [
        {
          text: 'Development',
          items: [
            { text: 'Testing', link: '/development/testing' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/konradmichalik/php-sync-tool' }
    ],

    footer: {
      message: 'Released under the GPL-3.0-or-later License.',
      copyright: 'Copyright © 2026-present Konrad Michalik'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/konradmichalik/php-sync-tool/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
