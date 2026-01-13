<?php

declare(strict_types=1);

/**
 * Laravel SEO Suite Configuration
 *
 * This is the package's default configuration file. When users publish the config
 * using `php artisan vendor:publish --tag=seo-config`, this is merged with their
 * customizations, ensuring the package always has working defaults.
 *
 * @see https://github.com/fibonoir/laravel-seo
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend Stack
    |--------------------------------------------------------------------------
    |
    | The frontend stack you're using. This is set automatically by the
    | installer but can be changed manually. Determines which components
    | and behaviors are active at runtime.
    |
    | Supported values:
    | - 'filament'  : Full Filament admin panel integration
    | - 'livewire'  : Livewire components for Blade templates
    | - 'vue'       : Vue 3 components for Inertia.js apps
    | - 'react'     : React components for Inertia.js apps
    | - 'api'       : Headless/API-only mode, no frontend components
    |
    */

    'stack' => env('SEO_STACK', 'api'),

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
    | Toggle individual SEO features on or off. Disabling features you don't
    | need improves performance and reduces database queries. Each feature
    | can also be controlled via environment variables.
    |
    | Available features:
    | - analytics:    Google Analytics 4 dashboard integration
    | - sitemap:      XML sitemap generation
    | - schema:       JSON-LD structured data markup
    | - multilingual: Hreflang tags for multi-language sites
    | - redirects:    301/302 redirect management
    | - scanner:      Site-wide SEO issue scanner
    | - 404_monitor:  Track and log 404 errors
    |
    */

    'features' => [
        'analytics' => env('SEO_ANALYTICS_ENABLED', false),
        'sitemap' => env('SEO_SITEMAP_ENABLED', true),
        'schema' => env('SEO_SCHEMA_ENABLED', true),
        'multilingual' => env('SEO_MULTILINGUAL_ENABLED', false),
        'redirects' => env('SEO_REDIRECTS_ENABLED', true),
        'scanner' => env('SEO_SCANNER_ENABLED', true),
        '404_monitor' => env('SEO_404_MONITOR_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Analyzer
    |--------------------------------------------------------------------------
    |
    | Configuration for the content analysis engine. The analyzer scores your
    | content against 32+ SEO best practices including readability, keyword
    | usage, meta tag optimization, and structural requirements.
    |
    */

    'analyzer' => [

        /*
         * Paths to directories containing custom analyzer rules.
         * The package will auto-discover any classes implementing RuleInterface.
         *
         * Format: 'Namespace\Path' => '/absolute/path/to/directory'
         *
         * Example:
         * 'App\Seo\Rules' => app_path('Seo/Rules'),
         */
        'rule_paths' => [],

        /*
         * Rules to exclude from analysis. Use rule IDs (snake_case).
         * Useful for disabling rules that don't apply to your content type.
         *
         * Available rule IDs:
         * - keyword_density, keyword_in_title, keyword_at_title_start
         * - keyword_in_url, keyword_in_description, keyword_in_headings
         * - keyword_in_first_paragraph, keyword_distribution
         * - title_length, description_length, title_has_number, title_has_power_word
         * - content_length, readability, heading_structure, transition_words
         * - too_long_sentences, short_paragraphs, table_of_contents, passive_voice
         * - image_alt_tags, keyword_in_image_alt, internal_links, external_links
         * - broken_links, broken_images
         * - invalid_head_elements, canonical_url, no_index_check, lang_attribute
         * - og_image_validation, mixed_content
         */
        'exclude_rules' => [],

        /*
         * Minimum word count for content to be considered "complete".
         * Content below this threshold will trigger a warning.
         * Set to 0 to disable this check.
         */
        'min_content_length' => 300,

        /*
         * Optimal keyword density range (as percentages).
         * [minimum, maximum] - Content outside this range receives warnings.
         * Generally accepted SEO best practice is 1-2.5%.
         */
        'keyword_density_range' => [1.0, 2.5],

        /*
         * Default locale for language-specific features like stemming,
         * stop words, and readability calculations.
         */
        'default_locale' => env('SEO_DEFAULT_LOCALE', 'en'),

        /*
         * Supported locales for language-specific analysis.
         * The analyzer will use English fallbacks for unsupported locales.
         *
         * Currently supported: en, it, de, fr, es, pt, nl, sv, no, da, fi, ru
         */
        'supported_locales' => ['en', 'it', 'de', 'fr', 'es', 'pt', 'nl'],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sitewide Scanner
    |--------------------------------------------------------------------------
    |
    | Configuration for the SEO scanner that crawls your site looking for
    | issues like duplicate titles, missing meta descriptions, broken links,
    | image alt text violations, and other common SEO problems.
    |
    | Run the scanner: php artisan seo:scan
    |
    */

    'scanner' => [

        /*
         * Number of pages to process per batch job.
         * Lower values reduce memory usage but increase job count.
         * Recommended: 25-100 depending on page complexity.
         */
        'batch_size' => 50,

        /*
         * Enable JavaScript rendering for single-page applications (SPAs).
         * When enabled, pages are rendered in a headless browser before analysis.
         *
         * Requires: spatie/browsershot package
         * Note: Significantly increases scan time and resource usage.
         */
        'javascript_rendering' => env('SEO_SCANNER_JS_RENDERING', false),

        /*
         * URL paths to exclude from scanning. Supports wildcards (*).
         * These paths will be completely ignored by the scanner.
         */
        'exclude_paths' => [
            'admin/*',
            'api/*',
            '_debugbar/*',
            'livewire/*',
            'horizon/*',
            'telescope/*',
            'sanctum/*',
        ],

        /*
         * Model classes to exclude from scanning.
         * Use fully qualified class names.
         *
         * Example: [\App\Models\Draft::class, \App\Models\PrivatePage::class]
         */
        'exclude_models' => [],

        /*
         * Queue connection/name for scanner background jobs.
         * Set to 'sync' to run synchronously (not recommended for production).
         */
        'queue' => env('SEO_SCANNER_QUEUE', 'default'),

        /*
         * Timeout in seconds for HTTP requests when validating external links.
         */
        'request_timeout' => 10,

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
    | Google Analytics (GA4) Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Analytics 4 API integration. This enables
    | fetching traffic data, top pages, and performance metrics directly
    | in your admin panel or application.
    |
    | Setup Requirements:
    | 1. Create a GA4 property in Google Analytics
    | 2. Create a service account in Google Cloud Console
    | 3. Download the credentials JSON file
    | 4. Add the service account email to your GA4 property
    |
    | Suggested package: google/apiclient
    |
    */

    'analytics' => [

        /*
         * Enable or disable the GA4 integration.
         * When disabled, all analytics features are hidden.
         */
        'enabled' => env('SEO_GA4_ENABLED', false),

        /*
         * Your GA4 Property ID.
         * Can be found in GA4 > Admin > Property Settings
         * Format: numeric ID (e.g., "123456789") or measurement ID (e.g., "G-XXXXXXXXXX")
         */
        'property_id' => env('SEO_GA4_PROPERTY_ID'),

        /*
         * Path to the Google service account credentials JSON file.
         * This file should be kept secure and NOT committed to version control.
         */
        'credentials_path' => env(
            'SEO_GA4_CREDENTIALS_PATH',
            storage_path('app/google-credentials.json')
        ),

        /*
         * How long to cache analytics data (in seconds).
         * Set higher for fewer API calls, lower for more real-time data.
         * Default: 3600 (1 hour)
         */
        'cache_ttl' => env('SEO_GA4_CACHE_TTL', 3600),

        /*
         * Queue connection/name for analytics sync background jobs.
         */
        'queue' => env('SEO_GA4_QUEUE', 'default'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects Manager
    |--------------------------------------------------------------------------
    |
    | Configuration for the redirect management system. Create 301/302 redirects
    | to handle URL changes, preserve link equity, and maintain a good user
    | experience when content moves.
    |
    */

    'redirects' => [

        /*
         * Enable redirect caching for performance.
         * Cached redirects are served without database queries.
         */
        'cache_enabled' => true,

        /*
         * How long to cache redirects (in seconds).
         * Set to 0 to disable caching.
         */
        'cache_ttl' => env('SEO_REDIRECTS_CACHE_TTL', 3600),

        /*
         * Track redirect hit counts in the database.
         * Useful for monitoring redirect usage and identifying cleanup opportunities.
         */
        'log_hits' => true,

        /*
         * URL paths to exclude from redirect checking.
         * Requests to these paths bypass redirect middleware entirely.
         */
        'exclude_paths' => [
            'api/*',
            '_debugbar/*',
            'livewire/*',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 404 Error Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for the 404 error logger. Tracks missing pages to help
    | identify broken links, create redirects, and improve user experience.
    |
    */

    '404_monitor' => [

        /*
         * URL paths to exclude from 404 logging.
         * Useful for ignoring expected 404s like missing favicons.
         */
        'exclude_paths' => [
            'api/*',
            '_debugbar/*',
            'livewire/*',
            '*.map',
            'favicon.ico',
        ],

        /*
         * File extensions to exclude from 404 logging.
         * Prevents logging noise from missing static assets.
         */
        'exclude_extensions' => [
            'js',
            'css',
            'map',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'ico',
            'woff',
            'woff2',
            'ttf',
            'eot',
        ],

        /*
         * Whether to log 404s from known bots and crawlers.
         * Disable to reduce noise from crawler probing for vulnerabilities.
         */
        'log_bots' => false,

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
    | Clear all SEO cache: php artisan cache:forget seo_*
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

        /*
         * Specific cache key names used by the package.
         * Override if you need custom key names to avoid conflicts.
         */
        'keys' => [
            'redirects' => 'seo_redirects',
            'defaults' => 'seo_defaults_',
            'analytics' => 'seo_analytics_',
            'stemmer' => 'seo_stem_',
            'link_index' => 'seo_link_index',
            'sitemap' => 'seo_sitemap_',
            'resolver' => 'seo_resolved_',
        ],

    ],

];
