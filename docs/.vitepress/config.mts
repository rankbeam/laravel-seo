import { defineConfig } from 'vitepress'
import { core, pro, reference, nav } from './navigation'
import { localizeNavigation, sidebarFor } from './layout-localization'
import { manualFor } from './manual-messages'
import { localizedThemeAccessibility } from './theme-a11y'
import { verifyLayoutSources } from './layout-validation'
import { localSearchMiniSearch, turkishSearchMiniSearch, greekSearchMiniSearch, japaneseSearchMiniSearch, simplifiedChineseSearchMiniSearch } from './local-search'
verifyLayoutSources()
import { generateLlmsArtifacts, SITE_ORIGIN } from './llms'
import { alternatePaths, editoriallyReviewed, localeInfo, translatedPaths, translationFiles, verifyTranslationSources } from './localization'

verifyTranslationSources()

const localSearchOptions = {
  miniSearch: localSearchMiniSearch,
  locales: Object.fromEntries(Object.entries(localeInfo).map(([locale, info]) => [locale, { translations: info.search }])),
}

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

export default defineConfig({
  vite: { plugins: [localizedThemeAccessibility()] },
  title: 'Rankbeam',
  description:
    'Laravel SEO package for layered metadata, canonical URLs, Open Graph, linked JSON-LD, XML sitemaps and crawler controls.',
  lang: 'en-US',
  locales: {
    root: { label: 'English', lang: 'en-US' },
    ...Object.fromEntries(Object.entries(localeInfo).map(([locale, info]) => [locale, {
      label: info.label, lang: info.lang,
      description: info.description,
      themeConfig: {
        nav: localizeNavigation(nav, locale, translatedPaths(locale)),
        sidebar: sidebarFor(locale, translatedPaths(locale)),
        outline: { label: info.outline }, docFooter: { prev: info.prev, next: info.next },
        ...info.theme,
        ...(locale === 'tr' ? { search: { provider: 'local' as const, options: { ...localSearchOptions, miniSearch: turkishSearchMiniSearch } } } : {}),
        ...(locale === 'el' ? { search: { provider: 'local' as const, options: { ...localSearchOptions, miniSearch: greekSearchMiniSearch } } } : {}),
        ...(locale === 'ja' ? {
          search: { provider: 'local' as const, options: { ...localSearchOptions, miniSearch: japaneseSearchMiniSearch } },
          // Honor the chosen edition even when the browser language is English.
          lastUpdated: { ...info.theme.lastUpdated, formatOptions: { forceLocale: true, dateStyle: 'short' as const, timeStyle: 'short' as const } },
        } : {}),
        ...(locale === 'zh-CN' ? {
          search: { provider: 'local' as const, options: { ...localSearchOptions, miniSearch: simplifiedChineseSearchMiniSearch } },
          lastUpdated: { ...info.theme.lastUpdated, formatOptions: { forceLocale: true, dateStyle: 'short' as const, timeStyle: 'short' as const } },
        } : {}),
        ...(manualFor(locale) ? { notFound: manualFor(locale).notFound } : {}),
        editLink: { pattern: 'https://github.com/rankbeam/laravel-seo/edit/master/docs/:path', text: info.edit },
        footer: { message: info.license, copyright: 'Copyright © 2026 Valentin Goxhaj — P.IVA 04936270612' },
      },
    }])),
  },
  lastUpdated: true,
  cleanUrls: true,

  // Publish /llms.txt + a raw-Markdown copy of every page for AI answer engines.
  buildEnd: generateLlmsArtifacts,

  // Emit /sitemap.xml. Setting hostname is what switches VitePress's built-in
  // generator on; it walks the built pages, honours cleanUrls, and — because
  // lastUpdated is on above — stamps each entry's <lastmod> from git. Redirect
  // routes (docs/public/_redirects) and the raw .md copies aren't VitePress
  // pages, so they never enter the sitemap.
  sitemap: { hostname: SITE_ORIGIN, transformItems: items => items.filter(item => {
    const p = item.url.replace(/^\//, '').replace(/\.html$/, '')
    if (/(^|\/)404$/.test(p)) return false
    const [locale, ...rest] = p.split('/')
    const target = (!p || p.endsWith('/') ? p + 'index' : p) + '.md'
    return !(locale in localeInfo) || translationFiles(locale).includes(target)
  }).map(item => {
    const p = item.url.replace(/^\//, '').replace(/\.html$/, '')
    const relative = (!p || p.endsWith('/') ? p + 'index' : p) + '.md'
    const alternates = alternatePaths(relative)
    return { ...item, links: alternates.length > 1
      ? [...alternates.map(a => ({ lang: a.lang, url: canonicalFor(a.path) })), { lang: 'x-default', url: canonicalFor(alternates[0].path) }]
      : [] }
  }) },

  // Per-page head that VitePress can't add statically: one absolute
  // self-canonical plus Open Graph / Twitter Card metadata, injected into each
  // page's own head. VitePress already emits <meta name="description"> from the
  // resolved page description (frontmatter first), so we don't repeat it here.
  transformPageData(pageData, { siteConfig }) {
    if (pageData.relativePath === 'nl/404.md' || pageData.relativePath === 'tr/404.md' || pageData.relativePath === 'pl/404.md' || pageData.relativePath === 'ru/404.md' || pageData.relativePath === 'cs/404.md' || pageData.relativePath === 'el/404.md' || pageData.relativePath === 'ja/404.md' || pageData.relativePath === 'zh-CN/404.md') {
      pageData.isNotFound = true
      ;(pageData.frontmatter.head ??= []).push(['meta', { name: 'robots', content: 'noindex,follow' }])
      return
    }
    // Skip virtual pages: the 404 sets isNotFound, and VitePress leaves
    // filePath empty for virtual pages. relativePath also feeds the canonical
    // below, so an empty one would wrongly resolve to the homepage — guard on
    // all three signals.
    if (pageData.isNotFound || !pageData.relativePath || !pageData.filePath) return

    const head = (pageData.frontmatter.head ??= [])
    if (pageData.frontmatter.fallbackFor) {
      head.push(['link', { rel: 'canonical', href: new URL(pageData.frontmatter.fallbackFor, SITE_ORIGIN).toString() }], ['meta', { name: 'robots', content: 'noindex,follow' }])
      return
    }
    const locale = pageData.relativePath.split('/')[0] as keyof typeof localeInfo
    if (locale in localeInfo) {
      pageData.frontmatter.translationNotice = editoriallyReviewed(pageData.relativePath) ? undefined : localeInfo[locale].review
      pageData.frontmatter.sourceLabel = localeInfo[locale].source
    }
    if (pageData.relativePath.endsWith('index.md')) {
      pageData.frontmatter.translatedPages = locale in localeInfo ? translatedPaths(locale) : []
    }
    const alternates = alternatePaths(pageData.relativePath)
    if (alternates.length > 1) {
      for (const alternate of alternates) head.push(['link', { rel: 'alternate', hreflang: alternate.lang, href: canonicalFor(alternate.path) }])
      head.push(['link', { rel: 'alternate', hreflang: 'x-default', href: canonicalFor(alternates[0].path) }])
    }
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
      ['meta', { property: 'og:image:alt', content: manualFor(locale)?.imageAlt ?? 'Rankbeam — open-core SEO infrastructure for Laravel' }],
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
      const renderLink = md.renderer.rules.link_open
      md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
        const copy = manualFor(env.relativePath?.split('/')[0])
        const label = tokens[idx].attrGet('aria-label')
        if (copy && label?.startsWith('Permalink to "')) {
          tokens[idx].attrSet('aria-label', label.replace('Permalink to', copy.permalink).replace(/\s*\{#[^}]+\}/g, ''))
        }
        return renderLink ? renderLink(tokens, idx, options, env, self) : self.renderToken(tokens, idx, options)
      }
      const renderFence = md.renderer.rules.fence!
      md.renderer.rules.fence = (tokens, idx, options, env, self) => {
        const html = renderFence(tokens, idx, options, env, self)
        const copy = manualFor(env.relativePath?.split('/')[0])
        return copy ? html.replace('title="Copy Code" class="copy"', `title="${copy.copyTitle}" aria-label="${copy.copyTitle}" class="copy"`) : html
      }
      md.renderer.rules.table_open = (tokens, idx, options, _env, self) => {
        let columns = 0
        for (let i = idx + 1; i < tokens.length; i++) {
          if (tokens[i].type === 'tr_close') break
          if (tokens[i].type === 'th_open') columns++
        }
        return `<div class="rb-table" tabindex="0" role="region" aria-label="${localeInfo[_env.relativePath?.split('/')[0] as keyof typeof localeInfo]?.table ?? 'Data table'}" style="--rb-cols:${columns || 3}">` + self.renderToken(tokens, idx, options)
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
    nav,

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
      options: localSearchOptions,
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
