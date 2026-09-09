import fs from 'node:fs'
import path from 'node:path'
import assert from 'node:assert/strict'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const dist = path.join(root, '.vitepress/dist')
const manifest = JSON.parse(fs.readFileSync(path.join(root, '.vitepress/translation-manifest.json'), 'utf8'))
const read = p => fs.readFileSync(p, 'utf8').replaceAll('\r\n', '\n')
const blocks = text => [...text.matchAll(/^```[^\n]*\n([\s\S]*?)^```/gm)].map(m => m[1])
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
  const en = read(htmlPath(route(record.source)))
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
