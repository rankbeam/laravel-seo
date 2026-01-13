<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Keyword;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Rules\AbstractRule;

/**
 * Checks if the focus keyword appears in the URL slug.
 *
 * URLs containing relevant keywords help search engines understand
 * page content and can improve click-through rates.
 *
 * ## How It Works
 * - Extracts the slug from the URL (last path segment)
 * - Normalizes by converting hyphens to spaces
 * - Uses stemmed matching for comparison
 *
 * ## Example
 * URL: `https://example.com/blog/seo-tips-beginners`
 * Slug normalized: "seo tips beginners"
 * Keyword: "SEO tips" → Pass ✓
 *
 * URL: `https://example.com/blog/post-12345`
 * Keyword: "SEO tips" → Fail ✗
 */
class KeywordInUrlRule extends AbstractRule
{
    public function getId(): string
    {
        return 'keyword_in_url';
    }

    public function getName(): string
    {
        return 'Keyword in URL';
    }

    public function getWeight(): int
    {
        return 15;
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

        if (empty($context->url)) {
            return RuleResult::skip(
                $this->getId(),
                'No URL available for analysis.'
            );
        }

        // Extract and normalize the slug
        $slug = $this->extractSlug($context->url);

        if (empty($slug)) {
            return RuleResult::skip(
                $this->getId(),
                'Could not extract slug from URL.'
            );
        }

        // Normalize slug (hyphens/underscores to spaces)
        $normalizedSlug = $this->normalizeSlug($slug);

        // Check if keyword appears in slug
        if ($this->keywordOrSynonymInText($primaryKeyword, $normalizedSlug, $context->locale)) {
            return RuleResult::pass(
                $this->getId(),
                "Focus keyword appears in the URL slug.",
                100
            );
        }

        return RuleResult::fail(
            $this->getId(),
            "Focus keyword \"{$primaryKeyword['original']}\" does not appear in the URL.",
            "Include your focus keyword in the URL slug for better SEO. Consider updating the URL to include \"{$this->keywordToSlug($primaryKeyword['original'])}\".",
            $slug,
            "Slug containing keyword"
        );
    }

    /**
     * Extract the slug (last path segment) from a URL.
     */
    protected function extractSlug(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (empty($path) || $path === '/') {
            return '';
        }

        // Remove trailing slash and get last segment
        $path = rtrim($path, '/');
        $segments = explode('/', $path);

        return end($segments);
    }

    /**
     * Normalize a slug by replacing separators with spaces.
     */
    protected function normalizeSlug(string $slug): string
    {
        // Replace hyphens and underscores with spaces
        $normalized = str_replace(['-', '_'], ' ', $slug);

        // Remove file extensions
        $normalized = preg_replace('/\.[a-z]+$/i', '', $normalized);

        return mb_strtolower(trim($normalized));
    }

    /**
     * Convert keyword to slug format for suggestion.
     */
    protected function keywordToSlug(string $keyword): string
    {
        $slug = mb_strtolower($keyword);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', $slug);

        return trim($slug, '-');
    }
}
