import fs from 'node:fs'
import path from 'node:path'
import assert from 'node:assert/strict'
import { fileURLToPath } from 'node:url'
import { createMarkdownRenderer } from 'vitepress'

const root = fileURLToPath(new URL('..', import.meta.url))
const dist = path.join(root, '.vitepress/dist')
const manifest = JSON.parse(fs.readFileSync(path.join(root, '.vitepress/translation-manifest.json'), 'utf8'))
const read = p => fs.readFileSync(p, 'utf8').replaceAll('\r\n', '\n')
const markdown = await createMarkdownRenderer(root)
const blocks = text => markdown.parse(text, {}).filter(token => token.type === 'fence').map(token => token.content)
const route = p => '/' + p.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '')
const htmlPath = url => path.join(dist, (url.endsWith('/') ? url + 'index' : url) + '.html')
const attrs = tag => Object.fromEntries([...tag.matchAll(/([\w-]+)="([^"]*)"/g)].map(m => [m[1], m[2]]))
const links = html => [...html.matchAll(/<link\b[^>]*>/g)].map(m => attrs(m[0]))
let examples = 0
for (const [target, record] of Object.entries(manifest.pages)) {
  const translated = read(path.join(root, target))
  const source = read(path.join(root, record.source))
  if (!target.endsWith('/index.md')) {
    assert.deepEqual(blocks(translated), blocks(source), `Code examples changed in ${target}`)
    examples += blocks(source).length
  }
  const html = read(htmlPath(route(target)))
  for (const match of html.matchAll(/<a\b[^>]*href="([^"]*)"/g)) {
    const url = new URL(match[1].replaceAll('&amp;', '&'), 'https://docs.rankbeam.dev' + route(target))
    if (url.origin !== 'https://docs.rankbeam.dev') continue
    const isAsset = /\.[a-z\d]+$/i.test(url.pathname) && !url.pathname.endsWith('.html')
    const destination = isAsset ? path.join(dist, decodeURI(url.pathname)) : htmlPath(decodeURI(url.pathname).replace(/\.html$/, ''))
    assert(fs.existsSync(destination), `Missing link ${target} -> ${url.pathname}`)
    if (url.hash && !isAsset) assert(read(destination).includes(`id="${decodeURIComponent(url.hash.slice(1))}"`), `Missing anchor ${target} -> ${url.pathname}${url.hash}`)
  }
  const en = read(htmlPath(route(record.source)))
  if (!target.endsWith('/index.md')) {
    const headings = content => [...content.matchAll(/<h[23]\b[^>]*id="([^"]+)"/g)].map(m => m[1])
    const localizedIds = headings(html)
    for (const id of headings(en)) assert(localizedIds.includes(id), `Missing source heading anchor ${target}#${id}`)
  }
  const translatedLinks = links(html)
  assert.equal(translatedLinks.filter(l => l.rel === 'canonical').length, 1)
  assert.equal(translatedLinks.find(l => l.rel === 'canonical').href, 'https://docs.rankbeam.dev' + route(target))
  assert.deepEqual(translatedLinks.filter(l => l.hreflang), links(en).filter(l => l.hreflang), `Nonreciprocal alternates for ${target}`)
}
const sitemap = read(path.join(dist, 'sitemap.xml'))
const locations = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1])
const advertised = [...sitemap.matchAll(/<xhtml:link\b[^>]*>/g)].map(m => attrs(m[0]).href)
for (const url of [...locations, ...advertised]) {
  const html = read(htmlPath(new URL(url).pathname))
  assert(!/<meta[^>]+name="robots"[^>]+content="noindex/.test(html), `Sitemap advertises fallback ${url}`)
}
const fallback = read(htmlPath('/it/pro/installation'))
assert.match(fallback, /noindex,follow/)
assert(!links(fallback).some(l => l.hreflang), 'Fallback must not advertise a translation')
assert(!locations.includes('https://docs.rankbeam.dev/it/pro/installation'))
assert(read(path.join(dist, 'llms.txt')).includes('/it/guide/quickstart.md'))
assert(!read(path.join(dist, 'llms.txt')).includes('/it/pro/installation.md'))
console.log(`${Object.keys(manifest.pages).length} localized pages; ${examples} unchanged code blocks; reciprocal head alternatives; ${locations.length} sitemap URLs and all advertised alternatives exclude fallbacks`)
