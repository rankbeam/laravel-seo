<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if the HTML lang attribute is set and matches content.
 *
 * The lang attribute helps:
 * - Search engines understand the page language
 * - Screen readers pronounce content correctly
 * - Browsers offer translation when needed
 *
 * ## Scoring
 * - **Pass:** lang attribute set and matches content locale
 * - **Warning:** lang attribute set but doesn't match content
 * - **Fail:** lang attribute missing
 *
 * ## Best Practices
 * - Use standard language codes (en, en-US, it, de, etc.)
 * - Match the content's actual language
 * - Use region codes when relevant (en-US vs en-GB)
 */
class LangAttributeRule implements RuleInterface
{
    public function getId(): string
    {
        return 'lang_attribute';
    }

    public function getName(): string
    {
        return 'HTML Lang Attribute';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'technical';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $htmlLang = $context->htmlLang ?? '';
        $contentLocale = $context->locale;

        // Missing lang attribute
        if (empty($htmlLang)) {
            return RuleResult::fail(
                $this->getId(),
                'HTML lang attribute is missing.',
                'Add a lang attribute to the <html> tag (e.g., <html lang="en">). This helps search engines and screen readers understand your content.',
                'Not set',
                'lang="' . $contentLocale . '"'
            );
        }

        // Normalize both for comparison
        $normalizedHtmlLang = $this->normalizeLocale($htmlLang);
        $normalizedContentLocale = $this->normalizeLocale($contentLocale);

        // Check if they match (base language)
        if ($normalizedHtmlLang === $normalizedContentLocale) {
            return RuleResult::pass(
                $this->getId(),
                "HTML lang attribute ({$htmlLang}) matches content locale.",
                100
            );
        }

        // Lang set but doesn't match content
        return RuleResult::warning(
            $this->getId(),
            "HTML lang attribute ({$htmlLang}) doesn't match content locale ({$contentLocale}).",
            "Update the lang attribute to match your content's language, or verify the content locale is correct.",
            70,
            "lang=\"{$htmlLang}\"",
            "lang=\"{$contentLocale}\""
        );
    }

    /**
     * Normalize locale to base language for comparison.
     *
     * Examples:
     * - en-US → en
     * - en_US → en
     * - pt-BR → pt
     */
    protected function normalizeLocale(string $locale): string
    {
        // Replace underscore with hyphen for consistency
        $locale = str_replace('_', '-', $locale);

        // Extract base language
        $parts = explode('-', $locale);

        return strtolower($parts[0]);
    }
}
