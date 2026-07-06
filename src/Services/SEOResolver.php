<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageManager;

/**
 * Core service for resolving SEO data with proper precedence chain.
 *
 * This is the heart of the SEO system. It merges SEO data from multiple
 * sources in a specific order, where later sources override earlier ones
 * (but only for non-null values).
 *
 * ## Precedence Chain (lowest to highest priority)
 *
 * 1. **Base Configuration** (from config/seo.php)
 *    - Site name, Twitter @handle, default robots directive
 *    - Always applied as the foundation
 *
 * 2. **Global Defaults** (SEODefault with scope='global')
 *    - Site-wide templates and defaults from database
 *    - Useful for: default og:image, title templates
 *
 * 3. **Model-Type Defaults** (SEODefault with scope=Model::class)
 *    - Per-model-type defaults (e.g., all Posts use article schema)
 *    - Useful for: blog post templates, product page defaults
 *
 * 4. **Route Defaults** (SEODefault with scope=route.name)
 *    - Per-route overrides (e.g., archive pages are noindex)
 *    - Useful for: static pages, category archives
 *
 * 5. **Computed Values** (SEOComputedBuilder)
 *    - Auto-generated from model attributes
 *    - Uses getSEOTitle() method or common fields like 'title', 'name'
 *    - Extracts description from excerpt/content
 *
 * 6. **Explicit Values** (SEOMeta database record)
 *    - User-entered values from admin panel
 *    - Highest priority - always wins when set
 *
 * ## Example Usage
 *
 * ```php
 * // Resolve SEO for a model
 * $seoData = $resolver->resolve($post);
 *
 * // Resolve for a route without a model
 * $seoData = $resolver->resolveForRoute('blog.index');
 *
 * // Apply programmatic overrides
 * $seoData = $resolver->resolveWithOverrides($baseSeo, ['robots' => 'noindex']);
 * ```
 *
 * @see \Rankbeam\Seo\Data\SEOData For the data structure
 * @see \Rankbeam\Seo\Services\SEODefaultsRepository For defaults retrieval
 * @see \Rankbeam\Seo\Services\SEOComputedBuilder For computed value extraction
 */
class SEOResolver
{
    /**
     * Create a new SEOResolver instance.
     *
     * @param SEODefaultsRepository $defaults Repository for SEO defaults from database
     * @param SEOComputedBuilder $computed Builder for computing SEO values from model content
     */
    public function __construct(
        protected SEODefaultsRepository $defaults,
        protected SEOComputedBuilder $computed,
    ) {}

    /**
     * Re-entrancy depth of resolve().
     *
     * Only a top-level resolve (depth 0) consults the result cache. The schema
     * layer's getSEOSchema() can call back into resolve() (to build a webPage()
     * node from the model's seoData()); that nested resolve must NOT read or
     * write the cache — its result deliberately omits the schema, and it shares
     * the outer call's cache key.
     */
    protected static int $depth = 0;

    /**
     * Resolve SEO data with proper precedence.
     *
     * This is the main entry point for getting fully-resolved SEO data.
     * It merges data from all sources according to the precedence chain.
     *
     * @param Model|null $model The Eloquent model to get SEO for (optional)
     * @param string|null $route The route name for route-specific defaults (auto-detected if null)
     * @param string|null $locale The locale for multi-language support (uses app locale if null)
     * @return SEOData Fully resolved SEO data ready for rendering
     *
     * @example
     * ```php
     * // For a blog post
     * $seoData = $resolver->resolve($post);
     *
     * // For a specific route
     * $seoData = $resolver->resolve($product, 'products.show', 'en');
     *
     * // For the current page without a model
     * $seoData = $resolver->resolve();
     * ```
     */
    public function resolve(
        ?Model $model = null,
        ?string $route = null,
        ?string $locale = null,
    ): SEOData {
        $locale ??= app()->getLocale();

        $cache = $this->resolutionCache();

        // The resolver result cache (opt-in, seo.cache.resolver.enabled) short-
        // circuits the whole precedence chain on a hit. Only model-backed,
        // top-level resolves are cached: a model has a stable (class, id)
        // identity to key and invalidate by, and self::$depth === 0 excludes the
        // nested resolve() the schema layer performs for a webPage() node (its
        // result lacks the schema by design — never cache that under the same
        // key). When the feature is off, $useCache is false and resolve()
        // behaves byte-identically to an uncached package.
        $useCache = $cache->enabled()
            && self::$depth === 0
            && $model !== null
            && $model->getKey() !== null;

        $effectiveRoute = $route;
        $url = null;

        if ($useCache) {
            // Mirror applyRouteDefaults()'s route derivation and capture the
            // canonical-determining request URL, so the key reflects exactly the
            // inputs the chain consumes.
            $effectiveRoute = $route ?? request()?->route()?->getName();
            $url = $this->currentRequestUrl();

            $cached = $cache->get($model::class, $model->getKey(), $locale, $effectiveRoute, $url);

            if ($cached !== null) {
                return $this->applyIndexingGuard($cached);
            }
        }

        self::$depth++;

        try {
            $result = $this->buildResolved($model, $route, $locale);
        } finally {
            self::$depth--;
        }

        if ($useCache) {
            $cache->put($model::class, $model->getKey(), $locale, $effectiveRoute, $url, $result);
        }

        // The indexing guard is applied on the way OUT — never baked into the
        // cached array. The stored entry stays environment-agnostic, and the
        // guard is re-evaluated against the live environment on every call
        // (cache hit or miss), so a shared cache store can never leak a
        // staging-guarded value into production, or vice versa. Correctness
        // with the resolver cache ON stays identical to OFF.
        return $this->applyIndexingGuard($result);
    }

