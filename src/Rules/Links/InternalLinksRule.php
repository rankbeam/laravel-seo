<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Links;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the content has sufficient internal links.
 *
 * Internal linking helps:
 * - Distribute page authority throughout your site
 * - Help search engines discover and index pages
 * - Keep users engaged and reduce bounce rate
 * - Establish content hierarchy and relationships
 *
 * ## Requirements
 * - **Pass:** 2+ internal links
 * - **Warning:** 1 internal link
 * - **Fail:** 0 internal links
 *
 * ## Detection
 * Internal links are identified as:
 * - Relative URLs (start with / or ./)
 * - Same-domain absolute URLs
 * - Anchor links (#) are excluded
 */
class InternalLinksRule implements RuleInterface
{
    /**
     * Minimum internal links recommended.
     */
    protected const MIN_LINKS = 2;

    public function getId(): string
    {
        return 'internal_links';
    }

    public function getName(): string
    {
        return 'Internal Links';
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
        $internalLinks = $context->internalLinks ?? [];
        $count = count($internalLinks);

        // Pass: 2+ internal links
        if ($count >= self::MIN_LINKS) {
            return RuleResult::pass(
                $this->getId(),
                "Good internal linking: {$count} internal links found.",
                100
            );
        }

        // Warning: 1 internal link
        if ($count === 1) {
            return RuleResult::warning(
                $this->getId(),
                "Only 1 internal link found.",
                'Add at least 1 more internal link to related content on your site. Internal links help distribute page authority and improve navigation.',
                50,
                '1 internal link',
                self::MIN_LINKS . '+ internal links'
            );
        }

        // Fail: 0 internal links
        return RuleResult::fail(
            $this->getId(),
            'No internal links found in content.',
            'Add at least ' . self::MIN_LINKS . ' internal links to related content. Internal linking is crucial for SEO and helps users discover more of your content.',
            '0 internal links',
            self::MIN_LINKS . '+ internal links'
        );
    }
}
