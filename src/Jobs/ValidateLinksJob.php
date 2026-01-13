<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Services\Scanner\BrokenLinkChecker;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Job to validate links or images asynchronously.
 *
 * This job runs after page scanning to check for broken links
 * and images without blocking the main scan process.
 *
 * ## Types
 * - **links:** Validates all internal and external links
 * - **images:** Validates all image src URLs
 *
 * ## Results Storage
 * Results are stored in seo_meta.analysis_report under:
 * - brokenLinks: array of broken link URLs
 * - brokenImages: array of broken image URLs
 */
class ValidateLinksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 120;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param class-string $modelClass
     * @param int|string $modelId
     * @param string $linkType 'links' or 'images'
     */
    public function __construct(
        public string $modelClass,
        public int|string $modelId,
        public string $linkType = 'links',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BrokenLinkChecker $checker): void
    {
        try {
            // Load the model
            $model = $this->modelClass::find($this->modelId);

            if (! $model) {
                Log::warning('ValidateLinksJob: Model not found', [
                    'class' => $this->modelClass,
                    'id' => $this->modelId,
                ]);

                return;
            }

            // Verify model has SEO trait
            if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
                return;
            }

            // Get content
            $content = method_exists($model, 'getContentForSEO')
                ? $model->getContentForSEO()
                : '';

            if (empty($content)) {
                return;
            }

            // Extract and validate based on type
            $broken = match ($this->linkType) {
                'links' => $this->validateLinks($content, $checker),
                'images' => $this->validateImages($content, $checker),
                default => [],
            };

            // Store results in analysis report
            $this->storeResults($model, $broken);

        } catch (\Exception $e) {
            Log::error('ValidateLinksJob: Error validating', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
                'type' => $this->linkType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract and validate links.
     *
     * @return array<int, array{url: string, status: int|string}>
     */
    protected function validateLinks(string $content, BrokenLinkChecker $checker): array
    {
        $links = $this->extractLinks($content);

        if (empty($links)) {
            return [];
        }

        return $checker->checkLinks($links);
    }

    /**
     * Extract and validate images.
     *
     * @return array<int, array{src: string, status: int|string}>
     */
    protected function validateImages(string $content, BrokenLinkChecker $checker): array
    {
        $images = $this->extractImageSrcs($content);

        if (empty($images)) {
            return [];
        }

        return $checker->checkImages($images);
    }

    /**
     * Extract all link URLs from content.
     *
     * @return array<int, string>
     */
    protected function extractLinks(string $html): array
    {
        $links = [];

        if (preg_match_all('/<a[^>]+href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                // Skip anchors, mailto, tel
                if (str_starts_with($href, '#') ||
                    str_starts_with($href, 'mailto:') ||
                    str_starts_with($href, 'tel:') ||
                    str_starts_with($href, 'javascript:')) {
                    continue;
                }

                // Convert relative URLs to absolute
                $absoluteUrl = $this->toAbsoluteUrl($href);

                if ($absoluteUrl && ! in_array($absoluteUrl, $links, true)) {
                    $links[] = $absoluteUrl;
                }
            }
        }

        return $links;
    }

    /**
     * Extract all image src URLs from content.
     *
     * @return array<int, string>
     */
    protected function extractImageSrcs(string $html): array
    {
        $images = [];

        if (preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                // Skip data URIs
                if (str_starts_with($src, 'data:')) {
                    continue;
                }

                // Convert relative URLs to absolute
                $absoluteUrl = $this->toAbsoluteUrl($src);

                if ($absoluteUrl && ! in_array($absoluteUrl, $images, true)) {
                    $images[] = $absoluteUrl;
                }
            }
        }

        return $images;
    }

    /**
     * Convert a URL to absolute if relative.
     */
    protected function toAbsoluteUrl(string $url): ?string
    {
        // Already absolute
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // Relative URL - use app URL
        $baseUrl = rtrim(config('app.url', ''), '/');

        if (str_starts_with($url, '/')) {
            return $baseUrl . $url;
        }

        return $baseUrl . '/' . $url;
    }

    /**
     * Store validation results in SEO meta.
     *
     * @param array<int, array<string, mixed>> $broken
     */
    protected function storeResults($model, array $broken): void
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta) {
            return;
        }

        $report = $seoMeta->analysis_report ?? [];
        $key = $this->linkType === 'links' ? 'brokenLinks' : 'brokenImages';

        $report[$key] = $broken;
        $report[$key . '_checked_at'] = now()->toIso8601String();

        $seoMeta->update([
            'analysis_report' => $report,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ValidateLinksJob failed', [
            'class' => $this->modelClass,
            'id' => $this->modelId,
            'type' => $this->linkType,
            'error' => $exception->getMessage(),
        ]);
    }
}
