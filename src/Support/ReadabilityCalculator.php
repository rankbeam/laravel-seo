<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Support;

use Fibonoir\LaravelSEO\Data\ReadabilityResult;

/**
 * Calculates readability scores for different languages.
 *
 * Analyzes text complexity and provides scores that indicate how easy
 * or difficult the content is to read. Different algorithms are used
 * for different languages to ensure accurate results.
 *
 * ## Supported Algorithms
 *
 * **English: Flesch-Kincaid Reading Ease**
 * - Formula: 206.835 - 1.015 × (words/sentences) - 84.6 × (syllables/words)
 * - Higher scores = easier to read
 * - Target for web content: 60-70 (8th-9th grade level)
 *
 * **Italian: Gulpease Index**
 * - Formula: 89 + (300 × sentences - 10 × letters) / words
 * - Higher scores = easier to read
 * - Target for web content: 60-80 (middle school level)
 *
 * ## Usage
 *
 * ```php
 * $calculator = app(ReadabilityCalculator::class);
 *
 * // Auto-detect algorithm based on locale
 * $result = $calculator->calculate($text, 'en');
 * echo $result->score;       // 65.5
 * echo $result->level;       // "good"
 * echo $result->description; // "Standard readability (8th-9th grade)"
 *
 * // Check if content is accessible
 * if ($result->isGood()) {
 *     // Content is readable by most people
 * }
 *
 * // Get improvement suggestions
 * foreach ($result->getSuggestions() as $suggestion) {
 *     echo $suggestion;
 * }
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Data\ReadabilityResult For result structure
 * @see \Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer For usage context
 */
class ReadabilityCalculator
{
    /**
     * Calculate readability for given text and locale.
     *
     * Routes to the appropriate algorithm based on the locale.
     * Currently supports:
     * - 'en' (and variants): Flesch-Kincaid
     * - 'it' (and variants): Gulpease
     * - Others: Fall back to Flesch-Kincaid
     *
     * @param string $text The text to analyze
     * @param string $locale The locale code (e.g., 'en', 'it', 'en_US')
     * @return ReadabilityResult The analysis result
     *
     * @example
     * ```php
     * $result = $calculator->calculate($blogPost->content, 'en');
     * $result = $calculator->calculate($articolo->contenuto, 'it');
     * ```
     */
    public function calculate(string $text, string $locale = 'en'): ReadabilityResult
    {
        $normalizedLocale = explode('_', str_replace('-', '_', $locale))[0];

        return match ($normalizedLocale) {
            'it' => $this->calculateGulpease($text),
            default => $this->calculateFleschKincaid($text),
        };
    }

    /**
     * Calculate Flesch-Kincaid Reading Ease score.
     *
     * The Flesch-Kincaid Reading Ease formula measures text readability
     * based on sentence length and word complexity (syllable count).
     *
     * **Formula:**
     * 206.835 - 1.015 × (words/sentences) - 84.6 × (syllables/words)
     *
     * **Score Interpretation:**
     * - 90-100: Very Easy (5th grade) - Comics, simple text
     * - 80-89: Easy (6th grade) - Consumer magazines
     * - 70-79: Fairly Easy (7th grade) - General interest
     * - 60-69: Standard (8th-9th grade) - Newspapers, ideal for web
     * - 50-59: Fairly Difficult (10th-12th grade) - Quality journalism
     * - 30-49: Difficult (College) - Academic, technical
     * - 0-29: Very Difficult (Graduate) - Scientific, legal
     *
     * @param string $text The English text to analyze
     * @return ReadabilityResult The analysis result with score 0-100
     */
    public function calculateFleschKincaid(string $text): ReadabilityResult
    {
        $stats = $this->getTextStats($text, 'en');

        if ($stats['words'] === 0 || $stats['sentences'] === 0) {
            return ReadabilityResult::insufficient('en');
        }

        // Calculate averages
        $wordsPerSentence = $stats['words'] / $stats['sentences'];
        $syllablesPerWord = $stats['syllables'] / $stats['words'];

        // Apply Flesch-Kincaid formula
        $score = 206.835 - (1.015 * $wordsPerSentence) - (84.6 * $syllablesPerWord);

        // Clamp to 0-100 range
        $score = max(0, min(100, $score));

        // Determine level and description
        [$level, $description] = $this->getFleschLevel($score);

        return new ReadabilityResult(
            score: round($score, 1),
            level: $level,
            description: $description,
            locale: 'en',
            stats: $stats
        );
    }

