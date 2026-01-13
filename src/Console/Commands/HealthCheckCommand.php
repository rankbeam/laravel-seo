<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Fibonoir\LaravelSEO\Models\SEOMeta;

/**
 * Artisan command to check SEO suite health.
 *
 * ## Usage
 *
 * ```bash
 * # Run health check
 * php artisan seo:health
 *
 * # Verbose output
 * php artisan seo:health -v
 *
 * # JSON output (for monitoring)
 * php artisan seo:health --json
 * ```
 *
 * ## Exit Codes
 *
 * - 0: All critical checks passed
 * - 1: One or more critical checks failed
 *
 * ## Monitoring Integration
 *
 * ```bash
 * # Use in monitoring scripts
 * php artisan seo:health --json | jq '.status'
 *
 * # Cron job to alert on failures
 * php artisan seo:health || notify-admin "SEO health check failed"
 * ```
 */
class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:health
                            {--json : Output results as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Check the health of the SEO suite installation';

    /**
     * Collected check results.
     *
     * @var array<string, array{status: string, message: string, critical: bool}>
     */
    protected array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('json')) {
            $this->displayHeader();
        }

        // Run all checks
        $this->checkDatabaseTables();
        $this->checkConfiguration();
        $this->checkCacheSystem();
        $this->checkQueueWorker();
        $this->checkRequiredPackages();
        $this->checkOptionalPackages();
        $this->checkDataIntegrity();

        // Output results
        if ($this->option('json')) {
            return $this->outputJson();
        }

        return $this->outputPretty();
    }

    /**
     * Display header.
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════╗');
        $this->line('║        <fg=cyan>SEO Suite Health Check</>              ║');
        $this->line('╚══════════════════════════════════════════════╝');
        $this->newLine();
    }

    /**
     * Check if required database tables exist.
     */
    protected function checkDatabaseTables(): void
    {
        $tables = [
            'seo_meta' => true,           // Critical
            'seo_defaults' => true,       // Critical
            'seo_redirects' => true,      // Critical
            'seo_404_logs' => false,      // Optional
            'seo_scan_runs' => false,     // Optional
            'seo_scan_issues' => false,   // Optional
            'seo_analytics_cache' => false, // Optional
            'seo_internal_links_index' => false, // Optional
        ];

        foreach ($tables as $table => $critical) {
            $exists = false;

            try {
                $exists = Schema::hasTable($table);
            } catch (\Exception $e) {
                // Database connection issue
            }

            if ($exists) {
                $this->addResult(
                    "table_{$table}",
                    'pass',
                    "Table '{$table}' exists",
                    $critical
                );
            } else {
                $status = $critical ? 'fail' : 'warn';
                $this->addResult(
                    "table_{$table}",
                    $status,
                    "Table '{$table}' " . ($critical ? 'missing (required)' : 'missing (optional)'),
                    $critical
                );
            }
        }
    }

    /**
     * Check configuration.
     */
    protected function checkConfiguration(): void
    {
        // Config file loaded
        $configLoaded = config('seo') !== null;
        $this->addResult(
            'config_loaded',
            $configLoaded ? 'pass' : 'fail',
            $configLoaded ? 'Configuration file loaded' : 'Configuration file not loaded',
            true
        );

        if (! $configLoaded) {
            return;
        }

        // Stack configuration
        $stack = config('seo.stack');
        $validStacks = ['filament', 'livewire', 'vue', 'react', 'api'];
        $stackValid = in_array($stack, $validStacks, true);

        $this->addResult(
            'config_stack',
            $stackValid ? 'pass' : 'fail',
            $stackValid ? "Stack configured: {$stack}" : "Invalid stack: {$stack}",
            true
        );

        // Site name
        $siteName = config('seo.site_name');
        $this->addResult(
            'config_site_name',
            ! empty($siteName) ? 'pass' : 'warn',
            ! empty($siteName) ? "Site name: {$siteName}" : 'Site name not configured',
            false
        );

        // Cache store
        $cacheStore = config('seo.cache.store');
        $recommendedStores = ['redis', 'memcached', 'database'];
        $cacheOptimal = in_array($cacheStore, $recommendedStores, true);

        $this->addResult(
            'config_cache',
            $cacheOptimal ? 'pass' : 'warn',
            $cacheOptimal
                ? "Cache store: {$cacheStore} (recommended)"
                : "Cache store: " . ($cacheStore ?: 'default') . " (consider redis/memcached for production)",
            false
        );
    }

    /**
     * Check cache system.
     */
    protected function checkCacheSystem(): void
    {
        $store = config('seo.cache.store');

        try {
            $cache = Cache::store($store);
            $testKey = 'seo_health_check_' . time();

            // Test write
            $cache->put($testKey, 'test', 60);

            // Test read
            $value = $cache->get($testKey);

            // Clean up
            $cache->forget($testKey);

            if ($value === 'test') {
                $this->addResult(
                    'cache_working',
                    'pass',
                    'Cache read/write working',
                    true
                );
            } else {
                $this->addResult(
                    'cache_working',
                    'fail',
                    'Cache read failed (write succeeded)',
                    true
                );
            }
        } catch (\Exception $e) {
            $this->addResult(
                'cache_working',
                'fail',
                'Cache error: ' . $e->getMessage(),
                true
            );
        }

        // Check if SEO caches are warmed
        try {
            $redirectsCached = Cache::store($store)->has(
                config('seo.cache.prefix', 'seo_') . 'redirects'
            );

            $this->addResult(
                'cache_warmed',
                $redirectsCached ? 'pass' : 'warn',
                $redirectsCached
                    ? 'SEO caches are warmed'
                    : 'SEO caches not warmed (run: php artisan seo:cache --warm)',
                false
            );
        } catch (\Exception $e) {
            // Ignore cache check errors
        }
    }

    /**
     * Check queue worker status.
     */
    protected function checkQueueWorker(): void
    {
        // Check if queue is configured for sync (not ideal for production)
        $connection = config('queue.default');

        if ($connection === 'sync') {
            $this->addResult(
                'queue_connection',
                'warn',
                "Queue connection: sync (jobs run immediately, not recommended for production)",
                false
            );

            return;
        }

        $this->addResult(
            'queue_connection',
            'pass',
            "Queue connection: {$connection}",
            false
        );

        // Try to detect if workers are running (check for recent jobs)
        try {
            // Check failed jobs table for recent activity
            if (Schema::hasTable('failed_jobs')) {
                $recentFailed = DB::table('failed_jobs')
                    ->where('failed_at', '>', now()->subDay())
                    ->count();

                if ($recentFailed > 10) {
                    $this->addResult(
                        'queue_health',
                        'warn',
                        "High failed job count: {$recentFailed} in last 24h",
                        false
                    );
                } else {
                    $this->addResult(
                        'queue_health',
                        'pass',
                        'Queue appears healthy',
                        false
                    );
                }
            }
        } catch (\Exception $e) {
            // Can't check queue health, skip
        }
    }

    /**
     * Check required packages.
     */
    protected function checkRequiredPackages(): void
    {
        // PHP Stemmer (wamania/php-stemmer)
        $stemmerInstalled = class_exists(\Wamania\Snowball\StemmerFactory::class);
        $this->addResult(
            'pkg_stemmer',
            $stemmerInstalled ? 'pass' : 'fail',
            $stemmerInstalled
                ? 'wamania/php-stemmer installed'
                : 'wamania/php-stemmer not installed (required for analysis)',
            true
        );
    }

    /**
     * Check optional packages.
     */
    protected function checkOptionalPackages(): void
    {
        // Spatie Sitemap
        $sitemapInstalled = class_exists(\Spatie\Sitemap\Sitemap::class);
        $sitemapEnabled = config('seo.features.sitemap', true);

        if ($sitemapEnabled && ! $sitemapInstalled) {
            $this->addResult(
                'pkg_sitemap',
                'warn',
                'spatie/laravel-sitemap not installed (needed for sitemap generation)',
                false
            );
        } elseif ($sitemapInstalled) {
            $this->addResult(
                'pkg_sitemap',
                'pass',
                'spatie/laravel-sitemap installed',
                false
            );
        }

        // Google API Client
        $googleInstalled = class_exists(\Google\Client::class);
        $analyticsEnabled = config('seo.analytics.enabled', false);

        if ($analyticsEnabled) {
            if ($googleInstalled) {
                $this->addResult(
                    'pkg_google',
                    'pass',
                    'google/apiclient installed',
                    false
                );

                // Check GA4 configuration
                $propertyId = config('seo.analytics.property_id');
                $credentialsPath = config('seo.analytics.credentials_path');
                $credentialsExist = $credentialsPath && file_exists($credentialsPath);

                $this->addResult(
                    'ga4_property',
                    $propertyId ? 'pass' : 'warn',
                    $propertyId ? "GA4 Property ID configured" : 'GA4 Property ID not set',
                    false
                );

                $this->addResult(
                    'ga4_credentials',
                    $credentialsExist ? 'pass' : 'warn',
                    $credentialsExist ? 'GA4 credentials file exists' : 'GA4 credentials file not found',
                    false
                );
            } else {
                $this->addResult(
                    'pkg_google',
                    'fail',
                    'google/apiclient not installed (required when analytics enabled)',
                    false
                );
            }
        }

        // Browsershot (for JS rendering)
        $browsershotInstalled = class_exists(\Spatie\Browsershot\Browsershot::class);
        $jsRenderingEnabled = config('seo.scanner.javascript_rendering', false);

        if ($jsRenderingEnabled && ! $browsershotInstalled) {
            $this->addResult(
                'pkg_browsershot',
                'warn',
                'spatie/browsershot not installed (needed for JS rendering)',
                false
            );
        }
    }

    /**
     * Check data integrity.
     */
    protected function checkDataIntegrity(): void
    {
        try {
            // Check for orphaned SEO meta records
            $totalMeta = SEOMeta::count();
            $orphaned = 0;

            // Sample check (don't load all records)
            SEOMeta::select(['id', 'seoable_type', 'seoable_id'])
                ->take(100)
                ->get()
                ->each(function ($meta) use (&$orphaned) {
                    if (! class_exists($meta->seoable_type)) {
                        $orphaned++;
                    } elseif (! $meta->seoable_type::find($meta->seoable_id)) {
                        $orphaned++;
                    }
                });

            if ($orphaned > 0) {
                $this->addResult(
                    'data_orphaned',
                    'warn',
                    "Found {$orphaned} potentially orphaned SEO records (sample of 100)",
                    false
                );
            } else {
                $this->addResult(
                    'data_integrity',
                    'pass',
                    "Data integrity check passed ({$totalMeta} SEO records)",
                    false
                );
            }

            // Check for unanalyzed content
            $unanalyzed = SEOMeta::whereNull('analyzed_at')->count();
            $stale = SEOMeta::where('analyzed_at', '<', now()->subDays(30))->count();

            if ($unanalyzed > 0 || $stale > 0) {
                $message = [];
                if ($unanalyzed > 0) {
                    $message[] = "{$unanalyzed} never analyzed";
                }
                if ($stale > 0) {
                    $message[] = "{$stale} stale (>30 days)";
                }

                $this->addResult(
                    'data_analysis',
                    'warn',
                    'Content needs analysis: ' . implode(', ', $message),
                    false
                );
            } else {
                $this->addResult(
                    'data_analysis',
                    'pass',
                    'All content recently analyzed',
                    false
                );
            }
        } catch (\Exception $e) {
            // Database might not be set up yet
        }
    }

    /**
     * Add a check result.
     */
    protected function addResult(string $key, string $status, string $message, bool $critical): void
    {
        $this->results[$key] = [
            'status' => $status,
            'message' => $message,
            'critical' => $critical,
        ];
    }

    /**
     * Output results as JSON.
     */
    protected function outputJson(): int
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $criticalFailed = false;

        foreach ($this->results as $result) {
            if ($result['status'] === 'pass') {
                $passed++;
            } elseif ($result['status'] === 'fail') {
                $failed++;
                if ($result['critical']) {
                    $criticalFailed = true;
                }
            } else {
                $warnings++;
            }
        }

        $output = [
            'status' => $criticalFailed ? 'unhealthy' : 'healthy',
            'summary' => [
                'passed' => $passed,
                'failed' => $failed,
                'warnings' => $warnings,
            ],
            'checks' => $this->results,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT));

        return $criticalFailed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Output results in pretty format.
     */
    protected function outputPretty(): int
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $criticalFailed = false;

        // Group results by category
        $categories = [
            'Database' => ['table_'],
            'Configuration' => ['config_'],
            'Cache' => ['cache_'],
            'Queue' => ['queue_'],
            'Packages' => ['pkg_'],
            'Analytics' => ['ga4_'],
            'Data' => ['data_'],
        ];

        foreach ($categories as $categoryName => $prefixes) {
            $categoryResults = [];

            foreach ($this->results as $key => $result) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $categoryResults[$key] = $result;
                        break;
                    }
                }
            }

            if (empty($categoryResults)) {
                continue;
            }

            $this->line("<fg=yellow>{$categoryName}:</>");

            foreach ($categoryResults as $result) {
                $icon = match ($result['status']) {
                    'pass' => '<fg=green>✓</>',
                    'fail' => '<fg=red>✗</>',
                    'warn' => '<fg=yellow>!</>',
                    default => '<fg=gray>○</>',
                };

                $color = match ($result['status']) {
                    'pass' => 'green',
                    'fail' => 'red',
                    'warn' => 'yellow',
                    default => 'gray',
                };

                $this->line("  {$icon} <fg={$color}>{$result['message']}</>");

                if ($result['status'] === 'pass') {
                    $passed++;
                } elseif ($result['status'] === 'fail') {
                    $failed++;
                    if ($result['critical']) {
                        $criticalFailed = true;
                    }
                } else {
                    $warnings++;
                }
            }

            $this->newLine();
        }

        // Summary
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $total = $passed + $failed + $warnings;

        if ($criticalFailed) {
            $this->error("Health Check Failed: {$failed} critical issue(s)");
        } elseif ($warnings > 0) {
            $this->warn("Health Check Passed with Warnings: {$warnings} warning(s)");
        } else {
            $this->info("Health Check Passed: All {$total} checks OK");
        }

        $this->newLine();
        $this->line("  <fg=green>Passed:</> {$passed}  <fg=yellow>Warnings:</> {$warnings}  <fg=red>Failed:</> {$failed}");
        $this->newLine();

        return $criticalFailed ? self::FAILURE : self::SUCCESS;
    }
}
