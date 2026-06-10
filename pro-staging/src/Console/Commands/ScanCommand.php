<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Fibonoir\LaravelSEO\Jobs\ScanPageJob;
use Fibonoir\LaravelSEO\Jobs\ScanSitewideJob;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Services\Scanner\SitewideScanner;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Artisan command for running SEO scans.
 *
 * ## Usage
 *
 * ```bash
 * # Full scan of all SEO-enabled models
 * php artisan seo:scan
 *
 * # Incremental scan (only changed content)
 * php artisan seo:scan --type=incremental
 *
 * # Scan a specific model class
 * php artisan seo:scan --model="App\Models\Post"
 *
 * # Scan a specific model instance
 * php artisan seo:scan --model="App\Models\Post" --id=123
 *
 * # Queue the scan instead of running directly
 * php artisan seo:scan --queue
 *
 * # Dry run (show what would be scanned)
 * php artisan seo:scan --dry-run
 * ```
 *
 * ## Scheduling
 *
 * ```php
 * // In routes/console.php
 * Schedule::command('seo:scan --type=incremental')->daily();
 * Schedule::command('seo:scan --type=full')->weekly();
 * ```
 */
class ScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:scan
                            {--type=full : Scan type (full, incremental)}
                            {--model= : Specific model class to scan}
                            {--id= : Specific model ID to scan (requires --model)}
                            {--queue : Queue the scan job instead of running directly}
                            {--dry-run : Show what would be scanned without scanning}';

    /**
     * The console command description.
     */
    protected $description = 'Run a sitewide SEO scan to find issues and update scores';

    /**
     * Execute the console command.
     */
    public function handle(SitewideScanner $scanner): int
    {
        $type = $this->option('type');
        $modelClass = $this->option('model');
        $modelId = $this->option('id');
        $isDryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');

        // Validate options
        if ($modelId && ! $modelClass) {
            $this->error('The --id option requires --model to be specified.');

            return self::INVALID;
        }

        if (! in_array($type, ['full', 'incremental'])) {
            $this->error("Invalid scan type: {$type}. Use 'full' or 'incremental'.");

            return self::INVALID;
        }

        // Single model scan
        if ($modelId && $modelClass) {
            return $this->scanSingleModel($modelClass, $modelId, $isDryRun);
        }

        // Model class scan
        if ($modelClass) {
            return $this->scanModelClass($modelClass, $type, $isDryRun, $useQueue);
        }

        // Sitewide scan
        return $this->scanSitewide($scanner, $type, $isDryRun, $useQueue);
    }

    /**
     * Scan a single model instance.
     */
    protected function scanSingleModel(string $modelClass, string $modelId, bool $isDryRun): int
    {
        if (! class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");

            return self::FAILURE;
        }

        if (! in_array(HasSEO::class, class_uses_recursive($modelClass), true)) {
            $this->error("Model {$modelClass} does not use the HasSEO trait.");

            return self::FAILURE;
        }

        $model = $modelClass::find($modelId);

        if (! $model) {
            $this->error("Model not found: {$modelClass}#{$modelId}");

            return self::FAILURE;
        }

        $this->info("Scanning: {$modelClass}#{$modelId}");

        if ($isDryRun) {
            $this->line('  <fg=yellow>[DRY RUN]</> Would analyze this model');

            return self::SUCCESS;
        }

        $this->line('  Analyzing...');

        try {
            $model->analyzeForSEO();
            $model->refresh();

            $score = $model->getSEOScore() ?? 'N/A';
            $this->info("  ✓ Complete. Score: {$score}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Scan all instances of a model class.
     */
    protected function scanModelClass(string $modelClass, string $type, bool $isDryRun, bool $useQueue): int
    {
        if (! class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");

            return self::FAILURE;
        }

        if (! in_array(HasSEO::class, class_uses_recursive($modelClass), true)) {
            $this->error("Model {$modelClass} does not use the HasSEO trait.");

            return self::FAILURE;
        }

        $this->info("Scanning model: {$modelClass} ({$type})");
        $this->newLine();

        // Get models to scan
        $query = $modelClass::query();

        if ($type === 'incremental') {
            $query->needingSEOAnalysis();
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No models need scanning.');

            return self::SUCCESS;
        }

        $this->line("Found {$total} models to scan.");

        if ($isDryRun) {
            $this->line('<fg=yellow>[DRY RUN]</> Would scan these models');
            $this->showPreview($query->take(5)->get());

            return self::SUCCESS;
        }

        if ($useQueue) {
            return $this->queueModelScans($modelClass, $query, $total);
        }

        return $this->runModelScans($modelClass, $query, $total);
    }

    /**
     * Run sitewide scan.
     */
    protected function scanSitewide(SitewideScanner $scanner, string $type, bool $isDryRun, bool $useQueue): int
    {
        $this->info("Starting {$type} sitewide SEO scan");
        $this->newLine();

        // Get scannable models
        $models = $scanner->getScanableModels();

        if ($models->isEmpty()) {
            $this->warn('No SEO-enabled models found.');
            $this->line('Ensure your models use the HasSEO trait.');

            return self::SUCCESS;
        }

        // Calculate totals
        $stats = $this->calculateScanStats($models, $type);

        $this->displayScanPlan($models, $stats, $type);

        if ($isDryRun) {
            $this->newLine();
            $this->line('<fg=yellow>[DRY RUN]</> No scan performed.');

            return self::SUCCESS;
        }

        if ($stats['total'] === 0) {
            $this->info('All content is up to date. Nothing to scan.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Proceed with scanning {$stats['total']} items?", true)) {
            $this->line('Scan cancelled.');

            return self::SUCCESS;
        }

        if ($useQueue) {
            return $this->queueSitewideScan($type);
        }

        return $this->runSitewideScan($scanner, $models, $type, $stats);
    }

    /**
     * Calculate scan statistics.
     *
     * @param Collection<int, class-string> $models
     * @return array{total: int, by_model: array<string, int>}
     */
    protected function calculateScanStats(Collection $models, string $type): array
    {
        $stats = [
            'total' => 0,
            'by_model' => [],
        ];

        foreach ($models as $modelClass) {
            if ($type === 'full') {
                $count = $modelClass::count();
            } else {
                // Incremental: count models needing analysis
                $count = SEOMeta::where('seoable_type', $modelClass)
                    ->where(function ($query) {
                        $query->whereNull('analyzed_at')
                            ->orWhere('analyzed_at', '<', now()->subDays(7));
                    })
                    ->count();

                // Also count models without any SEO meta
                $existingIds = SEOMeta::where('seoable_type', $modelClass)->pluck('seoable_id');
                $withoutMeta = $modelClass::whereNotIn('id', $existingIds)->count();
                $count += $withoutMeta;
            }

            $stats['by_model'][$modelClass] = $count;
            $stats['total'] += $count;
        }

        return $stats;
    }

    /**
     * Display the scan plan.
     *
     * @param Collection<int, class-string> $models
     * @param array{total: int, by_model: array<string, int>} $stats
     */
    protected function displayScanPlan(Collection $models, array $stats, string $type): void
    {
        $this->line('<fg=yellow>Scan Plan:</>');
        $this->newLine();

        $rows = [];
        foreach ($stats['by_model'] as $modelClass => $count) {
            $rows[] = [
                class_basename($modelClass),
                $count,
                $type === 'full' ? 'All' : 'Needs analysis',
            ];
        }

        $this->table(['Model', 'Count', 'Scope'], $rows);

        $this->newLine();
        $this->line("Total items to scan: <fg=cyan>{$stats['total']}</>");
    }

    /**
     * Queue a sitewide scan.
     */
    protected function queueSitewideScan(string $type): int
    {
        $this->line('Dispatching scan to queue...');

        // Create scan run record
        $scanRun = SEOScanRun::create([
            'type' => $type,
            'status' => 'pending',
        ]);

        ScanSitewideJob::dispatch($scanRun->id, $type);

        $this->newLine();
        $this->info("✓ Scan job dispatched (Run #{$scanRun->id})");
        $this->line('Monitor progress with: php artisan seo:scan-status ' . $scanRun->id);

        return self::SUCCESS;
    }

    /**
     * Run sitewide scan synchronously.
     *
     * @param Collection<int, class-string> $models
     * @param array{total: int, by_model: array<string, int>} $stats
     */
    protected function runSitewideScan(
        SitewideScanner $scanner,
        Collection $models,
        string $type,
        array $stats,
    ): int {
        $this->newLine();
        $this->line('Starting scan...');
        $this->newLine();

        // Create scan run record
        $scanRun = SEOScanRun::create([
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'total_pages' => $stats['total'],
        ]);

        $bar = $this->output->createProgressBar($stats['total']);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Initializing...');
        $bar->start();

        $results = [
            'scanned' => 0,
            'passed' => 0,
            'warning' => 0,
            'failed' => 0,
            'errors' => 0,
        ];

        foreach ($models as $modelClass) {
            $bar->setMessage("Scanning " . class_basename($modelClass) . "...");

            $query = $modelClass::query();

            if ($type === 'incremental') {
                $query->needingSEOAnalysis();
            }

            $query->chunk(50, function ($items) use ($bar, &$results) {
                foreach ($items as $model) {
                    try {
                        $model->analyzeForSEO();
                        $model->refresh();

                        $score = $model->getSEOScore();
                        $results['scanned']++;

                        if ($score >= 70) {
                            $results['passed']++;
                        } elseif ($score >= 50) {
                            $results['warning']++;
                        } else {
                            $results['failed']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors']++;
                    }

                    $bar->advance();
                }
            });
        }

        $bar->setMessage('Complete!');
        $bar->finish();

        $this->newLine(2);

        // Update scan run
        $scanRun->update([
            'status' => 'completed',
            'completed_at' => now(),
            'scanned_pages' => $results['scanned'],
        ]);

        // Display summary
        $this->displaySummary($results, $scanRun);

        return $results['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Queue model class scans.
     */
    protected function queueModelScans(string $modelClass, $query, int $total): int
    {
        $this->line('Dispatching jobs to queue...');

        $jobs = [];
        $query->select(['id'])->chunk(50, function ($items) use ($modelClass, &$jobs) {
            foreach ($items as $item) {
                $jobs[] = new ScanPageJob($modelClass, $item->id);
            }
        });

        Bus::batch($jobs)
            ->name("SEO Scan: " . class_basename($modelClass))
            ->allowFailures()
            ->dispatch();

        $this->newLine();
        $this->info("✓ Dispatched {$total} scan jobs to queue.");

        return self::SUCCESS;
    }

    /**
     * Run model class scans synchronously.
     */
    protected function runModelScans(string $modelClass, $query, int $total): int
    {
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->start();

        $results = [
            'scanned' => 0,
            'passed' => 0,
            'warning' => 0,
            'failed' => 0,
            'errors' => 0,
        ];

        $query->chunk(50, function ($items) use ($bar, &$results) {
            foreach ($items as $model) {
                $bar->setMessage("ID: {$model->id}");

                try {
                    $model->analyzeForSEO();
                    $model->refresh();

                    $score = $model->getSEOScore();
                    $results['scanned']++;

                    if ($score >= 70) {
                        $results['passed']++;
                    } elseif ($score >= 50) {
                        $results['warning']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                }

                $bar->advance();
            }
        });

        $bar->setMessage('Done');
        $bar->finish();

        $this->newLine(2);
        $this->displaySummary($results);

        return $results['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Display scan summary.
     */
    protected function displaySummary(array $results, ?SEOScanRun $scanRun = null): void
    {
        $this->line('<fg=yellow>Scan Summary:</>');
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Total Scanned', $results['scanned']],
            ['<fg=green>Good (70+)</>', "<fg=green>{$results['passed']}</>"],
            ['<fg=yellow>Needs Work (50-69)</>', "<fg=yellow>{$results['warning']}</>"],
            ['<fg=red>Poor (<50)</>', "<fg=red>{$results['failed']}</>"],
            ['Errors', $results['errors']],
        ]);

        if ($scanRun) {
            $this->newLine();
            $this->line("Scan Run ID: {$scanRun->id}");
            $duration = $scanRun->started_at?->diffForHumans($scanRun->completed_at, true);
            $this->line("Duration: {$duration}");
        }

        // Show score distribution
        $total = $results['scanned'];
        if ($total > 0) {
            $passedPct = round(($results['passed'] / $total) * 100);
            $warningPct = round(($results['warning'] / $total) * 100);
            $failedPct = round(($results['failed'] / $total) * 100);

            $this->newLine();
            $this->line('Score Distribution:');
            $this->line("  <fg=green>■</> Good: {$passedPct}%  <fg=yellow>■</> Needs Work: {$warningPct}%  <fg=red>■</> Poor: {$failedPct}%");
        }
    }

    /**
     * Show preview of models to scan.
     *
     * @param Collection $models
     */
    protected function showPreview(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('Preview (first 5):');

        foreach ($models as $model) {
            $title = $model->title ?? $model->name ?? "ID: {$model->id}";
            $score = $model->seoMeta?->seo_score ?? 'N/A';
            $this->line("  - {$title} (Score: {$score})");
        }
    }
}
