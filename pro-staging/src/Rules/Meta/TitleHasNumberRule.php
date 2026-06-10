<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Meta;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the SEO title contains a number.
 *
 * Titles with numbers tend to have higher click-through rates because:
 * - They promise specific, quantifiable information
 * - They stand out in search results
 * - They set clear expectations (e.g., "10 tips" vs "tips")
 *
 * ## Scoring
 * - **Pass:** Title contains at least one number
 * - **Warning:** Title doesn't contain a number (suggestion to add one)
 *
 * This is a low-weight rule as it's a best practice, not a requirement.
 *
 * ## Example
 * Good: "10 Essential SEO Tips for 2024" ✓
 * Good: "5 Ways to Improve Your Rankings" ✓
 * Could Improve: "Essential SEO Tips for Beginners" (no number)
 *
 * ## Common Number Patterns
 * - List posts: "10 Best...", "5 Ways to..."
 * - Years: "Guide for 2024"
 * - Percentages: "Increase Traffic by 50%"
 */
class TitleHasNumberRule implements RuleInterface
{
    public function getId(): string
    {
        return 'title_has_number';
    }

    public function getName(): string
    {
        return 'Title Contains Number';
    }

    public function getWeight(): int
    {
        return 3;
    }

    public function getCategory(): string
    {
        return 'meta';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        if (empty($context->title)) {
            return RuleResult::skip(
                $this->getId(),
                'No SEO title set.'
            );
        }

        // Check for any digit in the title
        if (preg_match('/\d+/', $context->title)) {
            return RuleResult::pass(
                $this->getId(),
                'Title contains a number, which can improve click-through rates.',
                100
            );
        }

        return RuleResult::warning(
            $this->getId(),
            'Title does not contain a number.',
            'Consider adding a number to your title (e.g., "10 Tips...", "5 Ways...") to potentially improve click-through rates.',
            50,
            'No number found',
            'Contains a number'
        );
    }
}
