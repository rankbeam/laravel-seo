import { defineConfig } from 'vitepress'
import { generateLlmsArtifacts } from './llms'

export default defineConfig({
  title: 'Rankbeam',
  description:
    'SEO for Laravel: layered meta resolution, Open Graph / Twitter Cards, JSON-LD schema graph, and XML sitemaps.',
  lang: 'en-US',
  lastUpdated: true,
  cleanUrls: true,

  // Publish /llms.txt + a raw-Markdown copy of every page for AI answer engines.
  buildEnd: generateLlmsArtifacts,

  head: [
    ['link', { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
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
      { text: 'Why Rankbeam', link: '/guide/why-rankbeam' },
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Concepts', link: '/concepts/resolver-precedence' },
      { text: 'Filament', link: '/guide/filament' },
      { text: 'Pro', link: '/pro/installation' },
      { text: 'Blog', link: '/blog/canonical-urls-in-laravel' },
      { text: 'rankbeam.dev ↗', link: 'https://rankbeam.dev' },
      {
        text: 'v3.0',
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
          { text: 'Why Rankbeam', link: '/guide/why-rankbeam' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quickstart', link: '/guide/quickstart' },
          { text: 'Run the demo', link: '/guide/demo' },
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
          { text: 'AI crawler control', link: '/guide/ai-crawlers' },
          { text: 'Indexing guard', link: '/guide/indexing-guard' },
          { text: 'Markdown for bots', link: '/guide/markdown-for-bots' },
          { text: 'Generated OG images', link: '/guide/og-image' },
          { text: 'Free SEO audit', link: '/guide/audit' },
          { text: 'Explain resolution', link: '/guide/explain' },
          { text: 'Migrating from WordPress', link: '/guide/migrate-from-wordpress' },
          { text: 'WordPress migration runbook', link: '/guide/wordpress-migration-runbook' },
          { text: 'Migrating from other packages', link: '/guide/migrate-from-other-packages' },
          { text: 'Filament admin fields', link: '/guide/filament' },
        ],
      },
      {
        text: 'Pro',
        items: [
          { text: 'Installing Pro', link: '/pro/installation' },
          { text: 'Production setup', link: '/pro/production' },
          { text: 'Headless usage', link: '/pro/headless' },
          { text: 'Scan issues', link: '/pro/scan-issues' },
          { text: 'SEO score', link: '/pro/scoring' },
          { text: 'AI-readiness score', link: '/pro/ai-readiness-score' },
          { text: 'On-page checklist', link: '/pro/on-page-checklist' },
          { text: 'Broken-link crawler', link: '/pro/broken-links' },
          { text: 'Search Console', link: '/pro/search-console' },
          { text: 'Search Console insights', link: '/pro/search-console-insights' },
          { text: 'White-label reports', link: '/pro/reports' },
          { text: 'IndexNow', link: '/pro/indexnow' },
          { text: 'AI-bot monitor', link: '/pro/ai-bot-monitor' },
          { text: 'MCP server', link: '/pro/mcp' },
          { text: 'AI assist', link: '/pro/ai-assist' },
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
          { text: 'Answer Engine Optimization for Laravel', link: '/blog/answer-engine-optimization-for-laravel' },
          { text: 'hreflang done right in Laravel', link: '/blog/hreflang-done-right-in-laravel' },
          { text: 'We replaced Rank Math with a Laravel package', link: '/blog/replaced-rank-math-with-a-laravel-package' },
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
