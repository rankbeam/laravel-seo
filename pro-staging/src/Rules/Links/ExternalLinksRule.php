<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Links;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the content has external links to authoritative sources.
 *
 * External linking helps:
 * - Support claims with authoritative sources
 * - Provide additional resources for readers
 * - Signal trust and credibility
 * - Build relationships with linked sites
 *
 * ## Requirements
 * - **Pass:** 1+ external links
 * - **Warning:** 0 external links
 *
 * ## Additional Info
 * - Reports nofollow vs dofollow breakdown
 * - External = different domain from site URL
 */
class ExternalLinksRule implements RuleInterface
{
    public function getId(): string
    {
        return 'external_links';
    }

    public function getName(): string
    {
        return 'External Links';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'links';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $externalLinks = $context->externalLinks ?? [];
        $count = count($externalLinks);

        // Pass: 1+ external links
        if ($count >= 1) {
            // Count nofollow links
            $nofollowCount = $this->countNofollowLinks($context->htmlContent, $externalLinks);
            $dofollowCount = $count - $nofollowCount;

            $details = [];
            if ($nofollowCount > 0 || $dofollowCount > 0) {
                $details['breakdown'] = "dofollow: {$dofollowCount}, nofollow: {$nofollowCount}";
            }

            return new RuleResult(
                ruleId: $this->getId(),
                status: 'pass',
                score: 100,
                message: "Good external linking: {$count} external link(s) found.",
                recommendation: null,
                actualValue: null,
                expectedValue: null,
                details: $details,
            );
        }

        // Warning: 0 external links
        return RuleResult::warning(
            $this->getId(),
            'No external links found in content.',
            'Consider adding links to authoritative external sources. Citing reputable sources builds trust and provides additional value to readers.',
            60,
            '0 external links',
            '1+ external links'
        );
    }

    /**
     * Count how many external links have rel="nofollow".
     *
     * @param array<int, string> $externalLinks
     */
    protected function countNofollowLinks(string $html, array $externalLinks): int
    {
        $count = 0;

        foreach ($externalLinks as $url) {
            // Find the anchor tag containing this URL
            $escapedUrl = preg_quote($url, '/');
            if (preg_match('/<a[^>]+href\s*=\s*["\']' . $escapedUrl . '["\'][^>]*>/i', $html, $match)) {
                $anchorTag = $match[0];
                if (preg_match('/rel\s*=\s*["\'][^"\']*nofollow[^"\']*["\']/i', $anchorTag)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
