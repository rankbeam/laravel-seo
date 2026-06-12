<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Rankbeam\Seo\Jobs\GenerateSitemapJob;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;

/**
 * Artisan command to generate XML sitemaps.
 *
 * ## Usage
 * ```bash
 * # Generate synchronously
 * php artisan seo:sitemap
 *
 * # Queue the generation
 * php artisan seo:sitemap --queue
 *
 * # Generate and ping search engines
 * php artisan seo:sitemap --ping
 *
 * # Queue with ping
 * php artisan seo:sitemap --queue --ping
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php
 * Schedule::command('seo:sitemap')->daily();
 *
 * // With search engine ping
 * Schedule::command('seo:sitemap --ping')->daily();
 * ```
 */
class SitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:sitemap
                            {--queue : Queue the sitemap generation job}
                            {--ping : Ping search engines after generation}';

    /**
     * The console command description.
     */
    protected $description = 'Generate XML sitemap(s) for all SEO-enabled models';

    /**
     * Execute the console command.
     */
    public function handle(SitemapBuilder $builder): int
    {
        if (! config('seo.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');

            return self::FAILURE;
        }

        $shouldPing = $this->option('ping');

        if ($this->option('queue')) {
            return $this->handleQueued($shouldPing);
        }

        return $this->handleSync($builder, $shouldPing);
    }

    /**
     * Handle queued generation.
     */
    protected function handleQueued(bool $ping): int
    {
        $this->info('Dispatching sitemap generation job...');

        GenerateSitemapJob::dispatch(pingSearchEngines: $ping);

        $this->info('✓ Sitemap generation job dispatched to queue.');

        if ($ping) {
            $this->line('  Search engines will be pinged after generation.');
        }

        return self::SUCCESS;
    }

    /**
     * Handle synchronous generation.
     */
    protected function handleSync(SitemapBuilder $builder, bool $ping): int
    {
        $this->info('Generating sitemap...');

        try {
            $startTime = microtime(true);

            $builder->generate();

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('✓ Sitemap generated successfully!');
            $this->line("  URL: {$builder->getSitemapUrl()}");
            $this->line("  Time: {$duration}s");

            // Ping search engines if requested
            if ($ping) {
                $this->pingSearchEngines($builder->getSitemapUrl());
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate sitemap: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Ping search engines about the sitemap.
     */
    protected function pingSearchEngines(string $sitemapUrl): void
    {
        $this->newLine();
        $this->info('Pinging search engines...');

        $encodedUrl = urlencode($sitemapUrl);

        $engines = [
            'Google' => "https://www.google.com/ping?sitemap={$encodedUrl}",
            'Bing' => "https://www.bing.com/ping?sitemap={$encodedUrl}",
        ];

        foreach ($engines as $name => $pingUrl) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(30)->get($pingUrl);

                if ($response->successful()) {
                    $this->line("  ✓ {$name}: Pinged successfully");
                } else {
                    $this->warn("  ⚠ {$name}: HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->warn("  ⚠ {$name}: {$e->getMessage()}");
            }
        }
    }
}
