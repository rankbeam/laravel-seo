<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears at the start of the title.
 *
 * Keywords at the beginning of the title have slightly more weight
 * in search rankings and are more visible to users.
 *
 * ## Threshold
 * Keyword should appear in the first 30% of the title.
 *
 * ## How It Works
 * - Calculates the position of the keyword in the title
 * - Passes if within the first 30% of characters
 * - Skips if keyword doesn't appear in title at all
 *
 * ## Example
 * Title: "SEO Tips for Beginners: A Complete Guide" (40 chars)
 * First 30% = 12 chars → "SEO Tips for"
 * Keyword "SEO" appears at position 0 → Pass ✓
 *
 * Title: "A Complete Guide to SEO Tips" (28 chars)
 * First 30% = ~8 chars
 * Keyword "SEO" appears at position 18 → Fail ✗
 */
class KeywordAtTitleStartRule extends AbstractRule
{
    /**
     * Percentage of title where keyword should appear.
     */
    protected const POSITION_THRESHOLD = 0.30;

    public function getId(): string
    {
        return 'keyword_at_title_start';
    }

    public function getName(): string
    {
        return 'Keyword at Title Start';
    }

    public function getWeight(): int
    {
        return 10;
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

        if (empty($context->title)) {
            return RuleResult::skip(
                $this->getId(),
                'No SEO title set.'
            );
        }

        // First check if keyword is in title at all
        if (! $this->keywordOrSynonymInText($primaryKeyword, $context->title, $context->locale)) {
            return RuleResult::skip(
                $this->getId(),
                'Keyword does not appear in title. Fix "Keyword in Title" first.'
            );
        }

        // Find the position of the keyword in the title
        $titleLower = mb_strtolower($context->title);
        $keywordLower = mb_strtolower($primaryKeyword['original']);

        $position = mb_strpos($titleLower, $keywordLower);

        // If exact match not found, try stemmed matching
        if ($position === false) {
            // Check synonyms for position
            foreach ($primaryKeyword['synonyms'] ?? [] as $synonym) {
                $synonymLower = mb_strtolower($synonym);
                $position = mb_strpos($titleLower, $synonymLower);
                if ($position !== false) {
                    break;
                }
            }
        }

        // Calculate threshold
        $titleLength = mb_strlen($context->title);
        $threshold = (int) floor($titleLength * self::POSITION_THRESHOLD);

        if ($position !== false && $position <= $threshold) {
            if ($position === 0) {
                return RuleResult::pass(
                    $this->getId(),
                    "Great! Focus keyword appears at the very beginning of the title.",
                    100
                );
            }

            return RuleResult::pass(
                $this->getId(),
                "Focus keyword appears near the start of the title (position {$position}).",
                90
            );
        }

        $actualPosition = $position !== false ? $position : 'not found';

        return RuleResult::warning(
            $this->getId(),
            "Focus keyword does not appear near the start of the title.",
            "Try moving your focus keyword closer to the beginning of the title for better SEO impact.",
            50,
            "Position: {$actualPosition}",
            "Position: 0-{$threshold}"
        );
    }
}