    /**
     * Calculate Gulpease Index for Italian text.
     *
     * The Gulpease Index is designed specifically for Italian text,
     * using letter count instead of syllables for more accurate results.
     *
     * **Formula:**
     * 89 + (300 × sentences - 10 × letters) / words
     *
     * **Score Interpretation:**
     * - 80+: Very Easy (elementary school)
     * - 60-79: Easy (middle school) - Ideal for web content
     * - 40-59: Difficult (high school)
     * - <40: Very Difficult (university level)
     *
     * @param string $text The Italian text to analyze
     * @return ReadabilityResult The analysis result with score 0-100
     */
    public function calculateGulpease(string $text): ReadabilityResult
    {
        $stats = $this->getTextStats($text, 'it');

        if ($stats['words'] === 0) {
            return ReadabilityResult::insufficient('it');
        }

        // Apply Gulpease formula
        $score = 89 + ((300 * $stats['sentences']) - (10 * $stats['letters'])) / $stats['words'];

        // Clamp to 0-100 range
        $score = max(0, min(100, $score));

        // Determine level and description
        [$level, $description] = $this->getGulpeaseLevel($score);

        return new ReadabilityResult(
            score: round($score, 1),
            level: $level,
            description: $description,
            locale: 'it',
            stats: $stats
        );
    }

    /**
     * Get comprehensive text statistics.
     *
     * @param string $text The text to analyze
     * @param string $locale The locale for language-specific rules
     * @return array{
     *     words: int,
     *     sentences: int,
     *     syllables: int,
     *     letters: int,
     *     avgWordsPerSentence: float,
     *     avgSyllablesPerWord: float
     * }
     */
    protected function getTextStats(string $text, string $locale): array
    {
        // Clean the text
        $text = $this->cleanText($text);

        // Count words
        $wordCount = $this->countWords($text);

        // Count sentences
        $sentenceCount = $this->countSentences($text);

        // Count syllables (extract words first)
        $words = $this->extractWords($text);
        $syllableCount = $this->countSyllablesInWords($words, $locale);

        // Count letters (for Gulpease)
        $letterCount = $this->countLetters($text);

        return [
            'words' => $wordCount,
            'sentences' => $sentenceCount,
            'syllables' => $syllableCount,
            'letters' => $letterCount,
            'avgWordsPerSentence' => $sentenceCount > 0
                ? round($wordCount / $sentenceCount, 1)
                : 0.0,
            'avgSyllablesPerWord' => $wordCount > 0
                ? round($syllableCount / $wordCount, 2)
                : 0.0,
        ];
    }

