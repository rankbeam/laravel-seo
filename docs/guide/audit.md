---
description: "Run php artisan seo:audit for a per-page pass/warn/fail table of what's wrong with your SEO right now — in-process, with no queue, license or network. Free, core."
---

# Free SEO audit (`seo:audit`)

`php artisan seo:audit` answers one question in one command, for free: **what's
wrong with my SEO right now?** It iterates your `HasSEO` models in-process —
**no queue, no license, no network** — and prints a per-page **pass / warn /
fail** table with a summary.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## What it checks

The audit runs only the **metadata** execution class — the checks that are
resolvable from the model and the [resolver](/concepts/resolver-precedence)
alone, with no page fetch:

| Check | Codes |
|---|---|
| Title / description present (fallback-aware) | `missing_title`, `missing_description` |
| OG image present (fallback-aware) | `missing_og_image` |
| Title / description length | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Duplicate title / description across the site | `duplicate_title`, `duplicate_description` |
| Robots conflicts & suspicious noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical format / cross-domain / shared / insecure | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Answer-readiness (AEO) — article structured data | `aeo_missing_author`, `aeo_article_missing_date` |
| Focus keyword set (opt-in) | `missing_focus_keyword` |

These are the **same issue codes** the Pro scan emits, so a finding means the
same thing here as it does there. Length reuses the editor's 60/160 thresholds
(measured against the **resolved** value, suffix included), so the audit never
contradicts the character counters in the [Filament editor](/guide/filament).

The **answer-readiness (AEO)** checks fire only when a page declares
article-type JSON-LD (`Article`, `BlogPosting`, `NewsArticle`, …) that is
missing a signal that makes the article legible in structured data — an `author`
entity (explicit authorship / provenance), or a `datePublished` / `dateModified`
(an explicit timeline). A page without an article is never flagged, so the audit
stays quiet where AEO doesn't apply.
They are advisory (notice-level) and held out of the Pro 0–100 score.

## What it does *not* check — the capability boundary

A free in-process audit can never equal the full Pro scan, and the command says
so on every run. It does **not** run:

- **Rendered-HTML checks** — `missing_h1`, `multiple_h1`, `missing_image_alt`,
  `thin_content`, `mixed_content`. These need the page's served HTML.
- **Live-canonical network checks** — `canonical_target_broken` / `_redirect` /
  `_noindex`. These need an outbound (guarded) fetch.
- **The numerical 0–100 score.** The score is a Pro feature, persisted on the
  scan-result record with a versioned rubric — see [SEO score](/pro/scoring).

Those ship in the **Pro scan** — see the full [issue registry](/pro/scan-issues).

## Choosing what to audit

By default the command audits the models listed under `seo.audit.models`,
falling back to `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Or pass models explicitly:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Options

| Option | Effect |
|---|---|
| `--model=` | A `HasSEO` model class to audit (repeatable). Overrides config. |
| `--locale=` | Resolve SEO data in this locale (defaults to the app locale). |
| `--limit=` | Max records to audit per model (`0` = all). |
| `--issues-only` | Only list pages that have at least one issue. |
| `--strict` | Exit with a non-zero status when any issue is found — for CI. |
| `--json` | Emit machine-readable JSON (pages, summary, coverage) instead of the table. |

### CI gate

`--strict` turns the audit into a build check:

```bash
php artisan seo:audit --strict
```

It exits `1` if any page warns or fails, `0` when every audited page passes.

### JSON

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Focus keywords

The `missing_focus_keyword` notice is **off by default**. It only fires once you
opt into the focus-keyword workflow:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

The Pro scan reads the **same** flag, so the audit, the scan, and the Pro
editor nag always agree. Set a page's keywords with the
[Filament focus-keyword field](/guide/filament) or
`$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## When a value isn't what you expect: `seo:explain`

`seo:audit` tells you *what's wrong*; [`seo:explain`](/guide/explain) tells you
*why a field resolved the way it did* — which layer (config / default / computed
/ explicit) set each value, what it overrode, and what post-processing (title
suffix, canonical strip, indexing guard) changed afterwards. Reach for it when an
audit finding or a rendered tag is surprising:

```bash
php artisan seo:explain "App\Models\Post" 42
```