    /**
     * Run the full precedence chain for a model/route/locale.
     *
     * This is the uncached body of {@see resolve()}: it always recomputes from
     * config, database defaults, computed model values, and explicit seo_meta.
     * resolve() wraps it with the optional result cache.
     *
     * @param  Model|null  $model  The Eloquent model to resolve for
     * @param  string|null  $route  The route name (auto-detected from the request if null)
     * @param  string  $locale  The locale to resolve for
     * @return SEOData Fully resolved SEO data
     */
    protected function buildResolved(?Model $model, ?string $route, string $locale): SEOData
    {
        // Layer 0: Base configuration from config/seo.php
        $result = $this->buildBaseConfig($locale);

        // Layer 1: Global defaults from database
        $result = $this->applyGlobalDefaults($result, $locale);

        // Layer 2: Model-type defaults (if we have a model)
        if ($model) {
            $result = $this->applyModelTypeDefaults($result, $model, $locale);
        }

        // Layer 3: Route-specific defaults
        $result = $this->applyRouteDefaults($result, $route, $locale);

        // Layer 4: Computed values from model content
        if ($model) {
            $result = $this->applyComputedValues($result, $model, $locale);
        }

        // Layer 5: Explicit SEO data saved on the model
        if ($model) {
            $result = $this->applyExplicitValues($result, $model, $locale);
        }

        // Post-processing: Apply title suffix, ensure canonical, absolutize images
        $result = $this->applyTitleSuffix($result);
        $result = $this->ensureCanonical($result, $model);
        $result = $this->applyGeneratedOgImage($result, $model);
        $result = $this->ensureAbsoluteImages($result);

        // Layer 6: Computed JSON-LD schema graph — only as a fallback when no
        // explicit schema (stored seo_meta.schema_jsonld or a default layer)
        // already set one.
        if ($model) {
            $result = $this->applyModelSchema($result, $model);
        }

        return $result;
    }

    /**
     * Resolve SEO data for a named route (without a model).
     *
     * Convenience method for static pages and routes that don't have
     * associated models (like "About Us", "Contact", archive pages).
     *
     * @param string $routeName The Laravel route name (e.g., 'pages.about')
     * @param string|null $locale The locale for multi-language support
     * @return SEOData Resolved SEO data for the route
     *
     * @example
     * ```php
     * // In your controller
     * $seoData = $resolver->resolveForRoute('blog.index');
     *
     * // With specific locale
     * $seoData = $resolver->resolveForRoute('pages.contact', 'de');
     * ```
     */
    public function resolveForRoute(string $routeName, ?string $locale = null): SEOData
    {
        return $this->resolve(null, $routeName, $locale);
    }

    /**
     * Resolve any render-surface input to a render-ready SEOData.
     *
     * Accepts the three shapes the facade and the @seo directive support:
     *
     * - **Model|null** — runs the full precedence chain via resolve().
     * - **SEOData** — a hand-built DTO (model-less pages: listings, search,
     *   controller-composed). The DTO is treated as explicit intent: the DB
     *   precedence chain is NOT merged in. Only the render-time gaps are
     *   filled (see prepare()).
     *
     * This is the single entry point behind SEO::render()/toArray()/
     * forInertia() so model and SEOData paths produce an equivalent tag set
     * for equivalent data.
     *
     * @param Model|SEOData|null $source The model, hand-built SEOData, or null
     * @param string|null $route Optional route name (Model/null path only)
     * @param string|null $locale Optional locale (Model/null path only)
     * @return SEOData Render-ready SEO data
     */
    public function resolveSource(
        Model|SEOData|null $source = null,
        ?string $route = null,
        ?string $locale = null,
    ): SEOData {
        if ($source instanceof SEOData) {
            return $this->prepare($source);
        }

        return $this->resolve($source, $route, $locale);
    }

