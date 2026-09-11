import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { translateThemeSource, hydrateLanguageFragments } from './theme-a11y.ts'

test('theme adapter covers upstream labels and rejects changed upstream contracts', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  for (const [file,label] of [['VPNavBarHamburger.vue','mobile navigation'],['VPSidebar.vue','Sidebar Navigation'],['VPDocFooter.vue','Pager'],['VPNavBarMenu.vue','Main Navigation']]) {
    const source=fs.readFileSync(path.join(root,'dist/client/theme-default/components',file),'utf8')
    const output=translateThemeSource(source,file)
    assert(output.includes(`?? '${label}'`), 'preserve the exact existing English fallback')
    assert(output.includes('rankbeamLang'))
    assert.equal(output.slice(output.indexOf('<style')), source.slice(source.indexOf('<style')), 'preserve upstream design')
    assert.throws(()=>translateThemeSource(source.replace(label,'Upstream changed'),file),/surface changed/)
  }
  assert.equal(translateThemeSource('unrelated','Other.vue'),null)
})

test('language fragment adapter guards the installed hydration contract', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  const source = fs.readFileSync(path.join(root,'dist/client/theme-default/composables/langs.js'),'utf8')
  assert(hydrateLanguageFragments(source).includes(" + (mounted.value ? hash.value : '')"))
  assert.throws(() => hydrateLanguageFragments(source.replace(' + hash.value','')), /contract changed/)
})
