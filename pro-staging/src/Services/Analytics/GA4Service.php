<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Analytics;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Google Analytics 4 Data API integration.
 *
 * Provides methods to fetch analytics data from GA4 with caching.
 *
 * ## Configuration
 * ```php
 * // config/seo.php
 * 'analytics' => [
 *     'enabled' => true,
 *     'property_id' => env('GA4_PROPERTY_ID'),
 *     'credentials_path' => storage_path('app/ga4-credentials.json'),
 *     'cache_ttl' => 3600, // 1 hour
 * ],
 * ```
 *
 * ## Usage
 * ```php
 * $analytics = app(GA4Service::class);
 *
 * // Get page views for last 30 days
 * $views = $analytics->getPageViews(Period::days(30));
 *
 * // Get top pages
 * $topPages = $analytics->getTopPages(Period::thisMonth(), limit: 20);
 *
 * // Get metrics for specific page
 * $metrics = $analytics->getPageMetrics('/blog/my-post', Period::days(7));
 * ```
 *
 * ## Requirements
 * - google/analytics-data package
 * - GA4 property with Data API enabled
 * - Service account credentials JSON file
 */
class GA4Service
{
    protected ?BetaAnalyticsDataClient $client = null;

    protected string $propertyId;

    protected int $cacheTtl;

    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('seo.analytics.enabled', false);
        $this->propertyId = config('seo.analytics.property_id', '');
        $this->cacheTtl = config('seo.analytics.cache_ttl', 3600);
    }

    /**
     * Get page views for a period.
     *
     * @param Period $period
     * @param string|null $path Filter by specific path
     * @return array{total: int, byDate: array<string, int>}
     */
    public function getPageViews(Period $period, ?string $path = null): array
    {
        $cacheKey = $this->getCacheKey('pageviews', $period, $path);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period, $path) {
            $metrics = ['screenPageViews'];
            $dimensions = ['date'];
            $filter = $path ? $this->createPathFilter($path) : null;

            $response = $this->runReport($metrics, $dimensions, $period, $filter);

            $byDate = [];
            $total = 0;

            foreach ($response as $row) {
                $date = $row['date'] ?? '';
                $views = (int) ($row['screenPageViews'] ?? 0);
                $byDate[$date] = $views;
                $total += $views;
            }

            return [
                'total' => $total,
                'byDate' => $byDate,
            ];
        });
    }

    /**
     * Get top pages by views.
     *
     * @return array<int, array{path: string, views: int, users: int}>
     */
    public function getTopPages(Period $period, int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('toppages', $period, (string) $limit);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period, $limit) {
            $metrics = ['screenPageViews', 'totalUsers'];
            $dimensions = ['pagePath'];

            $response = $this->runReport($metrics, $dimensions, $period);

            $pages = [];
            foreach ($response as $row) {
                $pages[] = [
                    'path' => $row['pagePath'] ?? '',
                    'views' => (int) ($row['screenPageViews'] ?? 0),
                    'users' => (int) ($row['totalUsers'] ?? 0),
                ];
            }

            // Sort by views descending
            usort($pages, fn ($a, $b) => $b['views'] <=> $a['views']);

            return array_slice($pages, 0, $limit);
        });
    }

    /**
     * Get total sessions for a period.
     */
    public function getSessions(Period $period): int
    {
        $cacheKey = $this->getCacheKey('sessions', $period);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period) {
            $response = $this->runReport(['sessions'], [], $period);

            return (int) ($response[0]['sessions'] ?? 0);
        });
    }

    /**
     * Get total users for a period.
     */
    public function getUsers(Period $period): int
    {
        $cacheKey = $this->getCacheKey('users', $period);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period) {
            $response = $this->runReport(['totalUsers'], [], $period);

            return (int) ($response[0]['totalUsers'] ?? 0);
        });
    }

    /**
     * Get bounce rate for a period (percentage).
     */
    public function getBounceRate(Period $period): float
    {
        $cacheKey = $this->getCacheKey('bouncerate', $period);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period) {
            $response = $this->runReport(['bounceRate'], [], $period);

            return round((float) ($response[0]['bounceRate'] ?? 0) * 100, 2);
        });
    }

    /**
     * Get average session duration in seconds.
     */
    public function getAverageSessionDuration(Period $period): float
    {
        $cacheKey = $this->getCacheKey('avgduration', $period);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($period) {
            $response = $this->runReport(['averageSessionDuration'], [], $period);

            return round((float) ($response[0]['averageSessionDuration'] ?? 0), 2);
        });
    }

    /**
     * Get detailed metrics for a specific page.
     *
     * @return array{views: int, users: int, avgTime: float, bounceRate: float, entrances: int}
     */
    public function getPageMetrics(string $path, Period $period): array
    {
        $cacheKey = $this->getCacheKey('pagemetrics', $period, $path);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($path, $period) {
            $metrics = [
                'screenPageViews',
                'totalUsers',
                'averageSessionDuration',
                'bounceRate',
                'entrances',
            ];

            $filter = $this->createPathFilter($path);
            $response = $this->runReport($metrics, [], $period, $filter);

            $row = $response[0] ?? [];

            return [
                'views' => (int) ($row['screenPageViews'] ?? 0),
                'users' => (int) ($row['totalUsers'] ?? 0),
                'avgTime' => round((float) ($row['averageSessionDuration'] ?? 0), 2),
                'bounceRate' => round((float) ($row['bounceRate'] ?? 0) * 100, 2),
                'entrances' => (int) ($row['entrances'] ?? 0),
            ];
        });
    }

    /**
     * Run a GA4 report.
     *
     * @param array<int, string> $metrics
     * @param array<int, string> $dimensions
     * @return array<int, array<string, mixed>>
     */
    protected function runReport(
        array $metrics,
        array $dimensions,
        Period $period,
        ?FilterExpression $filter = null,
    ): array {
        if (! $this->enabled || empty($this->propertyId)) {
            return [];
        }

        try {
            $client = $this->getClient();

            if (! $client) {
                return [];
            }

            // Build request
            $request = [
                'property' => 'properties/' . $this->propertyId,
                'dateRanges' => [
                    new DateRange([
                        'start_date' => $period->getStartForApi(),
                        'end_date' => $period->getEndForApi(),
                    ]),
                ],
                'metrics' => array_map(
                    fn ($name) => new Metric(['name' => $name]),
                    $metrics
                ),
            ];

            if (! empty($dimensions)) {
                $request['dimensions'] = array_map(
                    fn ($name) => new Dimension(['name' => $name]),
                    $dimensions
                );
            }

            if ($filter) {
                $request['dimensionFilter'] = $filter;
            }

            $response = $client->runReport($request);

            return $this->formatResponse($response, $metrics, $dimensions);
        } catch (\Exception $e) {
            Log::error('GA4Service: Error running report', [
                'error' => $e->getMessage(),
                'metrics' => $metrics,
            ]);

            return [];
        }
    }

    /**
     * Format GA4 response to array.
     *
     * @param array<int, string> $metrics
     * @param array<int, string> $dimensions
     * @return array<int, array<string, mixed>>
     */
    protected function formatResponse(
        RunReportResponse $response,
        array $metrics,
        array $dimensions,
    ): array {
        $result = [];

        foreach ($response->getRows() as $row) {
            $rowData = [];

            // Add dimension values
            foreach ($row->getDimensionValues() as $index => $value) {
                $dimensionName = $dimensions[$index] ?? "dimension_{$index}";
                $rowData[$dimensionName] = $value->getValue();
            }

            // Add metric values
            foreach ($row->getMetricValues() as $index => $value) {
                $metricName = $metrics[$index] ?? "metric_{$index}";
                $rowData[$metricName] = $value->getValue();
            }

            $result[] = $rowData;
        }

        return $result;
    }

    /**
     * Create a filter for page path.
     */
    protected function createPathFilter(string $path): FilterExpression
    {
        return new FilterExpression([
            'filter' => new Filter([
                'field_name' => 'pagePath',
                'string_filter' => new Filter\StringFilter([
                    'match_type' => Filter\StringFilter\MatchType::EXACT,
                    'value' => $path,
                ]),
            ]),
        ]);
    }

    /**
     * Get the Analytics Data client.
     */
    protected function getClient(): ?BetaAnalyticsDataClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $credentialsPath = config('seo.analytics.credentials_path');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            Log::warning('GA4Service: Credentials file not found', [
                'path' => $credentialsPath,
            ]);

            return null;
        }

        try {
            $this->client = new BetaAnalyticsDataClient([
                'credentials' => $credentialsPath,
            ]);

            return $this->client;
        } catch (\Exception $e) {
            Log::error('GA4Service: Failed to create client', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate cache key.
     */
    protected function getCacheKey(string $type, Period $period, ?string $suffix = null): string
    {
        $key = config('seo.cache.prefix', 'seo_') . "ga4_{$type}_{$period->getCacheKey()}";

        if ($suffix) {
            $key .= '_' . md5($suffix);
        }

        return $key;
    }

    /**
     * Clear all analytics cache.
     */
    public function clearCache(): void
    {
        // Note: This would require cache tags or manual tracking
        // For now, cache just expires naturally based on TTL
        Log::info('GA4Service: Cache clear requested (expires naturally)');
    }

    /**
     * Check if analytics is enabled and configured.
     */
    public function isConfigured(): bool
    {
        return $this->enabled
            && ! empty($this->propertyId)
            && file_exists(config('seo.analytics.credentials_path', ''));
    }
}
