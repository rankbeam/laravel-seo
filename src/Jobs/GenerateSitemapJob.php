<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;

/**
 * Job to generate XML sitemaps asynchronously.
 *
 * This job generates sitemaps and optionally pings search engines
 * to notify them of updates.
 *
 * ## Usage
 * ```php
 * // Basic dispatch
 * GenerateSitemapJob::dispatch();
 *
 * // With search engine pinging
 * GenerateSitemapJob::dispatch(pingSearchEngines: true);
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php or app/Console/Kernel.php
 * Schedule::job(new GenerateSitemapJob(pingSearchEngines: true))->daily();
 * ```
 */
class GenerateSitemapJob implements ShouldQueue, ShouldBeUnique
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
    public int $timeout = 600; // 10 minutes

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $pingSearchEngines = false,
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'generate-sitemap';
    }

    /**
     * Execute the job.
     */
    public function handle(SitemapBuilder $builder): void
    {
        Log::info('GenerateSitemapJob: Starting sitemap generation');

        try {
            // Generate sitemaps
            $builder->generate();

            Log::info('GenerateSitemapJob: Sitemap generated successfully', [
                'url' => $builder->getSitemapUrl(),
            ]);

            // Ping search engines if enabled
            if ($this->pingSearchEngines && config('seo.sitemap.ping_search_engines', false)) {
                $this->pingSearchEngines($builder->getSitemapUrl());
            }
        } catch (\Exception $e) {
            Log::error('GenerateSitemapJob: Failed to generate sitemap', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Ping search engines about the updated sitemap.
     */
    protected function pingSearchEngines(string $sitemapUrl): void
    {
        $encodedUrl = urlencode($sitemapUrl);

        $engines = [
            'Google' => "https://www.google.com/ping?sitemap={$encodedUrl}",
            'Bing' => "https://www.bing.com/ping?sitemap={$encodedUrl}",
        ];

        foreach ($engines as $name => $pingUrl) {
            try {
                $response = Http::timeout(30)->get($pingUrl);

                if ($response->successful()) {
                    Log::info("GenerateSitemapJob: Successfully pinged {$name}", [
                        'sitemap' => $sitemapUrl,
                    ]);
                } else {
                    Log::warning("GenerateSitemapJob: Failed to ping {$name}", [
                        'status' => $response->status(),
                        'sitemap' => $sitemapUrl,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("GenerateSitemapJob: Error pinging {$name}", [
                    'error' => $e->getMessage(),
                    'sitemap' => $sitemapUrl,
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSitemapJob failed', [
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
        return ['seo', 'sitemap'];
    }
}
