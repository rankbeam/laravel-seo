<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEOScanIssue;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer;
use Fibonoir\LaravelSEO\Services\Scanner\PageScanner;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Job to scan a single page/model for SEO issues.
 *
 * This job is dispatched as part of a batch by ScanSitewideJob.
 * It performs both issue scanning and content analysis.
 *
 * ## Process
 * 1. Load the model
 * 2. Run PageScanner for structural issues
 * 3. Create SEOScanIssue records
 * 4. If content changed, run ContentAnalyzer
 * 5. Dispatch ValidateLinksJob for async link checking
 * 6. Update scan run progress
 */
class ScanPageJob implements ShouldQueue
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
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

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
    public function handle(PageScanner $pageScanner, ContentAnalyzer $contentAnalyzer): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            // Load the model
            $model = $this->modelClass::find($this->modelId);

            if (! $model) {
                Log::warning('ScanPageJob: Model not found', [
                    'class' => $this->modelClass,
                    'id' => $this->modelId,
                ]);
                $this->updateProgress();

                return;
            }

            // Verify model has SEO trait
            if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
                $this->updateProgress();

                return;
            }

            // Run page scanner for structural issues
            $issues = $pageScanner->scan($model);

            // Clear existing open issues for this model (from this scan)
            SEOScanIssue::where('scannable_type', $this->modelClass)
                ->where('scannable_id', $this->modelId)
                ->where('status', 'open')
                ->when($this->scanRunId, fn ($q) => $q->where('scan_run_id', $this->scanRunId))
                ->delete();

            // Create new issue records
            $url = method_exists($model, 'getUrlForSEO') ? $model->getUrlForSEO() : null;

            foreach ($issues as $issueData) {
                SEOScanIssue::create([
                    'scannable_type' => $this->modelClass,
                    'scannable_id' => $this->modelId,
                    'url' => $issueData['url'] ?? $url,
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

            // Check if content has changed since last analysis
            if ($this->shouldAnalyzeContent($model)) {
                $this->analyzeContent($model, $contentAnalyzer);
            }

            // Dispatch async link validation
            ValidateLinksJob::dispatch($this->modelClass, $this->modelId, 'links');
            ValidateLinksJob::dispatch($this->modelClass, $this->modelId, 'images');

            // Update progress
            $this->updateProgress(count($issues));

        } catch (\Exception $e) {
            Log::error('ScanPageJob: Error scanning model', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if content should be analyzed.
     */
    protected function shouldAnalyzeContent($model): bool
    {
        $seoMeta = $model->seoMeta;

        // No meta yet - analyze
        if (! $seoMeta) {
            return true;
        }

        // No previous analysis - analyze
        if (empty($seoMeta->analysis_report)) {
            return true;
        }

        // Check if content has changed
        if (method_exists($model, 'getContentForSEO')) {
            $currentContent = $model->getContentForSEO();
            $currentHash = md5($currentContent);

            if ($seoMeta->content_hash !== $currentHash) {
                return true;
            }
        }

        // Check analyzed_at vs model updated_at
        if ($seoMeta->analyzed_at && $model->updated_at) {
            return $model->updated_at->gt($seoMeta->analyzed_at);
        }

        return false;
    }

    /**
     * Run content analysis on the model.
     */
    protected function analyzeContent($model, ContentAnalyzer $contentAnalyzer): void
    {
        $locale = $model->seoMeta?->locale ?? config('app.locale');

        try {
            $report = $contentAnalyzer->analyze($model, $locale);

            // Get content hash
            $contentHash = method_exists($model, 'getContentForSEO')
                ? md5($model->getContentForSEO())
                : null;

            // Update SEO meta
            $model->seoMeta()->updateOrCreate(
                ['locale' => $locale],
                [
                    'seo_score' => $report->totalScore,
                    'analysis_report' => $report->toArray(),
                    'content_hash' => $contentHash,
                    'analyzed_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::warning('ScanPageJob: Content analysis failed', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);
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
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScanPageJob failed', [
            'class' => $this->modelClass,
            'id' => $this->modelId,
            'scan_run_id' => $this->scanRunId,
            'error' => $exception->getMessage(),
        ]);

        // Still update progress even on failure
        $this->updateProgress();
    }
}
