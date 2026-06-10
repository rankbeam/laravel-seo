<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Job to analyze content and calculate SEO score.
 *
 * This job runs the ContentAnalyzer on a model to:
 * - Calculate SEO score based on all registered rules
 * - Generate detailed analysis report
 * - Store results in seo_meta
 * - Dispatch link validation for async checking
 *
 * ## Usage
 * Typically dispatched automatically when:
 * - Model is created/updated (via HasSEO trait)
 * - Manual analysis is triggered
 * - Part of a sitewide scan
 *
 * ```php
 * AnalyzeContentJob::dispatch($post);
 * AnalyzeContentJob::dispatch($post, 'de'); // Specific locale
 * ```
 */
class AnalyzeContentJob implements ShouldQueue
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
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param class-string $modelClass
     * @param int|string $modelId
     * @param string|null $locale
     */
    public function __construct(
        public string $modelClass,
        public int|string $modelId,
        public ?string $locale = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ContentAnalyzer $analyzer): void
    {
        try {
            // Load the model
            $model = $this->modelClass::find($this->modelId);

            if (! $model) {
                Log::warning('AnalyzeContentJob: Model not found', [
                    'class' => $this->modelClass,
                    'id' => $this->modelId,
                ]);

                return;
            }

            // Verify model has SEO trait
            if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
                Log::warning('AnalyzeContentJob: Model does not use HasSEO trait', [
                    'class' => $this->modelClass,
                ]);

                return;
            }

            $locale = $this->locale ?? $model->seoMeta?->locale ?? config('app.locale');

            // Run content analysis
            $report = $analyzer->analyze($model, $locale);

            // Calculate content hash for change detection
            $contentHash = method_exists($model, 'getContentForSEO')
                ? md5($model->getContentForSEO())
                : null;

            // Get content snapshot (truncated for storage)
            $contentSnapshot = method_exists($model, 'getContentForSEO')
                ? mb_substr(strip_tags($model->getContentForSEO()), 0, 500)
                : null;

            // Save the analysis results
            $model->seoMeta()->updateOrCreate(
                ['locale' => $locale],
                [
                    'seo_score' => $report->totalScore,
                    'analysis_report' => $report->toArray(),
                    'content_hash' => $contentHash,
                    'content_snapshot' => $contentSnapshot,
                    'snapshot_at' => now(),
                    'analyzed_at' => now(),
                ]
            );

            // Dispatch async link validation
            ValidateLinksJob::dispatch($this->modelClass, $this->modelId, 'links')
                ->delay(now()->addSeconds(5)); // Small delay to not overwhelm queue

            ValidateLinksJob::dispatch($this->modelClass, $this->modelId, 'images')
                ->delay(now()->addSeconds(10));

            Log::debug('AnalyzeContentJob: Analysis complete', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'score' => $report->totalScore,
            ]);

        } catch (\Exception $e) {
            Log::error('AnalyzeContentJob: Analysis failed', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeContentJob failed', [
            'class' => $this->modelClass,
            'id' => $this->modelId,
            'locale' => $this->locale,
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
        return [
            'seo',
            'analyze',
            $this->modelClass,
            "{$this->modelClass}:{$this->modelId}",
        ];
    }
}
