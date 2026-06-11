<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services;

use Illuminate\Database\Eloquent\Model;
use Fibonoir\LaravelSEO\Data\SEOData;

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
 * @see \Fibonoir\LaravelSEO\Data\SEOData For the data structure
 * @see \Fibonoir\LaravelSEO\Services\SEODefaultsRepository For defaults retrieval
 * @see \Fibonoir\LaravelSEO\Services\SEOComputedBuilder For computed value extraction
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
            $result = $this->applyExplicitValues($result, $model);
        }

        // Post-processing: Apply title suffix, ensure canonical, absolutize images
        $result = $this->applyTitleSuffix($result);
        $result = $this->ensureCanonical($result, $model);
        $result = $this->ensureAbsoluteImages($result);

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
    protected function applyExplicitValues(SEOData $result, Model $model): SEOData
    {
        // Check if model uses HasSEO trait
        if (! method_exists($model, 'seoMeta')) {
            return $result;
        }

        $explicit = SEOData::fromModel($model);

        return $result->merge($explicit);
    }

    /**
     * Apply title suffix from config.
     *
     * Appends the configured suffix (e.g., " | Site Name") to the title
     * if it's not already present.
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

        return $seoData->with('title', $seoData->title . $suffix);
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
    public function sitemaps(): \Fibonoir\LaravelSEO\Services\Sitemap\SitemapRegistry
    {
        return app(\Fibonoir\LaravelSEO\Services\Sitemap\SitemapRegistry::class);
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
