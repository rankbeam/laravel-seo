# Changelog

All notable changes to `rankbeam/laravel-seo` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
