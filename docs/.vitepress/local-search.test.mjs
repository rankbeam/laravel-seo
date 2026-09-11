import test from 'node:test'
import assert from 'node:assert/strict'
import MiniSearch from 'minisearch'
import fs from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { localSearchMiniSearch, turkishSearchMiniSearch } from './local-search.ts'

const defaults = { fields: ['title', 'titles', 'text'], storeFields: ['title', 'titles'] }
const defaultSearch = { fuzzy: 0.2, prefix: true, boost: { title: 4, text: 2, titles: 1 } }
const restoreFunctions = value => typeof value === 'function'
  ? new Function(`return (${value.toString()})`)()
  : Array.isArray(value) ? value.map(restoreFunctions)
  : value && typeof value === 'object' ? Object.fromEntries(Object.entries(value).map(([k, v]) => [k, restoreFunctions(v)])) : value

function client(documents, miniSearch = localSearchMiniSearch) {
  // Emulate the real boundary: VitePress builds with GLOBAL options, then
  // the browser reloads JSON with locale-specific options and serialized functions.
  const built = new MiniSearch({ ...defaults, ...localSearchMiniSearch.options })
  built.addAll(documents)
  const restored = restoreFunctions(miniSearch)
  return MiniSearch.loadJSON(JSON.stringify(built), {
    ...defaults,
    searchOptions: { ...defaultSearch, ...restored.searchOptions },
    ...restored.options,
  })
}

test('Turkish build/query casing agrees and retains original display headings', () => {
  const documents = [
    { id: '/tr/guide/installation#setup', title: 'ÇALIŞMA ve İçe Aktarma', titles: ['Türkçe', 'TARAYICI'], text: 'çalışma tarayıcı kurulum' },
    { id: '/tr/guide/robots#crawler', title: 'Tarayıcı ayarları', titles: ['Çalışma'], text: 'TARAYICI ÇALIŞMA' },
  ]
  const index = client(documents, turkishSearchMiniSearch)
  for (const [lower, upper] of [['çalışma', 'ÇALIŞMA'], ['tarayıcı', 'TARAYICI'], ['içe', 'İÇE']]) {
    assert(index.search(lower).length > 0)
    assert.deepEqual(index.search(upper), index.search(lower), `${lower}: same results, ranking and scores`)
  }
  const display = index.search('çalışma').find(r => r.id === documents[0].id)
  assert.equal(display.title, documents[0].title)
  assert.deepEqual(display.titles, documents[0].titles)
})

test('Turkish I/ı and İ/i stay distinct, including uppercase indexed text', () => {
  const index = client([
    { id: '/tr/a', title: 'I IRMAK', titles: [], text: '' },
    { id: '/tr/b', title: 'İ İNCİR', titles: [], text: '' },
    { id: '/tr/c', title: 'ı ırmak', titles: [], text: '' },
    { id: '/tr/d', title: 'i incir', titles: [], text: '' },
  ], turkishSearchMiniSearch)
  const ids = q => index.search(q, { fuzzy: false, prefix: false }).map(r => r.id).sort()
  for (const q of ['I', 'ı', 'IRMAK', 'ırmak']) assert.deepEqual(ids(q), ['/tr/a', '/tr/c'])
  for (const q of ['İ', 'i', 'İNCİR', 'incir']) assert.deepEqual(ids(q), ['/tr/b', '/tr/d'])
})

