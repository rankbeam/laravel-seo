<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;

/**
 * Artisan command to generate a managed robots.txt (or ai.txt) with AI-crawler
 * directives.
 *
 * robots.txt is the file the major AI crawlers honour (GPTBot, ClaudeBot,
 * PerplexityBot, Google-Extended). This writes it the same way `seo:llms-txt`
 * writes its file — to the configured disk and path — so a static, web-server
 * -served file stays in sync with the configured allow/disallow policy.
 *
 * ## Usage
 * ```bash
 * # Write public/robots.txt
 * php artisan seo:robots-txt
 *
 * # Print to stdout without writing (dry run / piping)
 * php artisan seo:robots-txt --print
 *
 * # Write to a specific path on the configured disk
 * php artisan seo:robots-txt --output=robots.txt
 *
 * # Operate on the ai.txt artifact instead of robots.txt
 * php artisan seo:robots-txt --ai-txt
 * ```
 *
 * ## Scheduling
 * ```php
 * // In routes/console.php
 * Schedule::command('seo:robots-txt')->daily();
 * ```
 */
class RobotsTxtCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:robots-txt
                            {--output= : Write to this path on the configured disk instead of the configured path}
                            {--print : Print the generated file to stdout without writing}
                            {--ai-txt : Generate the ai.txt artifact instead of robots.txt}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a managed robots.txt (or ai.txt) with AI-crawler directives';

    /**
     * Execute the console command.
     */
    public function handle(RobotsTxtBuilder $builder): int
    {
        if (! $this->enabled()) {
            $this->error('AI-crawler robots.txt generation is disabled in configuration.');

            return self::FAILURE;
        }

        $aiTxt = (bool) $this->option('ai-txt');
        $label = $aiTxt ? 'ai.txt' : 'robots.txt';

        try {
            // --print short-circuits to stdout: the same generated content the
            // file would contain, but nothing is written (dry run / piping).
            if ($this->option('print')) {
                $this->line($aiTxt ? $builder->buildAiTxt() : $builder->build());

                return self::SUCCESS;
            }

            $this->info("Generating {$label}...");

            $startTime = microtime(true);

            $output = $this->option('output');
            $content = $aiTxt ? $builder->buildAiTxt() : $builder->build();

            if (is_string($output) && $output !== '') {
                // --output writes to a caller-specified path on the SAME disk
                // the builder uses, mirroring seo:llms-txt.
                $this->getStorage()->put($output, $content);
                $url = url($output);
            } else {
                $aiTxt ? $builder->generateAiTxt() : $builder->generate();
                $url = $aiTxt ? url(config('seo.ai_crawlers.ai_txt_path', 'ai.txt')) : $builder->getRobotsTxtUrl();
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("✓ {$label} generated successfully!");
            $this->line("  URL: {$url}");
            $this->line("  Time: {$duration}s");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate {$label}: " . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Whether AI-crawler robots.txt generation is enabled.
     */
    protected function enabled(): bool
    {
        return (bool) config('seo.ai_crawlers.enabled', true);
    }

    /**
     * The storage disk the builder writes to (so --output stays on it).
     */
    protected function getStorage(): \Illuminate\Filesystem\FilesystemAdapter
    {
        $disk = config('seo.ai_crawlers.disk', config('seo.sitemap.disk', 'public'));

        return Storage::disk($disk);
    }
}
