# Upgrading from fibonoir/laravel-seo v1

v2.0.0 renames the package to `rankbeam/laravel-seo` and carves it down to a
focused core: meta resolution, rendering, JSON-LD, and sitemaps. The analyzer,
scanner, redirects, 404 monitor, and admin UI moved to separate packages.

## 1. Swap the package

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Update namespaces

Class names are unchanged; only the root namespace moved.

| v1 | v2 |
|---|---|
| `Fibonoir\LaravelSEO\Traits\HasSEO` | `Rankbeam\Seo\Traits\HasSEO` |
| `Fibonoir\LaravelSEO\Facades\SEO` | `Rankbeam\Seo\Facades\SEO` |
| `Fibonoir\LaravelSEO\Models\SEOMeta` | `Rankbeam\Seo\Models\SEOMeta` |
| `Fibonoir\LaravelSEO\Models\SEODefault` | `Rankbeam\Seo\Models\SEODefault` |
| `Fibonoir\LaravelSEO\Services\SEOResolver` | `Rankbeam\Seo\Services\SEOResolver` |
| `Fibonoir\LaravelSEO\Contracts\HasSEO` | `Rankbeam\Seo\Contracts\HasSEO` |
| `Fibonoir\LaravelSEO\*` (everything else) | `Rankbeam\Seo\*` |

A project-wide find-and-replace of `Fibonoir\LaravelSEO` → `Rankbeam\Seo`
covers it. The `SEO` facade alias and the `@seo` Blade directives are
unchanged.

## 3. Delete stale published files (important)

v1's `seo:install` published files into your app that will silently fight the
v2 package. Check for and remove:

- **`config/seo.php`** — if yours was published by v1 (or, worse, by
  `ralphjsmit/laravel-seo`, which v1's installer could leave behind), it
  shadows the package config and can null out `site_name` and every
  `{site_name}` template. Delete it, then re-publish:
  `php artisan vendor:publish --tag=seo-config`.
- **v1 migrations** in `database/migrations` for tables the core no longer
  owns: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`,
  `seo_analytics_cache`, `seo_internal_links_index`. Remove the migration
  files. If the tables already exist in production, drop them **before**
  installing `rankbeam/laravel-seo-pro` — Pro recreates them with a different
  schema.
- **Published stubs** under `app/` from v1's Filament 3 / Livewire / Vue /
  React flow (e.g. `app/Filament/**` SEO classes, `resources/js` SEO
  components). They reference classes that no longer exist.

The two core tables (`seo_meta`, `seo_defaults`) are schema-compatible; your
data survives the upgrade.

## 4. Removed features and where they went

| v1 feature | Where it lives now |
|---|---|
| Filament SEO form section | `rankbeam/laravel-seo-filament` (free, MIT) — `HasSEOFields` + `static::seoSection()` |
| Content analyzer (32 rules) | Not carried forward — the keyword-density/power-word rules aren't on the roadmap. Technical-SEO issue detection lives in `rankbeam/laravel-seo-pro`'s site scanner; the numeric SEO score is a Pro feature (issue-derived). |
| Sitewide scanner | `rankbeam/laravel-seo-pro` — queued scan pipeline + Filament dashboard |
| Redirect manager | `rankbeam/laravel-seo-pro` — hardened (regex validation, open-redirect guards) |
| 404 monitor | `rankbeam/laravel-seo-pro` — privacy-first (no IPs stored by default) |
| GA4 analytics, internal links | `rankbeam/laravel-seo-pro` backlog |
| `seo:install` interactive installer | Gone. Install is now: require, publish config, migrate. |

## 5. Behavior changes to review

- **`og:image` / `twitter:image` are always absolute URLs.** v1 emitted
  manually-set relative paths verbatim; v2 absolutizes them (OG spec).
- **Derived canonicals strip the query string.** Canonicals you set
  explicitly are preserved verbatim.
- **Sitemap auto-discovery defers to registered sources.** If you register a
  named source for a model (`SEO::sitemaps()->register('posts', Post::class)`),
  auto-discovery no longer also emits `sitemap-post.xml` for it.
- **JSON-LD is `JSON_HEX_*`-escaped** so `</script>` in content cannot break
  out of the script element. If you post-process the raw script output,
  expect `<`-style escapes.

## 6. Core 3 (v3): the dead analyzer columns are dropped

Core 3 removes the never-shipped content-analyzer scaffolding. A new cleanup
migration (`…_drop_dead_analyzer_columns_from_seo_meta`) drops six `seo_meta`
columns and their two indexes:

`seo_score`, `analysis_report`, `analyzed_at`, `content_snapshot`,
`content_hash`, `snapshot_at` (indexes `seo_meta_score_index`,
`seo_meta_analyzed_index`).

- **`focus_keywords` is kept** — it is core-owned and read by the Pro scanner.
- **The drop is irreversible** (`down()` is a no-op) and runs idempotently on
  fresh installs, upgrades, and partially-migrated schemas.
- **No shipped v2 code ever wrote these columns** (they were always `NULL`), so
  for virtually every install this is a pure schema cleanup with nothing to
  lose. If your *own* code called the removed public API (`HasSEO::updateScore`,
  `$meta->seo_score`, …) and populated them, that data is **dropped, not
  migrated** — the migration logs a warning with row counts when it finds any,
  so check your logs after `php artisan migrate` if that applies to you.
- **The numeric SEO score is now Pro-owned.** It lives on the Pro
  `seo_scan_results` record with a versioned rubric — run the Pro scan to
  (re)generate scores. The free core `seo:audit` reports pass/warn/fail with no
  number. See the Pro docs at `/pro/scoring`.

The removed public API (`HasSEO::getSEOScore`/`getSEOAnalysisReport`/
`needsSEOAnalysis`/`scopeWithLowSEOScore`/`scopeNeedingSEOAnalysis`,
`SEOMeta::updateScore`/`scopeLowScore`/`scopeNeedsAnalysis`,
`SEOData::$seoScore`/`$analysisReport`) was always-null and is gone — remove any
references.

## 7. Core 3: social-card defaults moved out of stored data

Core 3 corrects resolver precedence for `og:type` and `twitter:card`.
`SEOData::fromArray()` and `SEOData::fromModel()` no longer inject
`website` / `summary_large_image` into otherwise partial data. This means a
partial override such as `['robots' => 'noindex,follow']` no longer resets a
computed `og:type=article`, and an unset stored value no longer clobbers model
inference.

- Rendered pages still default `og:type` to `website`; that fallback now lives
  at render time. The resolver base configuration still supplies the default
  Twitter Card.
- Migration `2026_06_14_000002_make_seo_meta_og_type_nullable.php` makes
  `seo_meta.og_type` and `seo_meta.twitter_card` nullable with no database
  default. New rows leave them `NULL`; existing rows keep stored values such as
  `website` and are not rewritten.
- `$meta->og_type` and `$meta->twitter_card` must now be treated as `?string`.
- Models using `HasSEO` may add `getSEOAlternates(): ?array` to provide
  first-class hreflang entries to Blade, Inertia, and JSON rendering.

## 8. Known gotchas

- Laravel's default `DatabaseSeeder` uses the `WithoutModelEvents` trait,
  which disables `HasSEO`'s auto-create hook — seeded models won't get a
  `seo_meta` row until first save. Remove the trait or call `saveSEO()` in
  seeders.
- If a route-default title template already contains your brand, end the
  template with the configured `title_suffix` — the resolver then skips
  appending it again, avoiding "Brand — X | Brand".
