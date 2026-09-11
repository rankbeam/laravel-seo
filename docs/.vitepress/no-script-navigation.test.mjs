import test from 'node:test'
import assert from 'node:assert/strict'
import { noScriptNavigation } from './no-script-navigation.ts'
import { manualFor } from './manual-messages.ts'

test('Polish no-script navigation retains same-page language links and localized recovery copy', () => {
  const copy = manualFor('pl')
  const locales = { root: { label: 'English', lang: 'en-US' }, tr: { label: 'Türkçe', lang: 'tr' }, pl: { label: 'Polski', lang: 'pl' } }
  const theme = { nav: [{ text: 'Przewodnik', link: '/pl/guide/quickstart' }], sidebar: { '/pl/pro/': [{ text: 'Raporty', link: '/pl/pro/reports' }] } }
  const html = noScriptNavigation(theme, locales, 'pl/pro/reports.md', 'pl', copy)
  assert(html.includes('aria-label="Nawigacja główna"'))
  assert(html.includes(copy.noScriptHelp))
  assert(html.includes('href="/pl/pro/reports" lang="pl" aria-current="page"'))
  assert(html.includes('href="/tr/pro/reports" lang="tr"'))
  assert(html.includes('href="/pro/reports" lang="en-US"'))
  assert(noScriptNavigation(theme, locales, 'pl/404.md', 'pl', copy).includes('href="/pl/" lang="pl"'))
})
test('no-script navigation keeps matching groups, current-page locales and escaped labels', () => {
  const copy = { noScriptMenu: 'Menu', noScriptHelp: 'Help', mainNavigation: 'Navigatie', language: 'Taal' }
  const locales = { root: { label: 'English', lang: 'en-US' }, nl: { label: 'Nederlands', lang: 'nl' } }
  const theme = { nav: [{ text: 'Gids', link: '/nl/guide/quickstart' }], sidebar: {
    '/nl/guide/': [{ text: 'Core', items: [{ text: '<code> & uitleg', link: '/nl/guide/explain' }] }],
    '/nl/pro/': [{ text: 'Pro', link: '/nl/pro/installation' }],
  } }
  const html = noScriptNavigation(theme, locales, 'nl/guide/explain.md', 'nl', copy)
  assert(html.includes('&lt;code&gt; &amp; uitleg'))
  assert(html.includes('href="/guide/explain" lang="en-US"'))
  assert(html.includes('href="/nl/guide/explain" lang="nl" aria-current="page"'))
  assert(!html.includes('href="/nl/pro/installation"'))
  assert(noScriptNavigation(theme, locales, 'nl/404.md', 'nl', copy).includes('href="/nl/" lang="nl"'))
})
