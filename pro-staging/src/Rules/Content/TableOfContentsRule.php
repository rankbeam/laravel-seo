<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if long content has a table of contents.
 *
 * A table of contents improves user experience for long-form content by:
 * - Allowing readers to jump to relevant sections
 * - Providing an overview of the content structure
 * - Potentially generating rich snippets in search results
 *
 * ## When Applied
 * Only checks content that meets BOTH criteria:
 * - More than 1500 words
 * - More than 4 headings
 *
 * ## Detection Patterns
 * Looks for common TOC implementations:
 * - id="toc"
 * - class="toc"
 * - class="table-of-contents"
 * - nav with aria-label containing "contents"
 * - Links to anchors within the same page (#section)
 */
class TableOfContentsRule implements RuleInterface
{
    /**
     * Minimum word count for TOC recommendation.
     */
    protected const MIN_WORDS = 1500;

    /**
     * Minimum headings for TOC recommendation.
     */
    protected const MIN_HEADINGS = 4;

    public function getId(): string
    {
        return 'table_of_contents';
    }

    public function getName(): string
    {
        return 'Table of Contents';
    }

    public function getWeight(): int
    {
        return 3;
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Count total headings (H2-H4)
        $totalHeadings = 0;
        foreach (['h2', 'h3', 'h4'] as $level) {
            $totalHeadings += count($context->headings[$level] ?? []);
        }

        // Check if TOC should be recommended
        if ($context->wordCount < self::MIN_WORDS || $totalHeadings < self::MIN_HEADINGS) {
            return RuleResult::skip(
                $this->getId(),
                'Content is not long enough to require a table of contents (' . self::MIN_WORDS . '+ words and ' . self::MIN_HEADINGS . '+ headings needed).'
            );
        }

        // Check for TOC presence
        if ($this->hasTOC($context->htmlContent)) {
            return RuleResult::pass(
                $this->getId(),
                'Table of contents detected for long-form content.',
                100
            );
        }

        return RuleResult::warning(
            $this->getId(),
            "Long content ({$context->wordCount} words, {$totalHeadings} headings) would benefit from a table of contents.",
            'Add a table of contents to help readers navigate your content. This can also improve your chances of getting rich snippets in search results.',
            50,
            'No TOC found',
            'TOC for long content'
        );
    }

    /**
     * Check if the HTML contains a table of contents.
     */
    protected function hasTOC(string $html): bool
    {
        // Common TOC patterns
        $patterns = [
            // ID-based
            '/id\s*=\s*["\']toc["\']/i',
            '/id\s*=\s*["\']table-of-contents["\']/i',

            // Class-based
            '/class\s*=\s*["\'][^"\']*\btoc\b[^"\']*["\']/i',
            '/class\s*=\s*["\'][^"\']*\btable-of-contents\b[^"\']*["\']/i',
            '/class\s*=\s*["\'][^"\']*\bwp-block-table-of-contents\b[^"\']*["\']/i',

            // ARIA-based
            '/aria-label\s*=\s*["\'][^"\']*contents[^"\']*["\']/i',

            // Common WordPress/plugin classes
            '/class\s*=\s*["\'][^"\']*\bez-toc\b[^"\']*["\']/i',
            '/class\s*=\s*["\'][^"\']*\blwptoc\b[^"\']*["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }

        // Check for multiple anchor links to same-page sections
        // A TOC typically has 3+ links to #anchors
        if (preg_match_all('/<a[^>]+href\s*=\s*["\']#[^"\']+["\'][^>]*>/i', $html, $matches)) {
            if (count($matches[0]) >= 3) {
                return true;
            }
        }

        return false;
    }
}
