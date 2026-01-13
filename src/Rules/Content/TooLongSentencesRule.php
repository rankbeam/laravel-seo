<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\Tokenizer;

/**
 * Checks if the content has too many long sentences.
 *
 * Long sentences are harder to read and understand. Keeping sentences
 * concise improves readability and user engagement.
 *
 * ## Thresholds
 * - **Max sentence length:** 20 words
 * - **Pass:** <25% of sentences exceed limit
 * - **Warning:** 25-40% exceed limit
 * - **Fail:** >40% exceed limit
 *
 * ## Best Practices
 * - Vary sentence length for rhythm
 * - Break complex ideas into multiple sentences
 * - Use periods instead of commas when appropriate
 * - Target 15-20 words per sentence on average
 */
class TooLongSentencesRule implements RuleInterface
{
    /**
     * Maximum words per sentence.
     */
    protected const MAX_WORDS = 20;

    /**
     * Percentage thresholds.
     */
    protected const PASS_THRESHOLD = 25;
    protected const WARNING_THRESHOLD = 40;

    /**
     * Minimum sentences for analysis.
     */
    protected const MIN_SENTENCES = 5;

    /**
     * Maximum examples to include in details.
     */
    protected const MAX_EXAMPLES = 3;

    public function __construct(
        protected Tokenizer $tokenizer,
    ) {}

    public function getId(): string
    {
        return 'too_long_sentences';
    }

    public function getName(): string
    {
        return 'Sentence Length';
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
        // Split into sentences
        $sentences = $this->tokenizer->splitSentences($context->content);
        $totalSentences = count($sentences);

        if ($totalSentences < self::MIN_SENTENCES) {
            return RuleResult::skip(
                $this->getId(),
                "Content too short for sentence length analysis (minimum " . self::MIN_SENTENCES . " sentences)."
            );
        }

        // Analyze each sentence
        $longSentences = [];
        foreach ($sentences as $sentence) {
            $wordCount = $this->tokenizer->countWords($sentence);
            if ($wordCount > self::MAX_WORDS) {
                $longSentences[] = [
                    'text' => $sentence,
                    'words' => $wordCount,
                ];
            }
        }

        $longCount = count($longSentences);
        $percentage = round(($longCount / $totalSentences) * 100, 1);

        // Build details with examples
        $details = [];
        if (! empty($longSentences)) {
            $examples = array_slice($longSentences, 0, self::MAX_EXAMPLES);
            $details['examples'] = array_map(function ($s) {
                $preview = mb_strlen($s['text']) > 80
                    ? mb_substr($s['text'], 0, 80) . '...'
                    : $s['text'];
                return "{$s['words']} words: \"{$preview}\"";
            }, $examples);
        }

        // Determine result
        if ($percentage < self::PASS_THRESHOLD) {
            return RuleResult::pass(
                $this->getId(),
                "Good sentence lengths: only {$percentage}% of sentences exceed " . self::MAX_WORDS . " words ({$longCount}/{$totalSentences}).",
                100
            );
        }

        if ($percentage <= self::WARNING_THRESHOLD) {
            return new RuleResult(
                ruleId: $this->getId(),
                status: 'warning',
                score: 60,
                message: "Some sentences are too long: {$percentage}% exceed " . self::MAX_WORDS . " words ({$longCount}/{$totalSentences}).",
                recommendation: 'Break up long sentences to improve readability. Aim for an average of 15-20 words per sentence.',
                actualValue: "{$percentage}%",
                expectedValue: '<' . self::PASS_THRESHOLD . '%',
                details: $details,
            );
        }

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "Too many long sentences: {$percentage}% exceed " . self::MAX_WORDS . " words ({$longCount}/{$totalSentences}).",
            recommendation: 'Significantly reduce sentence length. Break complex sentences into shorter ones for better readability.',
            actualValue: "{$percentage}%",
            expectedValue: '<' . self::PASS_THRESHOLD . '%',
            details: $details,
        );
    }
}
