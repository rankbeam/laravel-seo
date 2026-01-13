<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEOScanIssue;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Services\Scanner\PageScanner;

/**
 * Job to scan a single model for SEO issues.
 *
 * This job is dispatched by SitewideScanner for each model
 * that needs to be analyzed. It runs asynchronously to allow
 * parallel processing of large sites.
 *
 * ## Features
 * - Scans model for SEO issues
 * - Creates issue records in database
 * - Updates scan run progress
 * - Handles completion detection
 */
class ScanModelJob implements ShouldQueue
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
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param class-string $modelClass
     * @param int|string $modelId
     */
    public function __construct(
        public string $modelClass,
        public int|string $modelId,
        public ?int $scanRunId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PageScanner $pageScanner): void
    {
        try {
            // Load the model
            $model = $this->modelClass::find($this->modelId);

            if (! $model) {
                Log::warning("ScanModelJob: Model not found", [
                    'class' => $this->modelClass,
                    'id' => $this->modelId,
                ]);
                $this->updateProgress();

                return;
            }

            // Scan the model
            $issues = $pageScanner->scan($model);

            // Clear existing open issues for this model
            SEOScanIssue::where('scannable_type', $this->modelClass)
                ->where('scannable_id', $this->modelId)
                ->where('status', 'open')
                ->delete();

            // Create new issues
            foreach ($issues as $issueData) {
                SEOScanIssue::create([
                    'scannable_type' => $this->modelClass,
                    'scannable_id' => $this->modelId,
                    'url' => $issueData['url'] ?? (method_exists($model, 'getUrlForSEO') ? $model->getUrlForSEO() : null),
                    'issue_type' => $issueData['issue_type'],
                    'severity' => $issueData['severity'],
                    'field' => $issueData['field'] ?? null,
                    'message' => $issueData['message'],
                    'context' => $issueData['context'] ?? null,
                    'status' => 'open',
                    'detected_at' => now(),
                    'scan_run_id' => $this->scanRunId,
                ]);
            }

            // Update analyzed timestamp on SEO meta
            if ($model->seoMeta) {
                $model->seoMeta->update(['analyzed_at' => now()]);
            }

            $this->updateProgress(count($issues));
        } catch (\Exception $e) {
            Log::error("ScanModelJob: Error scanning model", [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update scan run progress.
     */
    protected function updateProgress(int $issuesFound = 0): void
    {
        if (! $this->scanRunId) {
            return;
        }

        $scanRun = SEOScanRun::find($this->scanRunId);

        if (! $scanRun) {
            return;
        }

        $scanRun->incrementProgress($issuesFound);

        // Check if all pages scanned
        if ($scanRun->scanned_pages >= $scanRun->total_pages) {
            $scanRun->complete();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ScanModelJob failed", [
            'class' => $this->modelClass,
            'id' => $this->modelId,
            'scan_run_id' => $this->scanRunId,
            'error' => $exception->getMessage(),
        ]);

        // Still update progress even on failure
        $this->updateProgress();
    }
}
