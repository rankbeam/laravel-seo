<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Scanner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Fibonoir\LaravelSEO\Jobs\ScanModelJob;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Coordinates SEO scanning across all enabled models.
 *
 * The SitewideScanner orchestrates comprehensive SEO audits by:
 * - Discovering all models with the HasSEO trait
 * - Creating scan run records for tracking progress
 * - Dispatching batched jobs for parallel processing
 * - Supporting both full and incremental scans
 *
 * ## Scan Types
 * - **Full Scan:** Analyzes all SEO-enabled models
 * - **Incremental Scan:** Only models changed since last scan
 *
 * ## Usage
 * ```php
 * $scanner = app(SitewideScanner::class);
 *
 * // Full scan (all models)
 * $scanRun = $scanner->scanFull();
 *
 * // Incremental scan (changed only)
 * $scanRun = $scanner->scanIncremental();
 *
 * // Single model
 * $scanner->scanModel($post);
 * ```
 */
class SitewideScanner
{
    /**
     * Batch size for job dispatching.
     */
    protected int $batchSize = 50;

    public function __construct(
        protected PageScanner $pageScanner,
    ) {}

    /**
     * Perform a full scan of all SEO-enabled models.
     *
     * Creates a new scan run and dispatches jobs for all models
     * that use the HasSEO trait.
     */
    public function scanFull(): SEOScanRun
    {
        $scanRun = SEOScanRun::create([
            'type' => 'full',
            'status' => 'pending',
            'options' => ['batch_size' => $this->batchSize],
        ]);

        $models = $this->getScanableModels();
        $totalPages = $this->countTotalPages($models);

        $scanRun->setTotal($totalPages);
        $scanRun->start();

        // Dispatch jobs for each model type
        foreach ($models as $modelClass) {
            $this->dispatchForModel($modelClass, $scanRun);
        }

        return $scanRun;
    }

    /**
     * Perform an incremental scan of changed models.
     *
     * Only scans models that have been modified since their
     * last SEO analysis (using seo_meta.analyzed_at).
     */
    public function scanIncremental(): SEOScanRun
    {
        $scanRun = SEOScanRun::create([
            'type' => 'incremental',
            'status' => 'pending',
            'options' => ['batch_size' => $this->batchSize],
        ]);

        $models = $this->getScanableModels();
        $totalPages = $this->countChangedPages($models);

        $scanRun->setTotal($totalPages);
        $scanRun->start();

        // Dispatch jobs for changed models only
        foreach ($models as $modelClass) {
            $this->dispatchForChangedModel($modelClass, $scanRun);
        }

        return $scanRun;
    }

    /**
     * Scan a single model immediately (synchronous).
     */
    public function scanModel(Model $model): void
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return;
        }

        $issues = $this->pageScanner->scan($model);

        // Clear existing issues for this model
        $model->seoMeta?->scanIssues()->delete();

        // Create new issues
        foreach ($issues as $issueData) {
            \Fibonoir\LaravelSEO\Models\SEOScanIssue::create([
                'scannable_type' => get_class($model),
                'scannable_id' => $model->getKey(),
                'url' => $issueData['url'] ?? $model->getUrlForSEO(),
                'issue_type' => $issueData['issue_type'],
                'severity' => $issueData['severity'],
                'field' => $issueData['field'] ?? null,
                'message' => $issueData['message'],
                'context' => $issueData['context'] ?? null,
                'status' => 'open',
                'detected_at' => now(),
            ]);
        }

        // Update analyzed timestamp
        if ($model->seoMeta) {
            $model->seoMeta->update(['analyzed_at' => now()]);
        }
    }

    /**
     * Get all models that use the HasSEO trait.
     *
     * Uses config or auto-discovery to find all SEO-enabled models.
     *
     * @return Collection<int, class-string<Model>>
     */
    public function getScanableModels(): Collection
    {
        // First check config for explicit list
        $configured = config('seo.scannable_models', []);

        if (! empty($configured)) {
            return collect($configured)->filter(function ($class) {
                return class_exists($class)
                    && in_array(HasSEO::class, class_uses_recursive($class), true);
            });
        }

        // Auto-discover models in app/Models
        return $this->discoverModels();
    }

    /**
     * Auto-discover models with HasSEO trait.
     *
     * @return Collection<int, class-string<Model>>
     */
    protected function discoverModels(): Collection
    {
        $models = collect();
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return $models;
        }

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            $class = 'App\\Models\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (
                class_exists($class)
                && is_subclass_of($class, Model::class)
                && in_array(HasSEO::class, class_uses_recursive($class), true)
            ) {
                $models->push($class);
            }
        }

        return $models;
    }

    /**
     * Count total pages across all models.
     *
     * @param Collection<int, class-string<Model>> $models
     */
    protected function countTotalPages(Collection $models): int
    {
        return $models->sum(function ($modelClass) {
            return $modelClass::count();
        });
    }

    /**
     * Count pages that need scanning (changed since last analysis).
     *
     * @param Collection<int, class-string<Model>> $models
     */
    protected function countChangedPages(Collection $models): int
    {
        return $models->sum(function ($modelClass) {
            $table = (new $modelClass())->getTable();

            return SEOMeta::where('seoable_type', $modelClass)
                ->where(function ($query) use ($table, $modelClass) {
                    // No analysis yet
                    $query->whereNull('analyzed_at')
                        // Or model updated after analysis
                        ->orWhereRaw("analyzed_at < (SELECT updated_at FROM {$table} WHERE id = seoable_id)");
                })
                ->count();
        });
    }

    /**
     * Dispatch scan jobs for all instances of a model.
     *
     * @param class-string<Model> $modelClass
     */
    protected function dispatchForModel(string $modelClass, SEOScanRun $scanRun): void
    {
        $modelClass::query()
            ->select(['id'])
            ->chunkById($this->batchSize, function ($models) use ($modelClass, $scanRun) {
                foreach ($models as $model) {
                    ScanModelJob::dispatch($modelClass, $model->id, $scanRun->id);
                }
            });
    }

    /**
     * Dispatch scan jobs for changed instances of a model.
     *
     * @param class-string<Model> $modelClass
     */
    protected function dispatchForChangedModel(string $modelClass, SEOScanRun $scanRun): void
    {
        $table = (new $modelClass())->getTable();

        // Get IDs of models that need scanning
        $needsScanning = SEOMeta::where('seoable_type', $modelClass)
            ->where(function ($query) use ($table) {
                $query->whereNull('analyzed_at')
                    ->orWhereRaw("analyzed_at < (SELECT updated_at FROM {$table} WHERE id = seoable_id)");
            })
            ->pluck('seoable_id');

        // Also include models without SEO meta
        $withoutMeta = $modelClass::whereNotIn('id', function ($query) use ($modelClass) {
            $query->select('seoable_id')
                ->from('seo_meta')
                ->where('seoable_type', $modelClass);
        })->pluck('id');

        $allIds = $needsScanning->merge($withoutMeta)->unique();

        foreach ($allIds->chunk($this->batchSize) as $chunk) {
            foreach ($chunk as $id) {
                ScanModelJob::dispatch($modelClass, $id, $scanRun->id);
            }
        }
    }

    /**
     * Set the batch size for job dispatching.
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;

        return $this;
    }
}
