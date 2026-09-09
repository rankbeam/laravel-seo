import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

test('translation source gate rejects stale, missing and misbound evidence', async () => {
  const sourceRoot = fileURLToPath(new URL('..', import.meta.url))
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'rankbeam-docs-source-'))
  const manifest = JSON.parse(fs.readFileSync(path.join(sourceRoot, '.vitepress/translation-manifest.json'), 'utf8'))
  const copy = relative => {
    const dest = path.join(temp, relative)
    fs.mkdirSync(path.dirname(dest), { recursive: true })
    fs.copyFileSync(path.join(sourceRoot, relative), dest)
  }
  try {
    copy('.vitepress/localization.ts')
    for (const [target, record] of Object.entries(manifest.pages)) { copy(target); copy(record.source) }
    const save = value => fs.writeFileSync(path.join(temp, '.vitepress/translation-manifest.json'), JSON.stringify(value))
    save(manifest)
    const { verifyTranslationSources: verify } = await import(pathToFileURL(path.join(temp, '.vitepress/localization.ts')))
    assert.doesNotThrow(verify)
    const omitted = structuredClone(manifest); delete omitted.pages['it/index.md']; save(omitted)
    assert.throws(verify, /Untracked translation: it\/index.md/)
    save(manifest)
    fs.writeFileSync(path.join(temp, 'it/untracked.md'), '# Untracked')
    assert.throws(verify, /Untracked translation: it\/untracked.md/)
    fs.unlinkSync(path.join(temp, 'it/untracked.md'))
    const wrong = structuredClone(manifest)
    wrong.pages['it/guide/installation.md'] = { ...wrong.pages['it/guide/quickstart.md'] }
    save(wrong); assert.throws(verify, /Wrong source binding/); save(manifest)
    const installation = path.join(temp, 'guide/installation.md')
    const bytes = fs.readFileSync(installation)
    fs.appendFileSync(installation, '\nChanged English instructions.\n')
    assert.throws(verify, /Stale translation/)
    fs.writeFileSync(installation, bytes.toString().replaceAll('\r\n', '\n').replaceAll('\n', '\r\n'))
    assert.doesNotThrow(verify, 'Git checkout line endings must not invalidate content hashes')
    fs.unlinkSync(path.join(temp, 'it/guide/installation.md'))
    assert.throws(verify, /target is missing/)
  } finally {
    // mkdtemp created this exact isolated directory; no project path is removed.
    fs.rmSync(temp, { recursive: true, force: true })
  }
})
