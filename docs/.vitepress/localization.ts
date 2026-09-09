import fs from 'node:fs'
import path from 'node:path'
import { createHash } from 'node:crypto'
import { fileURLToPath } from 'node:url'

export const docsRoot = fileURLToPath(new URL('..', import.meta.url))
import { localeUi, scopedPaths } from './locale-ui.ts'

export const localeInfo = { it: localeUi.it, de: localeUi.de, fr: localeUi.fr, es: localeUi.es } as const

export function translatedPaths(locale: string): string[] {
  return scopedPaths.filter(p => fs.existsSync(path.join(docsRoot, locale, p + '.md')))
}
export function translationFiles(locale: string): string[] {
  const walk = (dir: string): string[] => fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const file = path.join(dir, entry.name)
    return entry.isDirectory() ? walk(file) : entry.name.endsWith('.md')
      ? [path.relative(docsRoot, file).replaceAll('\\', '/')] : []
  })
  return walk(path.join(docsRoot, locale))
}
export function englishPages(): string[] {
  const walk = (dir: string): string[] => fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    if (entry.name.startsWith('.') || entry.name === 'public' || entry.name in localeInfo) return []
    const file = path.join(dir, entry.name)
    return entry.isDirectory() ? walk(file) : entry.name.endsWith('.md') && !entry.name.includes('[') && entry.name !== '404.md'
      ? [path.relative(docsRoot, file).replaceAll('\\', '/')] : []
  })
  return walk(docsRoot)
}
export function sourceFor(relative: string): string {
  const [prefix, ...rest] = relative.split('/')
  return prefix in localeInfo ? rest.join('/') : relative
}
export function alternatePaths(relative: string): Array<{ lang: string; path: string }> {
  const source = sourceFor(relative)
  return [{ lang: 'en', path: source }, ...Object.entries(localeInfo).flatMap(([locale, info]) =>
    fs.existsSync(path.join(docsRoot, locale, source)) ? [{ lang: info.lang, path: locale + '/' + source }] : [])]
}
export function verifyTranslationSources(): void {
  const manifestFile = path.join(docsRoot, '.vitepress/translation-manifest.json')
  if (!fs.existsSync(manifestFile)) throw new Error('Translation source manifest is missing')
  const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'))
  const targets = Object.keys(localeInfo).flatMap(translationFiles)
  for (const [target, record] of Object.entries(manifest.pages) as [string, {source: string; sha256: string}][]) {
    if (!targets.includes(target)) throw new Error(`Translation manifest target is missing or outside active locales: ${target}`)
    if (record.source !== sourceFor(target)) throw new Error(`Wrong source binding for translation: ${target}`)
    const sha = createHash('sha256').update(fs.readFileSync(path.join(docsRoot, record.source), 'utf8').replaceAll('\r\n', '\n')).digest('hex')
    if (sha !== record.sha256) throw new Error(`Stale translation: ${target}; review changes to ${record.source} before updating its source hash`)
  }
  for (const target of targets) {
    if (!manifest.pages[target]) throw new Error(`Untracked translation: ${target}`)
  }
}
