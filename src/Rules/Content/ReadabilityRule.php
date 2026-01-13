<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\ReadabilityCalculator;

/**
 * Checks if the content is readable for the target audience.
 *
 * Uses the ReadabilityCalculator to determine how easy or difficult
 * the content is to read. Web content should generally aim for an
 * 8th-9th grade reading level for maximum accessibility.
 *
 * ## Scoring (based on readability level)
 * - **easy:** 100 points
 * - **good:** 85 points
 * - **moderate:** 65 points
 * - **difficult:** 40 points
 * - **very_difficult:** 20 points
 *
 * ## Algorithms Used
 * - English: Flesch-Kincaid Reading Ease
 * - Italian: Gulpease Index
 *
 * ## Skip Conditions
 * - Content less than 100 words (insufficient for analysis)
 */
class ReadabilityRule implements RuleInterface
{
    /**
     * Minimum words required for analysis.
     */
    protected const MIN_WORDS = 100;

    /**
     * Score mapping by readability level.
     *
     * @var array<string, int>
     */
    protected array $levelScores = [
        'easy' => 100,
        'good' => 85,
        'moderate' => 65,
        'difficult' => 40,
        'very_difficult' => 20,
        'unknown' => 0,
    ];

    public function __construct(
        protected ReadabilityCalculator $calculator,
    ) {}

    public function getId(): string
    {
        return 'readability';
    }

    public function getName(): string
    {
        return 'Content Readability';
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
        // Need minimum content for analysis
        if ($context->wordCount < self::MIN_WORDS) {
            return RuleResult::skip(
                $this->getId(),
                "Content too short for readability analysis (minimum " . self::MIN_WORDS . " words)."
            );
        }

        // Calculate readability
        $result = $this->calculator->calculate($context->content, $context->locale);

        // Get score for the level
        $score = $this->levelScores[$result->level] ?? 50;

        // Build result based on level
        if ($result->isGood()) {
            return RuleResult::pass(
                $this->getId(),
                "Content readability is {$result->level}: {$result->description} (Score: {$result->score})",
                $score
            );
        }

        if ($result->level === 'difficult') {
            return RuleResult::warning(
                $this->getId(),
                "Content is difficult to read: {$result->description} (Score: {$result->score})",
                $this->getRecommendation($result, $context->locale),
                $score,
                "Level: {$result->level}",
                'Level: good or easy'
            );
        }

        // very_difficult
        return RuleResult::fail(
            $this->getId(),
            "Content is very difficult to read: {$result->description} (Score: {$result->score})",
            $this->getRecommendation($result, $context->locale),
            "Level: {$result->level}",
            'Level: good or easy'
        );
    }

    /**
     * Get improvement recommendation based on readability stats.
     */
    protected function getRecommendation(\Fibonoir\LaravelSEO\Data\ReadabilityResult $result, string $locale): string
    {
        $suggestions = [];

        // Check average sentence length
        $avgWords = $result->stats['avgWordsPerSentence'] ?? 0;
        if ($avgWords > 20) {
            $suggestions[] = $locale === 'it'
                ? 'Accorcia le frasi (media attuale: ' . round($avgWords) . ' parole)'
                : 'Shorten your sentences (current average: ' . round($avgWords) . ' words)';
        }

        // Check syllables per word
        $avgSyllables = $result->stats['avgSyllablesPerWord'] ?? 0;
        if ($avgSyllables > 1.7 && $locale !== 'it') {
            $suggestions[] = 'Use simpler words with fewer syllables';
        }

        // General suggestions
        if (empty($suggestions)) {
            $suggestions[] = $locale === 'it'
                ? 'Usa frasi più brevi e parole più semplici'
                : 'Use shorter sentences and simpler words';
        }

        return implode('. ', $suggestions) . '.';
    }
}
