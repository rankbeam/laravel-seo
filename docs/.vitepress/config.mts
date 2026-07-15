import { defineConfig } from 'vitepress'
import { generateLlmsArtifacts, SITE_ORIGIN } from './llms'

// Default social-share image: the brand OG card, self-hosted in docs/public so
// every card resolves to an absolute docs URL that returns 200 (1200×630 PNG).
// Built with new URL() so a trailing slash on SITE_ORIGIN can't double up.
const OG_IMAGE = new URL('og.png', SITE_ORIGIN).toString()

// Map a page's source path to its live, clean URL using the SAME operation
// VitePress's sitemap generator uses to serialise a <loc>: the relative path
// (index.md → dir root, .md dropped because cleanUrls is on) resolved against
// the origin with new URL(). That makes each page's self-canonical byte-for-byte
// the URL listed in sitemap.xml — including the homepage ('' → origin + '/') —
// no matter how the origin string is written.
function canonicalFor(relativePath: string): string {
  const p = relativePath.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '')
  return new URL(p, SITE_ORIGIN).toString()
}

// The docs share rankbeam.dev's design system (rankbeam-site/assets/css/rb.css):
// Inter, night surfaces, one action blue for CTAs, brand red reserved for the mark.
// Tokens are mapped onto VitePress's own variables in theme/custom.css.

const core = [
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
]

