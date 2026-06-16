# Changelog

All notable changes to `rankbeam/laravel-seo` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### WordPress / legacy migration hardening + import verification report (improvement plan T9)

Hardening for the WordPress / legacy migration path, plus an import
**verification report** so an operator can confirm exactly what an import did
before decommissioning the old stack. Additive — no command option changes.

#### Added

- **Import verification report.** `seo:import-from` now prints — and exposes in
  `--json` under a `verification` key — a matched / url-only / truncated /
  unmapped breakdown. Rows that matched no model are counted as **url-only** as a
  first-class number, and unmapped fields with no Core 3 home now capture their
  distinct **values** (`unmapped_values`) — above all every `author` name. Author
  has no column (it is a `getSEOAuthor()` concern), so the report lets you re-home
  it deliberately instead of losing it silently.
- Anonymized ~900-page WordPress corpus fixtures (Yoast + Rank Math + a CSV
  excerpt) and expanded token / malformed-data / morph-map / locale / idempotency
  tests across all three WordPress importers.

#### Notes

- The redirect candidates the WordPress importers emit use a stable, versioned
  shape — **redirect CSV format v1** (`source_path,target_url,status_code,note`) —
  consumed by Pro's `seo-pro:redirects-import`. The format version is recorded
  here so the open-core contract is traceable.

#### Documentation

- New [WordPress migration runbook](docs/guide/wordpress-migration-runbook.md):
  the ordered, low-risk cutover procedure — coexist → import → verify with
  `seo:audit --strict` → explicit verification before removing the legacy
  package/table.

### Render surface accepts `Model | SEOData | null` (improvement plan T1)

Model-less pages (listings, search, controller-composed views) can now render
through the facade and the `@seo` directive with a hand-built `SEOData` instead
of reaching for `app(TagRenderer::class)->render($seoData)` by hand — the #1
integration papercut.

#### Added

- **`SEO::render()`, `SEO::toArray()`, `SEO::forInertia()` accept
  `Model|SEOData|null`.** A `Model` (or `null`) runs the full precedence chain as
  before; a hand-built `SEOData` is rendered directly. `SEO::forInertia()` is also
  now a first-class facade method (previously available on the resolver root but
  undeclared on the facade).
- **`@seo($seoData)`** — the Blade directive accepts a `SEOData` as well as a
  `Model`/`null`. `@seo($model)` and `@seo($seoData)` produce an equivalent tag
  set for equivalent data.
- **`seo.title_suffix_skip_when_contains`** (default `[]`) — a brand-aware skip
  list. When the resolved title already contains one of these tokens as a whole
  word (case-insensitive), the `title_suffix` is not appended, avoiding a
  redundant double-brand title. Applies to both the model and `SEOData` paths.

#### Behaviour

- A supplied `SEOData` is treated as **explicit intent**: every value you set is
  preserved, and only **absent** fields are filled before rendering —
  `canonical`/`og:url` derived from the current URL, the `title_suffix` applied
  when missing (honoring the new skip list), relative `og:image`/`twitter:image`
  absolutized via `url()` (not `secure_url()`, which would force HTTPS and break
  non-HTTPS/dev), and `og:site_name`/`locale` filled from config/app. The DB
  precedence chain (global/model-type/route/`seo_meta` defaults) is **not** merged
  into a hand-built `SEOData`.
- **`TagRenderer` is unchanged.** Preparation of a `SEOData` happens before the
  renderer, never inside it, so direct `TagRenderer::render($data)` callers are
  unaffected.

This is an **additive minor** (the new config key defaults to current behavior).
The facade/directive now accept `SEOData` as **new public API** — sibling
packages (`rankbeam/laravel-seo-filament`, `rankbeam/laravel-seo-pro`) that want
to pass a hand-built `SEOData` through the facade/`@seo` should require the core
version that ships this change once it is released.

### Blank explicit-value policy (improvement plan T2)

