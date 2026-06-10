<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Fibonoir\LaravelSEO\Data\SEOData;
use Fibonoir\LaravelSEO\Services\SEOResolver;

/**
 * SEO Facade
 *
 * Provides static access to the SEO Resolver service.
 *
 * @method static SEOData resolve(?Model $model = null, ?string $route = null, ?string $locale = null)
 * @method static SEOData resolveForRoute(string $routeName, ?string $locale = null)
 * @method static SEOData resolveWithOverrides(SEOData $base, array $overrides)
 * @method static string render(?Model $model = null, ?string $route = null, ?string $locale = null)
 * @method static array toArray(?Model $model = null, ?string $route = null, ?string $locale = null)
 * @method static \Fibonoir\LaravelSEO\Services\Sitemap\SitemapRegistry sitemaps()
 *
 * @see \Fibonoir\LaravelSEO\Services\SEOResolver
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
     * @param  Model|null  $model  The model to render SEO tags for
     * @param  string|null  $route  Optional route name
     * @param  string|null  $locale  Optional locale
     */
    public static function render(?Model $model = null, ?string $route = null, ?string $locale = null): string
    {
        $resolver = static::getFacadeRoot();
        $renderer = app(\Fibonoir\LaravelSEO\Services\TagRenderer::class);
        $seoData = $resolver->resolve($model, $route, $locale);

        return $renderer->render($seoData);
    }

    /**
     * Get SEO data as an array.
     *
     * Useful for Vue/React frontends or API responses.
     * Returns a structured array compatible with Inertia's Head component
     * or react-helmet-async.
     *
     * @param  Model|null  $model  The model to get SEO data for
     * @param  string|null  $route  Optional route name
     * @param  string|null  $locale  Optional locale
     * @return array<string, mixed>
     */
    public static function toArray(?Model $model = null, ?string $route = null, ?string $locale = null): array
    {
        $resolver = static::getFacadeRoot();
        $renderer = app(\Fibonoir\LaravelSEO\Services\TagRenderer::class);
        $seoData = $resolver->resolve($model, $route, $locale);

        return $renderer->toArray($seoData);
    }

    /**
     * Get SEO data formatted for Inertia's Head component.
     *
     * Returns data in the exact format expected by Inertia.js
     * for server-side rendering of meta tags.
     *
     * @param  Model|null  $model  The model to get SEO data for
     * @param  string|null  $route  Optional route name
     * @param  string|null  $locale  Optional locale
     * @return array<string, mixed>
     */
    public static function forInertia(?Model $model = null, ?string $route = null, ?string $locale = null): array
    {
        $resolver = static::getFacadeRoot();
        $renderer = app(\Fibonoir\LaravelSEO\Services\TagRenderer::class);
        $seoData = $resolver->resolve($model, $route, $locale);

        return $renderer->toInertiaHead($seoData);
    }
}
