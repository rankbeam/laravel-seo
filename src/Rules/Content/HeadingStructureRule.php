<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the content has a proper heading structure.
 *
 * Proper heading hierarchy helps both users and search engines
 * understand the content organization.
 *
 * ## Checks Performed
 * 1. **Exactly one H1** - Multiple H1s or no H1 is problematic
 * 2. **H2 subheadings** - Long content (>500 words) should have 2+ H2s
 * 3. **No empty headings** - Headings should contain text
 * 4. **Logical hierarchy** - No H3 without preceding H2
 *
 * ## Best Practices
 * - Use one H1 for the main title
 * - Use H2s for major sections
 * - Use H3s for subsections within H2s
 * - Don't skip levels (H1 → H3 without H2)
 */
class HeadingStructureRule implements RuleInterface
{
    /**
     * Word count threshold for requiring H2 headings.
     */
    protected const LONG_CONTENT_THRESHOLD = 500;

    /**
     * Minimum H2 headings for long content.
     */
    protected const MIN_H2_FOR_LONG_CONTENT = 2;

    public function getId(): string
    {
        return 'heading_structure';
    }

    public function getName(): string
    {
        return 'Heading Structure';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $issues = [];
        $h1Count = count($context->headings['h1'] ?? []);
        $h2Count = count($context->headings['h2'] ?? []);
        $h3Count = count($context->headings['h3'] ?? []);

        // Check for exactly one H1
        if ($h1Count === 0) {
            $issues[] = 'No H1 heading found';
        } elseif ($h1Count > 1) {
            $issues[] = "Multiple H1 headings found ({$h1Count}). Use only one H1 per page";
        }

        // Check for H2s in long content
        if ($context->wordCount > self::LONG_CONTENT_THRESHOLD && $h2Count < self::MIN_H2_FOR_LONG_CONTENT) {
            $issues[] = "Long content ({$context->wordCount} words) should have at least " . self::MIN_H2_FOR_LONG_CONTENT . " H2 subheadings to improve structure";
        }

        // Check for empty headings
        $emptyHeadings = $this->countEmptyHeadings($context->headings);
        if ($emptyHeadings > 0) {
            $issues[] = "{$emptyHeadings} empty heading(s) found";
        }

        // Check for logical hierarchy (H3 without H2)
        if ($h3Count > 0 && $h2Count === 0) {
            $issues[] = 'H3 headings found without any H2 headings - consider restructuring';
        }

        // Determine result
        if (empty($issues)) {
            $summary = $this->buildSummary($context->headings);

            return RuleResult::pass(
                $this->getId(),
                "Heading structure is good. {$summary}",
                100
            );
        }

        // Calculate score based on severity
        $score = $this->calculateScore($h1Count, $issues);

        if ($score >= 60) {
            return RuleResult::warning(
                $this->getId(),
                'Heading structure has minor issues: ' . implode('; ', $issues),
                $this->buildRecommendation($issues),
                $score,
                implode(', ', $issues),
                'Proper heading hierarchy'
            );
        }

        return RuleResult::fail(
            $this->getId(),
            'Heading structure has issues: ' . implode('; ', $issues),
            $this->buildRecommendation($issues),
            implode(', ', $issues),
            'Proper heading hierarchy'
        );
    }

    /**
     * Count empty headings across all levels.
     *
     * @param array<string, array<int, string>> $headings
     */
    protected function countEmptyHeadings(array $headings): int
    {
        $count = 0;

        foreach ($headings as $level => $items) {
            foreach ($items as $heading) {
                if (empty(trim($heading))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Build a summary of the heading structure.
     *
     * @param array<string, array<int, string>> $headings
     */
    protected function buildSummary(array $headings): string
    {
        $parts = [];

        foreach (['h1', 'h2', 'h3'] as $level) {
            $count = count($headings[$level] ?? []);
            if ($count > 0) {
                $parts[] = "{$count} " . strtoupper($level);
            }
        }

        return empty($parts) ? 'No headings found' : implode(', ', $parts);
    }

    /**
     * Calculate score based on issues.
     *
     * @param array<int, string> $issues
     */
    protected function calculateScore(int $h1Count, array $issues): int
    {
        $score = 100;

        // Major penalty for H1 issues
        if ($h1Count === 0) {
            $score -= 50;
        } elseif ($h1Count > 1) {
            $score -= 30;
        }

        // Moderate penalties for other issues
        $score -= (count($issues) - ($h1Count !== 1 ? 1 : 0)) * 15;

        return max(0, $score);
    }

    /**
     * Build recommendation based on issues.
     *
     * @param array<int, string> $issues
     */
    protected function buildRecommendation(array $issues): string
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            if (str_contains($issue, 'No H1')) {
                $recommendations[] = 'Add an H1 heading to define the main topic of the page';
            } elseif (str_contains($issue, 'Multiple H1')) {
                $recommendations[] = 'Keep only one H1 heading and convert others to H2';
            } elseif (str_contains($issue, 'H2 subheadings')) {
                $recommendations[] = 'Break up your content with H2 subheadings for better readability';
            } elseif (str_contains($issue, 'empty heading')) {
                $recommendations[] = 'Remove or add text to empty headings';
            } elseif (str_contains($issue, 'H3 headings found without')) {
                $recommendations[] = 'Add H2 headings before using H3 headings';
            }
        }

        return implode('. ', array_unique($recommendations)) . '.';
    }
}