A persisted blank (`''` / `'   '`) value in a `seo_meta` string column is an
*explicit* value, so the resolver's "last non-null wins" merge lets it override
every lower layer — silently blanking a tag or suppressing the computed
fallback / configured default. This adds an opt-in policy to let those blanks
fall through instead, plus an audit code so the condition is visible even before
you opt in.

#### Added

- **`seo.resolver.blank_is_unset`** (`SEO_BLANK_IS_UNSET`, default `false`) —
  when enabled, the resolver normalizes blank/whitespace **string** fields on
  the stored (explicit) layer to `null` during explicit-value extraction, so
  they fall through to the computed value / default. Only string fields are
  affected: arrays (`tags`, `focus_keywords`, `alternates`), the JSON-LD schema,
  and the literal string `"0"` are never touched. Persistence-layer only — it is
  applied to the freshly-extracted stored `SEOData`, never to the general DTO or
  a higher layer's intentional value. Clearing a field to `null` (e.g. from
  Filament) keeps falling through exactly as before, with the flag on or off.
- **`seo:audit` issue code `blank_explicit_override`** (warning) — the free
  audit reports when a stored `seo_meta` string is blank and would override the
  computed/default value, naming the blank columns. It is surfaced only while
  `blank_is_unset` is **off** (once on, the blanks already fall through), so the
  otherwise-silent condition is observable before you opt in. This is a
  **core-only** code — it is intentionally NOT part of the Pro-mirror
  `MetadataIssues::metadataCodes()` list shared with the Pro scan registry.

#### Behaviour

- With the flag **off** (the default) behaviour is **byte-identical** to today:
  blank explicit strings still override lower layers.
- The default **flips to `true` in the next major (Core 4)**; the
  `SEO_BLANK_IS_UNSET` env var is the escape hatch in either direction. See
  [UPGRADING.md](UPGRADING.md) (“Planned for Core 4”).

This is an **opt-in additive minor**; no public API was added that the sibling
packages need to call, so no minimum-core-version bump is required for them.

## [3.0.0]

The third major. The headline breaking changes are the **contract reset**
(dead score/analyzer API removed — see below) and two output-shape changes that
are breaking for programmatic consumers:

- **`SEO::toArray()` / `SEO::forInertia()` shape change.** The rendered output
  no longer includes a redundant `robots` entry (when the resolved directive
  equals `seo.default_robots`), an empty `canonical`, or empty/null meta
  entries. Any consumer that read `toArray()` and relied on those keys/elements
  always being present must guard for their absence. The new
  `seo.robots.emit_default = true` flag restores the always-emit robots
  behaviour. See [UPGRADING.md](UPGRADING.md).
- The `seo:import-from` importer now **only fills empty fields by default** (it
  never overwrites hand-edited `seo_meta`); pass `--overwrite` to replace
  existing values. See [UPGRADING.md](UPGRADING.md).

### Sitemap image & hreflang extensions (action plan RT15)

Optional, first-class image and hreflang entries in the generated sitemap,
derived from the data the package already resolves for each record.

#### Added

- **`seo.sitemap.images`** (env `SEO_SITEMAP_IMAGES`, default `false`) — adds a
  Google image-sitemap `<image:image><image:loc>` entry to each `HasSEO` model
  URL, built from the model's resolved og/content image (the same value
  rendered as `og:image`). When a record has no image of its own this resolves
  to the site-wide `default_og_image`.
- **`seo.sitemap.alternates`** (env `SEO_SITEMAP_ALTERNATES`, default `false`) —
  adds `<xhtml:link rel="alternate" hreflang="…">` entries to each `HasSEO`
  model URL from the model's `getSEOAlternates()`. Malformed entries (missing or
  non-string `hreflang`/`href`) are skipped.
- Both extensions degrade gracefully: a hand-built `Spatie\Sitemap\Tags\Url`
  (from `Sitemapable::toSitemapTag()` or a registered source) is passed through
  **verbatim** and never enriched, and a resolver error for one record leaves
  that URL without extensions instead of failing the whole sitemap.

#### Notes

- Both flags are **off by default** and change no existing output when off.
- The package config is now **deep-merged** under the published `config/seo.php`
  (see below), so apps that published the config before this release receive
  these `sitemap` keys (and any future nested defaults) automatically — the env
  vars `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` work without re-publishing.
