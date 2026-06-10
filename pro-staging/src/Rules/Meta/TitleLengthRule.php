<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Meta;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the SEO title length is optimal for search engines.
 *
 * Google typically displays 50-60 characters of a title tag in search results.
 * Titles that are too long get truncated with "...", reducing their effectiveness.
 *
 * ## Optimal Length
 * - **Pass:** 30-60 characters (ideal range)
 * - **Warning:** 20-30 characters (too short, could include more keywords)
 * - **Warning:** 60-70 characters (may be truncated)
 * - **Fail:** Empty, <20 characters, or >70 characters
 *
 * ## Best Practices
 * - Include your focus keyword near the beginning
 * - Make each title unique
 * - Write for humans, not just search engines
 * - Consider how it appears in search results
 *
 * ## Example
 * Good: "10 Essential SEO Tips for Beginners | Your Brand" (47 chars) ✓
 * Too Short: "SEO Tips" (8 chars) ✗
 * Too Long: "The Complete, Ultimate, and Comprehensive Guide to SEO Tips for Absolute Beginners in 2024" (90 chars) ✗
 */
class TitleLengthRule implements RuleInterface
{
    /**
     * Optimal length range.
     */
    protected const MIN_OPTIMAL = 30;
    protected const MAX_OPTIMAL = 60;

    /**
     * Warning thresholds.
     */
    protected const MIN_WARNING = 20;
    protected const MAX_WARNING = 70;

    public function getId(): string
    {
        return 'title_length';
    }

    public function getName(): string
    {
        return 'SEO Title Length';
    }

    public function getWeight(): int
    {
        return 10;
    }

    public function getCategory(): string
    {
        return 'meta';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        if (empty($context->title)) {
            return RuleResult::fail(
                $this->getId(),
                'No SEO title set.',
                'Add an SEO title between ' . self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters.',
                'Empty',
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        $length = mb_strlen($context->title);

        // Optimal range
        if ($length >= self::MIN_OPTIMAL && $length <= self::MAX_OPTIMAL) {
            return RuleResult::pass(
                $this->getId(),
                "SEO title length is optimal ({$length} characters).",
                100
            );
        }

        // Too short (fail)
        if ($length < self::MIN_WARNING) {
            return RuleResult::fail(
                $this->getId(),
                "SEO title is too short ({$length} characters).",
                "Expand your title to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters. Add relevant keywords or descriptive words.",
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Short warning range
        if ($length < self::MIN_OPTIMAL) {
            return RuleResult::warning(
                $this->getId(),
                "SEO title is slightly short ({$length} characters).",
                "Consider expanding to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters for better SEO impact.",
                70,
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Long warning range (may truncate)
        if ($length <= self::MAX_WARNING) {
            return RuleResult::warning(
                $this->getId(),
                "SEO title is slightly long ({$length} characters) and may be truncated in search results.",
                "Shorten to under " . self::MAX_OPTIMAL . " characters to ensure the full title displays.",
                60,
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Too long (fail)
        return RuleResult::fail(
            $this->getId(),
            "SEO title is too long ({$length} characters) and will be truncated in search results.",
            "Shorten your title to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters.",
            "{$length} characters",
            self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
        );
    }
}
