<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Media;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the primary keyword appears in any image alt text.
 *
 * Having your focus keyword in at least one image alt tag:
 * - Reinforces topic relevance
 * - Helps images rank in image search
 * - Provides additional keyword context
 *
 * ## Scoring
 * - **Pass:** Keyword found in at least one image alt
 * - **Warning:** Keyword not found in any image alt
 * - **Skip:** No images or no keyword set
 *
 * ## Best Practices
 * - Include keyword naturally in the most relevant image
 * - Don't keyword-stuff all alt tags
 * - Make alt text descriptive first, keyword-rich second
 */
class KeywordInImageAltRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_image_alt';
    }

    public function getName(): string
    {
        return 'Keyword in Image Alt';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Skip if no keyword
        if (empty($context->primaryKeyword)) {
            return $this->skipNoKeyword();
        }

        // Extract image alt texts
        $altTexts = $this->extractAltTexts($context->htmlContent);

        if (empty($altTexts)) {
            return RuleResult::skip(
                $this->getId(),
                'No images with alt text found in content.'
            );
        }

        // Check each alt text for keyword
        foreach ($altTexts as $altText) {
            if ($this->keywordOrSynonymInText($context, $altText)) {
                return RuleResult::pass(
                    $this->getId(),
                    "Primary keyword found in image alt text.",
                    100
                );
            }
        }

        return RuleResult::warning(
            $this->getId(),
            "Primary keyword \"{$context->primaryKeyword}\" not found in any image alt text.",
            'Add the focus keyword to at least one relevant image alt tag. This helps reinforce topic relevance and improves image search visibility.',
            50,
            'Keyword not in alt tags',
            'Keyword in 1+ alt tag'
        );
    }

    /**
     * Extract all alt texts from images.
     *
     * @return array<int, string>
     */
    protected function extractAltTexts(string $html): array
    {
        $altTexts = [];

        if (preg_match_all('/<img[^>]+alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $alt) {
                $alt = trim($alt);
                if (! empty($alt)) {
                    $altTexts[] = $alt;
                }
            }
        }

        return $altTexts;
    }
}