    /**
     * Clean text by removing HTML and normalizing whitespace.
     *
     * @param string $text The raw text
     * @return string Cleaned text
     */
    protected function cleanText(string $text): string
    {
        // Strip HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Count words in text.
     *
     * @param string $text The cleaned text
     * @return int Word count
     */
    protected function countWords(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return count($words);
    }

    /**
     * Extract words from text.
     *
     * @param string $text The cleaned text
     * @return array<int, string> Array of words
     */
    protected function extractWords(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        return preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Count sentences in text.
     *
     * Counts sentence-ending punctuation (. ! ?) while handling
     * common abbreviations to avoid false positives.
     *
     * @param string $text The cleaned text
     * @return int Sentence count (minimum 1 if text exists)
     */
    protected function countSentences(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Handle common abbreviations to avoid false counts
        $text = preg_replace('/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|i\.e|e\.g|Inc|Ltd|Corp)\./i', '$1<ABBR>', $text);

        // Count sentence-ending punctuation
        $count = preg_match_all('/[.!?]+/', $text);

        // Ensure at least 1 sentence if there's content
        return max(1, $count);
    }

    /**
     * Count letters (Unicode-aware) in text.
     *
     * Used by the Gulpease algorithm for Italian text.
     *
     * @param string $text The cleaned text
     * @return int Letter count
     */
    protected function countLetters(string $text): int
    {
        return preg_match_all('/\p{L}/u', $text);
    }

    /**
     * Count total syllables in an array of words.
     *
     * @param array<int, string> $words The words to count
     * @param string $locale The locale for language-specific rules
     * @return int Total syllable count
     */
    protected function countSyllablesInWords(array $words, string $locale): int
    {
        $total = 0;

        foreach ($words as $word) {
            $total += $this->countSyllables($word, $locale);
        }

        return $total;
    }

    /**
     * Count syllables in a single word.
     *
     * Uses vowel-counting with language-specific adjustments.
     *
     * **English rules:**
     * - Count vowel groups (a, e, i, o, u, y)
     * - Subtract 1 for silent 'e' at end (except -le, -ve, -se)
     * - Subtract 1 for -ed endings (except -ted, -ded)
     * - Minimum 1 syllable per word
     *
     * **Italian rules:**
     * - Count vowel groups (a, e, i, o, u, à, è, é, ì, ò, ù)
     * - Italian has fewer exceptions
     *
     * @param string $word The word to analyze
     * @param string $locale The locale ('en' or 'it')
     * @return int Syllable count (minimum 1)
     */
    protected function countSyllables(string $word, string $locale): int
    {
        $word = mb_strtolower(trim($word));

        // Short words are 1 syllable
        if (mb_strlen($word) <= 2) {
            return 1;
        }

        // Remove non-letter characters
        $word = preg_replace('/[^\p{L}]/u', '', $word);

        if (empty($word)) {
            return 1;
        }

        // Count vowel groups based on locale
        if ($locale === 'it') {
            // Italian vowels including accented characters
            $syllables = preg_match_all('/[aeiouàèéìíòóùú]+/ui', $word);
        } else {
            // English vowels (including y as vowel)
            $syllables = preg_match_all('/[aeiouy]+/i', $word);

            // English-specific adjustments
            $syllables = $this->adjustEnglishSyllables($word, $syllables);
        }

        // Every word has at least 1 syllable
        return max(1, $syllables);
    }

    /**
     * Adjust syllable count for English-specific patterns.
     *
     * @param string $word The word being analyzed
     * @param int $syllables Current syllable count
     * @return int Adjusted syllable count
     */
    protected function adjustEnglishSyllables(string $word, int $syllables): int
    {
        // Silent 'e' at end (but not -le, -ve, -se endings which are pronounced)
        if (preg_match('/[^lv]e$/i', $word) && ! preg_match('/(le|ve|se)$/i', $word)) {
            $syllables = max(1, $syllables - 1);
        }

        // Words ending in -ed usually don't add a syllable
        // Exception: words ending in -ted or -ded
        if (preg_match('/ed$/i', $word) && ! preg_match('/(ted|ded)$/i', $word)) {
            $syllables = max(1, $syllables - 1);
        }

        // Common exceptions - words that have fewer syllables than vowel groups suggest
        $exceptions = [
            'business' => 2,
            'every' => 2,
            'different' => 3,
            'evening' => 2,
            'several' => 2,
            'interest' => 2,
            'favorite' => 2,
            'chocolate' => 2,
            'camera' => 2,
            'family' => 2,
            'average' => 2,
            'vegetable' => 3,
            'comfortable' => 3,
        ];

        if (isset($exceptions[$word])) {
            return $exceptions[$word];
        }

        return $syllables;
    }

    /**
     * Get Flesch-Kincaid level and description.
     *
     * @param float $score The calculated score
     * @return array{0: string, 1: string} [level, description]
     */
    protected function getFleschLevel(float $score): array
    {
        return match (true) {
            $score >= 90 => ['easy', 'Very easy to read (5th grade level)'],
            $score >= 80 => ['easy', 'Easy to read (6th grade level)'],
            $score >= 70 => ['good', 'Fairly easy to read (7th grade level)'],
            $score >= 60 => ['good', 'Standard readability (8th-9th grade)'],
            $score >= 50 => ['moderate', 'Fairly difficult (10th-12th grade)'],
            $score >= 30 => ['difficult', 'Difficult to read (college level)'],
            default => ['very_difficult', 'Very difficult (graduate level)'],
        };
    }

    /**
     * Get Gulpease level and description.
     *
     * @param float $score The calculated score
     * @return array{0: string, 1: string} [level, description]
     */
    protected function getGulpeaseLevel(float $score): array
    {
        return match (true) {
            $score >= 80 => ['easy', 'Molto facile da leggere (scuola elementare)'],
            $score >= 60 => ['good', 'Facilmente comprensibile (scuola media)'],
            $score >= 40 => ['moderate', 'Difficile (scuola superiore)'],
            default => ['difficult', 'Molto difficile (livello universitario)'],
        };
    }

    /**
     * Get the recommended target score for web content.
     *
     * @param string $locale The locale
     * @return array{min: int, max: int, description: string}
     */
    public function getTargetScore(string $locale = 'en'): array
    {
        $normalizedLocale = explode('_', $locale)[0];

        return match ($normalizedLocale) {
            'it' => [
                'min' => 60,
                'max' => 80,
                'description' => 'Livello scuola media - ideale per contenuti web',
            ],
            default => [
                'min' => 60,
                'max' => 70,
                'description' => '8th-9th grade level - ideal for web content',
            ],
        };
    }
}
