<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Services\Scanner\SitewideScanner;

/**
 * Job to perform a sitewide SEO scan.
 *
 * This job orchestrates scanning across all SEO-enabled models,
 * dispatching batched page scan jobs for parallel processing.
 *
 * ## Types
 * - **full:** Scans all models regardless of last scan
 * - **incremental:** Only scans models changed since last analysis
 *
 * ## Features
 * - ShouldBeUnique: Prevents duplicate scans
 * - Uses Laravel Bus::batch() for efficient processing
 * - Tracks progress via SEOScanRun model
 */
class ScanSitewideJob implements ShouldQueue, ShouldBeUnique
{
    use Batchable;
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
    public int $timeout = 3600; // 1 hour

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     *
     * @param int|null $scanRunId Existing scan run ID, or null to create new
     * @param string $type 'full' or 'incremental'
     */
    public function __construct(
        public readonly ?int $scanRunId = null,
        public readonly string $type = 'incremental',
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'seo-scan-' . $this->type;
    }

    /**
     * Execute the job.
     */
    public function handle(SitewideScanner $scanner): void
    {
        // Get or create scan run
        $scanRun = $this->scanRunId
            ? SEOScanRun::find($this->scanRunId)
            : SEOScanRun::create([
                'type' => $this->type,
                'status' => 'pending',
            ]);

        if (! $scanRun) {
            Log::error('ScanSitewideJob: Scan run not found', ['id' => $this->scanRunId]);

            return;
        }

        try {
            // Get scannable models
            $models = $scanner->getScanableModels();

            if ($models->isEmpty()) {
                $scanRun->complete();
                Log::info('ScanSitewideJob: No scannable models found');

                return;
            }

            // Calculate total pages
            $totalPages = $this->calculateTotalPages($models, $scanner);
            $scanRun->setTotal($totalPages);
            $scanRun->start();

            // Get batch size from config
            $batchSize = config('seo.scanner.batch_size', 50);

            // Build jobs for batch
            $jobs = $this->buildScanJobs($models, $scanRun, $batchSize);

            if (empty($jobs)) {
                $scanRun->complete();

                return;
            }

            // Dispatch as batch
            Bus::batch($jobs)
                ->name("SEO Scan: {$this->type} - Run #{$scanRun->id}")
                ->allowFailures()
                ->finally(function () use ($scanRun) {
                    // Refresh to get latest counts
                    $scanRun->refresh();

                    if ($scanRun->status === 'running') {
                        $scanRun->complete();
                    }
                })
                ->dispatch();

        } catch (\Exception $e) {
            $scanRun->fail($e->getMessage());

            throw $e;
        }
    }

    /**
     * Calculate total pages to scan based on type.
     *
     * @param \Illuminate\Support\Collection $models
     */
    protected function calculateTotalPages($models, SitewideScanner $scanner): int
    {
        if ($this->type === 'full') {
            return $models->sum(fn ($class) => $class::count());
        }

        // Incremental: count changed models
        return $models->sum(function ($class) {
            $table = (new $class())->getTable();

            return \Fibonoir\LaravelSEO\Models\SEOMeta::where('seoable_type', $class)
                ->where(function ($query) use ($table) {
                    $query->whereNull('analyzed_at')
                        ->orWhereRaw("analyzed_at < (SELECT updated_at FROM {$table} WHERE id = seoable_id)");
                })
                ->count();
        });
    }

    /**
     * Build scan jobs for all models.
     *
     * @param \Illuminate\Support\Collection $models
     * @return array<int, ScanPageJob>
     */
    protected function buildScanJobs($models, SEOScanRun $scanRun, int $batchSize): array
    {
        $jobs = [];

        foreach ($models as $modelClass) {
            $query = $this->type === 'full'
                ? $modelClass::query()
                : $this->getChangedModelsQuery($modelClass);

            $query->select(['id'])->chunkById($batchSize, function ($items) use ($modelClass, $scanRun, &$jobs) {
                foreach ($items as $item) {
                    $jobs[] = new ScanPageJob($modelClass, $item->id, $scanRun->id);
                }
            });
        }

        return $jobs;
    }

    /**
     * Get query for changed models (incremental scan).
     *
     * @param class-string $modelClass
     */
    protected function getChangedModelsQuery(string $modelClass)
    {
        $table = (new $modelClass())->getTable();

        // Models that need scanning
        $needsScanning = \Fibonoir\LaravelSEO\Models\SEOMeta::where('seoable_type', $modelClass)
            ->where(function ($query) use ($table) {
                $query->whereNull('analyzed_at')
                    ->orWhereRaw("analyzed_at < (SELECT updated_at FROM {$table} WHERE id = seoable_id)");
            })
            ->pluck('seoable_id');

        return $modelClass::whereIn('id', $needsScanning);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScanSitewideJob failed', [
            'type' => $this->type,
            'scan_run_id' => $this->scanRunId,
            'error' => $exception->getMessage(),
        ]);

        if ($this->scanRunId) {
            $scanRun = SEOScanRun::find($this->scanRunId);
            $scanRun?->fail($exception->getMessage());
        }
    }
}
