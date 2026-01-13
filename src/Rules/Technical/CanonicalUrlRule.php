<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if a canonical URL is set and valid.
 *
 * The canonical URL tells search engines which URL is the
 * "official" version of a page, helping to:
 * - Prevent duplicate content issues
 * - Consolidate link equity
 * - Control which URL appears in search results
 *
 * ## Scoring
 * - **Pass:** Valid canonical URL set
 * - **Warning:** No canonical URL set
 * - **Fail:** Invalid canonical URL format
 *
 * ## Best Practices
 * - Use absolute URLs (not relative)
 * - Self-reference canonical on unique pages
 * - Point to canonical on duplicate/variant pages
 * - Include protocol (https://)
 */
class CanonicalUrlRule implements RuleInterface
{
    public function getId(): string
    {
        return 'canonical_url';
    }

    public function getName(): string
    {
        return 'Canonical URL';
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
        $canonical = $context->canonical ?? '';

        // No canonical set
        if (empty($canonical)) {
            return RuleResult::warning(
                $this->getId(),
                'No canonical URL set.',
                'Add a canonical URL to prevent duplicate content issues and consolidate link equity. Use the full absolute URL including https://.',
                50,
                'Not set',
                'Valid canonical URL'
            );
        }

        // Validate URL format
        if (! filter_var($canonical, FILTER_VALIDATE_URL)) {
            return RuleResult::fail(
                $this->getId(),
                'Canonical URL has invalid format.',
                'Use an absolute URL with protocol (e.g., https://example.com/page). Relative URLs are not valid for canonical tags.',
                $canonical,
                'Absolute URL (https://...)'
            );
        }

        // Check for HTTPS
        if (str_starts_with($canonical, 'http://')) {
            return RuleResult::warning(
                $this->getId(),
                'Canonical URL uses HTTP instead of HTTPS.',
                'Update the canonical URL to use HTTPS for better security and SEO.',
                70,
                'http://...',
                'https://...'
            );
        }

        return RuleResult::pass(
            $this->getId(),
            'Canonical URL is set and valid.',
            100
        );
    }
}
