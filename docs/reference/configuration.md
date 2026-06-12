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
