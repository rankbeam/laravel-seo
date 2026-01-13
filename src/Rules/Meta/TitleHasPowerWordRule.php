<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Meta;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the SEO title contains power words.
 *
 * Power words are persuasive, emotional terms that can improve click-through
 * rates by making titles more compelling and attention-grabbing.
 *
 * ## Scoring
 * - **Pass:** Title contains at least one power word
 * - **Warning:** Title doesn't contain any power words
 *
 * This is a low-weight rule as it's a best practice, not a requirement.
 *
 * ## Power Word Categories
 * - **Exclusivity:** Ultimate, Complete, Exclusive, Definitive
 * - **Urgency:** Essential, Fast, Quick, Now
 * - **Value:** Free, Best, Top, Proven
 * - **Curiosity:** Secret, Amazing, Incredible
 * - **Trust:** Guaranteed, Proven, Effective
 *
 * ## Example
 * Good: "The Ultimate Guide to SEO" ✓ (contains "Ultimate")
 * Good: "10 Proven Tips for Better Rankings" ✓ (contains "Proven")
 * Could Improve: "SEO Tips for Beginners" (no power words)
 */
class TitleHasPowerWordRule implements RuleInterface
{
    /**
     * Power words by locale.
     *
     * @var array<string, array<int, string>>
     */
    protected array $powerWords = [
        'en' => [
            'ultimate',
            'complete',
            'essential',
            'proven',
            'guaranteed',
            'free',
            'best',
            'top',
            'amazing',
            'incredible',
            'easy',
            'simple',
            'quick',
            'fast',
            'secret',
            'powerful',
            'effective',
            'definitive',
            'comprehensive',
            'exclusive',
            // Additional high-impact words
            'new',
            'instant',
            'now',
            'step-by-step',
            'expert',
            'must-have',
            'breakthrough',
            'revealed',
            'shocking',
            'surprising',
        ],
        'it' => [
            'guida',
            'completa',
            'completo',
            'migliori',
            'migliore',
            'facile',
            'veloce',
            'gratis',
            'gratuito',
            'gratuita',
            'segreto',
            'segreti',
            'definitiva',
            'definitivo',
            'essenziale',
            'essenziali',
            // Additional Italian power words
            'incredibile',
            'efficace',
            'potente',
            'esclusivo',
            'esclusiva',
            'garantito',
            'garantita',
            'provato',
            'provata',
            'semplice',
            'rapido',
            'rapida',
            'nuovo',
            'nuova',
        ],
    ];

    public function getId(): string
    {
        return 'title_has_power_word';
    }

    public function getName(): string
    {
        return 'Title Contains Power Word';
    }

    public function getWeight(): int
    {
        return 3;
    }

    public function getCategory(): string
    {
        return 'meta';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        if (empty($context->title)) {
            return RuleResult::skip(
                $this->getId(),
                'No SEO title set.'
            );
        }

        $locale = $this->normalizeLocale($context->locale);
        $powerWords = $this->getPowerWords($locale);

        // Convert title to lowercase for matching
        $titleLower = mb_strtolower($context->title);

        // Check for power words
        $foundWords = [];
        foreach ($powerWords as $powerWord) {
            // Match whole words only
            $pattern = '/\b' . preg_quote($powerWord, '/') . '\b/ui';
            if (preg_match($pattern, $titleLower)) {
                $foundWords[] = $powerWord;
            }
        }

        if (! empty($foundWords)) {
            $wordList = implode(', ', array_slice($foundWords, 0, 3));
            $message = count($foundWords) === 1
                ? "Title contains a power word: \"{$wordList}\"."
                : "Title contains power words: {$wordList}.";

            return RuleResult::pass(
                $this->getId(),
                $message . ' This can improve click-through rates.',
                100
            );
        }

        // Get suggestion based on locale
        $suggestions = $this->getSuggestions($locale);

        return RuleResult::warning(
            $this->getId(),
            'Title does not contain any power words.',
            "Consider adding a power word to make your title more compelling. Examples: {$suggestions}",
            50,
            'No power words found',
            'Contains power word(s)'
        );
    }

    /**
     * Get power words for a locale.
     *
     * @return array<int, string>
     */
    protected function getPowerWords(string $locale): array
    {
        return $this->powerWords[$locale] ?? $this->powerWords['en'];
    }

    /**
     * Get suggestion examples for a locale.
     */
    protected function getSuggestions(string $locale): string
    {
        return match ($locale) {
            'it' => '"Guida Completa", "I Migliori", "Facile", "Gratis"',
            default => '"Ultimate", "Complete", "Essential", "Proven", "Free"',
        };
    }

    /**
     * Normalize locale to base language code.
     */
    protected function normalizeLocale(string $locale): string
    {
        $base = explode('_', str_replace('-', '_', $locale))[0];

        return in_array($base, array_keys($this->powerWords)) ? $base : 'en';
    }

    /**
     * Get all power words for a specific locale (useful for UI).
     *
     * @return array<int, string>
     */
    public function getAvailablePowerWords(string $locale = 'en'): array
    {
        return $this->getPowerWords($this->normalizeLocale($locale));
    }
}
