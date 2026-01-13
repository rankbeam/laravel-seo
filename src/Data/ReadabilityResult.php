<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Value object representing readability analysis results.
 *
 * Contains the readability score, level classification, human-readable
 * description, and detailed statistics about the analyzed text.
 *
 * ## Score Interpretation
 *
 * **English (Flesch-Kincaid Reading Ease):**
 * - 90-100: Very Easy (5th grade) - easy
 * - 80-89: Easy (6th grade) - easy
 * - 70-79: Fairly Easy (7th grade) - good
 * - 60-69: Standard (8th-9th grade) - good
 * - 50-59: Fairly Difficult (10th-12th grade) - moderate
 * - 30-49: Difficult (College) - difficult
 * - 0-29: Very Difficult (Graduate) - very_difficult
 *
 * **Italian (Gulpease Index):**
 * - 80+: Easy (elementary school) - easy
 * - 60-79: Good (middle school) - good
 * - 40-59: Difficult (high school) - moderate
 * - <40: Very Difficult (university) - difficult
 *
 * ## Usage
 *
 * ```php
 * $result = $calculator->calculate($text, 'en');
 *
 * echo $result->score;       // 65.5
 * echo $result->level;       // "good"
 * echo $result->description; // "Standard readability (8th-9th grade)"
 *
 * // Check if content is readable enough
 * if ($result->isGood()) {
 *     // Content is accessible to most readers
 * }
 *
 * // Access detailed statistics
 * echo $result->stats['words'];              // 150
 * echo $result->stats['sentences'];          // 8
 * echo $result->stats['avgWordsPerSentence']; // 18.8
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Support\ReadabilityCalculator For calculation
 *
 * @implements Arrayable<string, mixed>
 */
final class ReadabilityResult implements Arrayable, JsonSerializable
{
    /**
     * Create a new ReadabilityResult instance.
     *
     * @param float $score The readability score (0-100)
     * @param string $level The level classification ('easy', 'good', 'moderate', 'difficult', 'very_difficult', 'unknown')
     * @param string $description Human-readable description of the readability level
     * @param string $locale The locale used for calculation ('en', 'it', etc.)
     * @param array{
     *     words: int,
     *     sentences: int,
     *     syllables: int,
     *     letters: int,
     *     avgWordsPerSentence: float,
     *     avgSyllablesPerWord: float
     * } $stats Detailed text statistics
     */
    public function __construct(
        public readonly float $score,
        public readonly string $level,
        public readonly string $description,
        public readonly string $locale,
        public readonly array $stats = [],
    ) {}

    /**
     * Check if the readability level is considered good.
     *
     * Returns true for levels: 'easy', 'good', 'moderate'
     *
     * @return bool True if readability is acceptable
     */
    public function isGood(): bool
    {
        return in_array($this->level, ['easy', 'good', 'moderate'], true);
    }

    /**
     * Check if the content needs improvement.
     *
     * Returns true for levels: 'difficult', 'very_difficult'
     *
     * @return bool True if content is too difficult
     */
    public function needsImprovement(): bool
    {
        return in_array($this->level, ['difficult', 'very_difficult'], true);
    }

    /**
     * Check if the content is very easy to read.
     *
     * @return bool True if level is 'easy'
     */
    public function isEasy(): bool
    {
        return $this->level === 'easy';
    }

    /**
     * Check if analysis was successful.
     *
     * @return bool True if level is not 'unknown'
     */
    public function isValid(): bool
    {
        return $this->level !== 'unknown';
    }

    /**
     * Get the grade level suggestion.
     *
     * @return string Grade level description or empty string
     */
    public function getGradeLevel(): string
    {
        return match ($this->level) {
            'easy' => $this->locale === 'it' ? 'Scuola elementare' : '5th-6th grade',
            'good' => $this->locale === 'it' ? 'Scuola media' : '7th-9th grade',
            'moderate' => $this->locale === 'it' ? 'Scuola superiore' : '10th-12th grade',
            'difficult' => $this->locale === 'it' ? 'Università' : 'College level',
            'very_difficult' => $this->locale === 'it' ? 'Post-laurea' : 'Graduate level',
            default => '',
        };
    }

    /**
     * Get suggested improvements based on the analysis.
     *
     * @return array<int, string> List of improvement suggestions
     */
    public function getSuggestions(): array
    {
        $suggestions = [];

        if (! $this->isValid()) {
            return ['Add more content for accurate analysis.'];
        }

        // Check average words per sentence (ideal: 15-20)
        $avgWords = $this->stats['avgWordsPerSentence'] ?? 0;
        if ($avgWords > 25) {
            $suggestions[] = $this->locale === 'it'
                ? 'Prova ad accorciare le frasi. La media attuale è ' . round($avgWords) . ' parole per frase.'
                : 'Try shortening your sentences. Current average is ' . round($avgWords) . ' words per sentence.';
        }

        // Check syllables per word (ideal: < 1.5 for English)
        $avgSyllables = $this->stats['avgSyllablesPerWord'] ?? 0;
        if ($this->locale !== 'it' && $avgSyllables > 1.7) {
            $suggestions[] = 'Consider using simpler words. Current average is ' . round($avgSyllables, 1) . ' syllables per word.';
        }

        // General suggestions based on level
        if ($this->needsImprovement()) {
            $suggestions[] = $this->locale === 'it'
                ? 'Considera di semplificare il vocabolario per raggiungere un pubblico più ampio.'
                : 'Consider simplifying vocabulary to reach a broader audience.';
        }

        return $suggestions;
    }

    /**
     * Get the word count from stats.
     *
     * @return int Word count
     */
    public function getWordCount(): int
    {
        return (int) ($this->stats['words'] ?? 0);
    }

    /**
     * Get the sentence count from stats.
     *
     * @return int Sentence count
     */
    public function getSentenceCount(): int
    {
        return (int) ($this->stats['sentences'] ?? 0);
    }

    /**
     * Get average words per sentence from stats.
     *
     * @return float Average words per sentence
     */
    public function getAvgWordsPerSentence(): float
    {
        return (float) ($this->stats['avgWordsPerSentence'] ?? 0);
    }

    /**
     * Get average syllables per word from stats.
     *
     * @return float Average syllables per word
     */
    public function getAvgSyllablesPerWord(): float
    {
        return (float) ($this->stats['avgSyllablesPerWord'] ?? 0);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level,
            'description' => $this->description,
            'locale' => $this->locale,
            'grade_level' => $this->getGradeLevel(),
            'is_good' => $this->isGood(),
            'stats' => $this->stats,
            'suggestions' => $this->getSuggestions(),
        ];
    }

    /**
     * JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a result for insufficient content.
     *
     * @param string $locale The locale
     * @return self
     */
    public static function insufficient(string $locale = 'en'): self
    {
        return new self(
            score: 0,
            level: 'unknown',
            description: $locale === 'it'
                ? 'Contenuto insufficiente per l\'analisi'
                : 'Not enough content for analysis',
            locale: $locale,
            stats: [
                'words' => 0,
                'sentences' => 0,
                'syllables' => 0,
                'letters' => 0,
                'avgWordsPerSentence' => 0,
                'avgSyllablesPerWord' => 0,
            ]
        );
    }
}
