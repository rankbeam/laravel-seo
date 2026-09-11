import fs from 'node:fs'
import { localeInfo } from './localization.ts'
import { createHash } from 'node:crypto'
import { fileURLToPath } from 'node:url'

export function verifyLayoutSources(root = fileURLToPath(new URL('.', import.meta.url))) {
  const messages = JSON.parse(fs.readFileSync(root + 'layout-messages.json', 'utf8'))
  if (JSON.stringify(Object.keys(messages).sort()) !== JSON.stringify(['en', ...Object.keys(localeInfo)].sort())) throw new Error('Incomplete layout locale set')
  const review = JSON.parse(fs.readFileSync(root + 'layout-review.json', 'utf8'))
  const sources = ['navigation.ts', 'home-content.ts', 'theme/components/Home.vue']
  const digest = createHash('sha256')
  for (const source of sources) digest.update(fs.readFileSync(root + source, 'utf8').replaceAll('\r\n', '\n'))
  digest.update(JSON.stringify(messages.en))
  const hash = digest.digest('hex')
  const keys = Object.keys(messages.en).sort()
  for (const [locale, catalog] of Object.entries(messages)) {
    if (!catalog || typeof catalog !== 'object' || JSON.stringify(Object.keys(catalog).sort()) !== JSON.stringify(keys) || Object.values(catalog).some(v => typeof v !== 'string' || !v.trim())) throw new Error(`Incomplete layout translation: ${locale}`)
    if (locale !== 'en' && review[locale]?.source_sha256 !== hash) throw new Error(`Stale layout translation: ${locale}; review the English layout before updating its source hash`)
  }
}
