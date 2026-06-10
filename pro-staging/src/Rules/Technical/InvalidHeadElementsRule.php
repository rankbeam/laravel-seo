<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks for invalid elements in the HTML <head>.
 *
 * **CRITICAL:** Google stops parsing the <head> when it encounters
 * an invalid element. This means any SEO tags after the invalid
 * element will be ignored!
 *
 * ## Valid Head Elements (per HTML spec)
 * - title, base, link, meta, style, script, noscript, template
 *
 * ## Common Invalid Elements
 * - div, span, img, a, p, br, hr
 * - Text nodes (non-whitespace)
 * - Custom elements/components
 *
 * ## Scoring
 * - **Pass:** Only valid elements in head
 * - **Fail:** Any invalid element found
 *
 * ## Reference
 * https://developer.mozilla.org/en-US/docs/Web/HTML/Element/head
 */
class InvalidHeadElementsRule implements RuleInterface
{
    /**
     * Valid elements allowed in <head>.
     *
     * @var array<int, string>
     */
    protected array $validElements = [
        'title',
        'base',
        'link',
        'meta',
        'style',
        'script',
        'noscript',
        'template',
    ];

    public function getId(): string
    {
        return 'invalid_head_elements';
    }

    public function getName(): string
    {
        return 'Invalid Head Elements';
    }

    public function getWeight(): int
    {
        return 10;
    }

    public function getCategory(): string
    {
        return 'technical';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $headHtml = $context->headHtml ?? '';

        if (empty($headHtml)) {
            return RuleResult::skip(
                $this->getId(),
                'No head HTML available for analysis.'
            );
        }

        // Remove template content (can contain anything)
        $cleanHead = $this->removeTemplateContent($headHtml);

        // Remove script content (can contain HTML-like strings)
        $cleanHead = $this->removeScriptContent($cleanHead);

        // Remove style content
        $cleanHead = $this->removeStyleContent($cleanHead);

        // Find all HTML tags
        $invalidElements = $this->findInvalidElements($cleanHead);

        if (empty($invalidElements)) {
            return RuleResult::pass(
                $this->getId(),
                'All elements in <head> are valid.',
                100
            );
        }

        $uniqueInvalid = array_unique($invalidElements);
        $count = count($invalidElements);

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "Found {$count} invalid element(s) in <head>. Google stops parsing head on invalid elements!",
            recommendation: 'Remove all non-metadata elements from the <head>. Only title, meta, link, script, style, noscript, base, and template are allowed. Move other content to <body>.',
            actualValue: implode(', ', $uniqueInvalid),
            expectedValue: 'Only valid head elements',
            details: [
                'invalid_elements' => array_slice($uniqueInvalid, 0, 10),
                'warning' => 'Any SEO tags after invalid elements may be ignored by Google!',
            ],
        );
    }

    /**
     * Remove <template> content (can contain anything).
     */
    protected function removeTemplateContent(string $html): string
    {
        return preg_replace('/<template[^>]*>.*?<\/template>/is', '', $html) ?? $html;
    }

    /**
     * Remove <script> content (can contain HTML-like strings).
     */
    protected function removeScriptContent(string $html): string
    {
        return preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html) ?? $html;
    }

    /**
     * Remove <style> content.
     */
    protected function removeStyleContent(string $html): string
    {
        return preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html) ?? $html;
    }

    /**
     * Find all invalid elements in the cleaned head HTML.
     *
     * @return array<int, string>
     */
    protected function findInvalidElements(string $html): array
    {
        $invalid = [];

        // Match opening tags (self-closing and regular)
        if (preg_match_all('/<([a-z][a-z0-9]*)[^>]*\/?>/i', $html, $matches)) {
            foreach ($matches[1] as $tagName) {
                $tagName = strtolower($tagName);
                if (! in_array($tagName, $this->validElements, true)) {
                    $invalid[] = "<{$tagName}>";
                }
            }
        }

        // Check for non-whitespace text outside of valid elements
        // Remove all valid tags and check what's left
        $textOnly = preg_replace('/<[^>]+>/', '', $html);
        $textOnly = trim($textOnly ?? '');
        if (! empty($textOnly) && preg_match('/\S/', $textOnly)) {
            $invalid[] = '(text content)';
        }

        return $invalid;
    }
}
