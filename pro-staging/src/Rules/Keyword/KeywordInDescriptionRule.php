<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears in the meta description.
 *
 * While meta descriptions aren't a direct ranking factor, they:
 * - Improve click-through rates when keyword matches search query
 * - Show keyword highlighted/bold in search results
 * - Help users understand page relevance
 *
 * ## How It Works
 * - Uses stemmed matching for accuracy
 * - Also checks synonyms if provided
 *
 * ## Example
 * Keyword: "content marketing"
 * Description: "Learn effective content marketing strategies..." → Pass ✓
 * Description: "Discover how to grow your business online." → Fail ✗
 */
class KeywordInDescriptionRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_description';
    }

    public function getName(): string
    {
        return 'Keyword in Meta Description';
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

        if (empty($context->description)) {
            return RuleResult::fail(
                $this->getId(),
                'No meta description set.',
                'Add a meta description that includes your focus keyword.',
                null,
                'Description with keyword'
            );
        }

        // Check if keyword or synonym appears in description
        if ($this->keywordOrSynonymInText($primaryKeyword, $context->description, $context->locale)) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword \"{$primaryKeyword['original']}\" appears in the meta description.",
                100
            );
        }

        return RuleResult::fail(
            $this->getId(),
            "Focus keyword \"{$primaryKeyword['original']}\" does not appear in the meta description.",
            "Include your focus keyword in the meta description. It will appear bold in search results when it matches the user's query.",
            mb_substr($context->description, 0, 60) . '...',
            "Description containing \"{$primaryKeyword['original']}\""
        );
    }
}
