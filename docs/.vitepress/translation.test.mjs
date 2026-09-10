import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { createHash } from 'node:crypto'
import { editoriallyReviewed, verifyTranslationRecord, verifyTranslationSources } from './localization.ts'

test('all translated sources and approved editorial reviews are current', () => {
  verifyTranslationSources()
  assert.equal(editoriallyReviewed('it/guide/blade.md'), true)
  assert.equal(editoriallyReviewed('it/guide/quickstart.md'), true)
  assert.equal(editoriallyReviewed('de/guide/quickstart.md'), true)
  assert.equal(editoriallyReviewed('fr/guide/quickstart.md'), true)
  assert.equal(editoriallyReviewed('es/guide/quickstart.md'), true)
  assert.equal(editoriallyReviewed('pt-BR/guide/quickstart.md'), false)
})

test('approval rejects changed source, changed translation and modified examples', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'rankbeam-docs-review-'))
  const hash = text => createHash('sha256').update(text).digest('hex')
  const source = '# Guide\n\n```php\nSEO::for($post);\n```\n'
  const translation = '# Guida\n\n```php\nSEO::for($post);\n```\n'
  fs.mkdirSync(path.join(root, 'guide'))
  fs.mkdirSync(path.join(root, 'it/guide'), { recursive: true })
  fs.writeFileSync(path.join(root, 'guide/sample.md'), source)
  fs.writeFileSync(path.join(root, 'it/guide/sample.md'), translation)
  const record = { source: 'guide/sample.md', sha256: hash(source), editorial_review: { status: 'approved', translation_sha256: hash(translation) } }
  try {
    verifyTranslationRecord(root, 'it/guide/sample.md', record)
    fs.writeFileSync(path.join(root, 'guide/sample.md'), source + 'New requirement\n')
    assert.throws(() => verifyTranslationRecord(root, 'it/guide/sample.md', record), /Stale translation/)
    fs.writeFileSync(path.join(root, 'guide/sample.md'), source)
    const changed = translation.replace('$post', '$article')
    fs.writeFileSync(path.join(root, 'it/guide/sample.md'), changed)
    assert.throws(() => verifyTranslationRecord(root, 'it/guide/sample.md', record), /Stale editorial review/)
    record.editorial_review.translation_sha256 = hash(changed)
    assert.throws(() => verifyTranslationRecord(root, 'it/guide/sample.md', record), /Code example drift/)
  } finally {
    fs.rmSync(root, { recursive: true, force: true })
  }
})
