import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Rankbeam',
  description:
    'SEO for Laravel: layered meta resolution, Open Graph / Twitter Cards, JSON-LD schema graph, and XML sitemaps.',
  lang: 'en-US',
  lastUpdated: true,
  cleanUrls: true,

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Concepts', link: '/concepts/resolver-precedence' },
      { text: 'Filament', link: '/guide/filament' },
      {
        text: 'v2.0',
        items: [
          { text: 'Changelog', link: 'https://github.com/rankbeam/laravel-seo/blob/master/CHANGELOG.md' },
          { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Getting started',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quickstart', link: '/guide/quickstart' },
        ],
      },
      {
        text: 'Concepts',
        items: [
          { text: 'Resolver precedence', link: '/concepts/resolver-precedence' },
        ],
      },
      {
        text: 'Guides',
        items: [
          { text: 'Blade', link: '/guide/blade' },
          { text: 'Inertia & JSON APIs', link: '/guide/inertia-json' },
          { text: 'Schema graph (JSON-LD)', link: '/guide/schema' },
          { text: 'Sitemap registry', link: '/guide/sitemaps' },
          { text: 'Filament admin fields', link: '/guide/filament' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/rankbeam/laravel-seo' },
    ],

    search: {
      provider: 'local',
    },

    footer: {
      message: 'rankbeam/laravel-seo is released under the MIT License.',
      copyright: 'Copyright © 2026 Valentin Goxhaj — P.IVA 04936270612',
    },
  },
})
