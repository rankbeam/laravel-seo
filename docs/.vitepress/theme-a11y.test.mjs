import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { translateThemeSource, hydrateLanguageFragments } from './theme-a11y.ts'
import { manualFor } from './manual-messages.ts'

test('theme adapter covers upstream labels and rejects changed upstream contracts', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  for (const [file,label] of [['VPNavBarHamburger.vue','mobile navigation'],['VPNavBarExtra.vue','extra navigation'],['VPSidebar.vue','Sidebar Navigation'],['VPDocFooter.vue','Pager'],['VPNavBarMenu.vue','Main Navigation']]) {
    const source=fs.readFileSync(path.join(root,'dist/client/theme-default/components',file),'utf8')
    const output=translateThemeSource(source,file)
    assert(output.includes(`?? '${label}'`), 'preserve the exact existing English fallback')
    assert(output.includes('rankbeamLang'))
    assert.equal(output.slice(output.indexOf('<style')), source.slice(source.indexOf('<style')), 'preserve upstream design')
    assert.throws(()=>translateThemeSource(source.replace(label,'Upstream changed'),file),/surface changed/)
    assert.throws(()=>translateThemeSource(source + source,file),/surface changed/)
  }
  assert.equal(translateThemeSource('unrelated','Other.vue'),null)
})

test('tablet extra-navigation label uses reviewed locale copy and unchanged English fallback', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  const source = fs.readFileSync(path.join(root,'dist/client/theme-default/components/VPNavBarExtra.vue'),'utf8')
  const output = translateThemeSource(source,'VPNavBarExtra.vue')
  assert(output.includes(':label="rankbeamManual(rankbeamLang)?.extraNavigation ?? \'extra navigation\'"'))
  assert.equal(manualFor('nl')?.extraNavigation, 'Extra navigatie')
  assert.equal(manualFor('tr')?.extraNavigation, 'Ek gezinme')
  assert.equal(manualFor('pl')?.extraNavigation, 'Dodatkowa nawigacja')
  assert.equal(manualFor('en-US')?.extraNavigation ?? 'extra navigation', 'extra navigation')
})

test('language fragment adapter guards the installed hydration contract', () => {
  const require = createRequire(import.meta.url)
  const root = path.dirname(require.resolve('vitepress/package.json'))
  const source = fs.readFileSync(path.join(root,'dist/client/theme-default/composables/langs.js'),'utf8')
  assert(hydrateLanguageFragments(source).includes(" + (mounted.value ? hash.value : '')"))
  assert.throws(() => hydrateLanguageFragments(source.replace(' + hash.value','')), /contract changed/)
})
