<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Console\Commands;

use Illuminate\Console\Command;
use Fibonoir\LaravelSEO\Jobs\SyncAnalyticsJob;
use Fibonoir\LaravelSEO\Services\Analytics\AnalyticsCache;
use Fibonoir\LaravelSEO\Services\Analytics\GA4Service;
use Fibonoir\LaravelSEO\Services\Analytics\Period;

/**
 * Artisan command to sync analytics data from GA4.
 *
 * ## Usage
 * ```bash
 * # Sync last 7 days (default)
 * php artisan seo:sync-analytics
 *
 * # Sync last 30 days
 * php artisan seo:sync-analytics --days=30
 *
 * # Sync specific path only
 * php artisan seo:sync-analytics --path=/blog/my-post
 *
 * # Queue the job instead of running directly
 * php artisan seo:sync-analytics --queue
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php
 * Schedule::command('seo:sync-analytics')->dailyAt('04:00');
 * Schedule::command('seo:sync-analytics --days=30')->weekly();
 * ```
 */
class SyncAnalyticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:sync-analytics
                            {--days=7 : Number of days to sync}
                            {--path= : Specific path to sync (optional)}
                            {--queue : Queue the job instead of running directly}
                            {--stats : Show cache statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Sync analytics data from Google Analytics 4 to local cache';

    /**
     * Execute the console command.
     */
    public function handle(GA4Service $ga4, AnalyticsCache $cache): int
    {
        // Show stats mode
        if ($this->option('stats')) {
            return $this->showStats($cache);
        }

        // Check if GA4 is configured
        if (! $ga4->isConfigured()) {
            $this->error('Google Analytics 4 is not configured.');
            $this->line('');
            $this->line('Please ensure the following are set in config/seo.php:');
            $this->line('  - analytics.enabled = true');
            $this->line('  - analytics.property_id = your GA4 property ID');
            $this->line('  - analytics.credentials_path = path to service account JSON');

            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        $path = $this->option('path');
        $paths = $path ? [$path] : null;

        if ($this->option('queue')) {
            return $this->handleQueued($days, $paths);
        }

        return $this->handleSync($ga4, $cache, $days, $paths);
    }

    /**
     * Handle queued execution.
     *
     * @param array<int, string>|null $paths
     */
    protected function handleQueued(int $days, ?array $paths): int
    {
        $this->info('Dispatching analytics sync job...');

        SyncAnalyticsJob::dispatch($days, $paths);

        $this->info('✓ Job dispatched to queue.');
        $this->line("  Period: Last {$days} days");

        if ($paths) {
            $this->line("  Paths: " . implode(', ', $paths));
        } else {
            $this->line('  Paths: Auto-discover');
        }

        return self::SUCCESS;
    }

    /**
     * Handle synchronous execution.
     *
     * @param array<int, string>|null $paths
     */
    protected function handleSync(
        GA4Service $ga4,
        AnalyticsCache $cache,
        int $days,
        ?array $paths,
    ): int {
        $this->info("Syncing analytics for last {$days} days...");
        $this->newLine();

        $period = Period::days($days);

        // Discover paths if not specified
        if ($paths === null) {
            $this->line('Discovering paths...');
            $job = new SyncAnalyticsJob($days);
            $paths = $this->invokePrivateMethod($job, 'discoverPaths');
            $this->line("Found " . count($paths) . " paths to sync.");
        }

        if (empty($paths)) {
            $this->warn('No paths to sync.');

            return self::SUCCESS;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar(count($paths));
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($paths as $path) {
            try {
                $metrics = $ga4->getPageMetrics($path, $period);
                $pageViews = $ga4->getPageViews($period, $path);

                // Store daily pageviews
                foreach ($pageViews['byDate'] as $date => $views) {
                    $cache->set($path, 'pageviews', \Carbon\Carbon::parse($date), (float) $views);
                }

                // Store aggregate metrics
                $today = \Carbon\Carbon::today();
                $cache->set($path, 'users', $today, (float) $metrics['users']);
                $cache->set($path, 'avg_duration', $today, (float) $metrics['avgTime']);
                $cache->set($path, 'bounce_rate', $today, (float) $metrics['bounceRate']);

                $synced++;

                // Rate limiting
                usleep(500 * 1000);
            } catch (\Exception $e) {
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Analytics sync complete!");
        $this->line("  Synced: {$synced} paths");

        if ($errors > 0) {
            $this->warn("  Errors: {$errors}");
        }

        return self::SUCCESS;
    }

    /**
     * Show cache statistics.
     */
    protected function showStats(AnalyticsCache $cache): int
    {
        $this->info('Analytics Cache Statistics');
        $this->newLine();

        $stats = $cache->getStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', number_format($stats['total_entries'])],
                ['Unique Paths', number_format($stats['unique_paths'])],
                ['Oldest Data', $stats['oldest_date'] ?? 'N/A'],
                ['Newest Data', $stats['newest_date'] ?? 'N/A'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Invoke a private method (for accessing discoverPaths).
     *
     * @return mixed
     */
    protected function invokePrivateMethod(object $object, string $method): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke($object);
    }
}
