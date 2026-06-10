<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the page has noindex directive.
 *
 * This is an **informational** check, not a penalty. Sometimes
 * noindex is intentional (login pages, thank you pages, etc.).
 *
 * ## What noindex means
 * - Page won't appear in search results
 * - Page may still be crawled
 * - Internal links from the page still pass value
 *
 * ## Scoring
 * - **Warning (score: 0):** noindex detected - informational only
 * - **Pass:** No noindex directive
 *
 * ## Common noindex use cases
 * - Admin/login pages
 * - Thank you/confirmation pages
 * - Search results pages
 * - Paginated archives (sometimes)
 * - Staging/development environments
 */
class NoIndexCheckRule implements RuleInterface
{
    public function getId(): string
    {
        return 'noindex_check';
    }

    public function getName(): string
    {
        return 'NoIndex Check';
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
        $robots = $context->robots ?? '';

        // Check for noindex in robots meta
        if ($this->hasNoIndex($robots)) {
            return new RuleResult(
                ruleId: $this->getId(),
                status: 'warning',
                score: 0, // Doesn't penalize, just informs
                message: "This page has 'noindex' directive and won't appear in search results.",
                recommendation: "If this is intentional (e.g., login page, thank you page), no action needed. If you want this page indexed, remove 'noindex' from the robots meta tag.",
                actualValue: $robots,
                expectedValue: 'index (or no robots directive)',
                details: [
                    'note' => 'This is informational. noindex is often intentional for certain page types.',
                ],
            );
        }

        // Check for noindex in X-Robots-Tag header (from context if available)
        // This would be passed in additionalData if detected

        return RuleResult::pass(
            $this->getId(),
            'Page is indexable (no noindex directive found).',
            100
        );
    }

    /**
     * Check if robots string contains noindex.
     */
    protected function hasNoIndex(string $robots): bool
    {
        if (empty($robots)) {
            return false;
        }

        $robots = strtolower($robots);

        // Check for noindex directive
        if (str_contains($robots, 'noindex')) {
            return true;
        }

        // Check for none (equivalent to noindex, nofollow)
        if (preg_match('/\bnone\b/', $robots)) {
            return true;
        }

        return false;
    }
}