test('all previous editions retain MiniSearch results, ranking, scores and stored titles', () => {
  const texts = {
    en: ['Installation guide', 'Canonical URLs and indexing', 'Install metadata resolver. Canonical URLs and metadata.'],
    it: ['Installazione e città', 'Metadati', 'Città e risoluzione metadati'],
    de: ['Überblick Straße', 'Metadaten', 'Änderung der Metadaten'],
    fr: ['Métadonnées et résumé', 'Installation', 'Résumé des métadonnées'],
    es: ['Instalación y canónica', 'Metadatos', 'Configuración de metadatos'],
    'pt-BR': ['Instalação e canônica', 'Metadados', 'Configuração de metadados'],
    nl: ['Installatie en ideeën', 'Metadata', 'Wijzigingen en ideeën'],
    pl: ['Źródło i właściwości', 'Błędy żądania', 'Indeksowanie i przekierowania'],
  }
  const normalize = results => results.map(result => ({ ...result, match: Object.fromEntries(Object.entries(result.match).map(([term, fields]) => [term, fields.map(f => ({ searchTitle: 'title', searchTitles: 'titles', searchText: 'text' })[f] ?? f)])) }))
  for (const [locale, [title, parent, text]] of Object.entries(texts)) {
    const documents = [
      { id: `/${locale}/a`, title, titles: [parent], text },
      { id: `/${locale}/b`, title: parent, titles: [title], text: `${text} ${text}` },
      { id: `/${locale}/c`, title: 'Other section', titles: [], text: title },
    ]
    const oldBuild = new MiniSearch(defaults)
    oldBuild.addAll(documents)
    const old = MiniSearch.loadJSON(JSON.stringify(oldBuild), { ...defaults, searchOptions: defaultSearch })
    const changed = client(documents)
    for (const q of [title, title.toUpperCase(), parent, text.split(' ')[0], title.slice(0, 5), 'nonexistent']) {
      assert.deepEqual(normalize(changed.search(q)), old.search(q), `${locale}: ${q}`)
    }
  }
})

test('extractor has no mutable locale state and does not change stored fields', () => {
  const extract = restoreFunctions(localSearchMiniSearch.options.extractField)
  const tr = { id: '/tr/guide#anchor', title: 'I İ', titles: ['ÇALIŞMA'], text: 'TARAYICI' }
  const en = { ...tr, id: '/guide#anchor' }
  assert.equal(extract(tr, 'searchTitle'), 'ı i')
  assert.equal(extract(en, 'searchTitle'), 'I İ')
  assert.equal(extract({ ...tr, id: '/pl/guide#anchor' }, 'searchTitle'), 'I İ')
  assert.equal(extract(tr, 'searchTitle'), 'ı i')
  assert.equal(extract({ ...tr, id: '/tricky/guide' }, 'searchTitle'), 'I İ')
  assert.equal(extract(tr, 'title'), 'I İ')
  assert.deepEqual(extract(tr, 'titles'), ['ÇALIŞMA'])
  assert.equal(extract(tr, 'id'), tr.id)
})

test('Polish default search matches uppercase diacritics without stripping accents', () => {
  const documents = [
    { id: '/pl/a', title: 'Źródło i właściwości', titles: ['Błędy żądania'], text: 'źródło właściwości żądania błędy' },
    { id: '/pl/b', title: 'WŁAŚCIWOŚCI', titles: ['ŹRÓDŁO'], text: 'ŻĄDANIA BŁĘDY' },
  ]
  const index = client(documents)
  for (const term of ['źródło', 'właściwości', 'żądania', 'błędy']) {
    assert(index.search(term).length > 0)
    assert.deepEqual(index.search(term.toUpperCase()), index.search(term))
  }
  assert.equal(index.search('zrodlo', { fuzzy: false, prefix: false }).length, 0)
  assert.equal(index.search('źródło').find(r => r.id === '/pl/a').title, documents[0].title)
})

test('installed VitePress retains the global-index and locale-client option contract', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  const nodeFiles = fs.readdirSync(path.join(root, 'dist/node')).filter(f => f.endsWith('.js'))
  const builder = nodeFiles.map(f => fs.readFileSync(path.join(root, 'dist/node', f), 'utf8')).find(s => s.includes('function getIndexByLocale(locale)'))
  assert(builder?.includes('...options.miniSearch?.options'))
  assert(builder?.includes('storeFields: ["title", "titles"]'))
  const component = fs.readFileSync(path.join(root, 'dist/client/theme-default/components/VPLocalSearchBox.vue'), 'utf8')
  assert(component.includes('theme.value.search.options?.miniSearch?.options'))
  assert(component.includes('theme.value.search.options?.miniSearch?.searchOptions'))
  assert(component.includes('storeFields: [\'title\', \'titles\']'))
})
