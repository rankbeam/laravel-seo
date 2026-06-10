# Laravel SEO (core)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)
[![Total Downloads](https://img.shields.io/packagist/dt/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)
[![License](https://img.shields.io/packagist/l/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)

SEO core for Laravel: meta tag resolution with a layered precedence chain, Open Graph / Twitter Cards, JSON-LD schema markup with a linked `@id` graph, and XML sitemap generation.

> **Status note:** the published Packagist release (`v1.0.0`, 2026-01-13) is the old "full suite" version. `master` has since been carved down to the core described below; the analyzer, scanner, redirect manager, 404 monitor, analytics, and UI stubs were removed from the shipped surface (see [`pro-staging/`](pro-staging/README.md)) and will return as separate packages.

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

**Database tables:** `seo_meta` (per-model explicit values, morph + locale) and `seo_defaults` (global/model-type/route defaults). Nothing else.

## Requirements

- PHP 8.2+
- Laravel 11 or 12 (CI runs the full matrix; primary development is on 12)
- `spatie/laravel-sitemap` ^7.0 (suggested, required for sitemap generation)

## Installation

```bash
composer require fibonoir/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

## Quick start

```php
use Fibonoir\LaravelSEO\Traits\HasSEO;

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
use Fibonoir\LaravelSEO\Facades\SEO;

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

Serving your own static `/sitemap.xml`? Disable the package routes:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Test status

`vendor/bin/pest` on `master`: **178 passed (408 assertions), 0 failed** under PHP 8.4 / Laravel 12. This counts only the shipped core; the staged pro code under `pro-staging/` is excluded from autoload and the suite (its tests, including 21 historical failures in the scan/analyzer pipeline, move with it).

```bash
git clone https://github.com/Fibonoir/laravel-seo.git
cd laravel-seo
composer install
vendor/bin/pest
```

## What is *not* in this package

Content analyzer (32 rules), sitewide scanner, redirect manager, 404 monitor, GA4 analytics, internal-link suggestions, and the Filament/Livewire/Vue/React UI — staged in [`pro-staging/`](pro-staging/README.md) for the upcoming `laravel-seo-pro` and `laravel-seo-filament` packages. The old `seo:install` stub-publishing flow is gone.

## License

MIT — see [LICENSE.md](LICENSE.md).
