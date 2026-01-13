<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Fibonoir\LaravelSEO\Data\SEOData;
use Fibonoir\LaravelSEO\Models\SEODefault;

/**
 * Repository for retrieving SEO defaults from database and config.
 *
 * This repository provides cached access to SEO default templates stored
 * in the `seo_defaults` table. Defaults can be scoped to:
 *
 * - **Global**: Site-wide defaults (scope = 'global')
 * - **Model Type**: Per-model-class defaults (scope = 'App\Models\Post')
 * - **Route**: Per-route defaults (scope = 'blog.index')
 *
 * ## Database Schema
 *
 * The `seo_defaults` table stores templates and defaults:
 * - `scope`: The scope identifier (global, model class, route name)
 * - `locale`: Language code for multi-language support
 * - `title_template`: Title template with placeholders
 * - `description_template`: Description template with placeholders
 * - `og_image_default`: Default Open Graph image
 * - `robots_default`: Default robots directive
 * - `schema_defaults`: Default JSON-LD schema
 *
 * ## Template Placeholders
 *
 * Templates support these placeholders:
 * - `{site_name}`: Site name from config
 * - `{year}`: Current year
 * - `{title}`: Model's title (processed in HasSEO trait)
 *
 * ## Caching
 *
 * All defaults are cached for performance. Cache is automatically
 * invalidated when SEODefault models are saved/deleted.
 *
 * @see \Fibonoir\LaravelSEO\Models\SEODefault For the Eloquent model
 * @see \Fibonoir\LaravelSEO\Services\SEOResolver For how defaults are used
 */
