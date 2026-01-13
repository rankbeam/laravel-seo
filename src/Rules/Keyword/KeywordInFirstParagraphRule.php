<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears in the first paragraph.
 *
 * Introducing your keyword early in the content helps:
 * - Search engines quickly understand the topic
 * - Users immediately see the content is relevant
 * - Reinforce the connection between title and content
 *
 * ## How It Works
 * - Extracts the first `<p>` element from HTML content
 * - Falls back to first ~50 words if no `<p>` found
 * - Uses stemmed matching for accuracy
 *
 * ## Example
 * Keyword: "content marketing"
 * First paragraph: "Content marketing is essential for modern businesses..."
 * Result: Pass ✓
 */
class KeywordInFirstParagraphRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_first_paragraph';
    }

    public function getName(): string
    {
        return 'Keyword in First Paragraph';
    }

    public function getWeight(): int
    {
        return 10;
    }

    public function getCategory(): string
    {
        return 'keyword';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $primaryKeyword = $context->getPrimaryKeyword();

        if (! $primaryKeyword) {
            return $this->skipNoKeyword();
        }

        if (empty($context->content)) {
            return RuleResult::skip(
                $this->getId(),
                'No content available for analysis.'
            );
        }

        // Get the first paragraph
        $firstParagraph = $context->getFirstParagraph();

        if (empty($firstParagraph)) {
            return RuleResult::skip(
                $this->getId(),
                'Could not extract first paragraph from content.'
            );
        }

        // Check if keyword appears in first paragraph
        if ($this->keywordOrSynonymInText($primaryKeyword, $firstParagraph, $context->locale)) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword \"{$primaryKeyword['original']}\" appears in the first paragraph.",
                100
            );
        }

        // Provide context about the first paragraph
        $preview = mb_strlen($firstParagraph) > 80
            ? mb_substr($firstParagraph, 0, 80) . '...'
            : $firstParagraph;

        return RuleResult::fail(
            $this->getId(),
            "Focus keyword \"{$primaryKeyword['original']}\" does not appear in the first paragraph.",
            "Introduce your focus keyword in the opening paragraph to immediately signal relevance to readers and search engines.",
            "First paragraph: \"{$preview}\"",
            "First paragraph containing \"{$primaryKeyword['original']}\""
        );
    }
}
