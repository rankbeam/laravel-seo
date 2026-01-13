<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Links;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks for broken links in the content.
 *
 * Broken links negatively impact:
 * - User experience (frustrating dead ends)
 * - SEO (signals poor maintenance)
 * - Crawl efficiency (wasted crawl budget)
 * - Trust and credibility
 *
 * ## Implementation
 * Uses pre-validated brokenLinks from context (populated by async validation).
 * If brokenLinks is null, the validation hasn't run yet.
 *
 * ## Scoring
 * - **Pass:** No broken links
 * - **Fail:** Any broken links found
 * - **Skip:** Validation not yet performed
 *
 * ## Note
 * Link validation is performed asynchronously to avoid
 * blocking the main analysis. Results are cached.
 */
class BrokenLinksRule implements RuleInterface
{
    public function getId(): string
    {
        return 'broken_links';
    }

    public function getName(): string
    {
        return 'Broken Links';
    }

    public function getWeight(): int
    {
        return 8;
    }

    public function getCategory(): string
    {
        return 'links';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Check if validation has been performed
        if ($context->brokenLinks === null) {
            return RuleResult::skip(
                $this->getId(),
                'Link validation has not been performed yet. Run async validation to check for broken links.'
            );
        }

        $brokenLinks = $context->brokenLinks;
        $count = count($brokenLinks);

        // Pass: No broken links
        if ($count === 0) {
            return RuleResult::pass(
                $this->getId(),
                'No broken links detected.',
                100
            );
        }

        // Fail: Broken links found
        $details = [
            'broken_links' => array_map(function ($link) {
                if (is_array($link)) {
                    $url = $link['url'] ?? '(unknown)';
                    $status = $link['status'] ?? 'error';
                    return "{$url} ({$status})";
                }
                return $link;
            }, array_slice($brokenLinks, 0, 10)),
        ];

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "{$count} broken link(s) detected.",
            recommendation: 'Fix or remove broken links. Broken links hurt user experience and SEO. Update URLs to working destinations or remove the links.',
            actualValue: "{$count} broken links",
            expectedValue: '0 broken links',
            details: $details,
        );
    }
}
