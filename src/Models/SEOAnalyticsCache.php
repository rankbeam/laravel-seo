<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Cached analytics data from Google Analytics 4.
 *
 * Stores analytics metrics per-path, per-date for efficient querying.
 * Data is synced via scheduled jobs and cached locally to reduce API calls.
 *
 * Common Metrics:
 * - screenPageViews: Total page views
 * - sessions: Number of sessions
 * - totalUsers: Unique users
 * - newUsers: First-time visitors
 * - bounceRate: Percentage that left immediately (0-100)
 * - averageSessionDuration: Avg session length in seconds
 * - engagementRate: Percentage of engaged sessions (0-100)
 *
 * @property int $id
 * @property string $path
 * @property string $metric_type
 * @property \Carbon\Carbon $date
 * @property float $value
 * @property array|null $dimensions
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see \Fibonoir\LaravelSEO\Services\Analytics\GA4Service
 * @see \Fibonoir\LaravelSEO\Services\Analytics\AnalyticsCache
 * @see \Fibonoir\LaravelSEO\Jobs\SyncAnalyticsJob
 */
class SEOAnalyticsCache extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seo_analytics_cache';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'path',
        'metric_type',
        'date',
        'value',
        'dimensions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'value' => 'float',
        'dimensions' => 'array',
    ];

    /**
     * Metric type constants.
     */
    public const METRIC_PAGE_VIEWS = 'screenPageViews';
    public const METRIC_SESSIONS = 'sessions';
    public const METRIC_USERS = 'totalUsers';
    public const METRIC_NEW_USERS = 'newUsers';
    public const METRIC_BOUNCE_RATE = 'bounceRate';
    public const METRIC_AVG_SESSION_DURATION = 'averageSessionDuration';
    public const METRIC_ENGAGEMENT_RATE = 'engagementRate';
    public const METRIC_EVENT_COUNT = 'eventCount';

    /**
     * Get cached analytics data for a path and metric within a date range.
     *
     * @param string $path The URL path (e.g., '/blog/my-post' or '*' for site-wide)
     * @param string $metric The metric type (e.g., 'screenPageViews')
     * @param Carbon|string $startDate Start of date range
     * @param Carbon|string $endDate End of date range
     * @return Collection<int, static> Collection of cache entries
     */
    public static function getCached(
        string $path,
        string $metric,
        Carbon|string $startDate,
        Carbon|string $endDate
    ): Collection {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return static::query()
            ->where('path', $path)
            ->where('metric_type', $metric)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();
    }

    /**
     * Get the sum of a metric for a path within a date range.
     *
     * @param string $path The URL path
     * @param string $metric The metric type
     * @param Carbon|string $startDate Start of date range
     * @param Carbon|string $endDate End of date range
     * @return float Total sum of the metric
     */
    public static function getSum(
        string $path,
        string $metric,
        Carbon|string $startDate,
        Carbon|string $endDate
    ): float {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return (float) static::query()
            ->where('path', $path)
            ->where('metric_type', $metric)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('value');
    }

    /**
     * Get the average of a metric for a path within a date range.
     *
     * @param string $path The URL path
     * @param string $metric The metric type
     * @param Carbon|string $startDate Start of date range
     * @param Carbon|string $endDate End of date range
     * @return float|null Average value or null if no data
     */
    public static function getAverage(
        string $path,
        string $metric,
        Carbon|string $startDate,
        Carbon|string $endDate
    ): ?float {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        $avg = static::query()
            ->where('path', $path)
            ->where('metric_type', $metric)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->avg('value');

        return $avg !== null ? (float) $avg : null;
    }

    /**
     * Upsert analytics data (insert or update on duplicate).
     *
     * @param string $path The URL path
     * @param string $metric The metric type
     * @param Carbon|string $date The date
     * @param float $value The metric value
     * @param array|null $dimensions Optional dimensions
     * @return static The created or updated model
     */
    public static function upsertMetric(
        string $path,
        string $metric,
        Carbon|string $date,
        float $value,
        ?array $dimensions = null
    ): static {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::updateOrCreate(
            [
                'path' => $path,
                'metric_type' => $metric,
                'date' => $date->toDateString(),
            ],
            [
                'value' => $value,
                'dimensions' => $dimensions,
            ]
        );
    }

    /**
     * Get top pages by a specific metric within a date range.
     *
     * @param string $metric The metric type
     * @param Carbon|string $startDate Start of date range
     * @param Carbon|string $endDate End of date range
     * @param int $limit Maximum number of results
     * @return Collection<int, object> Collection with path and total_value
     */
    public static function getTopPages(
        string $metric,
        Carbon|string $startDate,
        Carbon|string $endDate,
        int $limit = 10
    ): Collection {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return static::query()
            ->selectRaw('path, SUM(value) as total_value')
            ->where('metric_type', $metric)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('path', '!=', '*') // Exclude site-wide aggregate
            ->groupBy('path')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete cache entries older than a given date.
     *
     * @param Carbon|string $beforeDate Delete entries before this date
     * @return int Number of deleted rows
     */
    public static function purgeOlderThan(Carbon|string $beforeDate): int
    {
        $beforeDate = $beforeDate instanceof Carbon ? $beforeDate : Carbon::parse($beforeDate);

        return static::query()
            ->where('date', '<', $beforeDate->toDateString())
            ->delete();
    }

    /**
     * Scope to filter by path.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $path
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPath($query, string $path)
    {
        return $query->where('path', $path);
    }

    /**
     * Scope to filter by metric type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $metric
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMetric($query, string $metric)
    {
        return $query->where('metric_type', $metric);
    }

    /**
     * Scope to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|string $startDate
     * @param Carbon|string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, Carbon|string $startDate, Carbon|string $endDate)
    {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
    }
}
