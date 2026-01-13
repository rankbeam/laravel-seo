<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Data;

/**
 * Context object containing all data needed for SEO analysis.
 *
 * This is passed to each rule during analysis and contains
 * pre-processed content data for efficient analysis.
 */
final class AnalysisContext
{
    /**
     * @param  string  $title  The SEO title
     * @param  string  $description  The meta description
     * @param  string  $content  Plain text content (HTML stripped)
     * @param  string  $htmlContent  Original HTML content
     * @param  array<int, string>  $tokens  Tokenized words from content
     * @param  array<int, string>  $stemmedTokens  Stemmed version of tokens
     * @param  array<int, array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>}>  $focusKeywords  Focus keywords with stems
     * @param  array<string, array<int, string>>  $headings  Headings by level ['h1' => [...], 'h2' => [...]]
     * @param  array<int, array{url: string, text: string, is_external: bool, is_nofollow: bool}>  $links  Extracted links
     * @param  array<int, array{src: string, alt: ?string, width: ?int, height: ?int}>  $images  Extracted images
     * @param  int  $wordCount  Total word count
     * @param  int  $sentenceCount  Total sentence count
     * @param  int  $paragraphCount  Total paragraph count
     * @param  string  $locale  Content locale for language-specific analysis
     * @param  string|null  $url  The page URL (for URL-based checks)
     * @param  string|null  $robots  The robots meta value
     * @param  string|null  $canonical  The canonical URL
     * @param  string|null  $ogImage  The Open Graph image URL
     * @param  string|null  $htmlLang  The HTML lang attribute
     * @param  string|null  $headHtml  The <head> section HTML
     * @param  bool|null  $ogImageBroken  Result of async OG image validation
     * @param  array<int, array{url: string, status: int}>|null  $brokenLinks  Results of async link validation
     * @param  array<int, array{src: string, status: int}>|null  $brokenImages  Results of async image validation
     */
    public function __construct(
        // Core content
        public readonly string $title,
        public readonly string $description,
        public readonly string $content,
        public readonly string $htmlContent,

        // Pre-processed tokens
        public readonly array $tokens,
        public readonly array $stemmedTokens,

        // Keywords
        public readonly array $focusKeywords,

        // Extracted structure
        public readonly array $headings,
        public readonly array $links,
        public readonly array $images,

        // Metrics
        public readonly int $wordCount,
        public readonly int $sentenceCount,
        public readonly int $paragraphCount,

        // Locale
        public readonly string $locale,

        // Technical SEO fields
        public readonly ?string $url = null,
        public readonly ?string $robots = null,
        public readonly ?string $canonical = null,
        public readonly ?string $ogImage = null,
        public readonly ?string $htmlLang = null,
        public readonly ?string $headHtml = null,

        // Async validation results
        public readonly ?bool $ogImageBroken = null,
        public readonly ?array $brokenLinks = null,
        public readonly ?array $brokenImages = null,
    ) {}

    /**
     * Get the primary focus keyword.
     *
     * @return array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>}|null
     */
    public function getPrimaryKeyword(): ?array
    {
        foreach ($this->focusKeywords as $keyword) {
            if ($keyword['is_primary'] ?? false) {
                return $keyword;
            }
        }

        return $this->focusKeywords[0] ?? null;
    }

    /**
     * Get secondary (non-primary) keywords.
     *
     * @return array<int, array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>}>
     */
    public function getSecondaryKeywords(): array
    {
        return array_filter(
            $this->focusKeywords,
            fn($k) => ! ($k['is_primary'] ?? false)
        );
    }

    /**
     * Check if any focus keywords are set.
     */
    public function hasKeywords(): bool
    {
        return ! empty($this->focusKeywords);
    }

    /**
     * Count occurrences of a stemmed keyword in the content.
     */
    public function countKeywordOccurrences(string $stemmedKeyword): int
    {
        return count(array_filter(
            $this->stemmedTokens,
            fn($stem) => $stem === $stemmedKeyword
        ));
    }

    /**
     * Get H1 headings.
     *
     * @return array<int, string>
     */
    public function getH1Headings(): array
    {
        return $this->headings['h1'] ?? [];
    }

    /**
     * Get H2 headings.
     *
     * @return array<int, string>
     */
    public function getH2Headings(): array
    {
        return $this->headings['h2'] ?? [];
    }

    /**
     * Get internal links.
     *
     * @return array<int, array{url: string, text: string, is_external: bool, is_nofollow: bool}>
     */
    public function getInternalLinks(): array
    {
        return array_filter($this->links, fn($link) => ! $link['is_external']);
    }

    /**
     * Get external links.
     *
     * @return array<int, array{url: string, text: string, is_external: bool, is_nofollow: bool}>
     */
    public function getExternalLinks(): array
    {
        return array_filter($this->links, fn($link) => $link['is_external']);
    }

    /**
     * Get images missing alt text.
     *
     * @return array<int, array{src: string, alt: ?string, width: ?int, height: ?int}>
     */
    public function getImagesMissingAlt(): array
    {
        return array_filter($this->images, fn($img) => empty($img['alt']));
    }

    /**
     * Get the first paragraph text.
     */
    public function getFirstParagraph(): string
    {
        // Try to extract first <p> from HTML
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $this->htmlContent, $matches)) {
            return strip_tags($matches[1]);
        }

        // Fallback: first ~200 characters of content
        $words = explode(' ', $this->content);

        return implode(' ', array_slice($words, 0, 50));
    }

    /**
     * Split content into sections (for distribution checks).
     *
     * @param  int  $parts  Number of parts to split into
     * @return array<int, string>
     */
    public function splitContent(int $parts = 3): array
    {
        $words = $this->tokens;
        $chunkSize = max(1, (int) ceil(count($words) / $parts));

        $chunks = array_chunk($words, $chunkSize);

        return array_map(fn($chunk) => implode(' ', $chunk), $chunks);
    }
}
