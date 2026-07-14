/**
 * Build-time llms.txt + per-page Markdown generator.
 *
 * Practices what Rankbeam preaches: the docs site publishes an `/llms.txt`
 * index (https://llmstxt.org) plus a raw-Markdown copy of every page, so AI
 * answer engines can read the docs instead of guessing at an API.
 *
 * Called from `.vitepress/config.mts` `buildEnd(siteConfig)`.
 *
 *  - `/llms.txt`               curated index built from `themeConfig.sidebar`
 *  - `/<path>.md`              raw Markdown for every built page
 *
 * The section structure comes straight from the human sidebar (single source
 * of truth for "what the docs are"); the one-line description for each entry is
 * extracted from that page's own first paragraph, so it can never drift out of
 * sync with a hand-maintained list.
 */

import fs from 'node:fs'
import path from 'node:path'

// Canonical public origin for the docs site. Single source of truth for the
// URLs written into /llms.txt. Overridable via env so a preview/staging build
// can't silently publish production URLs; the VitePress config sets no
// `sitemap.hostname` to derive it from.
const SITE_ORIGIN = process.env.RANKBEAM_DOCS_ORIGIN ?? 'https://docs.rankbeam.dev'

type SidebarItem = { text?: string; link?: string; items?: SidebarItem[] }
type SidebarGroup = { text?: string; items?: SidebarItem[] }

/** Minimal shape of the VitePress SiteConfig fields we consume. */
interface SiteConfigLike {
  srcDir: string
  outDir: string
  pages: string[]
  site: { title?: string; description?: string; themeConfig?: any }
  logger?: { info?: (m: string) => void; warn?: (m: string) => void }
}

const strip = (s: string): string =>
  s
    .replace(/`([^`]+)`/g, '$1') // inline code
    .replace(/\*\*([^*]+)\*\*/g, '$1') // bold
    .replace(/\*([^*]+)\*/g, '$1') // italic
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1') // links -> text
    // Drop angle brackets but keep the inner text, so an inline `<head>` in
    // prose survives as "head" instead of being stripped away entirely.
    .replace(/[<>]/g, '')
    .replace(/\s+/g, ' ')
    .trim()

/**
 * Pull a one-line description from a page's source Markdown: prefer frontmatter
 * `description:`, otherwise the first prose paragraph after the H1. Returns ''
 * when nothing usable is found (caller decides how loud to be about that).
 */
function describe(mdPath: string): string {
  let raw: string
  try {
    raw = fs.readFileSync(mdPath, 'utf8')
  } catch {
    return ''
  }

  // Frontmatter: honour an explicit description, then drop the block.
  if (raw.startsWith('---')) {
    const end = raw.indexOf('\n---', 3)
    if (end !== -1) {
      const fm = raw.slice(3, end)
      const m = fm.match(/^\s*description:\s*(.+?)\s*$/m)
      if (m) return strip(m[1].replace(/^["']|["']$/g, '')).slice(0, 200)
      raw = raw.slice(end + 4)
    }
  }

  const lines = raw.split('\n')
  const para: string[] = []
  let seenHeading = false
  let inFence = false
  let inList = false
  for (const line of lines) {
    const t = line.trim()
    // Fenced code block: toggle and never treat its contents as prose.
    if (/^(```|~~~)/.test(t)) {
      inFence = !inFence
      if (para.length) break
      continue
    }
    if (inFence) continue
    if (!t) {
      inList = false
      if (para.length) break // paragraph ended
      continue
    }
    if (t.startsWith('#')) {
      seenHeading = true
      continue
    }
    // List item — and its wrapped/indented continuation lines — are not prose.
    if (/^[-*+]\s/.test(t) || /^\d+\.\s/.test(t)) {
      inList = true
      if (para.length) break
      continue
    }
    if (inList) {
      if (/^\s/.test(line)) continue // indented continuation of the list item
      inList = false
    }
    // Skip other non-prose block openers.
    if (t.startsWith(':::') || t.startsWith('|') || t.startsWith('>')) {
      if (para.length) break
      continue
    }
    if (!seenHeading) continue // wait until after the H1
    para.push(t)
  }

  const text = strip(para.join(' '))
  if (!text) return ''
  // First sentence, capped.
  const sentence = text.match(/^(.*?[.!?])(\s|$)/)
  const out = (sentence ? sentence[1] : text).trim()
  return out.length > 200 ? out.slice(0, 197).trimEnd() + '…' : out
}

