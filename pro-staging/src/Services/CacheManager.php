<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEO404Log;
use Fibonoir\LaravelSEO\Models\SEOAnalyticsCache;
use Fibonoir\LaravelSEO\Models\SEODefault;
use Fibonoir\LaravelSEO\Models\SEOInternalLinksIndex;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Models\SEORedirect;

/**
 * Centralized cache management for the SEO suite.
 *
 * This service provides a unified interface for managing all SEO-related caches,
 * including warming, clearing, and invalidation strategies.
 *
 * ## Cache Keys Documentation
 *
 * The SEO suite uses the following cache keys (prefixed with config value):
 *
 * | Key Pattern                          | Description                          | TTL      |
 * |--------------------------------------|--------------------------------------|----------|
 * | `seo_redirects`                      | All active redirects (exact+regex)   | 1 hour   |
 * | `seo_defaults:{scope}:{locale}`      | SEO defaults by scope/locale         | 1 hour   |
 * | `seo_analytics:{path}:{metric}`      | Cached analytics metrics             | 1 hour   |
 * | `seo_link_index:{locale}`            | Internal links index                 | 6 hours  |
 * | `seo_resolved:{model}:{id}:{locale}` | Resolved SEO data per model          | 30 min   |
 * | `seo_sitemap_generated`              | Last sitemap generation timestamp    | 24 hours |
 * | `seo_stem_{locale}:{word}`           | Stemmed words cache                  | 24 hours |
 *
 * ## Usage
 *
 * ```php
 * $cacheManager = app(CacheManager::class);
 *
 * // Warm all caches for production
 * $cacheManager->warmCache();
 *
 * // Clear everything
 * $cacheManager->clearAll();
 *
 * // Clear cache for a specific model
 * $cacheManager->clearForModel($post);
 *
 * // Get cache statistics
 * $stats = $cacheManager->getStats();
 * ```
 *
 * ## Console Command
 *
 * ```bash
 * # Warm cache
 * php artisan seo:cache --warm
 *
 * # Clear cache
 * php artisan seo:cache --clear
 *
 * # Show stats
 * php artisan seo:cache --stats
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Console\Commands\CacheCommand
 */
class CacheManager
{
    /**
     * Default cache TTL values in seconds.
     */
    protected const TTL_SHORT = 1800;      // 30 minutes
    protected const TTL_MEDIUM = 3600;     // 1 hour
    protected const TTL_LONG = 21600;      // 6 hours
    protected const TTL_DAILY = 86400;     // 24 hours

