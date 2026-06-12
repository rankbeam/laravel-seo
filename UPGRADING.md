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
| Content analyzer (32 rules) | `rankbeam/laravel-seo-pro` (commercial, in development) |
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

## 6. Known gotchas

- Laravel's default `DatabaseSeeder` uses the `WithoutModelEvents` trait,
  which disables `HasSEO`'s auto-create hook — seeded models won't get a
  `seo_meta` row until first save. Remove the trait or call `saveSEO()` in
  seeders.
- If a route-default title template already contains your brand, end the
  template with the configured `title_suffix` — the resolver then skips
  appending it again, avoiding "Brand — X | Brand".
