import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { verifyLayoutSources } from './layout-validation.ts'
import { localizeNavigation, destination, sidebarFor } from './layout-localization.ts'
import { core, pro, reference, nav } from './navigation.ts'
import { translatedPaths, englishPages } from './localization.ts'
const locales = ['it', 'de', 'fr', 'es', 'pt-BR']

test('all locales retain the English navigation groups, destinations and order', () => {
  function compare(source, result, locale) {
    assert.equal(result.length, source.length)
    source.forEach((item, i) => {
      assert(result[i].text)
      if (item.link?.startsWith('/')) assert.equal(result[i].link, `/${locale}${item.link}`)
      if (item.activeMatch) assert(new RegExp(result[i].activeMatch).test(`/${locale}${item.link}`))
      if (item.items) compare(item.items, result[i].items, locale)
    })
  }
  for (const locale of locales) {
    const pages = translatedPaths(locale), sidebar = sidebarFor(locale, pages)
    compare(nav, localizeNavigation(nav, locale, pages), locale)
    for (const [prefix, groups] of Object.entries({guide: core, concepts: core, pro, reference, contributing: reference})) compare(groups, sidebar[`/${locale}/${prefix}/`], locale)
  }
})
test('English cues disappear when a guide gains a translation; published site/blog links stay local', () => {
  for (const locale of locales) {
    assert.deepEqual(destination('/pro/installation', locale, []), {link:`/${locale}/pro/installation`,english:true})
    assert.equal(destination('/pro/installation',locale,['pro/installation']).english,false)
    assert.equal(destination('https://rankbeam.dev/#founding',locale,[]).link,`https://rankbeam.dev/${locale}/#founding`)
  }
  assert.equal(destination('https://blog.rankbeam.dev/posts/laravel-meta-tags','it',[]).link,'https://blog.rankbeam.dev/it/articoli/meta-tag-laravel')
  assert.equal(destination('https://blog.rankbeam.dev/posts/laravel-meta-tags','de',[]).english,true)
  assert.equal(englishPages().length,43)
  for (const locale of locales) assert(translatedPaths(locale).length >= 10)
})
test('layout review rejects changed source and incomplete or stale catalogs', () => {
  verifyLayoutSources()
  const source=fileURLToPath(new URL('.',import.meta.url)), root=fs.mkdtempSync(path.join(os.tmpdir(),'rankbeam-docs-layout-'))+path.sep
  const files=['layout-messages.json','layout-review.json','navigation.ts','home-content.ts','theme/components/Home.vue']
  try {
    const reset=()=>{for(const f of files){fs.mkdirSync(path.dirname(root+f),{recursive:true});fs.copyFileSync(source+f,root+f)}}
    reset();verifyLayoutSources(root)
    fs.appendFileSync(root+'home-content.ts','\n// source change');assert.throws(()=>verifyLayoutSources(root),/Stale layout/)
    for(const locale of locales){
      reset();const m=JSON.parse(fs.readFileSync(root+'layout-messages.json'));delete m[locale]['Quickstart'];fs.writeFileSync(root+'layout-messages.json',JSON.stringify(m));assert.throws(()=>verifyLayoutSources(root),/Incomplete layout/)
      reset();const review=JSON.parse(fs.readFileSync(root+'layout-review.json'));review[locale].source_sha256='stale';fs.writeFileSync(root+'layout-review.json',JSON.stringify(review));assert.throws(()=>verifyLayoutSources(root),/Stale layout/)
    }
  } finally {
    assert(path.resolve(root).startsWith(path.resolve(os.tmpdir())+path.sep+'rankbeam-docs-layout-'))
    fs.rmSync(root,{recursive:true,force:true})
  }
})
