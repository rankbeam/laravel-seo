# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=seo-config
```

Everything below lives in `config/seo.php`. Values shown are the defaults.

## Site-wide defaults (layer 1)

```php
'site_name'            => env('APP_NAME', 'My Site'),
'title_suffix'         => ' | ' . env('APP_NAME', 'My Site'),
'default_og_image'     => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'       => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card' => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'         => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'      => env('SEO_TWITTER_CREATOR'),
'favicon'              => '/favicon.ico',
```

`title_suffix` is appended to resolved titles unless the title already ends
with it.

## Feature toggles

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` creates an empty `seo_meta` row when a `HasSEO` model is
created (note: seeders using `WithoutModelEvents` bypass this).

## Focus keywords

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

The focus-keyword **workflow gate**. While `false` (the default), a page that
has no focus keyword is not flagged anywhere — neither [`seo:audit`](/guide/audit)
nor the Pro scan complains, so an app that never adopts focus keywords is never
nagged about a feature it doesn't use. Turn it on once you start setting focus
keywords (e.g. with the [Filament focus-keyword field](/guide/filament)) and the
free audit, the Pro scan, and the Pro editor all begin reporting a
`missing_focus_keyword` notice on pages that still lack one — they read this
same flag, so they always agree.

## Free audit (`seo:audit`)

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

The models the free [`seo:audit`](/guide/audit) command audits when no `--model`
option is passed. Each must use the `HasSEO` trait. When empty, the command
falls back to the models registered under `sitemap.models`.

## Computed fallbacks (layer 5)

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,
],
```

## Sitemaps

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

See the [sitemap registry guide](/guide/sitemaps) for programmatic sources.

## Schema (JSON-LD)

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

These feed the [schema graph](/guide/schema) nodes.

## Routes

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Set `enabled => false` when your app serves its own static `/sitemap.xml`.

## Cache

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default
],
```
