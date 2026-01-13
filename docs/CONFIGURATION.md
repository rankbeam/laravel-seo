# Configuration Reference

Complete reference for all configuration options in Laravel SEO Suite.

## Table of Contents

-   [Publishing Configuration](#publishing-configuration)
-   [Stack Configuration](#stack-configuration)
-   [Site-Wide Defaults](#site-wide-defaults)
-   [Feature Toggles](#feature-toggles)
-   [Content Analyzer](#content-analyzer)
-   [Sitewide Scanner](#sitewide-scanner)
-   [Sitemap Generation](#sitemap-generation)
-   [Google Analytics](#google-analytics)
-   [Redirects Manager](#redirects-manager)
-   [404 Monitor](#404-monitor)
-   [Schema Markup](#schema-markup)
-   [Routes](#routes)
-   [Cache](#cache)
-   [Environment Variables Summary](#environment-variables-summary)

---

## Publishing Configuration

```bash
php artisan vendor:publish --tag=seo-config
```

This creates `config/seo.php` in your application.

---

## Stack Configuration

```php
'stack' => env('SEO_STACK', 'api'),
```

| Value      | Description                                    |
| ---------- | ---------------------------------------------- |
| `filament` | Full Filament admin panel integration          |
| `livewire` | Livewire components for Blade templates        |
| `vue`      | Vue 3 components for Inertia.js                |
| `react`    | React components for Inertia.js                |
| `api`      | Headless/API-only mode, no frontend components |

**Environment Variable:** `SEO_STACK`

### Stack-Specific Publishing

```bash
# Filament components
php artisan vendor:publish --tag=seo-filament

# Livewire components
php artisan vendor:publish --tag=seo-livewire

# Vue components
php artisan vendor:publish --tag=seo-vue

# React components
php artisan vendor:publish --tag=seo-react
```

---

## Site-Wide Defaults

### site_name

```php
'site_name' => env('APP_NAME', 'My Site'),
```

Your website's name used in meta tags, Open Graph, and schema markup.

**Environment Variable:** `APP_NAME`

---

### title_suffix

```php
'title_suffix' => ' | ' . env('APP_NAME', 'My Site'),
```

Suffix appended to all page titles. Set to empty string for no suffix.

**Example Output:** `"Blog Post Title | My Website"`

---

### default_og_image

```php
'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
```

Default Open Graph image when no specific image is set.

**Recommended Size:** 1200x630px

**Environment Variable:** `SEO_DEFAULT_OG_IMAGE`

---

### twitter_site

```php
'twitter_site' => env('SEO_TWITTER_SITE'),
```

Twitter @username for the website (without @).

**Environment Variable:** `SEO_TWITTER_SITE`

**Output:** `<meta name="twitter:site" content="@yoursite">`

---

### twitter_creator

```php
'twitter_creator' => env('SEO_TWITTER_CREATOR'),
```

Twitter @username for content creator (without @).

**Environment Variable:** `SEO_TWITTER_CREATOR`

---

### favicon

```php
'favicon' => '/favicon.ico',
```

Path to favicon file, relative to public directory.

---

## Feature Toggles

```php
'features' => [
    'analytics' => env('SEO_ANALYTICS_ENABLED', false),
    'sitemap' => env('SEO_SITEMAP_ENABLED', true),
    'schema' => env('SEO_SCHEMA_ENABLED', true),
    'multilingual' => env('SEO_MULTILINGUAL_ENABLED', false),
    'redirects' => env('SEO_REDIRECTS_ENABLED', true),
    'scanner' => env('SEO_SCANNER_ENABLED', true),
    '404_monitor' => env('SEO_404_MONITOR_ENABLED', true),
],
```

| Feature        | Description                      | Default |
| -------------- | -------------------------------- | ------- |
| `analytics`    | GA4 dashboard integration        | `false` |
| `sitemap`      | XML sitemap generation           | `true`  |
| `schema`       | JSON-LD structured data          | `true`  |
| `multilingual` | Hreflang tags for multi-language | `false` |
| `redirects`    | 301/302 redirect management      | `true`  |
| `scanner`      | Site-wide SEO issue scanner      | `true`  |
| `404_monitor`  | Track and log 404 errors         | `true`  |

**Environment Variables:**

```env
SEO_ANALYTICS_ENABLED=true
SEO_SITEMAP_ENABLED=true
SEO_SCHEMA_ENABLED=true
SEO_MULTILINGUAL_ENABLED=false
SEO_REDIRECTS_ENABLED=true
SEO_SCANNER_ENABLED=true
SEO_404_MONITOR_ENABLED=true
```

---

## Content Analyzer

### rule_paths

```php
'analyzer' => [
    'rule_paths' => [
        // 'App\SEO\Rules' => app_path('SEO/Rules'),
    ],
],
```

Paths to directories containing custom analyzer rules. Classes must implement `RuleInterface`.

**Example:**

```php
'rule_paths' => [
    'App\SEO\Rules' => app_path('SEO/Rules'),
    'App\Custom\SEORules' => app_path('Custom/SEORules'),
],
```

---

### exclude_rules

```php
'exclude_rules' => [],
```

Rules to exclude from analysis by rule ID (snake_case).

**Available Rule IDs:**

| Category  | Rule IDs                                                                                                         |
| --------- | ---------------------------------------------------------------------------------------------------------------- |
| Keyword   | `keyword_density`, `keyword_in_title`, `keyword_at_title_start`, `keyword_in_url`, `keyword_in_description`, `keyword_in_headings`, `keyword_in_first_paragraph`, `keyword_distribution` |
| Meta      | `title_length`, `description_length`, `title_has_number`, `title_has_power_word`                                |
| Content   | `content_length`, `readability`, `heading_structure`, `transition_words`, `too_long_sentences`, `short_paragraphs`, `table_of_contents`, `passive_voice` |
| Links     | `internal_links`, `external_links`, `broken_links`                                                              |
| Media     | `image_alt_tags`, `keyword_in_image_alt`, `broken_images`                                                       |
| Technical | `invalid_head_elements`, `canonical_url`, `no_index_check`, `lang_attribute`, `og_image_validation`, `mixed_content` |

**Example:**

```php
'exclude_rules' => [
    'passive_voice',      // Don't check passive voice
    'table_of_contents',  // Don't require TOC
],
```

---

### min_content_length

```php
'min_content_length' => 300,
```

Minimum word count for content to be considered "complete". Set to `0` to disable.

---

### keyword_density_range

```php
'keyword_density_range' => [1.0, 2.5],
```

Optimal keyword density range as percentages `[minimum, maximum]`.

-   Content below minimum: "Add more keyword usage"
-   Content above maximum: "Reduce keyword stuffing"

---

### default_locale

```php
'default_locale' => env('SEO_DEFAULT_LOCALE', 'en'),
```

Default locale for language-specific features (stemming, stop words, readability).

**Environment Variable:** `SEO_DEFAULT_LOCALE`

---

### supported_locales

```php
'supported_locales' => ['en', 'it', 'de', 'fr', 'es', 'pt', 'nl'],
```

Locales with full language support. Unsupported locales fall back to English.

**Currently Supported:** `en`, `it`, `de`, `fr`, `es`, `pt`, `nl`, `sv`, `no`, `da`, `fi`, `ru`

---

## Sitewide Scanner

### batch_size

```php
'scanner' => [
    'batch_size' => 50,
],
```

Number of pages to process per batch job. Lower values reduce memory usage.

**Recommended:** 25-100 depending on page complexity.

---

### javascript_rendering

```php
'javascript_rendering' => env('SEO_SCANNER_JS_RENDERING', false),
```

Enable headless browser rendering for SPAs.

**Requirements:** `spatie/browsershot` package

**Warning:** Significantly increases scan time and resource usage.

**Environment Variable:** `SEO_SCANNER_JS_RENDERING`

---

### exclude_paths

```php
'exclude_paths' => [
    'admin/*',
    'api/*',
    '_debugbar/*',
    'livewire/*',
    'horizon/*',
    'telescope/*',
    'sanctum/*',
],
```

URL paths to exclude from scanning. Supports wildcards (`*`).

---

### exclude_models

```php
'exclude_models' => [
    // \App\Models\Draft::class,
    // \App\Models\PrivatePage::class,
],
```

Model classes to exclude from scanning. Use fully qualified class names.

---

### queue

```php
'queue' => env('SEO_SCANNER_QUEUE', 'default'),
```

Queue connection for scanner background jobs. Set to `'sync'` to run synchronously (not recommended for production).

**Environment Variable:** `SEO_SCANNER_QUEUE`

---

### request_timeout

```php
'request_timeout' => 10,
```

Timeout in seconds for HTTP requests when validating external links.

---

## Sitemap Generation

### disk

```php
'sitemap' => [
    'disk' => env('SEO_SITEMAP_DISK', 'public'),
],
```

Filesystem disk for sitemap storage. Must be publicly accessible.

**Environment Variable:** `SEO_SITEMAP_DISK`

---

### path

```php
'path' => 'sitemap.xml',
```

Filename for the sitemap (relative to disk root).

---

### max_urls_per_sitemap

```php
'max_urls_per_sitemap' => 50000,
```

Maximum URLs per sitemap file. If exceeded, a sitemap index is created.

**XML Spec Limit:** 50,000 URLs per file

---

### models

```php
'models' => [
    // \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    // \App\Models\Page::class => ['priority' => 0.6, 'changefreq' => 'monthly'],
],
```

Models to include in sitemap. Models must implement `Sitemapable` or use `HasSEO` trait.

**Options:**

| Option       | Values                                                        | Default |
| ------------ | ------------------------------------------------------------- | ------- |
| `priority`   | 0.0 to 1.0                                                    | 0.5     |
| `changefreq` | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never` | -       |

---

### static_urls

```php
'static_urls' => [
    // ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    // ['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly'],
],
```

Additional static URLs not backed by Eloquent models.

---

### ping_search_engines

```php
'ping_search_engines' => env('SEO_SITEMAP_PING', false),
```

Ping Google and Bing after sitemap generation. Only enable in production.

**Environment Variable:** `SEO_SITEMAP_PING`

---

## Google Analytics

### enabled

```php
'analytics' => [
    'enabled' => env('SEO_GA4_ENABLED', false),
],
```

Enable or disable GA4 integration.

**Environment Variable:** `SEO_GA4_ENABLED`

---

### property_id

```php
'property_id' => env('SEO_GA4_PROPERTY_ID'),
```

Your GA4 Property ID. Found in GA4 > Admin > Property Settings.

**Format:** Numeric ID (e.g., `"123456789"`) or measurement ID (e.g., `"G-XXXXXXXXXX"`)

**Environment Variable:** `SEO_GA4_PROPERTY_ID`

---

### credentials_path

```php
'credentials_path' => env(
    'SEO_GA4_CREDENTIALS_PATH',
    storage_path('app/google-credentials.json')
),
```

Path to Google service account credentials JSON file.

**Security:** Keep secure and never commit to version control.

**Environment Variable:** `SEO_GA4_CREDENTIALS_PATH`

---

### cache_ttl

```php
'cache_ttl' => env('SEO_GA4_CACHE_TTL', 3600),
```

Cache duration for analytics data in seconds.

**Default:** 3600 (1 hour)

**Environment Variable:** `SEO_GA4_CACHE_TTL`

---

### queue

```php
'queue' => env('SEO_GA4_QUEUE', 'default'),
```

Queue connection for analytics sync jobs.

**Environment Variable:** `SEO_GA4_QUEUE`

---

## Redirects Manager

### cache_enabled

```php
'redirects' => [
    'cache_enabled' => true,
],
```

Enable redirect caching. Cached redirects are served without database queries.

---

### cache_ttl

```php
'cache_ttl' => env('SEO_REDIRECTS_CACHE_TTL', 3600),
```

Cache duration for redirects in seconds. Set to `0` to disable caching.

**Environment Variable:** `SEO_REDIRECTS_CACHE_TTL`

---

### log_hits

```php
'log_hits' => true,
```

Track redirect hit counts in the database.

---

### exclude_paths

```php
'exclude_paths' => [
    'api/*',
    '_debugbar/*',
    'livewire/*',
],
```

Paths that bypass redirect middleware entirely.

---

## 404 Monitor

### exclude_paths

```php
'404_monitor' => [
    'exclude_paths' => [
        'api/*',
        '_debugbar/*',
        'livewire/*',
        '*.map',
        'favicon.ico',
    ],
],
```

URL paths to exclude from 404 logging.

---

### exclude_extensions

```php
'exclude_extensions' => [
    'js', 'css', 'map',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
    'woff', 'woff2', 'ttf', 'eot',
],
```

File extensions to exclude from 404 logging.

---

### log_bots

```php
'log_bots' => false,
```

Whether to log 404s from known bots and crawlers. Disable to reduce noise from vulnerability probing.

---

## Schema Markup

### organization

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url' => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [], // Social media profile URLs
    ],
],
```

Default organization data for Organization schema.

**Environment Variables:** `APP_NAME`, `APP_URL`, `SEO_ORGANIZATION_LOGO`

---

### publisher

```php
'publisher' => [
    'name' => env('APP_NAME'),
    'logo' => env('SEO_PUBLISHER_LOGO'),
],
```

Default publisher data for Article schema.

**Environment Variables:** `APP_NAME`, `SEO_PUBLISHER_LOGO`

---

### website

```php
'website' => [
    'name' => env('APP_NAME'),
    'url' => env('APP_URL'),
    // 'potentialAction' => [], // SearchAction for sitelinks search box
],
```

Default WebSite schema data.

---

## Routes

### prefix

```php
'routes' => [
    'prefix' => '',
],
```

Prefix for web routes (sitemap, robots). Leave empty for root-level.

---

### middleware

```php
'middleware' => ['web'],
```

Middleware applied to web routes.

---

### api_prefix

```php
'api_prefix' => 'api/seo',
```

Prefix for API routes. Endpoints available at `/api/seo/*`.

---

### api_middleware

```php
'api_middleware' => ['api'],
```

Middleware applied to API routes.

---

## Cache

### prefix

```php
'cache' => [
    'prefix' => 'seo_',
],
```

Prefix for all SEO cache keys. Change if you have conflicts with other packages.

---

### store

```php
'store' => env('SEO_CACHE_STORE'),
```

Cache store to use. `null` uses application default.

**Recommended:** `redis` or `memcached` for production.

**Environment Variable:** `SEO_CACHE_STORE`

---

### keys

```php
'keys' => [
    'redirects' => 'seo_redirects',
    'defaults' => 'seo_defaults_',
    'analytics' => 'seo_analytics_',
    'stemmer' => 'seo_stem_',
    'link_index' => 'seo_link_index',
    'sitemap' => 'seo_sitemap_',
    'resolver' => 'seo_resolved_',
],
```

Specific cache key names. Override to avoid conflicts.

---

## Environment Variables Summary

```env
# Stack
SEO_STACK=filament

# Site defaults
SEO_DEFAULT_OG_IMAGE=/images/og-default.jpg
SEO_TWITTER_SITE=yourhandle
SEO_TWITTER_CREATOR=authorhandle

# Features
SEO_ANALYTICS_ENABLED=true
SEO_SITEMAP_ENABLED=true
SEO_SCHEMA_ENABLED=true
SEO_MULTILINGUAL_ENABLED=false
SEO_REDIRECTS_ENABLED=true
SEO_SCANNER_ENABLED=true
SEO_404_MONITOR_ENABLED=true

# Analyzer
SEO_DEFAULT_LOCALE=en

# Scanner
SEO_SCANNER_JS_RENDERING=false
SEO_SCANNER_QUEUE=default

# Sitemap
SEO_SITEMAP_DISK=public
SEO_SITEMAP_PING=false

# Google Analytics 4
SEO_GA4_ENABLED=true
SEO_GA4_PROPERTY_ID=123456789
SEO_GA4_CREDENTIALS_PATH=/path/to/credentials.json
SEO_GA4_CACHE_TTL=3600
SEO_GA4_QUEUE=default

# Redirects
SEO_REDIRECTS_CACHE_TTL=3600

# Cache
SEO_CACHE_STORE=redis

# Schema
SEO_ORGANIZATION_LOGO=/images/logo.png
SEO_PUBLISHER_LOGO=/images/publisher-logo.png
```

---

## Configuration Examples

### Minimal Setup (Blog)

```php
return [
    'stack' => 'livewire',
    'site_name' => 'My Blog',
    'title_suffix' => ' | My Blog',

    'features' => [
        'analytics' => false,
        'sitemap' => true,
        'schema' => true,
        'redirects' => true,
        'scanner' => true,
        '404_monitor' => true,
    ],

    'sitemap' => [
        'models' => [
            \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
        ],
    ],
];
```

### E-Commerce Setup

```php
return [
    'stack' => 'vue',
    'site_name' => 'My Store',

    'features' => [
        'analytics' => true,
        'sitemap' => true,
        'schema' => true,
        'redirects' => true,
        'scanner' => true,
        '404_monitor' => true,
    ],

    'sitemap' => [
        'models' => [
            \App\Models\Product::class => ['priority' => 0.9, 'changefreq' => 'daily'],
            \App\Models\Category::class => ['priority' => 0.7, 'changefreq' => 'weekly'],
        ],
    ],

    'schema' => [
        'organization' => [
            'name' => 'My Store Inc.',
            'url' => 'https://mystore.com',
            'logo' => 'https://mystore.com/logo.png',
            'sameAs' => [
                'https://facebook.com/mystore',
                'https://twitter.com/mystore',
                'https://instagram.com/mystore',
            ],
        ],
    ],
];
```

### Multi-Language Setup

```php
return [
    'stack' => 'filament',

    'features' => [
        'multilingual' => true,
    ],

    'analyzer' => [
        'default_locale' => 'en',
        'supported_locales' => ['en', 'de', 'fr', 'es'],
    ],
];
```

### High-Traffic Production Setup

```php
return [
    'stack' => 'api',

    'cache' => [
        'store' => 'redis',
        'prefix' => 'seo_prod_',
    ],

    'scanner' => [
        'batch_size' => 25, // Lower for memory efficiency
        'queue' => 'seo-scanner',
    ],

    'analytics' => [
        'queue' => 'seo-analytics',
        'cache_ttl' => 7200, // 2 hours
    ],

    'redirects' => [
        'cache_ttl' => 7200,
    ],
];
```
