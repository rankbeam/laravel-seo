<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears in H1 and H2 headings.
 *
 * Headings help both users and search engines understand content structure.
 * Including keywords in headings reinforces topical relevance.
 *
 * ## Scoring
 * - **Pass:** Keyword found in both H1 and at least one H2
 * - **Warning:** Keyword found in H1 only OR H2 only
 * - **Fail:** Keyword not found in any headings
 *
 * ## How It Works
 * - Checks all H1 headings (ideally only one per page)
 * - Checks all H2 subheadings
 * - Uses stemmed matching for accuracy
 *
 * ## Example
 * Keyword: "SEO tips"
 * H1: "10 Essential SEO Tips for Beginners" → Found in H1 ✓
 * H2s: ["Getting Started", "Advanced SEO Tips", "Conclusion"]
 *       → Found in H2 ✓
 * Result: Pass (found in both)
 */
class KeywordInHeadingsRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_headings';
    }

    public function getName(): string
    {
        return 'Keyword in Headings';
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

        $h1Headings = $context->getH1Headings();
        $h2Headings = $context->getH2Headings();

        // Check for headings
        if (empty($h1Headings) && empty($h2Headings)) {
            return RuleResult::fail(
                $this->getId(),
                'No H1 or H2 headings found in the content.',
                'Add headings to structure your content and include your focus keyword.',
                'No headings',
                'H1 and H2 headings with keyword'
            );
        }

        // Check H1 headings
        $foundInH1 = false;
        foreach ($h1Headings as $heading) {
            if ($this->keywordOrSynonymInText($primaryKeyword, $heading, $context->locale)) {
                $foundInH1 = true;
                break;
            }
        }

        // Check H2 headings
        $foundInH2 = false;
        $h2WithKeywordCount = 0;
        foreach ($h2Headings as $heading) {
            if ($this->keywordOrSynonymInText($primaryKeyword, $heading, $context->locale)) {
                $foundInH2 = true;
                $h2WithKeywordCount++;
            }
        }

        // Evaluate results
        if ($foundInH1 && $foundInH2) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword appears in H1 and {$h2WithKeywordCount} H2 heading(s).",
                100
            );
        }

        if ($foundInH1) {
            if (empty($h2Headings)) {
                return RuleResult::warning(
                    $this->getId(),
                    "Focus keyword appears in H1, but no H2 subheadings found.",
                    "Add H2 subheadings to break up your content and include your keyword in some of them.",
                    60,
                    'Found in H1 only',
                    'Found in H1 and H2'
                );
            }

            return RuleResult::warning(
                $this->getId(),
                "Focus keyword appears in H1 but not in any H2 subheadings.",
                "Include your focus keyword in at least one H2 subheading.",
                70,
                'Found in H1 only',
                'Found in H1 and H2'
            );
        }

        if ($foundInH2) {
            if (empty($h1Headings)) {
                return RuleResult::warning(
                    $this->getId(),
                    "Focus keyword appears in H2 subheading(s), but no H1 heading found.",
                    "Add an H1 heading that includes your focus keyword.",
                    50,
                    'Found in H2 only, no H1',
                    'Found in H1 and H2'
                );
            }

            return RuleResult::warning(
                $this->getId(),
                "Focus keyword appears in H2 subheading(s) but not in the H1.",
                "Include your focus keyword in the H1 heading for better SEO.",
                60,
                'Found in H2 only',
                'Found in H1 and H2'
            );
        }

        // Not found in any heading
        $headingInfo = [];
        if (! empty($h1Headings)) {
            $headingInfo[] = count($h1Headings) . ' H1';
        }
        if (! empty($h2Headings)) {
            $headingInfo[] = count($h2Headings) . ' H2';
        }

        return RuleResult::fail(
            $this->getId(),
            "Focus keyword \"{$primaryKeyword['original']}\" does not appear in any headings.",
            "Include your focus keyword in the H1 heading and at least one H2 subheading.",
            implode(', ', $headingInfo) . ' found without keyword',
            'Keyword in H1 and H2'
        );
    }
}
