<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Fibonoir\LaravelSEO\Models\SEOAnalyticsCache;

/**
 * Database caching layer for analytics data.
 *
 * Stores analytics metrics in the seo_analytics_cache table for
 * faster access and historical tracking.
 *
 * ## Usage
 * ```php
 * $cache = app(AnalyticsCache::class);
 *
 * // Get cached data
 * $views = $cache->get('/blog/post', 'pageviews', Period::days(7));
 *
 * // Store data
 * $cache->set('/blog/post', 'pageviews', Carbon::today(), 150);
 *
 * // Get multiple metrics
 * $metrics = $cache->getMultiple('/blog/post', ['pageviews', 'users'], Period::thisMonth());
 * ```
 *
 * ## Metric Types
 * - pageviews: Page view count
 * - users: Unique users
 * - sessions: Session count
 * - bounce_rate: Bounce rate percentage
 * - avg_duration: Average session duration
 * - entrances: Entrance count
 */
class AnalyticsCache
{
    /**
     * Get cached metric value for a path and period.
     *
     * Returns sum of values across the period.
     */
    public function get(string $path, string $metric, Period $period): ?float
    {
        $result = SEOAnalyticsCache::forPath($path)
            ->forMetric($metric)
            ->betweenDates($period->getStartDate(), $period->getEndDate())
            ->sum('value');

        return $result > 0 ? (float) $result : null;
    }

    /**
     * Get average metric value for a path and period.
     */
    public function getAverage(string $path, string $metric, Period $period): ?float
    {
        $result = SEOAnalyticsCache::forPath($path)
            ->forMetric($metric)
            ->betweenDates($period->getStartDate(), $period->getEndDate())
            ->avg('value');

        return $result !== null ? round((float) $result, 2) : null;
    }

    /**
     * Set a cached metric value.
     *
     * @param array<string, mixed>|null $dimensions Additional dimensions
     */
    public function set(
        string $path,
        string $metric,
        Carbon $date,
        float $value,
        ?array $dimensions = null,
    ): void {
        SEOAnalyticsCache::upsertMetric(
            $path,
            $metric,
            $date,
            $value,
            $dimensions
        );
    }

    /**
     * Set multiple metric values at once (batch).
     *
     * @param array<string, float> $metrics ['pageviews' => 100, 'users' => 50]
     */
    public function setMultiple(
        string $path,
        array $metrics,
        Carbon $date,
    ): void {
        foreach ($metrics as $metric => $value) {
            $this->set($path, $metric, $date, $value);
        }
    }

    /**
     * Get multiple metrics for a path and period.
     *
     * @param array<int, string> $metrics
     * @return array<string, float|null>
     */
    public function getMultiple(string $path, array $metrics, Period $period): array
    {
        $result = [];

        foreach ($metrics as $metric) {
            $result[$metric] = $this->get($path, $metric, $period);
        }

        return $result;
    }

    /**
     * Get daily breakdown of a metric.
     *
     * @return array<string, float>
     */
    public function getDaily(string $path, string $metric, Period $period): array
    {
        $results = SEOAnalyticsCache::forPath($path)
            ->forMetric($metric)
            ->betweenDates($period->getStartDate(), $period->getEndDate())
            ->orderBy('date')
            ->get();

        $daily = [];
        foreach ($results as $row) {
            $daily[$row->date->format('Y-m-d')] = (float) $row->value;
        }

        return $daily;
    }

    /**
     * Invalidate cache for a path.
     */
    public function invalidate(string $path, ?string $metric = null): void
    {
        $query = SEOAnalyticsCache::forPath($path);

        if ($metric !== null) {
            $query->forMetric($metric);
        }

        $query->delete();
    }

    /**
     * Invalidate all cache older than a date.
     */
    public function invalidateOlderThan(Carbon $date): int
    {
        return SEOAnalyticsCache::purgeOlderThan($date);
    }

    /**
     * Get cache key for a specific entry.
     */
    public function getCacheKey(string $path, string $metric, string $date): string
    {
        return md5("{$path}:{$metric}:{$date}");
    }

    /**
     * Check if we have cached data for a path and period.
     */
    public function has(string $path, string $metric, Period $period): bool
    {
        return SEOAnalyticsCache::forPath($path)
            ->forMetric($metric)
            ->betweenDates($period->getStartDate(), $period->getEndDate())
            ->exists();
    }

    /**
     * Get all unique paths with cached data.
     *
     * @return array<int, string>
     */
    public function getCachedPaths(): array
    {
        return SEOAnalyticsCache::query()
            ->distinct()
            ->pluck('path')
            ->toArray();
    }

    /**
     * Get statistics about the cache.
     *
     * @return array{total_entries: int, unique_paths: int, oldest_date: ?string, newest_date: ?string}
     */
    public function getStats(): array
    {
        return [
            'total_entries' => SEOAnalyticsCache::count(),
            'unique_paths' => SEOAnalyticsCache::distinct('path')->count('path'),
            'oldest_date' => SEOAnalyticsCache::min('date'),
            'newest_date' => SEOAnalyticsCache::max('date'),
        ];
    }

    /**
     * Bulk upsert metrics (efficient for large imports).
     *
     * @param array<int, array{path: string, metric: string, date: string, value: float}> $data
     */
    public function bulkUpsert(array $data): void
    {
        $now = now();

        $records = array_map(function ($item) use ($now) {
            return [
                'path' => $item['path'],
                'metric_type' => $item['metric'],
                'date' => $item['date'],
                'value' => $item['value'],
                'dimensions' => isset($item['dimensions']) ? json_encode($item['dimensions']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $data);

        // Chunk for large datasets
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('seo_analytics_cache')->upsert(
                $chunk,
                ['path', 'metric_type', 'date'],
                ['value', 'dimensions', 'updated_at']
            );
        }
    }
}