class SEODefaultsRepository
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Get global defaults for a locale.
     *
     * Global defaults apply to all pages as a fallback.
     * Typically set site-wide title template and default og:image.
     *
     * @param string $locale The locale code (e.g., 'en', 'de')
     * @return SEOData|null Global defaults or null if not set
     *
     * @example
     * ```php
     * $global = $repository->global('en');
     * // Returns SEOData with site-wide defaults
     * ```
     */
    public function global(string $locale): ?SEOData
    {
        return $this->getCached('global', $locale);
    }

    /**
     * Get defaults for a specific model type.
     *
     * Model-type defaults apply to all instances of a model class.
     * For example, all Posts could have og:type='article' by default.
     *
     * @param Model $model The Eloquent model instance
     * @param string $locale The locale code
     * @return SEOData|null Model-type defaults or null if not set
     *
     * @example
     * ```php
     * // In seo_defaults: scope = 'App\Models\Post'
     * $defaults = $repository->forModelType($post, 'en');
     * ```
     */
    public function forModelType(Model $model, string $locale): ?SEOData
    {
        $scope = get_class($model);

        return $this->getCached($scope, $locale);
    }

    /**
     * Get defaults for a specific route.
     *
     * Route defaults apply to specific named routes. Useful for:
     * - Archive pages that should be noindex
     * - Static pages with fixed SEO settings
     * - Category/tag pages with templates
     *
     * @param string $route The Laravel route name (e.g., 'blog.index')
     * @param string $locale The locale code
     * @return SEOData|null Route defaults or null if not set
     *
     * @example
     * ```php
     * // In seo_defaults: scope = 'blog.archive'
     * $defaults = $repository->forRoute('blog.archive', 'en');
     * // Might return SEOData with robots='noindex,follow'
     * ```
     */
    public function forRoute(string $route, string $locale): ?SEOData
    {
        return $this->getCached($route, $locale);
    }

    /**
     * Get defaults for any scope (generic method).
     *
     * @param string $scope The scope identifier
     * @param string $locale The locale code
     * @return SEOData|null Defaults for the scope or null
     */
    public function forScope(string $scope, string $locale): ?SEOData
    {
        return $this->getCached($scope, $locale);
    }

    /**
     * Get cached SEO defaults for a scope.
     *
     * Results are cached for performance. Cache is automatically
     * invalidated when SEODefault models are saved/deleted.
     *
     * @param string $scope The scope identifier
     * @param string $locale The locale code
     * @return SEOData|null Cached defaults or null
     */
    protected function getCached(string $scope, string $locale): ?SEOData
    {
        $cacheKey = $this->getCacheKey($scope, $locale);

        return Cache::store($this->getCacheStore())
            ->remember($cacheKey, self::CACHE_TTL, function () use ($scope, $locale) {
                return $this->loadFromDatabase($scope, $locale);
            });
    }

    /**
     * Load SEO defaults from database.
     *
     * @param string $scope The scope identifier
     * @param string $locale The locale code
     * @return SEOData|null Loaded defaults or null
     */
    protected function loadFromDatabase(string $scope, string $locale): ?SEOData
    {
        // Check if the table exists
        if (! $this->tableExists()) {
            return null;
        }

        try {
            $default = SEODefault::query()
                ->where('scope', $scope)
                ->where('locale', $locale)
                ->first();

            if (! $default) {
                // Try fallback to default locale
                if ($locale !== 'en') {
                    $default = SEODefault::query()
                        ->where('scope', $scope)
                        ->where('locale', 'en')
                        ->first();
                }
            }

            if (! $default) {
                return null;
            }

            return $this->transformToSEOData($default);
        } catch (\Exception $e) {
            // Log the error but don't throw - graceful degradation
            report($e);

            return null;
        }
    }

    /**
     * Transform an SEODefault model to SEOData.
     *
     * @param SEODefault $default The database model
     * @return SEOData The SEO data object
     */
    protected function transformToSEOData(SEODefault $default): SEOData
    {
        return SEOData::fromArray([
            'title' => $this->processTemplate($default->title_template),
            'description' => $this->processTemplate($default->description_template),
            'og_image' => $default->og_image_default,
            'robots' => $default->robots_default,
            'schema_jsonld' => $default->schema_defaults,
        ]);
    }

    /**
     * Process a template string with placeholders.
     *
     * Replaces placeholders with actual values:
     * - {site_name}: From config('seo.site_name') or config('app.name')
     * - {year}: Current year
     *
     * Note: Model-specific placeholders like {title} are processed
     * later in the HasSEO trait after the model is available.
     *
     * @param string|null $template The template string
     * @return string|null Processed template or null
     */
    protected function processTemplate(?string $template): ?string
    {
        if (! $template) {
            return null;
        }

        $replacements = [
            '{site_name}' => config('seo.site_name', config('app.name', '')),
            '{year}' => date('Y'),
            '{month}' => date('F'),
            '{day}' => date('j'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Clear cached defaults for a specific scope/locale.
     *
     * @param string|null $scope The scope to clear (null = all)
     * @param string|null $locale The locale to clear (null = all locales for scope)
     */
    public function clearCache(?string $scope = null, ?string $locale = null): void
    {
        $store = Cache::store($this->getCacheStore());

        if ($scope && $locale) {
            // Clear specific scope/locale
            $store->forget($this->getCacheKey($scope, $locale));

            return;
        }

        if ($scope) {
            // Clear all locales for a scope
            foreach (['en', 'de', 'fr', 'es', 'nl', 'pt_BR'] as $loc) {
                $store->forget($this->getCacheKey($scope, $loc));
            }

            return;
        }

        // For full cache clear, use cache tags if available
        // Otherwise, the cache will naturally expire
    }

    /**
     * Refresh the cache for a specific default.
     *
     * Call this after updating an SEODefault to ensure fresh data.
     *
     * @param SEODefault $default The updated default
     */
    public function refreshCache(SEODefault $default): void
    {
        $this->clearCache($default->scope, $default->locale);

        // Pre-warm the cache
        $this->getCached($default->scope, $default->locale);
    }

    /**
     * Get the cache key for a scope/locale combination.
     *
     * @param string $scope The scope identifier
     * @param string $locale The locale code
     * @return string The cache key
     */
    protected function getCacheKey(string $scope, string $locale): string
    {
        $prefix = config('seo.cache.prefix', 'seo_');

        return "{$prefix}defaults:{$scope}:{$locale}";
    }

    /**
     * Get the cache store name.
     *
     * @return string|null The cache store name
     */
    protected function getCacheStore(): ?string
    {
        return config('seo.cache.store');
    }

    /**
     * Check if the seo_defaults table exists.
     *
     * @return bool True if table exists
     */
    protected function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = Schema::hasTable('seo_defaults');
            } catch (\Exception) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Get all available scopes from the database.
     *
     * Useful for admin interfaces to show what defaults are configured.
     *
     * @return array<int, string> List of unique scopes
     */
    public function getAvailableScopes(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        try {
            return SEODefault::query()
                ->distinct()
                ->pluck('scope')
                ->toArray();
        } catch (\Exception) {
            return [];
        }
    }
}