/** Map a sidebar link (`/guide/installation`) to its source `.md` path. */
function linkToSource(srcDir: string, link: string): string {
  let rel = link.replace(/^\//, '').replace(/[?#].*$/, '')
  if (rel === '' || rel.endsWith('/')) rel += 'index'
  return path.join(srcDir, rel + '.md')
}

/** Map a sidebar link to its published `.md` URL. */
function linkToMdUrl(link: string): string {
  let rel = link.replace(/^\//, '').replace(/[?#].*$/, '')
  if (rel === '' || rel.endsWith('/')) rel += 'index'
  return `${SITE_ORIGIN}/${rel}.md`
}

/**
 * Copy every built page's source Markdown into the output dir at the same path
 * with a `.md` extension, so `/<path>.md` serves raw Markdown for bots.
 */
function emitPageMarkdown(cfg: SiteConfigLike): number {
  let count = 0
  for (const page of cfg.pages) {
    const src = path.join(cfg.srcDir, page)
    const dest = path.join(cfg.outDir, page)
    try {
      const md = fs.readFileSync(src, 'utf8')
      fs.mkdirSync(path.dirname(dest), { recursive: true })
      fs.writeFileSync(dest, md, 'utf8')
      count++
    } catch (e) {
      cfg.logger?.warn?.(`[llms] could not copy ${page}: ${(e as Error).message}`)
    }
  }
  return count
}

/** Build the `/llms.txt` index from the sidebar and write it to the output dir. */
function emitLlmsTxt(cfg: SiteConfigLike): { entries: number; missing: string[] } {
  // The sidebar is now path-keyed (multi-sidebar: one per docs section), not a
  // single array. Flatten it back to an ordered group list — the `seen` set
  // below dedupes the groups that more than one section shares.
  const configured = cfg.site.themeConfig?.sidebar ?? []
  const sidebar: SidebarGroup[] = Array.isArray(configured)
    ? configured
    : (Object.values(configured).flat() as SidebarGroup[])
  const title = cfg.site.title || 'Rankbeam'
  const summary = strip(cfg.site.description || '')

  const out: string[] = []
  out.push(`# ${title}`)
  out.push('')
  if (summary) {
    out.push(`> ${summary}`)
    out.push('')
  }
  out.push(
    'Documentation for the Rankbeam Laravel SEO family. Every page below is also ' +
      'available as raw Markdown at the same URL with a `.md` suffix.'
  )
  out.push('')

  const seen = new Set<string>()
  const missing: string[] = []
  let entries = 0

  const walk = (items: SidebarItem[], bullets: string[]) => {
    for (const it of items) {
      if (it.link && !/^https?:\/\//.test(it.link)) {
        const key = it.link.replace(/[?#].*$/, '')
        if (!seen.has(key)) {
          seen.add(key)
          const desc = describe(linkToSource(cfg.srcDir, it.link))
          if (!desc) missing.push(it.link)
          const label = it.text || key
          bullets.push(
            `- [${label}](${linkToMdUrl(it.link)})${desc ? `: ${desc}` : ''}`
          )
          entries++
        }
      }
      if (it.items?.length) walk(it.items, bullets)
    }
  }

  for (const group of sidebar) {
    const bullets: string[] = []
    walk(group.items ?? [], bullets)
    if (!bullets.length) continue
    out.push(`## ${group.text || 'Docs'}`)
    out.push('')
    out.push(...bullets)
    out.push('')
  }

  fs.mkdirSync(cfg.outDir, { recursive: true })
  fs.writeFileSync(path.join(cfg.outDir, 'llms.txt'), out.join('\n').trimEnd() + '\n', 'utf8')
  return { entries, missing }
}

/** Entry point wired into VitePress `buildEnd`. */
export async function generateLlmsArtifacts(siteConfig: SiteConfigLike): Promise<void> {
  const log = siteConfig.logger?.info ?? console.log
  const pages = emitPageMarkdown(siteConfig)
  const { entries, missing } = emitLlmsTxt(siteConfig)
  log(
    `[llms] wrote /llms.txt (${entries} entries) + ${pages} per-page .md file(s) to ${path.relative(
      process.cwd(),
      siteConfig.outDir
    )}`
  )
  if (missing.length) {
    siteConfig.logger?.warn?.(
      `[llms] ${missing.length} sidebar page(s) had no extractable description: ${missing.join(', ')}`
    )
  }
}
