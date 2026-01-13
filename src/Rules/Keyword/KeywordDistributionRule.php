<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword is distributed throughout the content.
 *
 * Evenly distributed keywords indicate natural, well-structured content
 * rather than keyword stuffing in one area.
 *
 * ## How It Works
 * - Splits content into three equal sections (beginning, middle, end)
 * - Checks if keyword appears in each section
 *
 * ## Scoring
 * - **Pass (100):** Keyword appears in all 3 sections
 * - **Warning (65):** Keyword appears in 2 sections
 * - **Fail (0):** Keyword appears in 1 or 0 sections
 *
 * ## Example
 * 900-word article split into 3 sections of ~300 words each:
 * - Section 1 (words 1-300): "SEO" found → ✓
 * - Section 2 (words 301-600): "SEO" found → ✓
 * - Section 3 (words 601-900): "SEO" found → ✓
 * Result: Pass (3/3)
 */
class KeywordDistributionRule extends AbstractRule
{
    /**
     * Number of sections to divide content into.
     */
    protected const SECTIONS = 3;

    /**
     * Minimum word count for distribution analysis.
     */
    protected const MIN_WORDS = 150;

    public function getId(): string
    {
        return 'keyword_distribution';
    }

    public function getName(): string
    {
        return 'Keyword Distribution';
    }

    public function getWeight(): int
    {
        return 5;
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

        // Need sufficient content for distribution analysis
        if ($context->wordCount < self::MIN_WORDS) {
            return RuleResult::skip(
                $this->getId(),
                "Content too short for distribution analysis (minimum " . self::MIN_WORDS . " words)."
            );
        }

        // Split content into sections
        $sections = $context->splitContent(self::SECTIONS);
        $sectionsWithKeyword = [];

        // Check each section
        foreach ($sections as $index => $sectionText) {
            $sectionName = match ($index) {
                0 => 'beginning',
                1 => 'middle',
                2 => 'end',
                default => "section " . ($index + 1),
            };

            if ($this->keywordOrSynonymInText($primaryKeyword, $sectionText, $context->locale)) {
                $sectionsWithKeyword[] = $sectionName;
            }
        }

        $foundCount = count($sectionsWithKeyword);
        $totalSections = count($sections);

        // Evaluate distribution
        if ($foundCount === $totalSections) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword is well distributed throughout the content ({$foundCount}/{$totalSections} sections).",
                100
            );
        }

        if ($foundCount >= 2) {
            $missing = array_diff(['beginning', 'middle', 'end'], $sectionsWithKeyword);
            $missingSections = implode(', ', $missing);

            return RuleResult::warning(
                $this->getId(),
                "Focus keyword appears in {$foundCount}/{$totalSections} sections of your content.",
                "Add your keyword to the {$missingSections} of your content for better distribution.",
                65,
                "Found in: " . implode(', ', $sectionsWithKeyword),
                "Found in all sections"
            );
        }

        if ($foundCount === 1) {
            return RuleResult::fail(
                $this->getId(),
                "Focus keyword only appears in the {$sectionsWithKeyword[0]} of your content.",
                "Distribute your keyword more evenly throughout your content. Add it to the beginning, middle, and end sections.",
                "Found in: " . $sectionsWithKeyword[0] . " only",
                "Found in all 3 sections"
            );
        }

        // foundCount === 0
        return RuleResult::fail(
            $this->getId(),
            "Focus keyword was not found in any section of the content.",
            "Your content doesn't contain the focus keyword. Add it naturally throughout your text.",
            "Found in: 0/{$totalSections} sections",
            "Found in all sections"
        );
    }
}
