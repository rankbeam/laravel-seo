import messages from './layout-messages.json' with { type: 'json' }
import { core, pro, reference } from './navigation.ts'

export function message(locale: string, key: string): string {
  const catalog = messages[locale as keyof typeof messages] as Record<string, string> | undefined
  const value = (catalog ?? messages.en as Record<string, string>)[key]
  if (!value) throw new Error(`Missing layout message: ${locale}:${key}`)
  return value
}

// Published article routes only; editorial review is tracked separately.
const italianArticles: Record<string, string> = {
  'laravel-seo-guide': 'guida-seo-laravel',
  'laravel-meta-tags': 'meta-tag-laravel',
  'laravel-sitemaps': 'sitemap-laravel',
  'canonical-urls-in-laravel': 'url-canonical-in-laravel',
  'json-ld-schema-graphs-in-laravel': 'json-ld-schema-graph-in-laravel',
  'laravel-seo-audit': 'audit-seo-laravel',
}
export function destination(link: string, locale: string, translated: string[]) {
  if (locale === 'en') return { link, english: false }
  if (link.startsWith('/')) return { link: `/${locale}${link}`, english: !translated.includes(link.slice(1)) }
  const url = new URL(link)
  if (url.hostname === 'rankbeam.dev') {
    url.pathname = `/${locale}${url.pathname}`
    return { link: url.href, english: false }
  }
  if (locale === 'it' && url.hostname === 'blog.rankbeam.dev') {
    if (url.pathname === '/') return { link: 'https://blog.rankbeam.dev/it', english: false }
    const slug = url.pathname.replace('/posts/', '')
    if (italianArticles[slug]) return { link: `https://blog.rankbeam.dev/it/articoli/${italianArticles[slug]}${url.hash}`, english: false }
  }
  return { link, english: true }
}

type Item = { text: string; link?: string; activeMatch?: string; items?: Item[] }
export function localizeNavigation(items: Item[], locale: string, translated: string[]): Item[] {
  return items.map(item => {
    const target = item.link ? destination(item.link, locale, translated) : null
    return { ...item, text: message(locale, item.text) + (target?.english ? ` (${message(locale, 'English')})` : ''),
      ...(target ? { link: target.link } : {}),
      ...(item.activeMatch ? { activeMatch: item.activeMatch.replace('^/', `^/${locale}/`) } : {}),
      ...(item.items ? { items: localizeNavigation(item.items, locale, translated) } : {}),
    }
  })
}
export function sidebarFor(locale: string, translated: string[]) {
  const result: Record<string, Item[]> = {}
  for (const [prefix, groups] of Object.entries({ guide: core, concepts: core, pro, reference, contributing: reference })) {
    result[`/${locale}/${prefix}/`] = localizeNavigation(groups, locale, translated)
  }
  return result
}
