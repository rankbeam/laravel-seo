# Changelog

All notable changes to `rankbeam/laravel-seo` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

### 3.0.0 (contract reset — unreleased)

The open-core ownership reset (action plan RT0). Core keeps the metadata
contract; the numerical SEO score becomes a Pro-owned scan-result field.

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

[Unreleased]: https://github.com/rankbeam/laravel-seo/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/rankbeam/laravel-seo/releases/tag/v2.0.0
