# Laravel SEO (core)

[![Tests](https://github.com/rankbeam/laravel-seo/actions/workflows/tests.yml/badge.svg)](https://github.com/rankbeam/laravel-seo/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/rankbeam/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/rankbeam/laravel-seo)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

SEO core for Laravel: meta tag resolution with a layered precedence chain, Open Graph / Twitter Cards, JSON-LD schema markup with a linked `@id` graph, and XML sitemap generation.

> **Upgrading from `fibonoir/laravel-seo` v1?** See [UPGRADING.md](UPGRADING.md) — v2 renames the vendor and carves the old "full suite" down to this core; the analyzer, scanner, redirect manager, 404 monitor, and admin UI live on as separate packages ([`laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament), free; `laravel-seo-pro`, commercial).

## What this package does

| Area | Details |
|---|---|
| **Meta resolution** | `SEOResolver` merges six layers — config → global DB defaults → model-type defaults → route defaults → computed model values → explicit `seo_meta` values. Null never overwrites a lower layer. |
| **Computed fallbacks** | Title/description/image/robots derived from model attributes. Description candidates are configurable (`seo.computed.description_fields`), normalized (HTML stripped, entities decoded), and truncated at a word boundary (default 160 chars, no ellipsis). Robots can derive from an `is_indexable` attribute. |
| **Rendering** | `TagRenderer` outputs HTML (`@seo` Blade directives), structured arrays (Vue/React), or Inertia Head format. JSON-LD is emitted with `JSON_HEX_*` escaping so `</script>` in content cannot break out of the script element. |
| **Canonical policy** | Derived canonicals (model URL / current URL) get the query string stripped; explicitly set canonicals are preserved verbatim. |
| **Schema (JSON-LD)** | Builders for Article, Breadcrumb, FAQ, LocalBusiness, Organization, Product; `SchemaGraph` for Organization/WebSite/WebPage nodes cross-linked via stable `@id`s; breadcrumbs from a page's ancestor chain with a loop guard. |
| **Sitemaps** | `SitemapBuilder` (wraps spatie/laravel-sitemap) with config-driven model sources, programmatic named sources via `SEO::sitemaps()->register(...)`, sitemap index support, `seo:sitemap` command, and `/sitemap.xml` routes that can be disabled. |
| **Warnings** | `SEOWarningEvaluator` for admin UIs: title > 60 / description > 160 warnings, manual-vs-fallback indicators, social-image dimension checks (min 200x200, ideal 1200x630, local files only). |
| **Free audit** | `seo:audit` — an in-process "what's wrong with my SEO right now" command (no queue, license, or network). Runs the metadata-class checks (missing / over- / under-length title & description, OG image, robots conflicts, canonical format/cross-domain/shared/insecure, focus keyword) and prints a per-page pass/warn/fail table with an explicit capability matrix. `--strict` for CI, `--json` for tooling. No numerical score (that's Pro). |

**Database tables:** `seo_meta` (per-model explicit values, morph + locale) and `seo_defaults` (global/model-type/route defaults). Nothing else.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (CI runs the full matrix; Laravel 13 requires PHP 8.3+)
- `spatie/laravel-sitemap` ^7.0 or ^8.0 (suggested, required for sitemap generation)

## Installation

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

## Quick start

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

```blade
<head>
    @seo($post)
</head>
```

Explicit values from the admin side:

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Headless / Inertia:

```php
use Rankbeam\Seo\Facades\SEO;

return Inertia::render('Post', [
    'seo' => SEO::forInertia($post),
]);
```

Sitemap sources:

```php
// config/seo.php
'sitemap' => ['models' => [Post::class => ['priority' => 0.8]]],

// or programmatically (e.g. in a service provider)
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);
```

Then generate the files (requires `spatie/laravel-sitemap`) — the package's
`/sitemap.xml` route serves what this command writes:

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

Serving your own static `/sitemap.xml`? Disable the package routes:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

### Audit your SEO

```bash
# Audit the models under seo.audit.models / seo.sitemap.models
php artisan seo:audit

# Or target specific models, CI-fail on any issue, or emit JSON
php artisan seo:audit --model="App\Models\Post" --strict
php artisan seo:audit --json
```

A free, in-process pass/warn/fail report over the metadata-class checks. The
rendered-HTML and live-canonical checks, and the numerical score, are part of
the Pro scan — the command prints that boundary every run.

## Test status

`vendor/bin/pest` on `master`: **185 passed (418 assertions), 0 failed** under PHP 8.4 / Laravel 13 (CI matrix: PHP 8.2–8.4 × Laravel 11/12/13).

```bash
git clone https://github.com/rankbeam/laravel-seo.git
cd laravel-seo
composer install
vendor/bin/pest
```

## What is *not* in this package

Queued site scans, content analysis, redirect manager, 404 monitor, and the SEO dashboard ship in `laravel-seo-pro` (commercial); the Filament admin form fields ship in `laravel-seo-filament` (free). The old `seo:install` stub-publishing flow is gone.

## License

MIT — see [LICENSE.md](LICENSE.md).
