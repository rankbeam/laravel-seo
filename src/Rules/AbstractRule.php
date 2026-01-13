<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\Stemmer;

/**
 * Base class for SEO analysis rules.
 *
 * Provides common functionality for all rules including:
 * - Stemmer access for keyword matching
 * - Helper methods for text analysis
 * - Standard result creation patterns
 */
abstract class AbstractRule implements RuleInterface
{
    public function __construct(
        protected Stemmer $stemmer,
    ) {}

    /**
     * Get the rule category for grouping.
     */
    public function getCategory(): string
    {
        return 'general';
    }

    /**
     * Check if stemmed keyword appears in text.
     *
     * @param string $stemmedKeyword The stemmed keyword to find
     * @param string $text The text to search in
     * @param string $locale The locale for stemming
     * @return bool True if keyword found
     */
    protected function keywordInText(string $stemmedKeyword, string $text, string $locale): bool
    {
        $stemmedText = $this->stemmer->stemPhrase($text, $locale);

        return str_contains($stemmedText, $stemmedKeyword);
    }

    /**
     * Check if any keyword (including synonyms) appears in text.
     *
     * @param array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>} $keyword
     * @param string $text The text to search in
     * @param string $locale The locale for stemming
     * @return bool True if keyword or any synonym found
     */
    protected function keywordOrSynonymInText(array $keyword, string $text, string $locale): bool
    {
        // Check main keyword
        if ($this->keywordInText($keyword['stemmed'], $text, $locale)) {
            return true;
        }

        // Check synonyms
        foreach ($keyword['synonyms'] ?? [] as $synonym) {
            $stemmedSynonym = $this->stemmer->stemPhrase($synonym, $locale);
            if ($this->keywordInText($stemmedSynonym, $text, $locale)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count occurrences of keyword (including synonyms) in stemmed tokens.
     *
     * @param array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>} $keyword
     * @param array<int, string> $stemmedTokens The stemmed tokens to search
     * @param string $locale The locale for stemming
     * @return int Total occurrence count
     */
    protected function countKeywordOccurrences(array $keyword, array $stemmedTokens, string $locale): int
    {
        $count = 0;
        $keywordParts = explode(' ', $keyword['stemmed']);

        // For multi-word keywords, we need to check consecutive tokens
        if (count($keywordParts) > 1) {
            $count += $this->countPhraseOccurrences($keywordParts, $stemmedTokens);
        } else {
            // Single word keyword
            $count += count(array_filter($stemmedTokens, fn($t) => $t === $keyword['stemmed']));
        }

        // Count synonyms
        foreach ($keyword['synonyms'] ?? [] as $synonym) {
            $stemmedSynonym = $this->stemmer->stemPhrase($synonym, $locale);
            $synonymParts = explode(' ', $stemmedSynonym);

            if (count($synonymParts) > 1) {
                $count += $this->countPhraseOccurrences($synonymParts, $stemmedTokens);
            } else {
                $count += count(array_filter($stemmedTokens, fn($t) => $t === $stemmedSynonym));
            }
        }

        return $count;
    }

    /**
     * Count occurrences of a multi-word phrase in tokens.
     *
     * @param array<int, string> $phraseParts The phrase parts to find
     * @param array<int, string> $tokens The tokens to search
     * @return int Occurrence count
     */
    protected function countPhraseOccurrences(array $phraseParts, array $tokens): int
    {
        $count = 0;
        $phraseLength = count($phraseParts);
        $tokensCount = count($tokens);

        for ($i = 0; $i <= $tokensCount - $phraseLength; $i++) {
            $match = true;
            for ($j = 0; $j < $phraseLength; $j++) {
                if ($tokens[$i + $j] !== $phraseParts[$j]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create a skip result for missing keyword.
     */
    protected function skipNoKeyword(): RuleResult
    {
        return RuleResult::skip(
            $this->getId(),
            'No focus keyword set. Add a focus keyword to enable this check.'
        );
    }
}
