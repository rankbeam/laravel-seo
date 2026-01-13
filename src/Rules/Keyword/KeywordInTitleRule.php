<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears in the SEO title.
 *
 * This is the **highest-weighted rule** because the title tag is one of
 * the most important on-page SEO factors.
 *
 * ## Why It Matters
 * - Search engines use the title to understand page content
 * - Keywords in the title appear bold in search results when they match the query
 * - Strong correlation with rankings for target keywords
 *
 * ## How It Works
 * - Uses stemmed matching for accuracy
 * - Compares stemmed keyword against stemmed title
 * - Also checks synonyms if provided
 *
 * ## Example
 * Keyword: "content marketing"
 * Title: "10 Content Marketing Tips for 2024" → Pass ✓
 * Title: "10 Marketing Tips for Your Content" → Pass ✓ (words present)
 * Title: "10 Digital Strategy Tips" → Fail ✗
 */
class KeywordInTitleRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_title';
    }

    public function getName(): string
    {
        return 'Keyword in Title';
    }

    public function getWeight(): int
    {
        return 20; // Highest weight
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

        if (empty($context->title)) {
            return RuleResult::fail(
                $this->getId(),
                'No SEO title set.',
                'Add an SEO title that includes your focus keyword.',
                null,
                'Title with keyword'
            );
        }

        // Check if keyword or synonym appears in title
        if ($this->keywordOrSynonymInText($primaryKeyword, $context->title, $context->locale)) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword \"{$primaryKeyword['original']}\" appears in the SEO title.",
                100
            );
        }

        return RuleResult::fail(
            $this->getId(),
            "Focus keyword \"{$primaryKeyword['original']}\" does not appear in the SEO title.",
            "Include your focus keyword in the title. This is crucial for SEO.",
            $context->title,
            "Title containing \"{$primaryKeyword['original']}\""
        );
    }
}
