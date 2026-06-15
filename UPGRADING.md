# Upgrading `rankbeam/laravel-seo`

This guide covers the `fibonoir/laravel-seo` v1 → v2 rename/carve-down
(sections 1–5) and the v2 → **v3 (Core 3)** breaking changes (sections 6
onward). See [CHANGELOG.md](CHANGELOG.md) for full per-release detail.

v2.0.0 renames the package to `rankbeam/laravel-seo` and carves it down to a
focused core: meta resolution, rendering, JSON-LD, and sitemaps. The analyzer,
scanner, redirects, 404 monitor, and admin UI moved to separate packages.

## 0. Release order (Core 3 + Pro 2 + Filament)

The three packages share one dependency bridge. If you also run
`rankbeam/laravel-seo-pro` and/or `rankbeam/laravel-seo-filament`, upgrade /
publish in this order so a registry user is never left with an unsatisfiable
middle:

1. **`rankbeam/laravel-seo-filament`** — already requires core `^2.0 || ^3.0`
   (publish *before* Core 3 so stable users can resolve the supported
   combination).
2. **`rankbeam/laravel-seo` 3.0** (this package).
3. **`rankbeam/laravel-seo-pro` 2.0** — requires core `^2.0 || ^3.0`; see the
   Pro `UPGRADING.md` for its own breaking changes (the `robots_conflict`
   issue-code split + data migration).

Nothing pins an *exact* `^3.0`, so Core 2 installs keep working with the widened
Filament and Pro 2 — the bridge has no broken middle.

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

## 9. Core 3 (breaking): `SEO::toArray()` / `forInertia()` output shape

The rendered output (HTML, array, and Inertia head) no longer includes:

- a **`robots`** entry when the resolved directive equals `seo.default_robots`
  (a redundant `index,follow` is omitted — its absence already means
  index,follow to a crawler);
- an **empty `canonical`**;
- **empty / null meta entries** (empty-content meta tags).

A directive that *deviates* from the default (`noindex`, `nofollow`,
`max-snippet:-1`, …) is still emitted, verbatim. The granular `@seoRobots`
directive is unaffected (it always renders).

This is **breaking for programmatic consumers**: if you read `SEO::toArray()` /
`SEO::forInertia()` and relied on those keys/elements always being present
(an API payload, a custom renderer, an Inertia head builder), guard for their
absence. To restore the always-emit robots behaviour:

```php
// config/seo.php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', true),
],
```

## 10. Core 3 (behaviour): importer fills empty fields only

`php artisan seo:import-from` now **only fills empty `seo_meta` fields by
default** — an import never overwrites a value you hand-edited. This is the
documented contract; it is called out here because earlier docs were ambiguous
about it.

- Default: an imported attribute whose target column already holds a non-empty
  value is skipped; only empty (`null` / `''`) columns are filled.
- Pass `--overwrite` to replace existing non-empty values with the imported
  ones.

```bash
php artisan seo:import-from ralphjsmit              # fill-empty-only (safe)
php artisan seo:import-from ralphjsmit --overwrite  # replace existing values
```

## 11. Core 3 (no action required): config is deep-merged

`SEOServiceProvider` now deep-merges the package defaults *under* your published
`config/seo.php` (replacing Laravel's shallow `mergeConfigFrom`). New nested
keys a release adds now reach your resolved config and their env vars take
effect **without re-publishing**; every value you set is preserved (including
falsey leaves and replaced lists).

New nested `sitemap` keys you now get for free (both off by default, no output
change when off):

```php
// config/seo.php → 'sitemap' => [ ... ]
'images'     => env('SEO_SITEMAP_IMAGES', false),     // image-sitemap entries
'alternates' => env('SEO_SITEMAP_ALTERNATES', false), // hreflang xhtml:link entries
```

If you previously hand-added these keys to work around the old shallow merge,
leave them — your values win.

## 12. Planned for Core 4 (behavior changes)

These are **not** active yet — they are documented now so the change is never a
surprise. Each ships behind a flag you can flip ahead of time.

### `seo.resolver.blank_is_unset` default flips to `true`

A persisted blank (`''` / whitespace-only) value in a `seo_meta` string column
is an *explicit* value, and the resolver merges with "last non-null wins" — so a
blank silently **overrides** the computed fallback / configured default, blanking
the tag. A title cleared to `''` renders with no title even though the model
could compute one.

- **Core 3.x (now):** the fix is opt-in. `seo.resolver.blank_is_unset` defaults
  to `false`, so behaviour is byte-identical to before — blanks still override.
  Set `SEO_BLANK_IS_UNSET=true` to opt in early: blank string fields on the
  stored layer are normalized to `null` and fall through to the computed/default
  value. Only string fields are affected — arrays (`tags`, `focus_keywords`,
  `alternates`), the JSON-LD schema, and the literal `"0"` are never touched, and
  clearing a field to `null` already falls through regardless of the flag.
- **Core 4 (planned):** the default flips to `true`. If you *rely* on blank
  explicit strings overriding lower layers, set `SEO_BLANK_IS_UNSET=false`
  explicitly before upgrading to keep the old behaviour.

To find affected pages today, run `php artisan seo:audit` — it reports a
`blank_explicit_override` warning (naming the blank columns) on any page whose
stored SEO strings are blank, so you can clear them to `null` or opt in with
confidence before the flip.
