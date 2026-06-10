<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\TransitionWords;

/**
 * Checks if the content uses enough transition words.
 *
 * Transition words help readers follow the flow of your content by
 * signaling relationships between ideas (e.g., "however", "therefore",
 * "for example").
 *
 * ## Target
 * - **Pass:** 30%+ of sentences contain transition words
 * - **Warning:** 20-30% of sentences
 * - **Fail:** <20% of sentences
 *
 * ## Locale Support
 * - English: ~120 transition words/phrases
 * - Italian: ~80 transition words/phrases
 *
 * ## Categories
 * - Addition: also, furthermore, moreover
 * - Contrast: however, although, despite
 * - Cause/Effect: therefore, consequently, because
 * - Time: first, then, finally
 * - Example: for example, specifically, such as
 * - Conclusion: in conclusion, to summarize
 */
class TransitionWordsRule implements RuleInterface
{
    /**
     * Target percentage of sentences with transitions.
     */
    protected const TARGET_PERCENTAGE = 30;
    protected const WARNING_PERCENTAGE = 20;

    /**
     * Minimum sentences for analysis.
     */
    protected const MIN_SENTENCES = 5;

    public function __construct(
        protected TransitionWords $transitionWords,
    ) {}

    public function getId(): string
    {
        return 'transition_words';
    }

    public function getName(): string
    {
        return 'Transition Words Usage';
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
        // Need minimum sentences for analysis
        if ($context->sentenceCount < self::MIN_SENTENCES) {
            return RuleResult::skip(
                $this->getId(),
                "Content too short for transition words analysis (minimum " . self::MIN_SENTENCES . " sentences)."
            );
        }

        // Analyze sentences for transition words
        $analysis = $this->transitionWords->analyzeSentences($context->content, $context->locale);

        $percentage = $analysis['percentage'];
        $sentencesWithTransitions = $analysis['sentences_with_transitions'];
        $totalSentences = $analysis['total_sentences'];

        // Determine result
        if ($percentage >= self::TARGET_PERCENTAGE) {
            return RuleResult::pass(
                $this->getId(),
                "Good use of transition words: {$percentage}% of sentences contain transitions ({$sentencesWithTransitions}/{$totalSentences}).",
                100
            );
        }

        if ($percentage >= self::WARNING_PERCENTAGE) {
            return RuleResult::warning(
                $this->getId(),
                "Transition words usage could be improved: {$percentage}% ({$sentencesWithTransitions}/{$totalSentences} sentences).",
                $this->getRecommendation($context->locale),
                60,
                "{$percentage}%",
                self::TARGET_PERCENTAGE . '%+'
            );
        }

        return RuleResult::fail(
            $this->getId(),
            "Low transition words usage: {$percentage}% ({$sentencesWithTransitions}/{$totalSentences} sentences).",
            $this->getRecommendation($context->locale),
            "{$percentage}%",
            self::TARGET_PERCENTAGE . '%+'
        );
    }

    /**
     * Get locale-specific recommendation.
     */
    protected function getRecommendation(string $locale): string
    {
        $normalizedLocale = explode('_', $locale)[0];

        return match ($normalizedLocale) {
            'it' => 'Aggiungi parole di transizione per migliorare il flusso. Esempi: "inoltre", "tuttavia", "quindi", "per esempio", "infine".',
            default => 'Add transition words to improve content flow. Examples: "however", "therefore", "for example", "additionally", "in conclusion".',
        };
    }
}
