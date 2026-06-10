<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword density is optimal.
 *
 * Keyword density is the percentage of times a keyword appears
 * compared to the total word count.
 *
 * **Optimal range:** 1-2.5%
 *
 * ## Scoring
 * - **Pass (100):** Density between 1% and 2.5%
 * - **Warning (70):** Density between 0.5-1% (too low)
 * - **Warning (60):** Density between 2.5-3% (too high)
 * - **Fail (0):** Density below 0.5% or above 3%
 *
 * ## Example
 * For a 1000-word article with "SEO tips" appearing 15 times:
 * Density = (15 / 1000) × 100 = 1.5% → Pass
 */
class KeywordDensityRule extends AbstractRule
{
    /**
     * Optimal density range.
     */
    protected const MIN_OPTIMAL = 1.0;
    protected const MAX_OPTIMAL = 2.5;

    /**
     * Warning thresholds.
     */
    protected const MIN_WARNING = 0.5;
    protected const MAX_WARNING = 3.0;

    public function getId(): string
    {
        return 'keyword_density';
    }

    public function getName(): string
    {
        return 'Keyword Density';
    }

    public function getWeight(): int
    {
        return 15;
    }

    public function getCategory(): string
    {
        return 'keyword';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $primaryKeyword = $context->getPrimaryKeyword();

        if (! $primaryKeyword) {
            return $this->skipNoKeyword();
        }

        // Need minimum content for density calculation
        if ($context->wordCount < 100) {
            return RuleResult::skip(
                $this->getId(),
                'Content too short for density analysis (minimum 100 words).'
            );
        }

        // Count keyword occurrences (including synonyms)
        $occurrences = $this->countKeywordOccurrences(
            $primaryKeyword,
            $context->stemmedTokens,
            $context->locale
        );

        // Calculate density
        $density = ($occurrences / $context->wordCount) * 100;
        $formattedDensity = number_format($density, 2);

        // Evaluate density
        if ($density >= self::MIN_OPTIMAL && $density <= self::MAX_OPTIMAL) {
            return RuleResult::pass(
                $this->getId(),
                "Keyword density is optimal at {$formattedDensity}%.",
                100
            );
        }

        if ($density < self::MIN_WARNING) {
            return RuleResult::fail(
                $this->getId(),
                "Keyword density is too low at {$formattedDensity}%.",
                "Use your focus keyword more often. Aim for 1-2.5% density (about " . $this->suggestedOccurrences($context->wordCount) . " times for your content length).",
                $formattedDensity . '%',
                '1-2.5%'
            );
        }

        if ($density > self::MAX_WARNING) {
            return RuleResult::fail(
                $this->getId(),
                "Keyword density is too high at {$formattedDensity}%. This may be seen as keyword stuffing.",
                "Reduce keyword usage to avoid over-optimization. Aim for 1-2.5% density.",
                $formattedDensity . '%',
                '1-2.5%'
            );
        }

        // Warning range
        if ($density < self::MIN_OPTIMAL) {
            return RuleResult::warning(
                $this->getId(),
                "Keyword density is slightly low at {$formattedDensity}%.",
                "Consider using your focus keyword a few more times. Optimal range is 1-2.5%.",
                70,
                $formattedDensity . '%',
                '1-2.5%'
            );
        }

        // density > self::MAX_OPTIMAL && density <= self::MAX_WARNING
        return RuleResult::warning(
            $this->getId(),
            "Keyword density is slightly high at {$formattedDensity}%.",
            "Consider reducing keyword usage slightly. Optimal range is 1-2.5%.",
            60,
            $formattedDensity . '%',
            '1-2.5%'
        );
    }

    /**
     * Calculate suggested keyword occurrences for optimal density.
     */
    protected function suggestedOccurrences(int $wordCount): string
    {
        $min = (int) ceil($wordCount * (self::MIN_OPTIMAL / 100));
        $max = (int) floor($wordCount * (self::MAX_OPTIMAL / 100));

        return "{$min}-{$max}";
    }
}
