type Item = { text?: string; link?: string; items?: Item[] }
type Theme = { nav?: Item[]; sidebar?: Record<string, Item[]> | Item[] }
type Locale = { label?: string; lang?: string }
type Copy = { noScriptMenu: string; noScriptHelp: string; mainNavigation: string; language: string }
const escape = (value: string) => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;')
const itemsHtml = (items: Item[]): string => '<ul>' + items.map(item => '<li>' + (item.link
  ? `<a href="${escape(item.link)}">${escape(item.text ?? '')}</a>`
  : `<strong>${escape(item.text ?? '')}</strong>`) + (item.items ? itemsHtml(item.items) : '') + '</li>').join('') + '</ul>'

// Uses the same localized navigation objects as the JavaScript theme.
export function noScriptNavigation(theme: Theme, locales: Record<string, Locale>, relativePath: string, locale: string, copy: Copy): string {
  const current = '/' + relativePath.replace(/\.md$/, '').replace(/index$/, '')
  const sidebar = Array.isArray(theme.sidebar) ? theme.sidebar : Object.entries(theme.sidebar ?? {})
    .sort(([a], [b]) => b.length - a.length).find(([prefix]) => current.startsWith(prefix))?.[1] ?? []
  const source = relativePath.startsWith(locale + '/') ? relativePath.slice(locale.length + 1) : relativePath
  const pathname = source === '404.md' ? '' : source.replace(/\.md$/, '').replace(/(^|\/)index$/, '$1')
  const languageLinks = Object.entries(locales).map(([key, value]) => {
    const prefix = key === 'root' ? '' : key + '/'
    return `<li><a href="/${escape(prefix + pathname)}" lang="${escape(value.lang ?? key)}"${key === locale ? ' aria-current="page"' : ''}>${escape(value.label ?? key)}</a></li>`
  }).join('')
  return `<style>html[lang="${escape(locale)}"] :is(.VPNavBarSearch,.VPNavBarHamburger,.VPNavBarMenu,.VPNavBarTranslations,.VPNavBarAppearance,.VPLocalNav,button.copy){display:none!important}</style>`
    + `<details class="rb-noscript-menu"><summary>${escape(copy.noScriptMenu)}</summary><nav aria-label="${escape(copy.mainNavigation)}"><p>${escape(copy.noScriptHelp)}</p>${itemsHtml(theme.nav ?? [])}${itemsHtml(sidebar)}<strong>${escape(copy.language)}</strong><ul>${languageLinks}</ul></nav></details>`
}