    /**
     * Get the cache store instance.
     */
    protected function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store($this->getStoreName());
    }

    /**
     * Get the configured cache store name.
     */
    protected function getStoreName(): ?string
    {
        return config('seo.cache.store');
    }

    /**
     * Get the cache key prefix.
     */
    protected function getPrefix(): string
    {
        return config('seo.cache.prefix', 'seo_');
    }

    /**
     * Build a cache key with prefix.
     */
    protected function key(string $key): string
    {
        return $this->getPrefix() . $key;
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Warming
    |--------------------------------------------------------------------------
    |
    | Methods for preloading commonly accessed data into cache.
    |
    */

    /**
     * Warm all SEO caches.
     *
     * Preloads commonly accessed data to improve response times.
     * Recommended to run after deployments or during off-peak hours.
     *
     * @param bool $verbose Whether to log progress
     * @return array{redirects: int, defaults: int, link_index: int, duration_ms: float}
     */
    public function warmCache(bool $verbose = false): array
    {
        $startTime = microtime(true);
        $stats = [
            'redirects' => 0,
            'defaults' => 0,
            'link_index' => 0,
            'duration_ms' => 0,
        ];

        if ($verbose) {
            Log::info('CacheManager: Starting cache warm');
        }

        // Warm redirects cache
        $stats['redirects'] = $this->warmRedirectsCache();

        // Warm defaults cache
        $stats['defaults'] = $this->warmDefaultsCache();

        // Warm internal links index cache
        $stats['link_index'] = $this->warmLinkIndexCache();

        $stats['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        if ($verbose) {
            Log::info('CacheManager: Cache warm complete', $stats);
        }

        return $stats;
    }

    /**
     * Warm the redirects cache.
     *
     * Loads all active redirects into cache for fast matching.
     *
     * @return int Number of redirects cached
     */
    public function warmRedirectsCache(): int
    {
        $redirects = SEORedirect::active()
            ->orderBy('is_regex')
            ->get(['id', 'source_path', 'target_url', 'status_code', 'is_regex', 'preserve_query']);

        $this->store()->put(
            $this->key('redirects'),
            $redirects,
            self::TTL_MEDIUM
        );

        return $redirects->count();
    }

    /**
     * Warm the SEO defaults cache.
     *
     * Preloads all defaults by scope and locale.
     *
     * @return int Number of defaults cached
     */
    public function warmDefaultsCache(): int
    {
        $count = 0;
        $locales = config('seo.analyzer.supported_locales', ['en']);

        // Get all unique scopes
        $scopes = SEODefault::distinct()->pluck('scope')->toArray();

        foreach ($scopes as $scope) {
            foreach ($locales as $locale) {
                $default = SEODefault::forScope($scope)->forLocale($locale)->first();

                if ($default) {
                    $this->store()->put(
                        $this->key("defaults:{$scope}:{$locale}"),
                        $default,
                        self::TTL_MEDIUM
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Warm the internal links index cache.
     *
     * Preloads the link suggestion index by locale.
     *
     * @return int Number of index entries cached
     */
    public function warmLinkIndexCache(): int
    {
        $count = 0;
        $locales = config('seo.analyzer.supported_locales', ['en']);

        foreach ($locales as $locale) {
            $entries = SEOInternalLinksIndex::where('locale', $locale)
                ->get(['id', 'url', 'title', 'stemmed_keywords', 'headings']);

            if ($entries->isNotEmpty()) {
                $this->store()->put(
                    $this->key("link_index:{$locale}"),
                    $entries,
                    self::TTL_LONG
                );
                $count += $entries->count();
            }
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Clearing
    |--------------------------------------------------------------------------
    |
    | Methods for invalidating cached data.
    |
    */

    /**
     * Clear all SEO-related caches.
     *
     * @return array{cleared: array<int, string>}
     */
    public function clearAll(): array
    {
        $cleared = [];

        // Clear redirects
        $this->clearRedirectsCache();
        $cleared[] = 'redirects';

        // Clear defaults
        $this->clearDefaultsCache();
        $cleared[] = 'defaults';

        // Clear analytics
        $this->clearAnalyticsCache();
        $cleared[] = 'analytics';

        // Clear link index
        $this->clearLinkIndexCache();
        $cleared[] = 'link_index';

        // Clear resolved SEO data
        $this->clearResolvedCache();
        $cleared[] = 'resolved';

        // Clear sitemap cache
        $this->clearSitemapCache();
        $cleared[] = 'sitemap';

        // Clear stemmer cache
        $this->clearStemmerCache();
        $cleared[] = 'stemmer';

        Log::info('CacheManager: Cleared all caches', ['cleared' => $cleared]);

        return ['cleared' => $cleared];
    }

    /**
     * Clear cache for a specific model.
     *
     * Invalidates all caches related to a specific model instance.
     *
     * @param Model $model The model to clear cache for
     */
    public function clearForModel(Model $model): void
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        // Clear resolved SEO cache for all locales
        $locales = config('seo.analyzer.supported_locales', ['en']);
        foreach ($locales as $locale) {
            $this->store()->forget(
                $this->key("resolved:{$modelClass}:{$modelId}:{$locale}")
            );
        }

        // Clear model-type defaults cache
        foreach ($locales as $locale) {
            $this->store()->forget(
                $this->key("defaults:{$modelClass}:{$locale}")
            );
        }

        Log::debug('CacheManager: Cleared cache for model', [
            'model' => $modelClass,
            'id' => $modelId,
        ]);
    }

    /**
     * Clear the redirects cache.
     */
    public function clearRedirectsCache(): void
    {
        $this->store()->forget($this->key('redirects'));
    }

    /**
     * Clear SEO defaults cache.
     *
     * @param string|null $scope Optional specific scope to clear
     * @param string|null $locale Optional specific locale to clear
     */
    public function clearDefaultsCache(?string $scope = null, ?string $locale = null): void
    {
        if ($scope && $locale) {
            $this->store()->forget($this->key("defaults:{$scope}:{$locale}"));
            return;
        }

        $locales = $locale ? [$locale] : config('seo.analyzer.supported_locales', ['en']);

        if ($scope) {
            foreach ($locales as $loc) {
                $this->store()->forget($this->key("defaults:{$scope}:{$loc}"));
            }
            return;
        }

        // Clear all defaults - get scopes from database
        $scopes = SEODefault::distinct()->pluck('scope')->toArray();
        foreach ($scopes as $s) {
            foreach ($locales as $loc) {
                $this->store()->forget($this->key("defaults:{$s}:{$loc}"));
            }
        }
    }

    /**
     * Clear analytics cache.
     *
     * @param string|null $path Optional specific path to clear
     */
    public function clearAnalyticsCache(?string $path = null): void
    {
        if ($path) {
            // Clear specific path metrics
            $metrics = ['pageviews', 'users', 'avg_duration', 'bounce_rate', 'entrances'];
            foreach ($metrics as $metric) {
                $this->store()->forget($this->key("analytics:{$path}:{$metric}"));
            }
            return;
        }

        // For full analytics cache clear, we rely on cache tags or natural expiration
        // The database cache (seo_analytics_cache) is cleared separately if needed
    }

    /**
     * Clear internal links index cache.
     *
     * @param string|null $locale Optional specific locale to clear
     */
    public function clearLinkIndexCache(?string $locale = null): void
    {
        if ($locale) {
            $this->store()->forget($this->key("link_index:{$locale}"));
            return;
        }

        $locales = config('seo.analyzer.supported_locales', ['en']);
        foreach ($locales as $loc) {
            $this->store()->forget($this->key("link_index:{$loc}"));
        }
    }

    /**
     * Clear resolved SEO data cache.
     */
    public function clearResolvedCache(): void
    {
        // For stores that support tags, this would use cache tags
        // Otherwise, we rely on natural expiration for resolved SEO data
        // as the keys are dynamic (model-specific)
    }

    /**
     * Clear sitemap cache.
     */
    public function clearSitemapCache(): void
    {
        $this->store()->forget($this->key('sitemap_generated'));
        $this->store()->forget($this->key('sitemap_urls'));
    }

    /**
     * Clear stemmer cache.
     */
    public function clearStemmerCache(): void
    {
        // Stemmer cache uses dynamic keys, relies on natural expiration
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Statistics
    |--------------------------------------------------------------------------
    |
    | Methods for monitoring cache health and usage.
    |
    */

    /**
     * Get cache statistics and health information.
     *
     * @return array{
     *     store: string|null,
     *     prefix: string,
     *     keys: array<string, bool>,
     *     database_counts: array<string, int>,
     *     recommendations: array<int, string>
     * }
     */
    public function getStats(): array
    {
        $stats = [
            'store' => $this->getStoreName() ?? 'default',
            'prefix' => $this->getPrefix(),
            'keys' => [],
            'database_counts' => [],
            'recommendations' => [],
        ];

        // Check if common cache keys exist
        $stats['keys']['redirects'] = $this->store()->has($this->key('redirects'));
        $stats['keys']['link_index_en'] = $this->store()->has($this->key('link_index:en'));
        $stats['keys']['defaults_global_en'] = $this->store()->has($this->key('defaults:global:en'));

        // Get database counts
        $stats['database_counts'] = [
            'redirects' => SEORedirect::count(),
            'active_redirects' => SEORedirect::active()->count(),
            'defaults' => SEODefault::count(),
            'seo_meta' => SEOMeta::count(),
            'analytics_entries' => SEOAnalyticsCache::count(),
            'link_index_entries' => SEOInternalLinksIndex::count(),
            '404_logs' => SEO404Log::count(),
        ];

        // Generate recommendations
        $stats['recommendations'] = $this->generateRecommendations($stats);

        return $stats;
    }

    /**
     * Generate cache optimization recommendations.
     *
     * @param array<string, mixed> $stats Current statistics
     * @return array<int, string> List of recommendations
     */
    protected function generateRecommendations(array $stats): array
    {
        $recommendations = [];

        // Check if Redis/Memcached is being used
        $store = $stats['store'];
        if ($store === 'file' || $store === 'database' || $store === 'array') {
            $recommendations[] = "Consider using Redis or Memcached for better cache performance (currently using: {$store})";
        }

        // Check if caches are warmed
        if (! $stats['keys']['redirects'] && $stats['database_counts']['active_redirects'] > 0) {
            $recommendations[] = 'Redirects cache is empty - run `php artisan seo:cache --warm` to preload';
        }

        // Check for large datasets
        if ($stats['database_counts']['analytics_entries'] > 100000) {
            $recommendations[] = 'Analytics cache table is large - consider purging old data with `seo:analytics:purge`';
        }

        if ($stats['database_counts']['404_logs'] > 10000) {
            $recommendations[] = '404 logs table is large - consider cleaning up ignored entries';
        }

        return $recommendations;
    }

    /*
    |--------------------------------------------------------------------------
    | Cached Data Retrieval
    |--------------------------------------------------------------------------
    |
    | Methods for retrieving cached data with automatic warming.
    |
    */

    /**
     * Get cached redirects with automatic warming.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRedirects(): \Illuminate\Support\Collection
    {
        return $this->store()->remember(
            $this->key('redirects'),
            self::TTL_MEDIUM,
            function () {
                return SEORedirect::active()
                    ->orderBy('is_regex')
                    ->get(['id', 'source_path', 'target_url', 'status_code', 'is_regex', 'preserve_query']);
            }
        );
    }

    /**
     * Get cached defaults for a scope.
     *
     * @param string $scope The scope (e.g., 'global', model class, route name)
     * @param string $locale The locale
     * @return SEODefault|null
     */
    public function getDefault(string $scope, string $locale): ?SEODefault
    {
        return $this->store()->remember(
            $this->key("defaults:{$scope}:{$locale}"),
            self::TTL_MEDIUM,
            function () use ($scope, $locale) {
                return SEODefault::forScope($scope)->forLocale($locale)->first();
            }
        );
    }

    /**
     * Get cached link index for a locale.
     *
     * @param string $locale The locale
     * @return \Illuminate\Support\Collection
     */
    public function getLinkIndex(string $locale): \Illuminate\Support\Collection
    {
        return $this->store()->remember(
            $this->key("link_index:{$locale}"),
            self::TTL_LONG,
            function () use ($locale) {
                return SEOInternalLinksIndex::where('locale', $locale)
                    ->get(['id', 'url', 'title', 'stemmed_keywords', 'headings']);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Database Cache Management
    |--------------------------------------------------------------------------
    |
    | Methods for managing database-backed caches (analytics).
    |
    */

    /**
     * Purge old analytics cache entries.
     *
     * @param int $daysToKeep Number of days of data to retain
     * @return int Number of entries deleted
     */
    public function purgeAnalyticsCache(int $daysToKeep = 90): int
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);

        $deleted = SEOAnalyticsCache::where('date', '<', $cutoff)->delete();

        Log::info('CacheManager: Purged old analytics cache', [
            'days_kept' => $daysToKeep,
            'entries_deleted' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Optimize analytics cache table.
     *
     * Runs database-specific optimizations on the analytics cache table.
     */
    public function optimizeAnalyticsTable(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('OPTIMIZE TABLE seo_analytics_cache');
        } elseif ($driver === 'pgsql') {
            DB::statement('VACUUM ANALYZE seo_analytics_cache');
        }

        Log::info('CacheManager: Optimized analytics cache table', ['driver' => $driver]);
    }

    /**
     * Purge old 404 logs.
     *
     * @param int $daysToKeep Number of days to retain logs
     * @param bool $keepUnresolved Whether to keep unresolved (new) entries regardless of age
     * @return int Number of entries deleted
     */
    public function purge404Logs(int $daysToKeep = 30, bool $keepUnresolved = true): int
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);

        $query = SEO404Log::where('last_seen_at', '<', $cutoff);

        if ($keepUnresolved) {
            $query->where('status', '!=', 'new');
        }

        $deleted = $query->delete();

        Log::info('CacheManager: Purged old 404 logs', [
            'days_kept' => $daysToKeep,
            'entries_deleted' => $deleted,
        ]);

        return $deleted;
    }
}