const pro = [
  {
    text: 'Pro setup',
    items: [
      { text: 'Installing Pro', link: '/pro/installation' },
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

const reference = [
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

export default defineConfig({
  title: 'Rankbeam',
  description:
    'SEO for Laravel: layered meta resolution, Open Graph / Twitter Cards, JSON-LD schema graph, and XML sitemaps.',
  lang: 'en-US',
  lastUpdated: true,
  cleanUrls: true,

  // Publish /llms.txt + a raw-Markdown copy of every page for AI answer engines.
  buildEnd: generateLlmsArtifacts,

  // Emit /sitemap.xml. Setting hostname is what switches VitePress's built-in
  // generator on; it walks the built pages, honours cleanUrls, and — because
  // lastUpdated is on above — stamps each entry's <lastmod> from git. Redirect
  // routes (docs/public/_redirects) and the raw .md copies aren't VitePress
  // pages, so they never enter the sitemap.
  sitemap: { hostname: SITE_ORIGIN },

  // Per-page head that VitePress can't add statically: one absolute
  // self-canonical plus Open Graph / Twitter Card metadata, injected into each
  // page's own head. VitePress already emits <meta name="description"> from the
  // resolved page description (frontmatter first), so we don't repeat it here.
  transformPageData(pageData, { siteConfig }) {
    // Skip virtual pages: the 404 sets isNotFound, and VitePress leaves
    // filePath empty for virtual pages. relativePath also feeds the canonical
    // below, so an empty one would wrongly resolve to the homepage — guard on
    // all three signals.
    if (pageData.isNotFound || !pageData.relativePath || !pageData.filePath) return

    const head = (pageData.frontmatter.head ??= [])
    // If a page already declares its own canonical it is managing its head
    // deliberately; don't add a second one (keeps exactly one canonical/page).
    if (head.some((h: any) => h[0] === 'link' && h[1]?.rel === 'canonical')) return

    const canonical = canonicalFor(pageData.relativePath)
    const siteTitle = siteConfig.site.title || 'Rankbeam'
    const title = pageData.title || siteTitle
    const description = pageData.description || siteConfig.site.description

    head.push(
      ['link', { rel: 'canonical', href: canonical }],
      ['meta', { property: 'og:type', content: 'website' }],
      ['meta', { property: 'og:site_name', content: siteTitle }],
      ['meta', { property: 'og:title', content: title }],
      ['meta', { property: 'og:description', content: description }],
      ['meta', { property: 'og:url', content: canonical }],
      ['meta', { property: 'og:image', content: OG_IMAGE }],
      ['meta', { property: 'og:image:width', content: '1200' }],
      ['meta', { property: 'og:image:height', content: '630' }],
      ['meta', { property: 'og:image:alt', content: 'Rankbeam — open-core SEO infrastructure for Laravel' }],
      ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
      ['meta', { name: 'twitter:title', content: title }],
      ['meta', { name: 'twitter:description', content: description }],
      ['meta', { name: 'twitter:image', content: OG_IMAGE }],
    )
  },

  markdown: {
    // Code sits on the console surface (#0b1020) in both colour modes, the same
    // way rankbeam.dev puts dark code cards on its light sections. Pinning both
    // slots to one dark theme keeps a snippet the same object either way.
    //
    // The theme is `-dimmed` for an accessibility reason, not a stylistic one:
    // plain `github-dark` renders comments at #6A737D, which is 3.93:1 on the
    // console — below the 4.5:1 AA floor for body-size text. (It fails on
    // GitHub's own background too, at 3.05:1.) Every token of `-dimmed` clears
    // AA here, the worst being comments at 4.89:1. Re-check with a contrast
    // pass before changing this line.
    theme: { light: 'github-dark-dimmed', dark: 'github-dark-dimmed' },

    // VitePress emits a bare <table>. Wrapping it lets a wide table scroll on
    // its own axis instead of pushing the whole page sideways at 375px.
    //
    // The wrapper also carries the column count, because a single min-width for
    // every table is wrong in both directions: the five-column issue registry
    // needs a floor to stop its prose column collapsing, while a three-column
    // table already fits and would only be forced into a needless scroll. CSS
    // turns the count into a per-table floor.
    config(md) {
      md.renderer.rules.table_open = (tokens, idx, options, _env, self) => {
        let columns = 0
        for (let i = idx + 1; i < tokens.length; i++) {
          if (tokens[i].type === 'tr_close') break
          if (tokens[i].type === 'th_open') columns++
        }
        return `<div class="rb-table" style="--rb-cols:${columns || 3}">` + self.renderToken(tokens, idx, options)
      }
      md.renderer.rules.table_close = (tokens, idx, options, _env, self) =>
        self.renderToken(tokens, idx, options) + '</div>'
    },
  },

  head: [
    ['link', { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', {
      rel: 'stylesheet',
      href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400..800&display=swap',
    }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    // Six entries, and the two outbound links are grouped behind one menu
    // instead of sitting in the row as if they were sections of this site.
    nav: [
      { text: 'Guide', link: '/guide/installation', activeMatch: '^/(guide|concepts)/' },
      { text: 'Pro', link: '/pro/installation', activeMatch: '^/pro/' },
      { text: 'Reference', link: '/reference/configuration', activeMatch: '^/(reference|contributing)/' },
      {
        text: 'v3.11.1',
        items: [
          { text: 'Changelog', link: 'https://github.com/rankbeam/laravel-seo/blob/master/CHANGELOG.md' },
          { text: 'Upgrading from v1', link: '/guide/upgrade-from-v1' },
          { text: 'Rendering contract', link: '/contributing/rendering-contract' },
        ],
      },
      {
        text: 'rankbeam.dev',
        items: [
          { text: 'Product site ↗', link: 'https://rankbeam.dev' },
          { text: 'Blog ↗', link: 'https://blog.rankbeam.dev' },
        ],
      },
    ],

    sidebar: {
      '/guide/': core,
      '/concepts/': core,
      '/pro/': pro,
      '/reference/': reference,
      '/contributing/': reference,
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/rankbeam/laravel-seo' },
    ],

    search: {
      provider: 'local',
    },

    outline: { level: [2, 3], label: 'On this page' },

    editLink: {
      pattern: 'https://github.com/rankbeam/laravel-seo/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'rankbeam/laravel-seo is released under the MIT License.',
      copyright: 'Copyright © 2026 Valentin Goxhaj — P.IVA 04936270612',
    },
  },
})
