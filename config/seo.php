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
    'title_suffix' => ' | '.env('APP_NAME', 'My Site'),

    /*
     * Brand-aware suffix suppression.
     *
     * The title suffix (above) is normally appended to every title that
     * does not already end with it. But a title that already carries the
     * brand — "Acme · About Us" when the suffix is " | Acme" — would gain a
     * second, redundant brand mention. List tokens here (typically your
     * brand name) and the suffix is skipped whenever the resolved title
     * already contains one of them as a whole word (case-insensitive,
     * word-boundary aware, so "Acmestic" does NOT match "Acme").
     *
     * Default [] preserves the historical behavior (suffix always applied
     * unless the title already ends with the exact suffix string).
     *
     * @var array<int, string>
     */
    'title_suffix_skip_when_contains' => [],

    /*
     * Default Open Graph image used when no specific image is set.
     * Should be at least 1200x630px for optimal display on social platforms.
     * Path is relative to your public directory or an absolute URL.
     */
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),

    /*
    |--------------------------------------------------------------------------
    | Generated OG Images
    |--------------------------------------------------------------------------
    |
    | Automatically generate 1200x630 Open Graph / Twitter-card images for
    | pages that have no explicit og:image. A Blade template is rendered to a
    | real headless browser (spatie/browsershot) — so multi-line wrapping,
    | non-Latin scripts (CJK) and accents come out correct — then stored as a
    | PNG keyed by a hash of its content. The resolver points og:image at the
    | stored file ONLY once it exists, so a page never links a missing image.
    |
    | OFF by default: it needs the optional spatie/browsershot package (which
    | drives Chrome). When disabled, default_og_image above is used unchanged
    | and the package stays zero-dependency.
    |
    */
    'og_image' => [

        // Master switch. Requires spatie/browsershot + a reachable
        // Chrome/Chromium. Leave false to keep the package zero-dependency.
        'enabled' => env('SEO_OG_IMAGE_ENABLED', false),

        // Renderer driver. 'browsershot' = real headless Chrome (correct text
        // layout + automatic font fallback). Register your own with
        // OgImageManager::extend().
        'driver' => env('SEO_OG_IMAGE_DRIVER', 'browsershot'),

        // The default Blade view rendered as the image. Three ship with the
        // package: 'seo::og.default', 'seo::og.article' (adds author + date),
        // and 'seo::og.product'.
        'template' => env('SEO_OG_IMAGE_TEMPLATE', 'seo::og.default'),

        // Per-model-class template overrides, so an Article and a Product get
        // different cards automatically. A model can also override per instance
        // with a getOgImageTemplate() method. Example:
        //   'templates' => [
        //       App\Models\Post::class    => 'seo::og.article',
        //       App\Models\Product::class => 'seo::og.product',
        //   ],
        'templates' => [],

        // The bundled templates already show the site name, so a card title
        // that still carries the site-name title_suffix (e.g. "Post | Acme")
        // would print the brand twice. Trim the suffix off the card title.
        'strip_title_suffix' => env('SEO_OG_IMAGE_STRIP_SUFFIX', true),

        // Output dimensions. 1200x630 is the social-card standard.
        'width' => 1200,
        'height' => 630,

        // Filesystem disk + path prefix the PNGs are written to. The disk must
        // be publicly served — its url() becomes the og:image value.
        'disk' => env('SEO_OG_IMAGE_DISK', 'public'),
        'path' => env('SEO_OG_IMAGE_PATH', 'og-images'),

        // Models the seo:og-images command pre-generates cards for. Empty
        // falls back to the sitemap's models (seo.sitemap.models). Same shape:
        // a list [Post::class] or a map [Post::class => ['...' => '...']].
        'models' => [],

        // Bump to invalidate every generated image after changing a template or
        // brand colors. The installed package version is folded into the key
        // too, so a package upgrade busts the cache automatically.
        'cache_version' => env('SEO_OG_IMAGE_CACHE_VERSION', 1),

        // Brand gradient (diagonal) for the bundled default template.
        'gradient_from' => env('SEO_OG_IMAGE_GRADIENT_FROM', '#1e2a5a'),
        'gradient_to' => env('SEO_OG_IMAGE_GRADIENT_TO', '#3D5AFE'),

        // Browsershot binary paths. Leave null to use its defaults (node/npx on
        // PATH, puppeteer's bundled Chromium). Set explicitly in production.
        'chrome_path' => env('SEO_OG_IMAGE_CHROME_PATH'),
        'node_binary' => env('SEO_OG_IMAGE_NODE_BINARY'),
        'npm_module_path' => env('SEO_OG_IMAGE_NODE_MODULES'),

        // Hard timeout (seconds) for a single render.
        'timeout' => env('SEO_OG_IMAGE_TIMEOUT', 60),

        // Launch Chrome with --no-sandbox. Default Ubuntu 22.04+/24.04 servers
        // restrict unprivileged user namespaces (AppArmor), so puppeteer's
        // Chrome fails to start with "No usable sandbox!" and seo:og-images
        // errors out of the box. Setting this true is the standard fix for
        // running headless Chrome as a non-root user in a trusted container or
        // VM. Only the HTML the package itself generates is ever rendered, so
        // dropping the sandbox does not expose you to untrusted page content.
        // (The hardened alternative is an AppArmor profile granting userns to
        // the Chrome binary; this flag is the one-line option.)
        'no_sandbox' => env('SEO_OG_IMAGE_NO_SANDBOX', false),

        // Extra Chromium CLI flags for the render, e.g. on a container without
        // enough shared memory: ['disable-dev-shm-usage', 'disable-gpu'].
        // Passed to Browsershot::addChromiumArguments() — a leading "--" is
        // optional (both 'disable-gpu' and '--disable-gpu' work). Use the map
        // form for flags that take a value: ['proxy-server' => 'http://…'].
        'browsershot_args' => [],
    ],

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
    |--------------------------------------------------------------------------
    | Indexing Guard (non-production safety net)
    |--------------------------------------------------------------------------
    |
    | A staging or local copy of your site leaking into Google is one of the
    | most common — and most damaging — SEO mistakes: duplicate content, a
    | private environment in the index, weeks of cleanup. This guard makes it
    | structurally hard. When the app runs in an environment that is NOT in
    | `allowed_environments`, three things happen automatically:
    |
    |   1. The resolver forces `noindex,nofollow` on EVERY page — applied ABOVE
    |      the whole precedence chain, so it overrides even an explicit per-page
    |      robots value stored in seo_meta. (A staging DB is usually a
    |      production clone, so a page that stored `index,follow` must still be
    |      held back. This is the one place the "explicit wins" rule is
    |      deliberately inverted, precisely because the risk is one-directional:
    |      wrongly indexing staging is a disaster; wrongly noindexing it is a
    |      no-op.)
    |   2. SEO::robotsTxt()->build() — the seo:robots-txt command and the
    |      optional dynamic route — emits a disallow-all robots.txt / ai.txt.
    |   3. seo:audit prints a prominent banner so the state is never a surprise.
    |
    | On production (the default sole allowed environment) the guard is fully
    | inert: zero changed output, byte-identical rendering.
    |
    | DEFAULT OFF. It ships disabled so installing or upgrading the package
    | never changes what a non-production environment renders without your
    | say-so — the same byte-identical-until-opt-in policy the resolver's
    | `blank_is_unset` and the generated-OG-image feature follow. Arm it in ONE
    | line — SEO_INDEXING_GUARD=true — and staging/local are protected; disable
    | it with the same one line, SEO_INDEXING_GUARD=false. It is strongly
    | recommended, and a candidate to default ON in Core 4 (see UPGRADING.md).
    | Because the guard is inert on production, you can safely commit it enabled
    | and it only ever acts on the environments you didn't mean to index.
    |
    | Docs: /guide/indexing-guard
    |
    */

    'indexing_guard' => [

        /*
         * Master switch. OFF by default (byte-identical until you opt in). Set
         * SEO_INDEXING_GUARD=true to arm the guard; SEO_INDEXING_GUARD=false to
         * disable it — either way it is one line.
         */
        'enabled' => env('SEO_INDEXING_GUARD', false),

        /*
         * The environments allowed to be indexed. When app()->environment() is
         * NOT one of these, the guard activates. Entries are matched with
         * Str::is(), so wildcards work ('prod*' matches 'production' and
         * 'prod-eu'). An empty list means NO environment may index — the guard
         * is active everywhere (the fail-safe direction).
         *
         * Override via a comma-separated env var, e.g.
         * SEO_INDEXING_GUARD_ALLOWED="production,prod-eu". An empty/blank value
         * falls back to ['production'] so a typo can never silently arm the
         * guard on production; write an explicit [] here to intend "everywhere".
         */
        'allowed_environments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SEO_INDEXING_GUARD_ALLOWED', 'production')),
        ))) ?: ['production'],

        /*
         * Also send an `X-Robots-Tag: noindex,nofollow` HTTP header (via a
         * global middleware) while the guard is active. The forced <meta robots>
         * only reaches crawlers that parse HTML; the header additionally covers
         * PDFs, feeds, images and any other non-HTML response routed through the
         * app — the one noindex signal those responses can carry.
         *
         * ON by default WITHIN the guard: the guard itself is opt-in and inert
         * on production, so this only ever acts once you have armed the guard on
         * a non-production environment. The middleware is registered only when
         * the guard is enabled, so a package with the guard off adds nothing.
         * Set false to keep the meta-only behaviour.
         */
        'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),

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
    | Resolver
    |--------------------------------------------------------------------------
    |
    | Fine-grained control over how the SEOResolver merges the precedence chain.
    |
    | blank_is_unset
    | --------------
    | A persisted blank string ('' or whitespace-only) in a `seo_meta` field is
    | an *explicit* value and, because the resolver merges with "last non-null
    | wins", it OVERRIDES every lower layer — silently suppressing the computed
    | fallback or the configured default. A page whose title was cleared to ''
    | then renders with no title at all, even though the model could compute one.
    |
    | With this flag ON, the resolver normalizes blank/whitespace STRING fields
    | on the stored (explicit) layer to null before merging, so they fall
    | through to the computed value / default instead of blanking the page.
    | Only string fields are affected: arrays (tags, focus_keywords, alternates),
    | the JSON-LD schema, and the literal string "0" are never touched.
    |
    | Default is false, so the published v3 behaviour is byte-identical: blanks
    | still override. The condition is observable regardless of this flag via the
    | `seo:audit` `blank_explicit_override` notice. The DEFAULT FLIPS TO TRUE in
    | Core 4 — see UPGRADING.md. Set SEO_BLANK_IS_UNSET to opt in (or, in Core 4,
    | out) ahead of the flip.
    |
    */

    'resolver' => [
        'blank_is_unset' => env('SEO_BLANK_IS_UNSET', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical URLs
    |--------------------------------------------------------------------------
    |
    | query_whitelist
    | ---------------
    | A canonical the resolver DERIVES (from the request URL or a model's
    | getUrlForSEO()) has its query string stripped by default — tracking,
    | filter and sort params all create duplicate-content canonical targets
    | pointing at the same page. But some params identify a genuinely distinct
    | page that should be its own canonical: most commonly `page` for paginated
    | archives (/blog?page=2 is not /blog). List those keys here and they are
    | KEPT in derived canonicals, in this order; every other param is still
    | stripped.
    |
    | An EXPLICITLY set canonical (admin-entered, or from a higher precedence
    | layer) is always emitted verbatim, query string and all — this list only
    | governs the derived fallback.
    |
    | Default [] preserves the strip-everything behaviour.
    |
    | Example: ['page']
    |
    */

    'canonical' => [
        'query_whitelist' => [],
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

        /*
         * Social / Open Graph image selection.
         *
         * strategy
         * --------
         * 'first' (default) — first-match: the highest-priority non-empty
         *   source wins (getSEOImage(), then common fields, content, default).
         *   No image file is opened or measured. This is the historical
         *   behavior and is byte-identical to previous versions.
         *
         * 'best' — opt-in, dimension-aware. Every candidate (getSEOImage()
         *   first, then the model's getSEOImages() ordered hook, then common
         *   fields / content / the default below) is scored by how close its
         *   pixel dimensions are to the ideal, and any candidate smaller than
         *   the minimum is skipped. getSEOImage() stays the highest-priority
         *   candidate and wins ties.
         *
         *   Core measures LOCAL images only — a relative path under public/,
         *   the public disk, or an absolute URL on your own host. A remote
         *   image is never fetched (SSRF / latency / cache); it cannot be
         *   scored or skipped for size and only acts as a fallback. Remote
         *   dimension checks are the Filament preview's client-side job. When
         *   no local candidate clears the minimum, selection falls back to
         *   first-match, so 'best' never returns less than 'first' would.
         *
         * Expose the ordered candidate list from your model:
         *
         *   use Rankbeam\Seo\Data\SEOImageCandidate;
         *
         *   public function getSEOImages(): iterable
         *   {
         *       return [
         *           SEOImageCandidate::make($this->hero_url)->priority(100),
         *           SEOImageCandidate::make($this->thumbnail_url)->priority(10),
         *       ];
         *   }
         */
        'image_selection' => [
            'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
            'minimum_width' => 200,
            'minimum_height' => 200,
            'ideal_width' => 1200,
            'ideal_height' => 630,
        ],

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
         * Add the resolved og/content image as an <image:image> entry for
         * each model URL (Google image-sitemap extension). The image is the
         * same value rendered as og:image — which may be the site-wide
         * default_og_image when a model has none of its own. Opt-in: it adds
         * a resolver call per record and changes the sitemap output shape.
         */
        'images' => env('SEO_SITEMAP_IMAGES', false),

        /*
         * Add hreflang <xhtml:link rel="alternate"> entries for each model
         * URL, derived from the model's getSEOAlternates() links. Opt-in for
         * the same reasons as 'images' above.
         */
        'alternates' => env('SEO_SITEMAP_ALTERNATES', false),

        /*
         * Styled sitemap stylesheet.
         *
         * References an XSL stylesheet from every generated sitemap (via an
         * <?xml-stylesheet?> instruction) so a browser renders the sitemap as a
         * readable, branded table — URLs, lastmod, image/alternate counts, and
         * inline validation notes — instead of raw XML. Search engines ignore
         * the instruction, so this is purely a human/browser nicety and costs
         * nothing at generation time.
         *
         * On by default: unlike 'images'/'alternates' above it does not change
         * the machine-readable data or add any per-record work — it only adds
         * one instruction line crawlers skip. Set 'enabled' => false to emit
         * plain XML with no stylesheet reference.
         */
        'stylesheet' => [

            /*
             * Emit the <?xml-stylesheet?> reference and register the route that
             * serves the .xsl. When false, sitemaps are plain XML again.
             */
            'enabled' => env('SEO_SITEMAP_STYLESHEET', true),

            /*
             * The href written into the <?xml-stylesheet?> instruction. Leave
             * null to derive it from the package's own /sitemap.xsl route
             * (recommended — browsers only apply an XSL served from the same
             * origin as the sitemap). Set an absolute or root-relative URL when
             * you self-host the stylesheet (e.g. behind a CDN): publish it with
             * `php artisan vendor:publish --tag=seo-assets` and point here.
             *
             * If you disabled the package routes (seo.routes.enabled = false) to
             * serve your own sitemaps, set this explicitly — otherwise no
             * stylesheet is referenced, since the package no longer serves one.
             */
            'url' => env('SEO_SITEMAP_STYLESHEET_URL'),

        ],

        /*
         * Whether to ping search engines (Google, Bing) after generation.
         * Only enable in production environments.
         */
        'ping_search_engines' => env('SEO_SITEMAP_PING', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | llms.txt Generation
    |--------------------------------------------------------------------------
    |
    | llms.txt (https://llmstxt.org) is an optional markdown index of your
    | site's key content, for the tools that choose to consume it. It is not an
    | established standard, and Google Search does not use it. It REUSES the
    | sitemap's content sources — the models under
    | `sitemap.models` and the named sources registered via
    | `SEO::sitemaps()->register(...)` — so the two artifacts never disagree.
    |
    | Generate: php artisan seo:llms-txt
    | Access at: https://yoursite.com/llms.txt
    |
    */

    'llms_txt' => [

        /*
         * Master switch for llms.txt generation. When false the seo:llms-txt
         * command refuses to run.
         */
        'enabled' => env('SEO_LLMS_TXT_ENABLED', true),

        /*
         * Serve /llms.txt via the package route. The route is also subject to
         * the master `routes.enabled` switch below; disable just this one when
         * you serve a statically generated /llms.txt yourself.
         */
        'route' => env('SEO_LLMS_TXT_ROUTE', true),

        /*
         * Filesystem disk the file is written to. Defaults to the sitemap disk
         * so both public artifacts land in the same place.
         */
        'disk' => env('SEO_LLMS_TXT_DISK', env('SEO_SITEMAP_DISK', 'public')),

        /*
         * Filename for the file (relative to the disk root).
         */
        'path' => 'llms.txt',

        /*
         * The H1 site title at the top of the file. Falls back to seo.site_name
         * (then app.name) when left null.
         */
        'title' => env('SEO_LLMS_TXT_TITLE'),

        /*
         * Optional one-line summary rendered as a `> blockquote` under the H1.
         * Leave null to omit it.
         */
        'description' => env('SEO_LLMS_TXT_DESCRIPTION'),

        /*
         * Whitelist of REGISTERED sitemap source names to include as sections.
         * Empty array (the default) includes every registered source. Configured
         * `sitemap.models` are always included regardless of this list.
         *
         * Format:
         * ['posts', 'pages']
         */
        'sources' => [],

        /*
         * Maximum number of bullets emitted per section, bounding the file for
         * very large sources (llms.txt is an orientation index, not a full
         * sitemap).
         */
        'max_entries_per_section' => 100,

    ],

    /*
    |--------------------------------------------------------------------------
    | AI Crawler Control (robots.txt / ai.txt)
    |--------------------------------------------------------------------------
    |
    | A managed robots.txt for the AI era. robots.txt is the file the major AI
    | crawlers actually honour — OpenAI's GPTBot / OAI-SearchBot, Anthropic's
    | ClaudeBot, PerplexityBot and Google-Extended all document it as the
    | control surface. The default policy "allows the AI-search and assistant
    | crawlers and disallows the ones that train on you": AI-search + AI-assistant
    | crawlers are allowed (they drive the AI referral channel), AI-training
    | crawlers are disallowed.
    |
    | Generate: php artisan seo:robots-txt
    | Paste-only: SEO::robotsTxt()->aiDirectives()
    |
    */

    'ai_crawlers' => [

        /*
         * Master switch. When false the seo:robots-txt command refuses to run —
         * UNLESS the indexing guard (above) is active, in which case the command
         * still generates the guard's disallow-all robots.txt so a non-production
         * site is never left with a permissive file.
         */
        'enabled' => env('SEO_AI_CRAWLERS_ENABLED', true),

        /*
         * Serve /robots.txt via the package route. OFF by default: most apps
         * ship a static public/robots.txt that the web server serves before
         * Laravel routing runs. Enable to serve robots.txt dynamically from the
         * configured policy. (Also subject to the master `routes.enabled` below.)
         */
        'route' => env('SEO_AI_CRAWLERS_ROUTE', false),

        /*
         * Filesystem disk + filename the command writes to. Defaults to the
         * sitemap disk so the public artifacts land together.
         */
        'disk' => env('SEO_AI_CRAWLERS_DISK', env('SEO_SITEMAP_DISK', 'public')),
        'path' => 'robots.txt',
        'ai_txt_path' => 'ai.txt',

        /*
         * Default policy per crawler purpose. Each catalogued bot is tagged as
         * one of: 'ai_training' (collects data to train models), 'ai_search'
         * (indexes content for AI-search answers), or 'ai_assistant'
         * (fetches a page in real time on a user's behalf). Map each to 'allow'
         * or 'disallow'.
         */
        'policy' => [
            'ai_training' => 'disallow',
            'ai_search' => 'allow',
            'ai_assistant' => 'allow',
        ],

        /*
         * Per-bot overrides keyed by catalog id (overrides the purpose policy).
         * e.g. ['gptbot' => 'allow', 'perplexitybot' => 'disallow']
         * See \Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry for the catalog ids.
         */
        'overrides' => [],

        /*
         * Which bots get an explicit directive: 'blocked' (only disallowed bots
         * — a lean file that gates the trainers) or 'all' (every known bot gets
         * an explicit allow/disallow line — fully auditable).
         */
        'list' => 'blocked',

        /*
         * Emit a Content-Signal usage-preference line (contentsignals.org, the
         * standard championed by Cloudflare) in the `User-agent: *` group,
         * derived from the `policy` above: ai_search → search, ai_assistant →
         * ai-input, ai_training → ai-train, each allow → yes / disallow → no. A
         * purpose you remove from `policy` emits no signal (the spec's "no
         * preference"). Content signals state how your content may be USED and
         * are ADVISORY — distinct from the Allow/Disallow crawl-access rules.
         * OFF by default: the file stays byte-identical until you opt in.
         */
        'content_signals' => env('SEO_AI_CONTENT_SIGNALS', false),

        /*
         * The general `User-agent: *` section. true emits a permissive default;
         * a string is prepended verbatim (your own general rules); false omits
         * it. Keep a value when serving robots.txt dynamically so the file is a
         * complete, valid robots.txt.
         */
        'general' => true,

        /*
         * Append a `Sitemap:` line. Leave sitemap_url null to derive it from the
         * package sitemap route; set it to point elsewhere.
         */
        'include_sitemap' => true,
        'sitemap_url' => env('SEO_AI_CRAWLERS_SITEMAP_URL'),

        /*
         * Append a comment pointing AI crawlers at the llms.txt content index.
         */
        'include_llms_txt' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown for bots (content negotiation)
    |--------------------------------------------------------------------------
    |
    | Serve a clean markdown representation of a page to AI crawlers instead of
    | HTML, via content negotiation. OFF by default; when on, the middleware
    | swaps in markdown ONLY when the request asks for it (an explicit
    | `Accept: text/markdown`, `?format=md`, or — opt-in — a known AI crawler)
    | AND a markdown source resolves for the route. Sources: a model's
    | `toSeoMarkdown()` method, a route registered via `SEO::markdown()->register()`,
    | or the built title + description + `getContentForSEO()` fallback.
    |
    */

    'markdown_for_bots' => [

        // Master switch. When false the middleware is never registered (no
        // footprint at all).
        'enabled' => env('SEO_MARKDOWN_FOR_BOTS', false),

        // Auto-register the content-negotiation middleware globally.
        'auto_register_middleware' => true,

        // Also serve markdown to known AI crawlers detected by user-agent (via
        // the AI-crawler catalog), not only on an explicit Accept/?format ask.
        'serve_to_known_bots' => false,

        // The query trigger: ?format=md.
        'query_param' => 'format',
        'query_value' => 'md',

        // Build a basic markdown document (title + description + getContentForSEO())
        // when a model has no toSeoMarkdown() of its own. The content is served
        // verbatim — implement toSeoMarkdown() if your content is HTML.
        'build_from_content' => true,
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

        /*
         * Optional per-model-type schema builders (model class => builder).
         *
         * A fallback used ONLY when a model has no explicit stored
         * seo_meta.schema_jsonld and does not override getSEOSchema(): the
         * resolver invokes the mapped builder with the model and emits the
         * nodes it returns. An explicit stored schema always wins.
         *
         * The canonical builder is an invokable class — App\Seo\PostSchema with
         * `public function __invoke(\Illuminate\Database\Eloquent\Model $model): array`
         * returning one or more schema.org nodes (compose them with
         * Rankbeam\Seo\Services\Schema\SchemaGraph::for($model)). A class-string
         * survives config:cache; a Closure does not, so use one only for
         * runtime configuration.
         *
         * Example:
         *   'type_map' => [
         *       App\Models\Post::class => App\Seo\PostSchema::class,
         *   ],
         */
        'type_map' => [
            //
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

        /*
         * Resolver result cache — the scale lever for hot frontends.
         *
         * The SEOResolver runs the full precedence chain (config → global /
         * model-type / route defaults → computed model values → explicit
         * seo_meta → title suffix / canonical / schema) on every frontend
         * render. On a high-traffic site (the reference app does ~20k req/day)
         * that is several DB reads per page. Enable this and a model's resolved
         * SEO is cached as a plain array (rehydrated with SEOData::fromArray(),
         * never as an object — Laravel 13 ships cache.serializable_classes =
         * false, so a cached object returns as __PHP_Incomplete_Class) and a
         * cache hit skips the precedence chain entirely.
         *
         * Entries are keyed by (model class, id, locale, route, request URL)
         * and invalidated automatically when the page's seo_meta row is
         * saved/deleted, when a content field (see getSEOContentFields()) on the
         * model changes, or when any seo_defaults row changes. Correctness with
         * caching ON is identical to OFF.
         *
         * On a taggable store (redis, memcached, array) a model's entries clear
         * via cache tags; on a non-taggable store (file, database) the package
         * falls back to a per-model version stamp — both work, no key-scanning.
         * It uses the `store` configured above. OFF by default: turn it on once
         * you have a shared, persistent cache (redis/memcached) in production.
         */
        'resolver' => [
            'enabled' => env('SEO_RESOLVER_CACHE', false),
            'ttl' => env('SEO_RESOLVER_CACHE_TTL', 3600),
        ],

    ],

];
