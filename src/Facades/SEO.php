<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\SEOResolver;

/**
 * SEO Facade
 *
 * Provides static access to the SEO Resolver service.
 *
 * @method static SEOData resolve(?Model $model = null, ?string $route = null, ?string $locale = null)
 * @method static SEOData resolveForRoute(string $routeName, ?string $locale = null)
 * @method static SEOData resolveWithOverrides(SEOData $base, array $overrides)
 * @method static SEOData resolveSource(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null)
 * @method static string render(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null)
 * @method static array toArray(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null)
 * @method static array forInertia(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null)
 * @method static \Rankbeam\Seo\Services\Sitemap\SitemapRegistry sitemaps()
 *
 * @see \Rankbeam\Seo\Services\SEOResolver
 */
class SEO extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'seo';
    }

    /**
     * Resolve SEO data for a model.
     *
     * This is a convenience method that resolves SEO data and applies the
     * full precedence chain: global → model-type → route → computed → explicit.
     *
     * @param  Model|null  $model  The model to resolve SEO data for
     * @param  string|null  $route  Optional route name for route-specific defaults
     * @param  string|null  $locale  Optional locale for multilingual sites
     */
    public static function resolve(?Model $model = null, ?string $route = null, ?string $locale = null): SEOData
    {
        return static::getFacadeRoot()->resolve($model, $route, $locale);
    }

    /**
     * Resolve SEO data for a named route (without a model).
     *
     * Use this for static pages that don't have an associated model,
     * like home pages, contact pages, or archive listings.
     *
     * @param  string  $routeName  The named route to resolve defaults for
     * @param  string|null  $locale  Optional locale for multilingual sites
     */
    public static function forRoute(string $routeName, ?string $locale = null): SEOData
    {
        return static::getFacadeRoot()->resolveForRoute($routeName, $locale);
    }

    /**
     * Render SEO tags as HTML.
     *
     * Returns a complete HTML string containing all SEO meta tags,
     * ready to be inserted in the <head> section.
     *
     * Accepts a Model (runs the full precedence chain), a hand-built
     * SEOData (model-less pages — listings, search, controller-composed;
     * absent fields are filled but the DB precedence chain is not merged),
     * or null (the current page).
     *
     * @param  Model|SEOData|null  $source  The model, hand-built SEOData, or null
     * @param  string|null  $route  Optional route name (Model/null path only)
     * @param  string|null  $locale  Optional locale (Model/null path only)
     */
    public static function render(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null): string
    {
        return static::getFacadeRoot()->render($source, $route, $locale);
    }

    /**
     * Get SEO data as an array.
     *
     * Useful for Vue/React frontends or API responses.
     * Returns a structured array compatible with Inertia's Head component
     * or react-helmet-async.
     *
     * @param  Model|SEOData|null  $source  The model, hand-built SEOData, or null
     * @param  string|null  $route  Optional route name (Model/null path only)
     * @param  string|null  $locale  Optional locale (Model/null path only)
     * @return array<string, mixed>
     */
    public static function toArray(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null): array
    {
        return static::getFacadeRoot()->toArray($source, $route, $locale);
    }

    /**
     * Get SEO data formatted for Inertia's Head component.
     *
     * Returns data in the exact format expected by Inertia.js
     * for server-side rendering of meta tags.
     *
     * @param  Model|SEOData|null  $source  The model, hand-built SEOData, or null
     * @param  string|null  $route  Optional route name (Model/null path only)
     * @param  string|null  $locale  Optional locale (Model/null path only)
     * @return array<string, mixed>
     */
    public static function forInertia(Model|SEOData|null $source = null, ?string $route = null, ?string $locale = null): array
    {
        return static::getFacadeRoot()->forInertia($source, $route, $locale);
    }
}