- Enabling an extension resolves each record's full `seoData()` once per URL —
  built for the scheduled `seo:sitemap` command; benchmark on very large sites.

#### Fixed

- **Published config is now deep-merged.** `SEOServiceProvider` replaced
  Laravel's shallow `mergeConfigFrom()` with a recursive merge that fills
  package defaults UNDER the published `config/seo.php` at every depth. A config
  published before a release that adds nested keys (e.g. `sitemap.images`,
  `sitemap.alternates`, `robots.emit_default`) now receives those defaults — and
  their env vars take effect — without re-publishing. Every value the app set
  (including falsey leaves and replaced lists) is preserved.
- **Defaults resolution no longer re-queries on every call.**
  `SEODefaultsRepository` now memoizes resolved defaults — **including null
  misses** — for the request, keyed by scope/locale. Laravel's
  `Cache::remember()` treats a cached `null` as a miss and re-runs the loader,
  so on the common install with no `seo_defaults` rows every `seoData()`
  resolution was issuing repeated DB/cache lookups that always returned null.
  This is an app-wide per-page cost, amplified by the sitemap extensions above
  (one `seoData()` per record — ~100k redundant lookups on a 50k-URL sitemap).
  `clearCache()`/`refreshCache()` reset the memo so admin updates still take
  effect. The positive-only `tableExists()` memo is unchanged.
- **Editing or deleting an `SEODefault` now invalidates the resolved-defaults
  cache.** The model's save/delete hook previously only forgot the
  `getForScope()` model cache (`default:…`), leaving the repository's separate
  resolved cache (`defaults:…`, 1-hour TTL) stale — so an admin edit could take
  up to an hour to appear. The hook now also calls
  `SEODefaultsRepository::clearCache()`; saving or deleting the `en` row clears
  the **whole scope**, since other locales cache the `en` row as their fallback.

### Importer from competing Laravel SEO packages (action plan RT12)

A migration path off other Laravel SEO packages, so switching to Rankbeam is a
day's work, not a rewrite.

#### Added

- **`seo:import-from` command** with a pluggable importer registry
  (`Rankbeam\Seo\Importing\ImporterRegistry` + `Contracts\Importer`). New
  sources register a key without touching the command.
- **`ralphjsmit` importer** — reads `ralphjsmit/laravel-seo`'s `seo` morph
  table into `seo_meta`. Idempotent (re-run updates the same row, never
  duplicates), `--dry-run` (report-only), `--model=` scoping, `--locale=`,
  `--table=` / `--connection=` for renamed/legacy sources, `--limit=`, and
  `--json`. Each row is **re-resolved to its live model** so the `seoable`
  relation is correct under the app's current morph map; deleted models are
  skipped, never written as orphans.
- Fields are mapped **explicitly** (`title`, `description`,
  `canonical_url`→`canonical`, `robots`, `image`→`og_image`), with over-length
  values trimmed-and-reported and the unsupported `author` column counted
  rather than copied (Core 3 has no author column). The importer only ever
  fills empty fields — it never overwrites existing `seo_meta` values.
- New guide: [Migrating from other packages](docs/guide/migrate-from-other-packages.md)
  mapping `ralphjsmit`, `artesaos/seotools`, and the Spatie builders onto
  `HasSEO` + `saveSEO()`.

### WordPress importer — Yoast / Rank Math (action plan RT13)

A migration path for content sites leaving WordPress, reusing the RT12 importer
scaffolding (no command change — new sources just register keys).

#### Added

- **`wordpress-csv` importer** — reads a `url,title,description,canonical,robots,focus_keyword`
  CSV export into `seo_meta`. Columns in any order; unrecognised columns and
  malformed rows (missing `url`, wrong column count) are reported and skipped.
- **`yoast` and `rank-math` importers** — read the live WordPress database
  (`{prefix}posts` + `{prefix}postmeta`) behind `--connection=`, mapping each
  plugin's keys **explicitly** to `seo_meta` (title, description, canonical,
  robots, focus keyword, **and** the OpenGraph/Twitter overrides). Keys with no
  Core 3 home (image IDs, scores, primary-category) are reported as *unmapped*,
  never invented.
