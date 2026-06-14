import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Rankbeam',
  description:
    'SEO for Laravel: layered meta resolution, Open Graph / Twitter Cards, JSON-LD schema graph, and XML sitemaps.',
  lang: 'en-US',
  lastUpdated: true,
  cleanUrls: true,

  head: [
    ['link', { rel: 'icon', href: '/logo.svg', type: 'image/svg+xml' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', {
      rel: 'stylesheet',
      href: 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap',
    }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Concepts', link: '/concepts/resolver-precedence' },
      { text: 'Filament', link: '/guide/filament' },
      { text: 'Pro', link: '/pro/installation' },
      { text: 'Blog', link: '/blog/canonical-urls-in-laravel' },
      { text: 'rankbeam.dev ↗', link: 'https://rankbeam.dev' },
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
          { text: 'Livewire', link: '/guide/livewire' },
          { text: 'Schema graph (JSON-LD)', link: '/guide/schema' },
          { text: 'Sitemap registry', link: '/guide/sitemaps' },
          { text: 'Free SEO audit', link: '/guide/audit' },
          { text: 'Filament admin fields', link: '/guide/filament' },
        ],
      },
      {
        text: 'Pro',
        items: [
          { text: 'Installing Pro', link: '/pro/installation' },
          { text: 'Headless usage', link: '/pro/headless' },
          { text: 'Scan issues', link: '/pro/scan-issues' },
          { text: 'SEO score', link: '/pro/scoring' },
          { text: 'AI assist (beta)', link: '/pro/ai-assist' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
        ],
      },
      {
        text: 'Blog',
        items: [
          { text: 'Canonical URLs in Laravel', link: '/blog/canonical-urls-in-laravel' },
          { text: 'JSON-LD schema graphs', link: '/blog/json-ld-schema-graphs-in-laravel' },
        ],
      },
      {
        text: 'Contributing',
        items: [
          { text: 'Rendering contract', link: '/contributing/rendering-contract' },
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
