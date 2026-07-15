<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder;

/**
 * Artisan command to generate an llms.txt index.
 *
 * llms.txt is an optional markdown index of the site's key content, for the
 * tools that choose to consume it (Google Search does not use it). This command
 * writes it the same way `seo:sitemap`
 * writes its file — to the configured disk and path.
 *
 * ## Usage
 * ```bash
 * # Write public/llms.txt
 * php artisan seo:llms-txt
 *
 * # Print to stdout without writing (dry run / piping)
 * php artisan seo:llms-txt --print
 *
 * # Write to a specific path on the configured disk
 * php artisan seo:llms-txt --output=ai/llms.txt
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php
 * Schedule::command('seo:llms-txt')->daily();
 * ```
 */
class LlmsTxtCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:llms-txt
                            {--output= : Write to this path on the configured disk instead of the configured path}
                            {--print : Print the generated llms.txt to stdout without writing a file}';

    /**
     * The console command description.
     */
    protected $description = 'Generate an llms.txt index of the site for AI crawlers';

    /**
     * Execute the console command.
     */
    public function handle(LlmsTxtBuilder $builder): int
    {
        if (! $this->enabled()) {
            $this->error('llms.txt generation is disabled in configuration.');

            return self::FAILURE;
        }

        try {
            // --print short-circuits to stdout: the same generated markdown the
            // file would contain, but nothing is written (dry run / piping).
            if ($this->option('print')) {
                $this->line($builder->build());

                return self::SUCCESS;
            }

            $this->info('Generating llms.txt...');

            $startTime = microtime(true);

            $output = $this->option('output');

            if (is_string($output) && $output !== '') {
                // --output writes to a caller-specified path on the SAME disk
                // the builder uses, mirroring how seo:sitemap stays on its disk.
                $this->getStorage()->put($output, $builder->build());
                $url = url($output);
            } else {
                $builder->generate();
                $url = $builder->getLlmsTxtUrl();
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('✓ llms.txt generated successfully!');
            $this->line("  URL: {$url}");
            $this->line("  Time: {$duration}s");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate llms.txt: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Whether llms.txt generation is enabled.
     *
     * Reads the dedicated `seo.llms_txt.enabled` flag, defaulting to true so the
     * command works out of the box once sources exist — the same posture
     * seo:sitemap takes with its feature flag.
     */
    protected function enabled(): bool
    {
        return (bool) config('seo.llms_txt.enabled', true);
    }

    /**
     * The storage disk the builder writes to (so --output stays on it).
     */
    protected function getStorage(): \Illuminate\Filesystem\FilesystemAdapter
    {
        $disk = config('seo.llms_txt.disk', config('seo.sitemap.disk', 'public'));

        return Storage::disk($disk);
    }
}
