# Sitemap registry

The package generates XML sitemaps (one file per source plus an index) and
serves them at `/sitemap.xml` and `/sitemap-{name}.xml`. Generation wraps
[spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registering sources

Register named sources in a service provider's `boot()`:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Each source renders to `sitemap-{name}.xml`; `sitemap.xml` becomes the index
listing them all.

The registry API also offers `has($name)`, `names()`, `forget($name)`, and
`flush()`.

## Config-driven sources

Prefer configuration? `config/seo.php` accepts model sources and static URLs:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Auto-discovery defers to the registry
If a model is covered by a named registered source, auto-discovery skips it —
registering `'posts'` will not also produce a `sitemap-post.xml`.
:::

## Generating

```bash
php artisan seo:sitemap
```

Files are written to the disk configured at `seo.sitemap.disk` (default
`public`). Schedule the command to keep sitemaps fresh:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Sitemaps exceeding `seo.sitemap.max_urls_per_sitemap` (default 50,000 — the
XML spec limit) are split automatically.

## Serving

The package routes serve whatever the command generated, with XML headers,
cache headers, and `X-Robots-Tag: noindex`:

- `/sitemap.xml` — the index (or single sitemap)
- `/sitemap-posts.xml` — a named source

Serving your own statically generated sitemap instead? Disable the routes:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## What gets included

Model sources include records that resolve as indexable; a model whose
robots resolve to `noindex` stays out of the sitemap. URLs come from
`getUrlForSEO()` — the same method that powers canonicals, so the sitemap
and the canonical never disagree.
