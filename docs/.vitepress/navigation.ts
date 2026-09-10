export const core = [
  {
    text: 'Getting started',
    items: [
      { text: 'What is Rankbeam?', link: '/guide/why-rankbeam' },
      { text: 'Installation', link: '/guide/installation' },
      { text: 'Quickstart', link: '/guide/quickstart' },
      { text: 'Run the demo', link: '/guide/demo' },
    ],
  },
  {
    text: 'Core concepts',
    items: [
      { text: 'Resolver precedence', link: '/concepts/resolver-precedence' },
      { text: 'Explain resolution', link: '/guide/explain' },
    ],
  },
  {
    // The three rendering targets plus the admin UI — "how does the data reach
    // the DOM". Previously these sat in one 14-link dump with everything else.
    text: 'Rendering',
    items: [
      { text: 'Blade', link: '/guide/blade' },
      { text: 'Inertia & JSON APIs', link: '/guide/inertia-json' },
      { text: 'Livewire', link: '/guide/livewire' },
      { text: 'Filament admin fields', link: '/guide/filament' },
    ],
  },
  {
    text: 'Metadata & schema',
    items: [
      { text: 'Schema graph (JSON-LD)', link: '/guide/schema' },
      { text: 'Generated OG images', link: '/guide/og-image' },
      { text: 'Sitemap registry', link: '/guide/sitemaps' },
    ],
  },
  {
    text: 'Crawlers & indexing',
    items: [
      { text: 'AI crawler control', link: '/guide/ai-crawlers' },
      { text: 'Indexing guard', link: '/guide/indexing-guard' },
      { text: 'Markdown for bots', link: '/guide/markdown-for-bots' },
      { text: 'Free SEO audit', link: '/guide/audit' },
      { text: 'Translations', link: '/guide/translations' },
      { text: 'Multilingual content', link: '/guide/multilingual' },
    ],
  },
  {
    text: 'Migrating',
    items: [
      { text: 'From WordPress', link: '/guide/migrate-from-wordpress' },
      { text: 'WordPress runbook', link: '/guide/wordpress-migration-runbook' },
      { text: 'From other packages', link: '/guide/migrate-from-other-packages' },
      { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
    ],
  },
  {
    // Long-form articles on the blog. The reference pages above say what a
    // feature does; these say why and show it on a site that runs the package.
    text: 'Guides',
    items: [
      { text: 'Laravel SEO: the complete guide ↗', link: 'https://blog.rankbeam.dev/posts/laravel-seo-guide' },
      { text: 'Best Laravel SEO packages compared ↗', link: 'https://blog.rankbeam.dev/posts/laravel-seo-packages-compared' },
      { text: 'Laravel meta tags ↗', link: 'https://blog.rankbeam.dev/posts/laravel-meta-tags' },
      { text: 'Canonical URLs in Laravel ↗', link: 'https://blog.rankbeam.dev/posts/canonical-urls-in-laravel' },
      { text: 'JSON-LD schema graphs ↗', link: 'https://blog.rankbeam.dev/posts/json-ld-schema-graphs-in-laravel' },
      { text: 'Laravel sitemaps ↗', link: 'https://blog.rankbeam.dev/posts/laravel-sitemaps' },
      { text: 'Laravel SEO audit in CI ↗', link: 'https://blog.rankbeam.dev/posts/laravel-seo-audit' },
      { text: 'hreflang done right ↗', link: 'https://blog.rankbeam.dev/posts/hreflang-done-right-in-laravel' },
      { text: 'Answer engine optimization ↗', link: 'https://blog.rankbeam.dev/posts/answer-engine-optimization-for-laravel' },
      { text: 'Filament SEO fields ↗', link: 'https://blog.rankbeam.dev/posts/laravel-filament-seo' },
    ],
  },
]

export const pro = [
  {
    text: 'Pro setup',
    items: [
      { text: 'Installing Pro', link: '/pro/installation' },
      { text: 'Scan → fix → report', link: '/pro/walkthrough' },
      { text: 'Production setup', link: '/pro/production' },
      { text: 'Headless usage', link: '/pro/headless' },
    ],
  },
  {
    text: 'Scanning & scoring',
    items: [
      { text: 'Scan issues', link: '/pro/scan-issues' },
      { text: 'Scan scheduling & delta', link: '/pro/scan-scheduling' },
      { text: 'SEO score', link: '/pro/scoring' },
      { text: 'AI-readiness score', link: '/pro/ai-readiness-score' },
      { text: 'On-page checklist', link: '/pro/on-page-checklist' },
      { text: 'Broken-link crawler', link: '/pro/broken-links' },
    ],
  },
  {
    text: 'Search Console',
    items: [
      { text: 'Connecting Search Console', link: '/pro/search-console' },
      { text: 'Search Console insights', link: '/pro/search-console-insights' },
    ],
  },
  {
    text: 'Reporting & automation',
    items: [
      { text: 'White-label reports', link: '/pro/reports' },
      { text: 'IndexNow', link: '/pro/indexnow' },
      { text: 'AI-bot monitor', link: '/pro/ai-bot-monitor' },
      { text: 'MCP server', link: '/pro/mcp' },
      { text: 'AI assist', link: '/pro/ai-assist' },
    ],
  },
]

export const reference = [
  {
    text: 'Reference',
    items: [
      { text: 'Configuration', link: '/reference/configuration' },
      { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
    ],
  },
  {
    text: 'Contributing',
    items: [{ text: 'Rendering contract', link: '/contributing/rendering-contract' }],
  },
]

export const nav = [
      { text: 'Guide', link: '/guide/quickstart', activeMatch: '^/(guide|concepts)/' },
      { text: 'Pro', link: '/pro/installation', activeMatch: '^/pro/' },
      { text: 'Reference', link: '/reference/configuration', activeMatch: '^/(reference|contributing)/' },
      {
        text: 'Releases',
        items: [
          { text: 'Changelog', link: 'https://github.com/rankbeam/laravel-seo/blob/master/CHANGELOG.md' },
          { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
          { text: 'Rendering contract', link: '/contributing/rendering-contract' },
        ],
      },
      {
        text: 'Product',
        items: [
          { text: 'Free core ↗', link: 'https://rankbeam.dev/#core' },
          { text: 'Free Filament editor ↗', link: 'https://rankbeam.dev/#filament' },
          { text: 'Pro and pricing ↗', link: 'https://rankbeam.dev/#founding' },
          { text: 'AI access checker ↗', link: 'https://rankbeam.dev/can-ai-read-your-site' },
          { text: 'Blog ↗', link: 'https://blog.rankbeam.dev' },
        ],
      },
    ]