    /**
     * Prepare a hand-built SEOData for rendering.
     *
     * INTERNAL: not part of the public API. Its per-field transforms are an
     * implementation detail of the render surface, not a compatibility
     * contract — do not call it directly or depend on its exact output.
     *
     * A supplied SEOData is explicit intent, so every value the caller set is
     * PRESERVED; only ABSENT (null) fields are filled, mirroring what the
     * resolver chain produces for a model:
     *
     * - canonical / og:url derived from the current URL when null
     *   (via ensureCanonical with no model);
     * - title_suffix applied only when the title lacks it, honoring the
     *   brand-aware seo.title_suffix_skip_when_contains skip list;
     * - relative og:image / twitter:image absolutized via the configured URL
     *   generator (url(), NOT secure_url() — forcing HTTPS breaks non-HTTPS
     *   dev);
     * - og:site_name filled from config when absent;
     * - locale filled from the current app locale when absent (so og:locale
     *   renders).
     *
     * The DB precedence chain (global / model-type / route / seo_meta
     * defaults) is deliberately NOT merged — a hand-built SEOData is the
     * caller's explicit statement of intent.
     *
     * TagRenderer is never modified by this step; preparation happens before
     * it, so direct TagRenderer::render($data) callers are unaffected.
     *
     * @param SEOData $seoData The hand-built SEO data
     * @return SEOData Render-ready SEO data with absent fields filled
     */
    protected function prepare(SEOData $seoData): SEOData
    {
        // Site-level identity that the base-config layer supplies for the
        // model path — fill only when the caller left it absent.
        if ($seoData->ogSiteName === null) {
            $siteName = config('seo.site_name', config('app.name'));
            if ($siteName !== null && $siteName !== '') {
                $seoData = $seoData->with('ogSiteName', $siteName);
            }
        }

        if ($seoData->locale === null) {
            $seoData = $seoData->with('locale', app()->getLocale());
        }

        // The same render-time post-processing the model path runs, reused
        // verbatim so both paths normalize identically.
        $seoData = $this->applyTitleSuffix($seoData);
        $seoData = $this->ensureCanonical($seoData, null);
        $seoData = $this->applyGeneratedOgImage($seoData);
        $seoData = $this->ensureAbsoluteImages($seoData);

        // A hand-built SEOData (listing / search / controller-composed page) is
        // rendered on non-production too, so it gets the same non-production
        // indexing guard as a model-backed page.
        $seoData = $this->applyIndexingGuard($seoData);

        return $seoData;
    }

    /**
     * Render SEO tags as HTML for a model, a hand-built SEOData, or null.
     *
     * @param Model|SEOData|null $source The model, hand-built SEOData, or null
     * @param string|null $route Optional route name (Model/null path only)
     * @param string|null $locale Optional locale (Model/null path only)
     * @return string HTML string with all meta tags
     */
    public function render(
        Model|SEOData|null $source = null,
        ?string $route = null,
        ?string $locale = null,
    ): string {
        return $this->renderer()->render($this->resolveSource($source, $route, $locale));
    }

    /**
     * Get SEO data as a structured array (Vue/React/API).
     *
     * @param Model|SEOData|null $source The model, hand-built SEOData, or null
     * @param string|null $route Optional route name (Model/null path only)
     * @param string|null $locale Optional locale (Model/null path only)
     * @return array<string, mixed>
     */
    public function toArray(
        Model|SEOData|null $source = null,
        ?string $route = null,
        ?string $locale = null,
    ): array {
        return $this->renderer()->toArray($this->resolveSource($source, $route, $locale));
    }

    /**
     * Get SEO data formatted for Inertia's Head component.
     *
     * @param Model|SEOData|null $source The model, hand-built SEOData, or null
     * @param string|null $route Optional route name (Model/null path only)
     * @param string|null $locale Optional locale (Model/null path only)
     * @return array<string, mixed>
     */
    public function forInertia(
        Model|SEOData|null $source = null,
        ?string $route = null,
        ?string $locale = null,
    ): array {
        return $this->renderer()->toInertiaHead($this->resolveSource($source, $route, $locale));
    }

    /**
     * Resolve the shared TagRenderer instance.
     */
    protected function renderer(): TagRenderer
    {
        return app(TagRenderer::class);
    }

    /**
     * Resolve the shared resolution-cache instance.
     *
     * Looked up lazily (like the TagRenderer) so the constructor signature is
     * unchanged for anyone building SEOResolver directly.
     */
    protected function resolutionCache(): SEOResolutionCache
    {
        return app(SEOResolutionCache::class);
    }

    /**
     * Resolve the shared indexing-guard instance.
     *
     * Looked up lazily (like the TagRenderer and resolution cache) so the
     * constructor signature is unchanged for anyone building SEOResolver
     * directly.
     */
    protected function indexingGuard(): IndexingGuard
    {
        return app(IndexingGuard::class);
    }

