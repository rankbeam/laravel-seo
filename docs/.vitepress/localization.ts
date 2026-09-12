import fs from 'node:fs'
import path from 'node:path'
import { createHash } from 'node:crypto'
import { fileURLToPath } from 'node:url'

export const docsRoot = fileURLToPath(new URL('..', import.meta.url))
import { localeUi } from './locale-ui.ts'

export const localeInfo = { it: localeUi.it, de: localeUi.de, fr: localeUi.fr, es: localeUi.es, 'pt-BR': localeUi['pt-BR'], nl: localeUi.nl, tr: localeUi.tr, pl: localeUi.pl, ru: localeUi.ru, cs: localeUi.cs, el: localeUi.el, ja: localeUi.ja } as const

export function translatedPaths(locale: string): string[] {
  return englishPages().filter(p => p !== 'index.md' && fs.existsSync(path.join(docsRoot, locale, p))).map(p => p.replace(/\.md$/, ''))
}
export function translationFiles(locale: string): string[] {
  const walk = (dir: string): string[] => fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const file = path.join(dir, entry.name)
    return entry.isDirectory() ? walk(file) : entry.name.endsWith('.md') && entry.name !== '404.md'
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
type TranslationRecord = { source: string; sha256: string; editorial_review?: { status: string; translation_sha256?: string } }
const digest = (file: string) => createHash('sha256').update(fs.readFileSync(file, 'utf8').replaceAll('\r\n', '\n')).digest('hex')

export function editoriallyReviewed(target: string): boolean {
  const manifest = JSON.parse(fs.readFileSync(path.join(docsRoot, '.vitepress/translation-manifest.json'), 'utf8'))
  const record: TranslationRecord | undefined = manifest.pages[target]
  return record?.editorial_review?.status === 'approved'
    && record.sha256 === digest(path.join(docsRoot, record.source))
    && record.editorial_review.translation_sha256 === digest(path.join(docsRoot, target))
}

export function verifyTranslationRecord(root: string, target: string, record: TranslationRecord): void {
  if (record.source !== sourceFor(target)) throw new Error(`Wrong source binding for translation: ${target}`)
  if (digest(path.join(root, record.source)) !== record.sha256) throw new Error(`Stale translation: ${target}; review changes to ${record.source} before updating its source hash`)
  if (record.editorial_review?.status === 'approved') {
    if (record.editorial_review.translation_sha256 !== digest(path.join(root, target))) throw new Error(`Stale editorial review: ${target}; review the translated content before updating its review hash`)
    if (record.source !== 'index.md') {
      const fences = (file: string) => [...fs.readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n').matchAll(/^([ \t]*)```[^\n]*\n[\s\S]*?^\1```[^\n]*/gm)].map(m => m[0])
      if (JSON.stringify(fences(record.source)) !== JSON.stringify(fences(target))) throw new Error(`Code example drift: ${target}`)
    }
  }
}

export function verifyTranslationSources(): void {
  const manifestFile = path.join(docsRoot, '.vitepress/translation-manifest.json')
  if (!fs.existsSync(manifestFile)) throw new Error('Translation source manifest is missing')
  const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'))
  const targets = Object.keys(localeInfo).flatMap(translationFiles)
  for (const [target, record] of Object.entries(manifest.pages) as [string, TranslationRecord][]) {
    if (!targets.includes(target)) throw new Error(`Translation manifest target is missing or outside active locales: ${target}`)
    verifyTranslationRecord(docsRoot, target, record)
  }
  for (const target of targets) {
    if (!manifest.pages[target]) throw new Error(`Untracked translation: ${target}`)
  }
}
