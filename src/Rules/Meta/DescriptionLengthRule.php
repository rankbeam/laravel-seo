<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Meta;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the meta description length is optimal for search engines.
 *
 * Google typically displays 150-160 characters of a meta description in search results.
 * While meta descriptions aren't a direct ranking factor, they affect click-through rates.
 *
 * ## Optimal Length
 * - **Pass:** 120-160 characters (ideal range)
 * - **Warning:** 70-120 characters (could be longer)
 * - **Warning:** 160-170 characters (may be truncated)
 * - **Fail:** Empty, <70 characters, or >170 characters
 *
 * ## Best Practices
 * - Include your focus keyword naturally
 * - Write a compelling call-to-action
 * - Accurately describe the page content
 * - Make each description unique
 *
 * ## Example
 * Good: "Learn the 10 most effective SEO strategies for 2024. This comprehensive guide covers
 *        keyword research, content optimization, and link building techniques." (156 chars) ✓
 */
class DescriptionLengthRule implements RuleInterface
{
    /**
     * Optimal length range.
     */
    protected const MIN_OPTIMAL = 120;
    protected const MAX_OPTIMAL = 160;

    /**
     * Warning/fail thresholds.
     */
    protected const MIN_WARNING = 70;
    protected const MAX_WARNING = 170;

    public function getId(): string
    {
        return 'description_length';
    }

    public function getName(): string
    {
        return 'Meta Description Length';
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
        if (empty($context->description)) {
            return RuleResult::fail(
                $this->getId(),
                'No meta description set.',
                'Add a meta description of ' . self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters to improve click-through rates.',
                'Empty',
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        $length = mb_strlen($context->description);

        // Optimal range
        if ($length >= self::MIN_OPTIMAL && $length <= self::MAX_OPTIMAL) {
            return RuleResult::pass(
                $this->getId(),
                "Meta description length is optimal ({$length} characters).",
                100
            );
        }

        // Too short (fail)
        if ($length < self::MIN_WARNING) {
            return RuleResult::fail(
                $this->getId(),
                "Meta description is too short ({$length} characters).",
                "Expand your description to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters. Include your keyword and a compelling call-to-action.",
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Short warning range
        if ($length < self::MIN_OPTIMAL) {
            return RuleResult::warning(
                $this->getId(),
                "Meta description could be longer ({$length} characters).",
                "Expand to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters for maximum impact in search results.",
                70,
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Long warning range
        if ($length <= self::MAX_WARNING) {
            return RuleResult::warning(
                $this->getId(),
                "Meta description is slightly long ({$length} characters) and may be truncated.",
                "Shorten to under " . self::MAX_OPTIMAL . " characters to ensure the full description displays.",
                60,
                "{$length} characters",
                self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
            );
        }

        // Too long (fail)
        return RuleResult::fail(
            $this->getId(),
            "Meta description is too long ({$length} characters) and will be truncated.",
            "Shorten your description to " . self::MIN_OPTIMAL . "-" . self::MAX_OPTIMAL . " characters.",
            "{$length} characters",
            self::MIN_OPTIMAL . '-' . self::MAX_OPTIMAL . ' characters'
        );
    }
}