    /**
     * Force `noindex,nofollow` when the non-production indexing guard is active.
     *
     * This is the ONE place the resolver's "explicit / highest layer wins" rule
     * is deliberately inverted. It runs ABOVE the entire precedence chain
     * (config → defaults → computed → explicit seo_meta), so even a page whose
     * seo_meta stored `index,follow` still resolves to noindex on a
     * non-production environment. That inversion is intentional and one-
     * directional: wrongly indexing a staging clone is an SEO disaster, while
     * wrongly noindexing it is a harmless no-op, so the guard is a floor the
     * stored value cannot punch through.
     *
     * When the guard is inactive (production, or the feature disabled) the
     * input is returned unchanged — no allocation, byte-identical output.
     *
     * @param SEOData $seoData The fully resolved SEO data
     * @return SEOData The same data, or a copy forced to noindex,nofollow
     */
    protected function applyIndexingGuard(SEOData $seoData): SEOData
    {
        if (! $this->indexingGuard()->active()) {
            return $seoData;
        }

        return $seoData->with('robots', IndexingGuard::DIRECTIVE);
    }

    /**
     * The canonical-determining URL of the current request, or null in a
     * request-less context (console, sitemap generation, queue worker).
     *
     * url()->current() carries the scheme, host, and path (never the query
     * string), which is exactly what ensureCanonical()'s fallback feeds into the
     * canonical / og:url — so including it in the cache key keeps a cached
     * resolution correct for a model whose canonical derives from the request
     * URL, while two requests to the same path (differing only by query string)
     * still share one entry.
     */
    protected function currentRequestUrl(): ?string
    {
        if (! request()) {
            return null;
        }

        try {
            return url()->current();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve with explicit overrides applied on top.
     *
     * Useful for programmatic SEO modifications, like setting noindex
     * for paginated pages or overriding titles for A/B testing.
     *
     * @param SEOData $base The base SEO data to extend
     * @param array<string, mixed> $overrides Key-value pairs to override
     * @return SEOData New SEOData with overrides applied
     *
     * @example
     * ```php
     * // Make paginated pages noindex
     * if ($page > 1) {
     *     $seoData = $resolver->resolveWithOverrides($seoData, [
     *         'robots' => 'noindex,follow',
     *         'title' => "Page $page - {$seoData->title}",
     *     ]);
     * }
     * ```
     */
    public function resolveWithOverrides(SEOData $base, array $overrides): SEOData
    {
        $overrideData = SEOData::fromArray($overrides);

        return $base->merge($overrideData);
    }

    /**
     * Build base configuration from config/seo.php.
     *
     * @param string $locale Current locale
     * @return SEOData Base configuration SEO data
     */
    protected function buildBaseConfig(string $locale): SEOData
    {
        return new SEOData(
            locale: $locale,
            ogSiteName: config('seo.site_name', config('app.name')),
            twitterSite: config('seo.twitter_site'),
            twitterCard: config('seo.default_twitter_card', 'summary_large_image'),
            robots: config('seo.default_robots', 'index,follow'),
            ogImage: config('seo.default_og_image'),
        );
    }

    /**
     * Apply global defaults from database.
     *
     * @param SEOData $result Current SEO data
     * @param string $locale Current locale
     * @return SEOData SEO data with global defaults applied
     */
    protected function applyGlobalDefaults(SEOData $result, string $locale): SEOData
    {
        $globalDefaults = $this->defaults->global($locale);

        if ($globalDefaults) {
            $result = $result->merge($globalDefaults);
        }

        return $result;
    }

    /**
     * Apply model-type defaults from database.
     *
     * @param SEOData $result Current SEO data
     * @param Model $model The Eloquent model
     * @param string $locale Current locale
     * @return SEOData SEO data with model-type defaults applied
     */
    protected function applyModelTypeDefaults(SEOData $result, Model $model, string $locale): SEOData
    {
        $typeDefaults = $this->defaults->forModelType($model, $locale);

        if ($typeDefaults) {
            $result = $result->merge($typeDefaults);
        }

        return $result;
    }

    /**
     * Apply route-specific defaults from database.
     *
     * @param SEOData $result Current SEO data
     * @param string|null $route Route name (auto-detected if null)
     * @param string $locale Current locale
     * @return SEOData SEO data with route defaults applied
     */
    protected function applyRouteDefaults(SEOData $result, ?string $route, string $locale): SEOData
    {
        $route ??= request()?->route()?->getName();

        if ($route) {
            $routeDefaults = $this->defaults->forRoute($route, $locale);
            if ($routeDefaults) {
                $result = $result->merge($routeDefaults);
            }
        }

        return $result;
    }

    /**
     * Apply computed values from model content.
     *
     * @param SEOData $result Current SEO data
     * @param Model $model The Eloquent model
     * @param string $locale Current locale
     * @return SEOData SEO data with computed values applied
     */
    protected function applyComputedValues(SEOData $result, Model $model, string $locale): SEOData
    {
        $computedData = $this->computed->fromModel($model, $locale);

        return $result->merge($computedData);
    }

    /**
     * Apply explicit SEO values saved on the model.
     *
     * @param SEOData $result Current SEO data
     * @param Model $model The Eloquent model
     * @return SEOData SEO data with explicit values applied
     */
    protected function applyExplicitValues(SEOData $result, Model $model, string $locale): SEOData
    {
        // Check if model uses HasSEO trait
        if (! method_exists($model, 'seoMeta')) {
            return $result;
        }

        $explicit = SEOData::fromModel($model, $locale);

        // Blank explicit-value policy: a persisted '' / '   ' is an explicit
        // value and would override (via "last non-null wins") the computed
        // fallback / configured default, blanking the page. When opted in, drop
        // blank string fields so they fall through. Persistence-layer only — it
        // is applied to the freshly-extracted stored SEOData, never to the
        // general DTO or to a higher layer's intentional value.
        if (config('seo.resolver.blank_is_unset', false)) {
            $explicit = $this->unsetBlankStrings($explicit);
        }

        return $result->merge($explicit);
    }

    /**
     * Normalize blank/whitespace string fields on a stored SEOData to null.
     *
     * Implements the `seo.resolver.blank_is_unset` policy. Only the STRING
     * fields are considered — arrays (tags, focus_keywords, alternates), the
     * JSON-LD schema, and DateTime fields are left untouched, and the literal
     * string "0" is preserved (it is `empty()` in PHP but a meaningful value).
     * A field is unset only when it is a string that is empty after trimming.
     *
     * @param SEOData $explicit The stored/explicit SEO data extracted from seo_meta
     * @return SEOData The same data with blank string fields normalized to null
     */
    protected function unsetBlankStrings(SEOData $explicit): SEOData
    {
        $stringFields = [
            'title', 'description', 'canonical', 'robots',
            'ogTitle', 'ogDescription', 'ogImage', 'ogType', 'ogSiteName', 'ogUrl',
            'twitterTitle', 'twitterDescription', 'twitterImage', 'twitterCard',
            'twitterSite', 'twitterCreator',
            'author', 'section', 'locale',
        ];

        foreach ($stringFields as $field) {
            $value = $explicit->{$field};

            if (is_string($value) && trim($value) === '') {
                $explicit = $explicit->with($field, null);
            }
        }

        return $explicit;
    }

    /**
     * Re-entrancy guard for the computed-schema layer.
     *
     * Keyed by model+locale. A model's getSEOSchema() typically builds a
     * WebPage node from SchemaGraph::for($this)->webPage(), which resolves the
     * model's seoData() again — re-entering this resolver. The guard makes that
     * nested resolve skip the schema layer (the WebPage node never needs the
     * schema), so composition terminates instead of recursing forever.
     *
     * @var array<string, bool>
     */
    protected static array $resolvingSchema = [];

    /**
     * Apply the computed JSON-LD schema graph as a fallback.
     *
     * Mirrors the explicit-over-computed precedence used everywhere else: an
     * explicit schema (a stored seo_meta.schema_jsonld, already merged in by
     * applyExplicitValues, or one supplied by a default layer) is
     * AUTHORITATIVE — it is emitted as-is and the hook / type-map is NOT
     * invoked. Only when no schema is present does the model's
     * getSEOSchema() hook (or the seo.schema.type_map config mapping) produce
     * the graph.
     *
     * @param SEOData $result The resolved SEO data so far
     * @param Model $model The Eloquent model
     * @return SEOData SEO data with a computed schema graph filled in, or unchanged
     */
    protected function applyModelSchema(SEOData $result, Model $model): SEOData
    {
        // Explicit schema is authoritative — never override it, never call the
        // hook for this model.
        if ($result->schemaJsonld !== null && $result->schemaJsonld !== []) {
            return $result;
        }

        $key = $model::class . ':' . ($model->getKey() ?? spl_object_id($model));

        // Re-entrant (the hook resolved seoData() again) — break the cycle.
        if (isset(self::$resolvingSchema[$key])) {
            return $result;
        }

        self::$resolvingSchema[$key] = true;

        try {
            $schema = $this->computeModelSchema($model);
        } finally {
            unset(self::$resolvingSchema[$key]);
        }

        if ($schema === null || $schema === []) {
            return $result;
        }

        return $result->with('schemaJsonld', $schema);
    }

    /**
     * Produce the computed schema graph for a model, hook first.
     *
     * Precedence: the per-model getSEOSchema() hook (most specific) wins when
     * it returns a non-empty graph; otherwise the seo.schema.type_map config
     * mapping (model class → builder) is consulted. Returns null when neither
     * yields anything.
     *
     * @param Model $model The Eloquent model
     * @return array<int|string, mixed>|null One or more schema.org nodes, or null
     */
    protected function computeModelSchema(Model $model): ?array
    {
        if (method_exists($model, 'getSEOSchema')) {
            $nodes = $model->getSEOSchema();

            if (is_array($nodes) && $nodes !== []) {
                return $nodes;
            }
        }

        return $this->schemaFromTypeMap($model);
    }

    /**
     * Resolve a schema graph from the seo.schema.type_map config mapping.
     *
     * The map keys are model class names; an exact match wins, otherwise the
     * first mapped class the model is an instance of (so a base-class mapping
     * covers subclasses). The value is a builder — a Closure, an invokable
     * class-string (resolved through the container), an invokable object, or
     * any callable — invoked with the model and expected to return nodes.
     *
     * @param Model $model The Eloquent model
     * @return array<int|string, mixed>|null
     */
    protected function schemaFromTypeMap(Model $model): ?array
    {
        /** @var array<class-string, mixed> $map */
        $map = (array) config('seo.schema.type_map', []);

        if ($map === []) {
            return null;
        }

        $builder = $map[$model::class] ?? null;

        if ($builder === null) {
            foreach ($map as $class => $candidate) {
                if (is_string($class) && $model instanceof $class) {
                    $builder = $candidate;
                    break;
                }
            }
        }

        if ($builder === null) {
            return null;
        }

        $nodes = $this->callSchemaBuilder($builder, $model);

        return is_array($nodes) && $nodes !== [] ? $nodes : null;
    }

    /**
     * Invoke a type-map builder for a model.
     *
     * A class-string is resolved through the container first (so the builder
     * can have its own dependencies), then invoked. The canonical config form
     * is an invokable class (`__invoke(Model $model): array`), which survives
     * config:cache — a Closure does not and is supported only for runtime use.
     *
     * @param mixed $builder The configured builder
     * @param Model $model The Eloquent model
     */
    protected function callSchemaBuilder(mixed $builder, Model $model): mixed
    {
        if ($builder instanceof \Closure) {
            return $builder($model);
        }

        if (is_string($builder) && class_exists($builder)) {
            $builder = app($builder);
        }

        if (is_object($builder) && is_callable($builder)) {
            return $builder($model);
        }

        if (is_callable($builder)) {
            return $builder($model);
        }

        return null;
    }

    /**
     * Apply title suffix from config.
     *
     * Appends the configured suffix (e.g., " | Site Name") to the title
     * if it's not already present. A brand-aware skip list
     * (seo.title_suffix_skip_when_contains) suppresses the suffix when the
     * title already mentions the brand as a whole word, avoiding a
     * redundant double-brand title.
     *
     * @param SEOData $seoData Current SEO data
     * @return SEOData SEO data with title suffix applied
     */
    protected function applyTitleSuffix(SEOData $seoData): SEOData
    {
        if (! $seoData->title) {
            return $seoData;
        }

        $suffix = config('seo.title_suffix');

        if (! $suffix || str_ends_with($seoData->title, $suffix)) {
            return $seoData;
        }

        if ($this->titleContainsBrandToken($seoData->title)) {
            return $seoData;
        }

        return $seoData->with('title', $seoData->title . $suffix);
    }

    /**
     * Determine whether the title already mentions a configured brand token.
     *
     * Tokens from seo.title_suffix_skip_when_contains are matched
     * case-insensitively on a word boundary, so a title that already carries
     * the brand keeps a single brand mention instead of gaining the suffix.
     *
     * @param string $title The resolved title
     * @return bool True when a skip token is present as a whole word
     */
    protected function titleContainsBrandToken(string $title): bool
    {
        /** @var array<int, string> $tokens */
        $tokens = (array) config('seo.title_suffix_skip_when_contains', []);

        foreach ($tokens as $token) {
            $token = is_string($token) ? trim($token) : '';

            if ($token === '') {
                continue;
            }

            if (preg_match('/\b' . preg_quote($token, '/') . '\b/iu', $title) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure canonical URL is set.
     *
     * Sets canonical URL from:
     * 1. Model's getUrlForSEO() method (if available)
     * 2. Current request URL (fallback)
     *
     * Derived canonicals always have their query string stripped: query
     * parameters (tracking, pagination, filters) create duplicate-content
     * canonical targets. An explicitly set canonical (admin-entered or from
     * a higher layer) is preserved verbatim, query string included.
     *
     * Also sets og:url if not already set.
     *
     * @param SEOData $seoData Current SEO data
     * @param Model|null $model The Eloquent model
     * @return SEOData SEO data with canonical URL ensured
     */
    protected function ensureCanonical(SEOData $seoData, ?Model $model): SEOData
    {
        if ($seoData->canonical) {
            // Canonical is set, but ensure og:url is also set
            if (! $seoData->ogUrl) {
                return $seoData->with('ogUrl', $seoData->canonical);
            }

            return $seoData;
        }

        $canonical = null;

        // Try to get canonical from model
        if ($model && method_exists($model, 'getUrlForSEO')) {
            $canonical = $model->getUrlForSEO();
        }

        // Fallback to current URL
        if (! $canonical && request()) {
            $canonical = url()->current();
        }

        if (! $canonical) {
            return $seoData;
        }

        // Strip query string for a clean canonical
        $canonical = strtok($canonical, '?') ?: $canonical;

        // Apply both canonical and og:url
        $result = $seoData->with('canonical', $canonical);

        if (! $result->ogUrl) {
            $result = $result->with('ogUrl', $canonical);
        }

        return $result;
    }

    /**
     * Opt-in: when no og:image survived the merge chain and generation is
     * enabled, point og:image at a generated 1200x630 card.
     *
     * Uses the generator's existence-gated urlFor(), so a web request never
     * spawns a browser and a page never links a not-yet-generated (404) image
     * — the seo:og-images command (or a model-save hook) produces the file.
     * Runs before ensureAbsoluteImages() so a relative storage URL gets
     * absolutized like any other og:image value.
     */
    protected function applyGeneratedOgImage(SEOData $seoData, ?Model $model = null): SEOData
    {
        if (! config('seo.og_image.enabled', false)) {
            return $seoData;
        }

        // Only replace a "placeholder" image — nothing set, or the site-wide
        // static default_og_image. An explicit per-model og:image always wins
        // over a generated card. The computed layer may already have
        // absolutized the default before this step, so compare the normalized
        // (absolutized) forms rather than the raw strings.
        $current = $seoData->ogImage;
        $default = config('seo.default_og_image');

        $isPlaceholder = $current === null || $current === '' || $current === $default;
        if (! $isPlaceholder && is_string($default) && $default !== '') {
            $isPlaceholder = $this->absolutizeUrl($current) === $this->absolutizeUrl($default);
        }

        if (! $isPlaceholder) {
            return $seoData;
        }

        $template = app(OgImageManager::class)->templateFor($model);
        $url = app(OgImageGenerator::class)->urlFor($seoData, $template);

        return $url !== null ? $seoData->with('ogImage', $url) : $seoData;
    }

    /**
     * Ensure social-share image URLs are absolute.
     *
     * The OG spec requires og:image to be a full URL. Computed fallbacks are
     * already absolutized by SEOComputedBuilder, but explicit values (admin
     * panels store paths like `/images/share.jpg`) and database defaults
     * arrive verbatim — normalize every winning value at the end of the
     * chain so the rendered output is consistent regardless of which layer
     * produced it.
     *
     * @param SEOData $seoData Current SEO data
     * @return SEOData SEO data with absolute ogImage/twitterImage
     */
    protected function ensureAbsoluteImages(SEOData $seoData): SEOData
    {
        foreach (['ogImage', 'twitterImage'] as $field) {
            $value = $seoData->{$field};

            if ($value === null || $value === '') {
                continue;
            }

            $absolute = $this->absolutizeUrl($value);

            if ($absolute !== $value) {
                $seoData = $seoData->with($field, $absolute);
            }
        }

        return $seoData;
    }

    /**
     * Make a possibly-relative URL absolute against the app URL.
     *
     * Mirrors SEOComputedBuilder::normalizeImageUrl() so explicit and
     * computed values normalize identically.
     */
    protected function absolutizeUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative URL
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return url($url);
    }

    /**
     * Get the registry of named sitemap sources.
     *
     * Exposed here so the SEO facade (whose root is this resolver) can
     * offer `SEO::sitemaps()->register($name, $source)`.
     *
     * @example
     * ```php
     * SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);
     * ```
     */
    public function sitemaps(): \Rankbeam\Seo\Services\Sitemap\SitemapRegistry
    {
        return app(\Rankbeam\Seo\Services\Sitemap\SitemapRegistry::class);
    }

    /**
     * Get the llms.txt builder.
     *
     * Exposed here so the SEO facade (whose root is this resolver) can offer
     * `SEO::llmsTxt()->build()` / `->generate()`. The builder reuses the shared
     * sitemap source registry, so the same `SEO::sitemaps()->register(...)`
     * sources feed both artifacts.
     *
     * @example
     * ```php
     * $markdown = SEO::llmsTxt()->build();
     * SEO::llmsTxt()->generate();
     * ```
     */
    public function llmsTxt(): \Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder
    {
        return app(\Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder::class);
    }

    /**
     * Get the AI-crawler catalog + policy registry.
     *
     * Exposed here so the SEO facade can offer `SEO::aiCrawlers()` — the curated
     * catalog of known AI bots and the resolved allow/disallow policy that the
     * robots.txt builder renders.
     *
     * @example
     * ```php
     * SEO::aiCrawlers()->all();                  // the catalog
     * SEO::aiCrawlers()->actionFor('gptbot');    // 'allow' | 'disallow'
     * ```
     */
    public function aiCrawlers(): \Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry
    {
        return app(\Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry::class);
    }

    /**
     * Get the robots.txt builder.
     *
     * Exposed here so the SEO facade can offer `SEO::robotsTxt()->build()` /
     * `->generate()` / `->aiDirectives()`. The builder renders the AI-crawler
     * policy from {@see aiCrawlers()} as robots.txt directives.
     *
     * @example
     * ```php
     * echo SEO::robotsTxt()->aiDirectives();   // paste-able AI block
     * SEO::robotsTxt()->generate();            // write public/robots.txt
     * ```
     */
    public function robotsTxt(): \Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder
    {
        return app(\Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder::class);
    }

    /**
     * Get the markdown-for-bots source registry.
     *
     * Exposed here so the SEO facade can offer `SEO::markdown()->register(...)` —
     * register a markdown source for a named route so the content-negotiation
     * middleware can serve clean markdown to AI crawlers.
     *
     * @example
     * ```php
     * SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_md);
     * ```
     */
    public function markdown(): \Rankbeam\Seo\Services\Markdown\MarkdownRegistry
    {
        return app(\Rankbeam\Seo\Services\Markdown\MarkdownRegistry::class);
    }

    /**
     * The ordered precedence layers, keyed, with the RAW SEOData each one
     * contributes — the read-only hook behind `seo:explain`.
     *
     * This reuses the resolver's own layer sources (buildBaseConfig, the
     * SEODefaultsRepository, the SEOComputedBuilder, and the explicit seo_meta
     * extraction with the same blank_is_unset normalization applyExplicitValues
     * uses), collected in the SAME order buildResolved() merges them — WITHOUT
     * running the merge. It deliberately does not touch resolve()/buildResolved(),
     * so the render hot path is unaffected; an explainer merges these for
     * attribution while the real resolve() remains the source of truth for the
     * final, post-processed values.
     *
     * Layer keys (lowest → highest precedence): config, global, model-type,
     * route, computed, explicit. A layer that does not apply (no model, no
     * route) is null.
     *
     * @param  Model|null  $model  The Eloquent model, or null for a route-only page
     * @param  string|null  $route  The route name (auto-detected from the request if null, like applyRouteDefaults)
     * @param  string|null  $locale  The locale (app locale if null)
     * @return array<string, SEOData|null> Ordered layer key => contribution
     */
    public function layerContributions(?Model $model = null, ?string $route = null, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        // Mirror applyRouteDefaults()'s route derivation so the route layer here
        // matches the one the resolver actually applies.
        $route ??= request()?->route()?->getName();

        return [
            'config' => $this->buildBaseConfig($locale),
            'global' => $this->defaults->global($locale),
            'model-type' => $model ? $this->defaults->forModelType($model, $locale) : null,
            'route' => $route ? $this->defaults->forRoute($route, $locale) : null,
            'computed' => $model ? $this->computed->fromModel($model, $locale) : null,
            'explicit' => $model ? $this->explicitContribution($model, $locale) : null,
        ];
    }

    /**
     * The explicit (seo_meta) layer's contribution, mirroring
     * applyExplicitValues(): the stored SEOData with the same blank_is_unset
     * normalization, or null when the model carries no seoMeta relation.
     */
    protected function explicitContribution(Model $model, string $locale): ?SEOData
    {
        if (! method_exists($model, 'seoMeta')) {
            return null;
        }

        // Faithful to applyExplicitValues(): the SAME SEOData::fromModel() +
        // blank_is_unset the resolver merges — including the all-null DTO
        // fromModel() returns for a model with no stored seo_meta row (its
        // og_type/twitter_card defaults suppressed). Because that DTO
        // contributes nothing, the trace attributes og_type/twitter_card to the
        // real lower layer that set them (e.g. computed og_type='article'),
        // matching what resolve() renders — the "cannot drift from what renders"
        // guarantee.
        $explicit = SEOData::fromModel($model, $locale);

        if (config('seo.resolver.blank_is_unset', false)) {
            $explicit = $this->unsetBlankStrings($explicit);
        }

        return $explicit;
    }

    /**
     * Resolve SEO data for multiple models at once.
     *
     * Useful for sitemap generation or listing pages where you need
     * SEO data for many items efficiently.
     *
     * @param iterable<Model> $models Collection of models
     * @param string|null $locale The locale for multi-language support
     * @return array<int, SEOData> Array of resolved SEO data indexed by model key
     */
    public function resolveMany(iterable $models, ?string $locale = null): array
    {
        $results = [];

        foreach ($models as $model) {
            $results[$model->getKey()] = $this->resolve($model, null, $locale);
        }

        return $results;
    }
}
