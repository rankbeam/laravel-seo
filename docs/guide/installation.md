# Installation

## Requirements

- PHP 8.2+ (8.3+ on Laravel 13)
- Laravel 11, 12, or 13
- `spatie/laravel-sitemap` ^7.0 or ^8.0 — optional, required only for sitemap
  generation

## Install the package

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

That's the whole install. The service provider and the `SEO` facade are
auto-discovered; the two migrations create the only tables the package owns:

| Table | Purpose |
|---|---|
| `seo_meta` | Explicit per-model values (morph + locale) |
| `seo_defaults` | Global, model-type, and route defaults |

## Optional: sitemaps

Sitemap generation wraps [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

See the [sitemap registry guide](/guide/sitemaps) for sources and generation.

## Upgrading from v1?

If your app used `fibonoir/laravel-seo` v1, read
[Upgrading from v1](/guide/upgrade-from-v1) first — the vendor, the namespace,
and the package surface all changed, and v1 may have published files that
fight the v2 config.

## Companion packages

| Package | What it adds | License |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | SEO section for Filament 4/5 resource forms | MIT |
| `rankbeam/laravel-seo-pro` | Queued site scans, SEO dashboard, redirect manager, 404 monitor | Commercial |
