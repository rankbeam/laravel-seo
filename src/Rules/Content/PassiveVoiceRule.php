<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\Tokenizer;

/**
 * Checks if the content uses too much passive voice.
 *
 * Active voice is generally more engaging, direct, and easier to read.
 * Too much passive voice can make content feel distant and harder to follow.
 *
 * ## Thresholds
 * - **Pass:** <10% passive voice sentences
 * - **Warning:** 10-20% passive voice
 * - **Fail:** >20% passive voice
 *
 * ## Detection (English only)
 * Passive voice pattern: (is|are|was|were|been|being|be) + past participle
 * Past participles typically end in -ed, -en, -t, -n
 *
 * ## Examples
 * - Passive: "The article was written by John"
 * - Active: "John wrote the article"
 * - Passive: "Mistakes were made"
 * - Active: "We made mistakes"
 *
 * ## Locale Support
 * Currently only supports English. Other locales skip this check.
 */
class PassiveVoiceRule implements RuleInterface
{
    /**
     * Percentage thresholds.
     */
    protected const PASS_THRESHOLD = 10;
    protected const WARNING_THRESHOLD = 20;

    /**
     * Minimum sentences for analysis.
     */
    protected const MIN_SENTENCES = 5;

    /**
     * Supported locales (English only for now).
     *
     * @var array<int, string>
     */
    protected array $supportedLocales = ['en', 'en_US', 'en_GB', 'en_AU', 'en_CA'];

    public function __construct(
        protected Tokenizer $tokenizer,
    ) {}

    public function getId(): string
    {
        return 'passive_voice';
    }

    public function getName(): string
    {
        return 'Passive Voice Usage';
    }

    public function getWeight(): int
    {
        return 3;
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Only supported for English
        $normalizedLocale = explode('_', $context->locale)[0];
        if ($normalizedLocale !== 'en') {
            return RuleResult::skip(
                $this->getId(),
                'Passive voice detection is only available for English content.'
            );
        }

        // Split into sentences
        $sentences = $this->tokenizer->splitSentences($context->content);
        $totalSentences = count($sentences);

        if ($totalSentences < self::MIN_SENTENCES) {
            return RuleResult::skip(
                $this->getId(),
                "Content too short for passive voice analysis (minimum " . self::MIN_SENTENCES . " sentences)."
            );
        }

        // Detect passive voice sentences
        $passiveSentences = [];
        foreach ($sentences as $sentence) {
            if ($this->isPassiveVoice($sentence)) {
                $passiveSentences[] = $sentence;
            }
        }

        $passiveCount = count($passiveSentences);
        $percentage = round(($passiveCount / $totalSentences) * 100, 1);

        // Build details with examples
        $details = [];
        if (! empty($passiveSentences)) {
            $examples = array_slice($passiveSentences, 0, 3);
            $details['examples'] = array_map(function ($s) {
                return mb_strlen($s) > 80 ? mb_substr($s, 0, 80) . '...' : $s;
            }, $examples);
        }

        // Determine result
        if ($percentage < self::PASS_THRESHOLD) {
            return RuleResult::pass(
                $this->getId(),
                "Good use of active voice: only {$percentage}% passive sentences ({$passiveCount}/{$totalSentences}).",
                100
            );
        }

        if ($percentage <= self::WARNING_THRESHOLD) {
            return new RuleResult(
                ruleId: $this->getId(),
                status: 'warning',
                score: 60,
                message: "Moderate passive voice usage: {$percentage}% of sentences ({$passiveCount}/{$totalSentences}).",
                recommendation: 'Try to convert some passive voice sentences to active voice. Example: "The report was written by the team" → "The team wrote the report".',
                actualValue: "{$percentage}%",
                expectedValue: '<' . self::PASS_THRESHOLD . '%',
                details: $details,
            );
        }

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "High passive voice usage: {$percentage}% of sentences ({$passiveCount}/{$totalSentences}).",
            recommendation: 'Significantly reduce passive voice. Active voice is more engaging and direct. Rewrite sentences to put the subject first.',
            actualValue: "{$percentage}%",
            expectedValue: '<' . self::PASS_THRESHOLD . '%',
            details: $details,
        );
    }

    /**
     * Check if a sentence contains passive voice.
     *
     * Pattern: (is|are|was|were|been|being|be) + past participle
     */
    protected function isPassiveVoice(string $sentence): bool
    {
        // Common passive voice patterns
        $patterns = [
            // be + past participle (commonly ending in -ed)
            '/\b(is|are|was|were|been|being|be|am)\s+(\w+ed)\b/i',

            // be + irregular past participles
            '/\b(is|are|was|were|been|being|be|am)\s+(written|made|done|taken|given|shown|known|seen|found|told|thought|brought|bought|caught|taught|felt|left|lost|sent|spent|built|meant|kept|held|led|read|said|paid|put|run|set|shut|cut|hit|let|hurt|cost|become|begun|chosen|come|drawn|driven|eaten|fallen|flown|forgotten|forgiven|frozen|gotten|grown|hidden|known|lain|risen|ridden|rung|seen|shaken|shrunk|sung|sunk|spoken|stolen|sworn|swum|thrown|woken|worn|won|written)\b/i',

            // get + past participle (passive-like construction)
            '/\b(get|gets|got|gotten|getting)\s+(\w+ed|written|made|done|taken|given|shown|known|seen|found|told|hurt)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sentence)) {
                return true;
            }
        }

        return false;
    }
}
