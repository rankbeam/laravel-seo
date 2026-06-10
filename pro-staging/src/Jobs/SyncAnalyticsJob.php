<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Services\Analytics\AnalyticsCache;
use Fibonoir\LaravelSEO\Services\Analytics\GA4Service;
use Fibonoir\LaravelSEO\Services\Analytics\Period;

/**
 * Job to sync analytics data from GA4 to local cache.
 *
 * Fetches metrics from Google Analytics 4 and stores them
 * in the seo_analytics_cache table for faster access.
 *
 * ## Usage
 * ```php
 * // Sync last 7 days for all paths
 * SyncAnalyticsJob::dispatch();
 *
 * // Sync specific period
 * SyncAnalyticsJob::dispatch(days: 30);
 *
 * // Sync specific paths only
 * SyncAnalyticsJob::dispatch(paths: ['/blog/post-1', '/blog/post-2']);
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php
 * Schedule::job(new SyncAnalyticsJob(days: 7))->dailyAt('04:00');
 * ```
 */
class SyncAnalyticsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600; // 1 hour

    /**
     * Delay between API requests (milliseconds).
     */
    protected int $rateLimitDelay = 500;

    /**
     * Create a new job instance.
     *
     * @param int $days Number of days to sync
     * @param array<int, string>|null $paths Specific paths to sync (null = auto-discover)
     */
    public function __construct(
        public int $days = 7,
        public ?array $paths = null,
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'sync-analytics';
    }

    /**
     * Execute the job.
     */
    public function handle(GA4Service $ga4, AnalyticsCache $cache): void
    {
        if (! $ga4->isConfigured()) {
            Log::warning('SyncAnalyticsJob: GA4 is not configured');

            return;
        }

        Log::info('SyncAnalyticsJob: Starting analytics sync', [
            'days' => $this->days,
            'paths_count' => $this->paths ? count($this->paths) : 'auto',
        ]);

        $paths = $this->paths ?? $this->discoverPaths();

        if (empty($paths)) {
            Log::info('SyncAnalyticsJob: No paths to sync');

            return;
        }

        $period = Period::days($this->days);
        $synced = 0;
        $errors = 0;

        foreach ($paths as $path) {
            try {
                $this->syncPath($path, $period, $ga4, $cache);
                $synced++;

                // Rate limiting
                usleep($this->rateLimitDelay * 1000);
            } catch (\Exception $e) {
                $errors++;
                Log::warning('SyncAnalyticsJob: Failed to sync path', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncAnalyticsJob: Completed', [
            'synced' => $synced,
            'errors' => $errors,
        ]);
    }

    /**
     * Sync analytics for a single path.
     */
    protected function syncPath(
        string $path,
        Period $period,
        GA4Service $ga4,
        AnalyticsCache $cache,
    ): void {
        // Get page metrics
        $metrics = $ga4->getPageMetrics($path, $period);

        // Get daily pageviews
        $pageViews = $ga4->getPageViews($period, $path);

        // Store aggregated metrics for each day in period
        // Note: GA4 returns aggregated data, we store the daily breakdown
        foreach ($pageViews['byDate'] as $date => $views) {
            $cache->set($path, 'pageviews', Carbon::parse($date), (float) $views);
        }

        // Store the latest aggregate metrics
        $today = Carbon::today();
        $cache->set($path, 'users', $today, (float) $metrics['users']);
        $cache->set($path, 'avg_duration', $today, (float) $metrics['avgTime']);
        $cache->set($path, 'bounce_rate', $today, (float) $metrics['bounceRate']);
        $cache->set($path, 'entrances', $today, (float) $metrics['entrances']);
    }

    /**
     * Discover paths from SEO meta and config.
     *
     * @return array<int, string>
     */
    protected function discoverPaths(): array
    {
        $paths = [];

        // Get paths from config
        $configPaths = config('seo.analytics.sync_paths', []);
        if (! empty($configPaths)) {
            $paths = array_merge($paths, $configPaths);
        }

        // Get paths from SEO meta (models with URLs)
        $metaPaths = SEOMeta::query()
            ->whereNotNull('canonical')
            ->distinct()
            ->pluck('canonical')
            ->map(function ($url) {
                return parse_url($url, PHP_URL_PATH) ?? '/';
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $paths = array_merge($paths, $metaPaths);

        // Limit to reasonable number
        $maxPaths = config('seo.analytics.max_sync_paths', 500);

        return array_slice(array_unique($paths), 0, $maxPaths);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAnalyticsJob failed', [
            'days' => $this->days,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['seo', 'analytics', 'sync'];
    }
}
