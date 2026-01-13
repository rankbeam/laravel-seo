<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Support;

/**
 * Text tokenizer for SEO analysis.
 *
 * Breaks text into individual words (tokens) for keyword analysis,
 * word counting, and other text processing tasks.
 *
 * ## Tokenization Rules
 *
 * - All text is converted to lowercase
 * - HTML entities are decoded
 * - Punctuation is removed (except apostrophes in contractions)
 * - Text is split on whitespace
 * - Empty strings are filtered out
 * - Words shorter than minimum length (default: 2) are excluded
 * - Pure numbers are excluded
 *
 * ## Usage
 *
 * ```php
 * $tokenizer = app(Tokenizer::class);
 *
 * // Basic tokenization
 * $words = $tokenizer->tokenize("Hello, World! This is a test.");
 * // ["hello", "world", "this", "is", "test"]
 *
 * // With positions (for keyword density analysis)
 * $tokens = $tokenizer->tokenizeWithPositions("Hello World");
 * // [["word" => "hello", "position" => 0], ["word" => "world", "position" => 6]]
 *
 * // Word count
 * $count = $tokenizer->countWords($text);
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer For usage context
 * @see \Fibonoir\LaravelSEO\Support\StopWords For filtering common words
 */
class Tokenizer
{
    /**
     * Minimum word length to include in results.
     *
     * @var int
     */
    protected int $minWordLength = 2;

    /**
     * Tokenize text into an array of words.
     *
     * @param string $text The text to tokenize
     * @return array<int, string> Array of lowercase words
     *
     * @example
     * ```php
     * $tokenizer->tokenize("Hello, World!");
     * // ["hello", "world"]
     *
     * $tokenizer->tokenize("Don't stop believing!");
     * // ["dont", "stop", "believing"]
     * ```
     */
    public function tokenize(string $text): array
    {
        // Convert to lowercase
        $text = mb_strtolower($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip HTML tags
        $text = strip_tags($text);

        // Remove punctuation except apostrophes within words
        // Keep letters (any language), numbers, apostrophes, and spaces
        $text = preg_replace("/[^\p{L}\p{N}'\s]/u", ' ', $text);

        // Handle contractions by merging them (don't → dont)
        $text = preg_replace("/(\w)'(\w)/u", '$1$2', $text);

        // Remove standalone apostrophes
        $text = str_replace("'", ' ', $text);

        // Split on whitespace
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter by minimum length and exclude pure numbers
        return array_values(array_filter($words, function ($word) {
            return mb_strlen($word) >= $this->minWordLength
                && ! is_numeric($word);
        }));
    }

    /**
     * Tokenize text and return words with their character positions.
     *
     * Useful for keyword density analysis and highlighting.
     *
     * @param string $text The text to tokenize
     * @return array<int, array{word: string, position: int}> Words with positions
     *
     * @example
     * ```php
     * $tokenizer->tokenizeWithPositions("Hello World");
     * // [
     * //     ["word" => "hello", "position" => 0],
     * //     ["word" => "world", "position" => 6]
     * // ]
     * ```
     */
    public function tokenizeWithPositions(string $text): array
    {
        $tokens = [];
        $originalText = $text;
        $text = mb_strtolower($text);

        // Match word boundaries - letters and numbers
        preg_match_all('/\b[\p{L}\p{N}]+\b/u', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $word = $match[0];
            $position = $match[1];

            // Apply same filtering as tokenize()
            if (mb_strlen($word) >= $this->minWordLength && ! is_numeric($word)) {
                $tokens[] = [
                    'word' => $word,
                    'position' => $position,
                    'original' => mb_substr($originalText, $position, mb_strlen($word)),
                ];
            }
        }

        return $tokens;
    }

    /**
     * Count the number of words in text.
     *
     * @param string $text The text to count
     * @return int Number of words
     *
     * @example
     * ```php
     * $tokenizer->countWords("Hello World!"); // 2
     * ```
     */
    public function countWords(string $text): int
    {
        return count($this->tokenize($text));
    }

    /**
     * Get word frequency counts.
     *
     * @param string $text The text to analyze
     * @return array<string, int> Word => count mapping, sorted by frequency
     *
     * @example
     * ```php
     * $tokenizer->getWordFrequencies("hello world hello");
     * // ["hello" => 2, "world" => 1]
     * ```
     */
    public function getWordFrequencies(string $text): array
    {
        $words = $this->tokenize($text);
        $frequencies = array_count_values($words);
        arsort($frequencies);

        return $frequencies;
    }

    /**
     * Split text into sentences.
     *
     * @param string $text The text to split
     * @return array<int, string> Array of sentences
     *
     * @example
     * ```php
     * $tokenizer->splitSentences("Hello world. How are you? Fine!");
     * // ["Hello world", "How are you", "Fine"]
     * ```
     */
    public function splitSentences(string $text): array
    {
        // Handle common abbreviations to avoid false splits
        $text = preg_replace('/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|i\.e|e\.g)\./i', '$1<PERIOD>', $text);

        // Split on sentence-ending punctuation
        $sentences = preg_split('/[.!?]+\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Restore abbreviations
        return array_map(function ($sentence) {
            return trim(str_replace('<PERIOD>', '.', $sentence));
        }, $sentences);
    }

    /**
     * Split text into paragraphs.
     *
     * @param string $text The text to split
     * @return array<int, string> Array of paragraphs
     */
    public function splitParagraphs(string $text): array
    {
        // Split on double newlines or <p> tags
        $text = preg_replace('/<\/?p[^>]*>/i', "\n\n", $text);
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map('trim', $paragraphs)));
    }

    /**
     * Set the minimum word length for tokenization.
     *
     * @param int $length Minimum number of characters
     * @return self For method chaining
     */
    public function setMinWordLength(int $length): self
    {
        $this->minWordLength = max(1, $length);

        return $this;
    }

    /**
     * Get the current minimum word length.
     *
     * @return int The minimum word length
     */
    public function getMinWordLength(): int
    {
        return $this->minWordLength;
    }

    /**
     * Extract n-grams (word sequences) from text.
     *
     * @param string $text The text to analyze
     * @param int $n The n-gram size (2 = bigrams, 3 = trigrams)
     * @return array<int, string> Array of n-grams
     *
     * @example
     * ```php
     * $tokenizer->getNgrams("hello beautiful world", 2);
     * // ["hello beautiful", "beautiful world"]
     * ```
     */
    public function getNgrams(string $text, int $n = 2): array
    {
        $words = $this->tokenize($text);
        $ngrams = [];

        for ($i = 0; $i <= count($words) - $n; $i++) {
            $ngrams[] = implode(' ', array_slice($words, $i, $n));
        }

        return $ngrams;
    }
}
