<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Services\InternalLinks\LinkIndexBuilder;

/**
 * Job to build or rebuild the internal links index.
 *
 * Use this job for:
 * - Initial index setup after installation
 * - Full rebuild after major content changes
 * - Scheduled periodic rebuilds
 *
 * ## Usage
 * ```php
 * // Build entire index
 * BuildLinkIndexJob::dispatch();
 *
 * // Via artisan (will be implemented in command)
 * php artisan seo:build-link-index
 * ```
 *
 * ## Scheduling
 * ```php
 * // Weekly rebuild to ensure index freshness
 * Schedule::job(new BuildLinkIndexJob())->weekly();
 * ```
 */
class BuildLinkIndexJob implements ShouldQueue, ShouldBeUnique
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
    public int $timeout = 3600; // 1 hour

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600; // 1 hour

    /**
     * Create a new job instance.
     *
     * @param bool $truncateFirst Whether to clear existing index before building
     */
    public function __construct(
        public bool $truncateFirst = false,
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'build-link-index';
    }

    /**
     * Execute the job.
     */
    public function handle(LinkIndexBuilder $builder): void
    {
        Log::info('BuildLinkIndexJob: Starting index build', [
            'truncate_first' => $this->truncateFirst,
        ]);

        try {
            // Optionally truncate existing index
            if ($this->truncateFirst) {
                \Fibonoir\LaravelSEO\Models\SEOInternalLinksIndex::truncate();
                Log::info('BuildLinkIndexJob: Truncated existing index');
            }

            // Build the index
            $builder->buildIndex();

            // Get stats
            $stats = $builder->getStats();

            Log::info('BuildLinkIndexJob: Index build complete', $stats);
        } catch (\Exception $e) {
            Log::error('BuildLinkIndexJob: Failed to build index', [
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
        Log::error('BuildLinkIndexJob failed', [
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
        return ['seo', 'internal-links', 'index'];
    }
}