- **Model matching.** A WordPress row's slug (URL last segment / `post_name`) is
  matched to a content model via `--model=` on its route key or `--match-by=`.
  Rows matching nothing are reported as **url-only** (honest about what attached
  to a model vs. didn't). New options: `--file=`, `--match-by=`, `--post-type=*`,
  `--redirects-csv=`, `--site-url=`.
- **Template tokens resolved.** Yoast (`%%title%%`) and Rank Math (`%title%`)
  title/description tokens are resolved where derivable (`title`, `sitename`
  from `wp_options`, `sep`) and stripped otherwise — a stored value is never a
  raw token string. Resolution is flagged in the report.
- **Redirects via CSV, not the Pro table.** `--redirects-csv=` emits a
  `source_path,target_url,status_code,note` CSV for import into Rankbeam Pro
  (core never writes the Pro `seo_redirects` table). Sources: a CSV row whose
  `canonical` points to a different path; and Rank Math's redirections table
  (active, exact-match rules only — non-exact rules reported).
- New guide: [Migrating from WordPress](docs/guide/migrate-from-wordpress.md).

### Rendering contract & framework correctness (action plan RT4)

The single canonical [Rendering Contract](docs/contributing/rendering-contract.md)
every front-end stack's `<head>` must satisfy, plus the renderer fixes and
fast, framework-free unit tests that prove it.

#### Changed

- **Robots is now a configurable policy.** `@seo` / `SEO::render()` /
  `SEO::toArray()` / `SEO::forInertia()` emit `<meta name="robots">` **only when
  the resolved directive deviates from the site default** (`seo.default_robots`).
  A redundant `index,follow` is no longer rendered — its absence already means
  index,follow to a crawler. A deviating directive (`noindex`, `nofollow`,
  `max-snippet:-1`, …) is emitted **verbatim**; the comparison is
  whitespace-insensitive. This is a **rendered-output change** (minor): set the
  new `seo.robots.emit_default = true` to restore the always-emit behaviour. The
  granular `@seoRobots` directive is unaffected (explicit opt-in, always renders).
- **JSON-LD `<script>` tags now carry `data-seo-schema` + `data-seo-url`** so
  client-side navigation (Livewire `wire:navigate`) can find and remove stale
  schema instead of accumulating it. See the new Livewire guide for the
  `livewire:navigated` cleanup snippet.
- `toArray()` / `render()` no longer emit empty/null tags (empty canonical,
  empty-content meta).

#### Added

- **Stable Inertia `head-key`** on every `SEO::forInertia()` / `toInertiaHead()`
  meta and link entry (`name ?? property`, repeatables disambiguated; canonical
  and hreflang keyed). Bind it as `:head-key` so page meta dedupes/replaces
  layout meta across client visits instead of stacking duplicates.
- **`seo.robots.emit_default`** config flag (default `false`).
- Framework guides: a new **Livewire** guide (`@seo` on initial render, the
  `wire:navigate` caveat, the JSON-LD cleanup snippet), an Inertia **Svelte**
  recipe (`<svelte:head>`), a **working JSON-LD-in-Inertia** recipe (replacing
  the broken `@seoSchema($post)`-in-root-Blade doc), and an explicit
  "crawler-visible meta requires Inertia SSR/prerendering" note.
- Renderer-shape contract tests (`tests/Unit/Services/RenderingContractTest.php`)
  proving title/meta-order/robots/dedup-head-keys/canonical≡og:url/script
  presence-absence and cross-renderer semantic parity — fast, required CI.

### Free SEO audit & focus-keyword workflow (additive)

The instant free-tier payoff (action plan RT2): a one-command "what's wrong
with my SEO right now" audit and the focus-keyword workflow gate.

#### Added

- **`php artisan seo:audit`** — a free, in-process SEO audit. It runs only the
  **metadata-class** checks (resolvable from the model + the resolver, with no
  page fetch, no queue, and no license): missing / over- / under-length title &
  description, missing OG image, robots conflicts, canonical format /
  cross-domain / shared / insecure, and the optional missing-focus-keyword
  notice. It prints a per-page **pass / warn / fail** table, a summary, and an
  explicit **capability matrix** every run (so its coverage is never mistaken
  for the Pro scan), and it never produces a numerical score. Options:
  `--model` (repeatable), `--locale`, `--limit`, `--issues-only`, `--strict`
  (non-zero exit when any issue is found, for CI), `--json`. Models default to
  `seo.audit.models`, falling back to `seo.sitemap.models`.
  See [the audit guide](docs/guide/audit.md).
- **`Rankbeam\Seo\Auditing\MetadataAuditor`** + `MetadataIssues` (a tiny
  core-side mirror of the Pro registry's metadata codes) + `AuditIssue`. The
  code strings and severities are kept identical to the Pro
  `Rankbeam\Seo\Pro\Scanning\IssueRegistry`, so the free audit, the Pro scan,
  the Filament editor, and exports all agree on what each code means — without
  the free core depending on Pro.
- **`seo.keywords.enabled`** config flag (default `false`) — the focus-keyword
  workflow gate. While off, a page with no focus keyword is **not** flagged.
  Turn it on once you adopt focus keywords and the `missing_focus_keyword`
  notice fires in the free audit, the Pro scan, and the Pro editor — all three
  read this same core flag, so they always agree.
- **`seo.audit.models`** config — the models `seo:audit` audits by default.

### Contract reset (action plan RT0)

The open-core ownership reset. Core keeps the metadata contract; the numerical
SEO score becomes a Pro-owned scan-result field.

#### Fixed

- Partial resolver overrides no longer reset a computed `og:type=article` to
  `website` or replace an existing `twitter:card`. Stored metadata with no
  explicit social-card values now preserves computed model inference.

#### Added

- `HasSEO::getSEOAlternates(): ?array`, a first-class hreflang resolver hook
  consumed by Blade, Inertia, and JSON rendering.

#### Changed

- `seo_meta.og_type` and `seo_meta.twitter_card` are nullable with no database
  defaults. The renderer still defaults an unset `og:type` to `website`, and
  the resolver base configuration still supplies the default Twitter Card.

#### Removed

- **Breaking:** the dead content-analyzer columns on `seo_meta` —
  `seo_score`, `analysis_report`, `analyzed_at`, `content_snapshot`,
  `content_hash`, `snapshot_at` — and their `seo_meta_score_index` /
  `seo_meta_analyzed_index` indexes. Nothing in the shipped core or Pro ever
  wrote to them. Dropped by a new, idempotent, **irreversible** cleanup
  migration that runs safely on fresh and upgraded (incl. partially-migrated)
  schemas. **`focus_keywords` is kept.** The published `create_seo_meta_table`
  migration is left immutable.
- **Breaking:** the always-null score/analysis public API —
  `HasSEO::getSEOScore()`, `getSEOAnalysisReport()`, `needsSEOAnalysis()`,
  `scopeWithLowSEOScore()`, `scopeNeedingSEOAnalysis()`;
  `SEOMeta::updateScore()`, `scopeLowScore()`, `scopeNeedsAnalysis()`; and the
  `SEOData::$seoScore` / `$analysisReport` properties (with their
  `fromModel`/`fromArray`/`merge`/`toArray`/`toFlatArray` plumbing). These
  read columns that were always NULL.

#### Notes

- The numerical 0–100 score moves to Pro, persisted on a Pro scan-result
  record with a stored `rubric_version` (implemented in a later thread) — it
  will never live in `seo_meta`. See [UPGRADING.md](UPGRADING.md).

## [2.0.1] - 2026-06-13

### Fixed

- SEO defaults are cached as plain arrays instead of objects.
  Laravel 13 ships `cache.serializable_classes => false`, under which
  cached objects come back as `__PHP_Incomplete_Class` — any app using
  SEO defaults with a persistent cache store (database is the Laravel 13
  default) got a 500 once a defaults row existed. Stale object-format
  cache entries are detected and dropped automatically.

## [2.0.0] - 2026-06-12

First release under the `rankbeam` vendor. v2 is the focused "core": meta
resolution, rendering, JSON-LD, and sitemaps. Everything else moved to
dedicated packages — see [UPGRADING.md](UPGRADING.md) for the full map.

### Changed

- **Breaking:** package renamed from `fibonoir/laravel-seo` to
  `rankbeam/laravel-seo`; PHP namespace renamed from `Fibonoir\LaravelSEO`
  to `Rankbeam\Seo`. No class names changed — update imports and re-publish
  the config.
- **Breaking:** derived canonicals (model URL / current URL) now strip the
  query string; explicitly set canonicals are preserved verbatim.
- **Breaking:** `og:image` and `twitter:image` are always emitted as absolute
  URLs, regardless of which resolver layer produced the value (the OG spec
  requires absolute URLs; v1 emitted manual values verbatim).
- Description fallbacks are normalized (HTML stripped, entities decoded) and
  truncated at a word boundary; candidate attributes are configurable via
  `seo.computed.description_fields` and `seo.computed.description_max_length`.
- Sitemap model auto-discovery is skipped for models already covered by a
  named registered source, so registering `'posts'` no longer produces a
  duplicate `sitemap-post.xml` alongside `sitemap-posts.xml`.

### Added

- Laravel 13 support (`illuminate/* ^11.0|^12.0|^13.0`; Laravel 13 requires
  PHP 8.3+).
- `SEO::sitemaps()->register($name, $source)` — programmatic named sitemap
  sources (model classes, closures, or URL arrays) rendered as
  `sitemap-{name}.xml` plus a sitemap index.
- `config('seo.routes.enabled')` — disable the package's `/sitemap.xml`
  routes when serving your own.
- `SEOWarningEvaluator` — title/description length warnings (60/160),
  manual-vs-fallback indicators, and social-image dimension checks for
  admin UIs.
- `SchemaGraph` — Organization/WebSite/WebPage JSON-LD nodes cross-linked
  via stable `@id`s.
- `BreadcrumbSchema::fromModelAncestors()` with an ancestor-loop guard.
- Robots meta can derive from an `is_indexable` model attribute.

### Removed

- **Breaking:** content analyzer (32 rules), readability/stemmer/tokenizer
  support classes, and the `wamania/php-stemmer` dependency.
- **Breaking:** sitewide scanner, redirect manager, 404 monitor, GA4
  analytics, and internal-link suggestions — these live in
  `rankbeam/laravel-seo-pro` (commercial).
- **Breaking:** the `seo:install` stub-publishing flow and all Filament 3 /
  Livewire / Vue / React stubs — Filament admin fields live in
  `rankbeam/laravel-seo-filament` (free).
- **Breaking:** migrations for `seo_redirects`, `seo_404_logs`,
  `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, and
  `seo_internal_links_index`. The core ships only `seo_meta` and
  `seo_defaults`.

### Fixed

- Sitemap index no longer lists duplicate files when named sources cover a
  model that auto-discovery would also pick up.
- The defaults repository no longer disables database defaults for the whole
  PHP process if it ever ran before the `seo_defaults` table existed (the
  table check latched `false` in a process-wide static — long-running
  queue/Octane workers could permanently lose DB defaults).

### Security

- JSON-LD output (`TagRenderer` and `SchemaCollection::toScript()/toJson()`)
  is encoded with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`,
  so `</script>` in user content cannot break out of the script element.

## 1.0.0 - 2026-01-13

Initial release as `fibonoir/laravel-seo` (the old "full suite" package:
core + analyzer + scanner + redirects + 404 monitor + analytics + UI stubs;
since removed from distribution).

[Unreleased]: https://github.com/rankbeam/laravel-seo/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/rankbeam/laravel-seo/compare/v2.0.1...v3.0.0
[2.0.1]: https://github.com/rankbeam/laravel-seo/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/rankbeam/laravel-seo/releases/tag/v2.0.0
