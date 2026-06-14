<?php

declare(strict_types=1);

/**
 * Laravel SEO Configuration
 *
 * This is the package's default configuration file. When users publish the config
 * using `php artisan vendor:publish --tag=seo-config`, this is merged with their
 * customizations, ensuring the package always has working defaults.
 *
 * The core package covers: meta tag resolution (SEOResolver precedence chain),
 * tag rendering (HTML / array / Inertia), JSON-LD schema markup, and XML
 * sitemap generation. The site scanner, redirects, and 404 monitoring live in
 * the Pro package.
 *
 * @see https://github.com/rankbeam/laravel-seo
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Site-wide Defaults
    |--------------------------------------------------------------------------
    |
    | Default values applied to all pages. These serve as fallbacks when no
    | model-specific or route-specific SEO data is defined. All values can
    | be overridden at the model, route, or individual page level.
    |
    */

    /*
     * Your website's name. Used in meta tags, Open Graph, and schema markup.
     * This appears in browser tabs and social media shares.
     */
    'site_name' => env('APP_NAME', 'My Site'),

    /*
     * Suffix appended to all page titles. Leave empty for no suffix.
     * Example result: "Blog Post Title | My Website"
     */
    'title_suffix' => ' | ' . env('APP_NAME', 'My Site'),

    /*
     * Default Open Graph image used when no specific image is set.
     * Should be at least 1200x630px for optimal display on social platforms.
     * Path is relative to your public directory or an absolute URL.
     */
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),

    /*
     * Default robots directive applied when nothing more specific is set.
     */
    'default_robots' => env('SEO_DEFAULT_ROBOTS', 'index,follow'),

    /*
     * Robots rendering policy.
     *
     * By default the rendered <head> omits the robots meta tag when the
     * resolved directive is the site default above — a redundant
     * "index,follow" is noise, and its absence is exactly what a crawler
     * treats as index,follow. A directive that DEVIATES from the default
     * (noindex, nofollow, max-snippet:-1, …) is always emitted, verbatim.
     *
     * Set emit_default to true to always render the robots tag, even when
     * it matches the default (restores pre-3.1 behavior).
     */
    'robots' => [
        'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
    ],

    /*
     * Default Twitter card type (summary, summary_large_image, ...).
     */
    'default_twitter_card' => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),

    /*
     * Twitter @username for the website (without @).
     * Used in Twitter Card meta tags: twitter:site
     */
    'twitter_site' => env('SEO_TWITTER_SITE'),

    /*
     * Twitter @username for the content creator (without @).
     * Used in Twitter Card meta tags: twitter:creator
     */
    'twitter_creator' => env('SEO_TWITTER_CREATOR'),

    /*
     * Path to your favicon file, relative to the public directory.
     */
    'favicon' => '/favicon.ico',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Toggle individual SEO features on or off.
    |
    | Available features:
    | - auto_create_meta: Create an empty seo_meta record when a model using
    |                     the HasSEO trait is created
    | - sitemap:          XML sitemap generation
    | - schema:           JSON-LD structured data markup
    | - multilingual:     Hreflang tags for multi-language sites
    |
    */

    'features' => [
        'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
        'sitemap' => env('SEO_SITEMAP_ENABLED', true),
        'schema' => env('SEO_SCHEMA_ENABLED', true),
        'multilingual' => env('SEO_MULTILINGUAL_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Focus Keywords
    |--------------------------------------------------------------------------
    |
    | The focus-keyword workflow gate. Set a page's focus keywords in the
    | Filament SEO section (or via $model->saveSEO(['focus_keywords' => ...])).
    |
    | While this is false (the default), a page that has no focus keyword is
    | NOT flagged: `seo:audit` and the Pro scan both stay quiet about it, so an
    | app that never adopts focus keywords is never nagged about a feature it
    | doesn't use. Turn it on once you start setting focus keywords and the
    | free audit, the Pro scan, and the Pro editor all begin reporting a
    | `missing_focus_keyword` notice on pages that still lack one — they read
    | this same flag, so they always agree.
    |
    */

    'keywords' => [
        'enabled' => env('SEO_KEYWORDS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Audit (seo:audit)
    |--------------------------------------------------------------------------
    |
    | The free, in-process `php artisan seo:audit` command runs the
    | metadata-class checks (resolvable from the model + resolver, no fetch)
    | and prints a per-page pass / warn / fail table. It needs no queue, no
    | license, and no network. The rendered-HTML and live-canonical checks, and
    | the numerical 0-100 score, are part of the Pro scan.
    |
    | Models to audit when no --model option is passed. Each must use the
    | HasSEO trait. When this is empty the command falls back to the models
    | registered under `sitemap.models` below.
    |
    | Format:
    | \App\Models\Post::class,
    | \App\Models\Page::class,
    |
    */

    'audit' => [
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Computed Values
    |--------------------------------------------------------------------------
    |
    | Settings for the SEOComputedBuilder, which derives fallback SEO values
    | from model attributes when no explicit SEO data is set.
    |
    */

    'computed' => [

        /*
         * Ordered list of model attributes checked when computing a meta
         * description. The first attribute containing meaningful text (after
         * stripping HTML and decoding entities) wins. Leave empty to use the
         * built-in defaults (excerpt, summary, description, intro, lead,
         * teaser, content, body, text, article).
         *
         * Example for a medical site:
         * ['subtitle', 'pathology_description', 'description', 'abstract',
         *  'content', 'biography', 'training']
         */
        'description_fields' => [],

        /*
         * Maximum length for computed descriptions. Text is truncated at a
         * word boundary (no ellipsis) and trailing punctuation is trimmed.
         */
        'description_max_length' => 160,

    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Generation
    |--------------------------------------------------------------------------
    |
    | Configuration for XML sitemap generation. The sitemap helps search engines
    | discover and index your content more efficiently.
    |
    | Generate sitemap: php artisan seo:sitemap
    | Access at: https://yoursite.com/sitemap.xml
    |
    | Suggested package: spatie/laravel-sitemap
    |
    */

    'sitemap' => [

        /*
         * Filesystem disk where sitemaps are stored.
         * Must be publicly accessible for search engines.
         */
        'disk' => env('SEO_SITEMAP_DISK', 'public'),

        /*
         * Filename for the sitemap (relative to disk root).
         */
        'path' => 'sitemap.xml',

        /*
         * Maximum URLs per sitemap file.
         * If exceeded, a sitemap index will be created automatically.
         * XML sitemap spec limit is 50,000 URLs per file.
         */
        'max_urls_per_sitemap' => 50000,

        /*
         * Models to include in the sitemap.
         * Models must implement the Sitemapable interface or use the HasSEO trait.
         *
         * Format:
         * ModelClass::class => [
         *     'priority' => 0.8,        // 0.0 to 1.0 (default: 0.5)
         *     'changefreq' => 'weekly', // always, hourly, daily, weekly, monthly, yearly, never
         * ]
         *
         * Example:
         * \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
         * \App\Models\Page::class => ['priority' => 0.6, 'changefreq' => 'monthly'],
         */
        'models' => [],

        /*
         * Additional static URLs to include in the sitemap.
         * Useful for pages not backed by Eloquent models.
         *
         * Format:
         * [
         *     ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
         *     ['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly'],
         * ]
         */
        'static_urls' => [],

        /*
         * Whether to ping search engines (Google, Bing) after generation.
         * Only enable in production environments.
         */
        'ping_search_engines' => env('SEO_SITEMAP_PING', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Markup (JSON-LD)
    |--------------------------------------------------------------------------
    |
    | Default values for JSON-LD structured data. Schema markup helps search
    | engines understand your content and can enable rich snippets in search
    | results (star ratings, FAQs, breadcrumbs, etc.).
    |
    | Reference: https://schema.org
    |
    */

    'schema' => [

        /*
         * Default organization data for Organization schema.
         * Used when no specific organization is defined on a page.
         */
        'organization' => [
            'name' => env('APP_NAME'),
            'url' => env('APP_URL'),
            'logo' => env('SEO_ORGANIZATION_LOGO'),
            // 'sameAs' => [], // Social media profile URLs
        ],

        /*
         * Default publisher data for Article schema.
         * Used for blog posts, news articles, etc.
         */
        'publisher' => [
            'name' => env('APP_NAME'),
            'logo' => env('SEO_PUBLISHER_LOGO'),
        ],

        /*
         * Default WebSite schema data.
         * Used for the overall website identity.
         */
        'website' => [
            'name' => env('APP_NAME'),
            'url' => env('APP_URL'),
            // 'potentialAction' => [], // SearchAction for sitelinks search box
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the package's web and API routes. Customize the route
    | prefix and middleware to match your application's structure.
    |
    */

    'routes' => [

        /*
         * Master switch for all package routes (sitemap.xml, sitemap-{name}.xml,
         * and any API endpoints). Disable when your application serves its own
         * sitemap (e.g. a statically generated file) to avoid route collisions.
         */
        'enabled' => env('SEO_ROUTES_ENABLED', true),

        /*
         * Prefix for web routes (sitemap, robots, etc.).
         * Leave empty for root-level routes.
         */
        'prefix' => '',

        /*
         * Middleware applied to web routes.
         */
        'middleware' => ['web'],

        /*
         * Prefix for API routes.
         * Default provides endpoints at /api/seo/*
         */
        'api_prefix' => 'api/seo',

        /*
         * Middleware applied to API routes.
         */
        'api_middleware' => ['api'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache key prefixes and store settings. All SEO-related cache keys use
    | these settings, making it easy to clear all SEO cache at once.
    |
    */

    'cache' => [

        /*
         * Prefix for all SEO cache keys.
         * Change if you have conflicts with other packages.
         */
        'prefix' => 'seo_',

        /*
         * Cache store to use (null = application default).
         * Recommended: 'redis' or 'memcached' for production.
         */
        'store' => env('SEO_CACHE_STORE'),

    ],

];
