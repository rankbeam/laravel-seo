<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the content has sufficient length for SEO.
 *
 * Longer, comprehensive content tends to rank better as it:
 * - Covers topics more thoroughly
 * - Provides more value to readers
 * - Contains more keyword variations naturally
 * - Earns more backlinks
 *
 * ## Scoring
 * - **Excellent (100):** 2000+ words - comprehensive content
 * - **Good (80):** 1000-1999 words - solid content
 * - **Warning (50):** 300-999 words - thin content
 * - **Fail (0):** <300 words - insufficient content
 *
 * ## Context Matters
 * While longer content often performs better, quality matters more than quantity.
 * Some topics can be covered adequately in fewer words.
 *
 * ## Example
 * - Blog posts: aim for 1000-2000+ words
 * - Product pages: 300-500 words may be sufficient
 * - Guides/tutorials: 2000+ words for comprehensive coverage
 */
class ContentLengthRule implements RuleInterface
{
    /**
     * Word count thresholds.
     */
    protected const EXCELLENT_MIN = 2000;
    protected const GOOD_MIN = 1000;
    protected const WARNING_MIN = 300;

    public function getId(): string
    {
        return 'content_length';
    }

    public function getName(): string
    {
        return 'Content Length';
    }

    public function getWeight(): int
    {
        return 10;
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $wordCount = $context->wordCount;

        // Excellent - comprehensive content
        if ($wordCount >= self::EXCELLENT_MIN) {
            return RuleResult::pass(
                $this->getId(),
                "Excellent content length: {$wordCount} words. Comprehensive content performs well in search.",
                100
            );
        }

        // Good - solid content
        if ($wordCount >= self::GOOD_MIN) {
            $remaining = self::EXCELLENT_MIN - $wordCount;

            return RuleResult::pass(
                $this->getId(),
                "Good content length: {$wordCount} words.",
                80
            );
        }

        // Warning - thin content
        if ($wordCount >= self::WARNING_MIN) {
            return RuleResult::warning(
                $this->getId(),
                "Content is relatively thin at {$wordCount} words.",
                "Aim for at least " . self::GOOD_MIN . " words for better SEO performance. Consider adding more details, examples, or sections.",
                50,
                "{$wordCount} words",
                self::GOOD_MIN . '+ words'
            );
        }

        // Fail - insufficient content
        return RuleResult::fail(
            $this->getId(),
            "Content is too short at {$wordCount} words.",
            "Add more content to reach at least " . self::WARNING_MIN . " words. Very short content rarely ranks well in search results.",
            "{$wordCount} words",
            self::WARNING_MIN . '+ words minimum'
        );
    }
}
