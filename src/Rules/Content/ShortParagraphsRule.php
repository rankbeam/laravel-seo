<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Content;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\Tokenizer;

/**
 * Checks if paragraphs are kept at a readable length.
 *
 * Long paragraphs are intimidating and hard to read, especially on mobile.
 * Breaking content into shorter paragraphs improves scanning and engagement.
 *
 * ## Threshold
 * - **Max paragraph length:** 150 words
 *
 * ## Scoring
 * - **Pass:** All paragraphs under 150 words
 * - **Warning:** 1-2 paragraphs exceed limit
 * - **Fail:** 3+ paragraphs exceed limit
 *
 * ## Best Practices
 * - Keep paragraphs to 3-5 sentences
 * - One main idea per paragraph
 * - Use line breaks to create visual breathing room
 * - Consider bullet points for lists
 */
class ShortParagraphsRule implements RuleInterface
{
    /**
     * Maximum words per paragraph.
     */
    protected const MAX_WORDS = 150;

    /**
     * Warning threshold (number of long paragraphs).
     */
    protected const WARNING_THRESHOLD = 2;

    /**
     * Minimum paragraphs for analysis.
     */
    protected const MIN_PARAGRAPHS = 2;

    public function __construct(
        protected Tokenizer $tokenizer,
    ) {}

    public function getId(): string
    {
        return 'short_paragraphs';
    }

    public function getName(): string
    {
        return 'Paragraph Length';
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
        // Extract paragraphs from HTML
        $paragraphs = $this->extractParagraphs($context->htmlContent);

        if (count($paragraphs) < self::MIN_PARAGRAPHS) {
            return RuleResult::skip(
                $this->getId(),
                "Not enough paragraphs for analysis (minimum " . self::MIN_PARAGRAPHS . ")."
            );
        }

        // Analyze each paragraph
        $longParagraphs = [];
        foreach ($paragraphs as $index => $paragraph) {
            $wordCount = $this->tokenizer->countWords($paragraph);
            if ($wordCount > self::MAX_WORDS) {
                $longParagraphs[] = [
                    'index' => $index + 1,
                    'words' => $wordCount,
                    'text' => $paragraph,
                ];
            }
        }

        $longCount = count($longParagraphs);
        $totalParagraphs = count($paragraphs);

        // Determine result
        if ($longCount === 0) {
            return RuleResult::pass(
                $this->getId(),
                "All {$totalParagraphs} paragraphs are under " . self::MAX_WORDS . " words. Good readability!",
                100
            );
        }

        // Build details
        $details = [];
        if (! empty($longParagraphs)) {
            $details['long_paragraphs'] = array_map(function ($p) {
                $preview = mb_strlen($p['text']) > 60
                    ? mb_substr($p['text'], 0, 60) . '...'
                    : $p['text'];
                return "Paragraph {$p['index']}: {$p['words']} words";
            }, array_slice($longParagraphs, 0, 5));
        }

        if ($longCount <= self::WARNING_THRESHOLD) {
            return new RuleResult(
                ruleId: $this->getId(),
                status: 'warning',
                score: 60,
                message: "{$longCount} paragraph(s) exceed " . self::MAX_WORDS . " words.",
                recommendation: 'Break up long paragraphs into smaller chunks. Aim for 3-5 sentences per paragraph.',
                actualValue: "{$longCount} long paragraphs",
                expectedValue: '0 long paragraphs',
                details: $details,
            );
        }

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "{$longCount} paragraphs exceed " . self::MAX_WORDS . " words out of {$totalParagraphs} total.",
            recommendation: 'Break up long paragraphs into smaller, more digestible chunks. Consider using bullet points or subheadings.',
            actualValue: "{$longCount} long paragraphs",
            expectedValue: '0 long paragraphs',
            details: $details,
        );
    }

    /**
     * Extract paragraphs from HTML content.
     *
     * @return array<int, string>
     */
    protected function extractParagraphs(string $html): array
    {
        $paragraphs = [];

        // Match <p> tags
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $content) {
                $text = trim(strip_tags($content));
                if (! empty($text)) {
                    $paragraphs[] = $text;
                }
            }
        }

        // If no <p> tags, split by double line breaks
        if (empty($paragraphs)) {
            $text = strip_tags($html);
            $parts = preg_split('/\n\s*\n/', $text);
            foreach ($parts as $part) {
                $part = trim($part);
                if (! empty($part)) {
                    $paragraphs[] = $part;
                }
            }
        }

        return $paragraphs;
    }
}
