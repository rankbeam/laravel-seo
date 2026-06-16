# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=seo-config
```

Everything below lives in `config/seo.php`. Values shown are the defaults.

## Site-wide defaults (layer 1)

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` is appended to resolved titles unless the title already ends
with it.

`title_suffix_skip_when_contains` is a brand-aware suppression list. When the
resolved title already contains one of these tokens **as a whole word**
(case-insensitive, word-boundary aware — so `Acmestic` does not match `Acme`),
the suffix is skipped to avoid a redundant double-brand title. The default `[]`
preserves the historical behavior.

## Robots rendering policy

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

The rendered `<head>` omits the `<meta name="robots">` tag when the resolved
directive equals `default_robots` (above) — a redundant `index,follow` is noise,
and its absence is exactly what a crawler treats as index,follow. A directive
that **deviates** (`noindex`, `nofollow`, `max-snippet:-1`, …) is always emitted,
verbatim. Set `emit_default` to `true` to always render the tag (restores the
pre-3.1 behaviour). The granular `@seoRobots` directive is unaffected — it is an
explicit opt-in and always renders. See the
[Rendering Contract](/contributing/rendering-contract) for the supported
directive vocabulary and precedence.

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

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Under the opt-in `best` strategy, the builder scores an ordered candidate list —
`getSEOImage()` first (it stays the highest-priority candidate), then the model's
`getSEOImages()` hook, then the common image fields, the first content image, and
the configured default — by how close each image's pixel dimensions are to the
ideal, and **skips any below the minimum**. Only **local** images are measured (a
relative path under `public/`, the public disk, or an absolute URL on your own
host); a remote URL is never fetched and only acts as a fallback. When no local
candidate clears the minimum, selection falls back to first-match, so `best`
never returns less than `first` would. Expose candidates from your model:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
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

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Resolver result cache

The `SEOResolver` runs the full precedence chain — config → global / model-type /
route defaults → computed model values → explicit `seo_meta` → title suffix /
canonical / schema — on **every** frontend render. On a high-traffic site (the
reference app does ~20k req/day) that is several DB reads per page.

Enable `cache.resolver.enabled` and a model's fully-resolved SEO is cached and a
cache **hit skips the precedence chain entirely** — in the package benchmark a
warm hit issues **zero** database queries where each uncached resolve re-reads
the model's `seo_meta`. The cached payload is a plain array, rehydrated with
`SEOData::fromArray()` (never an object — Laravel 13 ships
`cache.serializable_classes = false`, so a cached object returns as a
`__PHP_Incomplete_Class`).

It uses the `store` configured above, so point it at a **shared, persistent
cache** (`redis` / `memcached`) in production — the cache and its invalidation
must be visible to every web/queue worker. Leave it off until you have one.

**Invalidation is automatic and correct** — caching ON resolves identically to
OFF. Entries are keyed by `(model class, id, locale, route, request URL)` and
cleared when:

- the page's `seo_meta` row is **saved or deleted** (any path: `saveSEO()`,
  Filament, a direct `SEOMeta` write);
- a **content field** on the model changes — the columns from
  `getSEOContentFields()` (the default includes every built-in computed
  fallback field: title/headline fields, excerpt/summary/content/body/text/
  article fields, and common image fields such as `featured_image`,
  `thumbnail`, `cover_image`, `og_image`, `photo`, `banner`, and `hero_image`;
  override it if your model computes SEO from additional columns);
- **any `seo_defaults` row** changes (a default can feed any model, so this
  flushes the whole resolution cache).

On a **taggable** store (`redis`, `memcached`, `array`) a model's entries clear
via cache **tags**; on a **non-taggable** store (`file`, `database`) the package
falls back to a per-model **version stamp**. Both work without key-scanning.

::: tip
Only model-backed resolves are cached. `SEO::render()`/`@seo()` for a hand-built
`SEOData` and `@seoForRoute()` for a model-less route still resolve live.
:::

::: warning
The cache reflects the model's `updated_at`/computed `modified_time` as of the
last **content-field** change (or until the TTL expires). A bare `touch()` that
moves only `updated_at` without changing a `getSEOContentFields()` column does
not force a re-resolve — `article:modified_time` may lag by up to the TTL. Add
any app-specific computed column to `getSEOContentFields()` if you need it
busted eagerly.
:::
