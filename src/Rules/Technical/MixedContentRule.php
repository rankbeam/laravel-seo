<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks for mixed content (HTTP resources on HTTPS pages).
 *
 * Mixed content can:
 * - Trigger browser security warnings
 * - Be blocked by modern browsers
 * - Reduce user trust
 * - Negatively impact SEO
 *
 * ## What is Mixed Content?
 * When an HTTPS page loads resources (images, scripts, stylesheets)
 * over insecure HTTP connections.
 *
 * ## Types
 * - **Active:** Scripts, stylesheets, iframes (blocked by browsers)
 * - **Passive:** Images, audio, video (warning, may be blocked)
 *
 * ## Scoring
 * - **Pass:** All resources use HTTPS
 * - **Warning:** HTTP resources found
 *
 * ## Fix
 * Update all resource URLs to use https:// or protocol-relative (//)
 */
class MixedContentRule implements RuleInterface
{
    public function getId(): string
    {
        return 'mixed_content';
    }

    public function getName(): string
    {
        return 'Mixed Content Check';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'technical';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $html = $context->htmlContent;

        // Collect all HTTP URLs
        $httpResources = $this->findHttpResources($html);

        if (empty($httpResources)) {
            return RuleResult::pass(
                $this->getId(),
                'No mixed content detected. All resources use HTTPS.',
                100
            );
        }

        $count = count($httpResources);
        $uniqueResources = array_unique($httpResources);

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'warning',
            score: 50,
            message: "{$count} HTTP resource(s) found (mixed content).",
            recommendation: 'Update all resource URLs to use HTTPS. Mixed content can be blocked by browsers and triggers security warnings.',
            actualValue: "{$count} HTTP resources",
            expectedValue: '0 HTTP resources',
            details: [
                'http_resources' => array_map(
                    fn ($url) => $this->truncateUrl($url),
                    array_slice($uniqueResources, 0, 10)
                ),
            ],
        );
    }

    /**
     * Find all HTTP resources in HTML.
     *
     * @return array<int, string>
     */
    protected function findHttpResources(string $html): array
    {
        $httpUrls = [];

        // Patterns to check
        $patterns = [
            // Images
            '/<img[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Scripts
            '/<script[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Stylesheets
            '/<link[^>]+href\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Iframes
            '/<iframe[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Video
            '/<video[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Audio
            '/<audio[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Source elements
            '/<source[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Object/embed
            '/<object[^>]+data\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            '/<embed[^>]+src\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i',
            // Background images in style
            '/url\s*\(\s*["\']?(http:\/\/[^"\')\s]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $httpUrls = array_merge($httpUrls, $matches[1]);
            }
        }

        // Also check links (less critical but still worth noting)
        // Only include external link hrefs, not anchor links
        if (preg_match_all('/<a[^>]+href\s*=\s*["\']?(http:\/\/[^"\'>\s]+)/i', $html, $matches)) {
            // Filter out external links (those are expected to sometimes be HTTP)
            // We only care about resources loaded from HTTP, not link destinations
            // So we skip <a> tags for the main check
        }

        return $httpUrls;
    }

    /**
     * Truncate long URLs for display.
     */
    protected function truncateUrl(string $url): string
    {
        if (mb_strlen($url) > 60) {
            return mb_substr($url, 0, 57) . '...';
        }

        return $url;
    }
}
