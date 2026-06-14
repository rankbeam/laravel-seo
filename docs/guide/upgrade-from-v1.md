# Upgrading from fibonoir/laravel-seo v1

v2.0.0 renames the package to `rankbeam/laravel-seo` and carves it down to a
focused core: meta resolution, rendering, JSON-LD, and sitemaps. The
analyzer, scanner, redirects, 404 monitor, and admin UI moved to separate
packages.

## 1. Swap the package

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Update namespaces

Class names are unchanged; only the root namespace moved:
`Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. A project-wide find-and-replace
covers it. The `SEO` facade alias and the `@seo` Blade directives are
unchanged.

## 3. Delete stale published files

::: warning This bites silently
v1's `seo:install` published files into your app that will fight the v2
package without producing a single error message.
:::

- **`config/seo.php`** — if yours was published by v1 (or by
  `ralphjsmit/laravel-seo`, which v1's installer could leave behind), it
  shadows the package config and can null out `site_name` and every
  `{site_name}` template. Delete it, then re-publish:
  `php artisan vendor:publish --tag=seo-config`.
- **v1 migrations** for tables the core no longer owns: `seo_redirects`,
  `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`,
  `seo_internal_links_index`. Remove the migration files. If the tables
  exist in production, drop them **before** installing
  `rankbeam/laravel-seo-pro` — Pro recreates them with a different schema.
- **Published stubs** under `app/` and `resources/js` from v1's Filament 3 /
  Livewire / Vue / React flow — they reference classes that no longer exist.

The two core tables (`seo_meta`, `seo_defaults`) are schema-compatible; your
data survives the upgrade.

## 4. Removed features and where they went

| v1 feature | Where it lives now |
|---|---|
| Filament SEO form section | [`rankbeam/laravel-seo-filament`](/guide/filament) (free, MIT) |
| Content analyzer (32 rules) | Not carried forward — the keyword-density/power-word rules aren't on the roadmap. Technical-SEO issue detection lives in `rankbeam/laravel-seo-pro`'s site scanner; the numeric SEO score is a Pro feature (issue-derived). |
| Sitewide scanner | `rankbeam/laravel-seo-pro` — queued pipeline + dashboard |
| Redirect manager | `rankbeam/laravel-seo-pro` — hardened (regex validation, open-redirect guards) |
| 404 monitor | `rankbeam/laravel-seo-pro` — privacy-first (no IPs stored by default) |
| GA4 analytics, internal links | `rankbeam/laravel-seo-pro` backlog |
| `seo:install` installer | Gone — install is require, publish config, migrate |

## 5. Behavior changes to review

- **`og:image` / `twitter:image` are always absolute URLs.** v1 emitted
  manually-set relative paths verbatim.
- **Derived canonicals strip the query string.** Explicit canonicals are
  preserved verbatim.
- **Sitemap auto-discovery defers to registered sources** — no more
  duplicate `sitemap-post.xml` next to a registered `sitemap-posts.xml`.
- **JSON-LD is `JSON_HEX_*`-escaped** — if you post-process raw script
  output, expect `<`-style escapes.

## 6. Known gotchas

- Laravel's default `DatabaseSeeder` uses `WithoutModelEvents`, which
  disables `HasSEO`'s auto-create hook in seeders.
- If a route-default title template already contains your brand, end the
  template with the configured `title_suffix` — the resolver then skips
  appending it again.
