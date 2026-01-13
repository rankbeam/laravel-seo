<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Console\Commands;

use Illuminate\Console\Command;
use Fibonoir\LaravelSEO\Services\CacheManager;

/**
 * Artisan command for managing SEO caches.
 *
 * Provides a CLI interface to the CacheManager service for warming,
 * clearing, and monitoring SEO-related caches.
 *
 * ## Usage
 *
 * ```bash
 * # Warm all caches
 * php artisan seo:cache --warm
 *
 * # Clear all caches
 * php artisan seo:cache --clear
 *
 * # Show statistics
 * php artisan seo:cache --stats
 *
 * # Warm specific caches only
 * php artisan seo:cache --warm --only=redirects
 * php artisan seo:cache --warm --only=defaults
 * php artisan seo:cache --warm --only=link-index
 *
 * # Clear for specific model
 * php artisan seo:cache --clear --model="App\Models\Post" --id=123
 * ```
 */
class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:cache
                            {--warm : Warm all SEO caches}
                            {--clear : Clear all SEO caches}
                            {--stats : Show cache statistics}
                            {--only= : Only warm/clear specific cache (redirects, defaults, link-index, analytics)}
                            {--model= : Model class for model-specific operations}
                            {--id= : Model ID for model-specific operations}';

    /**
     * The console command description.
     */
    protected $description = 'Manage SEO caches - warm, clear, or view statistics';

    /**
     * Execute the console command.
     */
    public function handle(CacheManager $cacheManager): int
    {
        $hasAction = $this->option('warm') || $this->option('clear') || $this->option('stats');

        if (! $hasAction) {
            $this->error('Please specify an action: --warm, --clear, or --stats');
            $this->newLine();
            $this->line('Examples:');
            $this->line('  php artisan seo:cache --warm      # Warm all caches');
            $this->line('  php artisan seo:cache --clear     # Clear all caches');
            $this->line('  php artisan seo:cache --stats     # Show statistics');

            return self::INVALID;
        }

        if ($this->option('stats')) {
            return $this->showStats($cacheManager);
        }

        if ($this->option('warm')) {
            return $this->warmCache($cacheManager);
        }

        if ($this->option('clear')) {
            return $this->clearCache($cacheManager);
        }

        return self::SUCCESS;
    }

    /**
     * Show cache statistics.
     */
    protected function showStats(CacheManager $cacheManager): int
    {
        $this->info('SEO Cache Statistics');
        $this->newLine();

        $stats = $cacheManager->getStats();

        // Cache configuration
        $this->line('<comment>Configuration:</comment>');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Store', $stats['store']],
                ['Prefix', $stats['prefix']],
            ]
        );

        // Cache keys status
        $this->newLine();
        $this->line('<comment>Cache Keys Status:</comment>');
        $keyRows = [];
        foreach ($stats['keys'] as $key => $exists) {
            $keyRows[] = [
                $key,
                $exists ? '<info>✓ Cached</info>' : '<fg=yellow>✗ Not cached</>',
            ];
        }
        $this->table(['Key', 'Status'], $keyRows);

        // Database counts
        $this->newLine();
        $this->line('<comment>Database Counts:</comment>');
        $countRows = [];
        foreach ($stats['database_counts'] as $table => $count) {
            $countRows[] = [$table, number_format($count)];
        }
        $this->table(['Table', 'Count'], $countRows);

        // Recommendations
        if (! empty($stats['recommendations'])) {
            $this->newLine();
            $this->line('<comment>Recommendations:</comment>');
            foreach ($stats['recommendations'] as $rec) {
                $this->line("  <fg=yellow>⚠</> {$rec}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Warm caches.
     */
    protected function warmCache(CacheManager $cacheManager): int
    {
        $only = $this->option('only');

        if ($only) {
            return $this->warmSpecificCache($cacheManager, $only);
        }

        $this->info('Warming all SEO caches...');
        $this->newLine();

        $startTime = microtime(true);

        $stats = $cacheManager->warmCache(verbose: true);

        $this->table(
            ['Cache', 'Items Cached'],
            [
                ['Redirects', $stats['redirects']],
                ['Defaults', $stats['defaults']],
                ['Link Index', $stats['link_index']],
            ]
        );

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->newLine();
        $this->info("✓ Cache warming complete in {$duration}ms");

        return self::SUCCESS;
    }

    /**
     * Warm a specific cache.
     */
    protected function warmSpecificCache(CacheManager $cacheManager, string $type): int
    {
        $count = match ($type) {
            'redirects' => $cacheManager->warmRedirectsCache(),
            'defaults' => $cacheManager->warmDefaultsCache(),
            'link-index', 'link_index' => $cacheManager->warmLinkIndexCache(),
            default => null,
        };

        if ($count === null) {
            $this->error("Unknown cache type: {$type}");
            $this->line('Valid types: redirects, defaults, link-index');

            return self::FAILURE;
        }

        $this->info("✓ Warmed {$type} cache: {$count} items cached");

        return self::SUCCESS;
    }

    /**
     * Clear caches.
     */
    protected function clearCache(CacheManager $cacheManager): int
    {
        // Check for model-specific clear
        $modelClass = $this->option('model');
        $modelId = $this->option('id');

        if ($modelClass && $modelId) {
            return $this->clearModelCache($cacheManager, $modelClass, $modelId);
        }

        $only = $this->option('only');

        if ($only) {
            return $this->clearSpecificCache($cacheManager, $only);
        }

        if (! $this->confirm('This will clear ALL SEO caches. Continue?', true)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $this->info('Clearing all SEO caches...');

        $result = $cacheManager->clearAll();

        $this->info('✓ Cleared caches: ' . implode(', ', $result['cleared']));

        return self::SUCCESS;
    }

    /**
     * Clear cache for a specific model.
     */
    protected function clearModelCache(CacheManager $cacheManager, string $modelClass, string $modelId): int
    {
        if (! class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");

            return self::FAILURE;
        }

        $model = $modelClass::find($modelId);

        if (! $model) {
            $this->error("Model not found: {$modelClass}#{$modelId}");

            return self::FAILURE;
        }

        $cacheManager->clearForModel($model);

        $this->info("✓ Cleared cache for {$modelClass}#{$modelId}");

        return self::SUCCESS;
    }

    /**
     * Clear a specific cache.
     */
    protected function clearSpecificCache(CacheManager $cacheManager, string $type): int
    {
        $success = match ($type) {
            'redirects' => tap(true, fn () => $cacheManager->clearRedirectsCache()),
            'defaults' => tap(true, fn () => $cacheManager->clearDefaultsCache()),
            'link-index', 'link_index' => tap(true, fn () => $cacheManager->clearLinkIndexCache()),
            'analytics' => tap(true, fn () => $cacheManager->clearAnalyticsCache()),
            'sitemap' => tap(true, fn () => $cacheManager->clearSitemapCache()),
            default => false,
        };

        if (! $success) {
            $this->error("Unknown cache type: {$type}");
            $this->line('Valid types: redirects, defaults, link-index, analytics, sitemap');

            return self::FAILURE;
        }

        $this->info("✓ Cleared {$type} cache");

        return self::SUCCESS;
    }
}
