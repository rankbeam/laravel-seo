<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Analyzer;

use Illuminate\Database\Eloquent\Model;
use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\AnalysisReport;
use Fibonoir\LaravelSEO\Data\RuleResult;
use Fibonoir\LaravelSEO\Support\Stemmer;
use Fibonoir\LaravelSEO\Support\StopWords;
use Fibonoir\LaravelSEO\Support\Tokenizer;

/**
 * Orchestrates the SEO content analysis process.
 *
 * Runs all registered rules against the content and produces
 * a weighted score with detailed results.
 */
class ContentAnalyzer
{
    /**
     * @var array<string, RuleInterface>
     */
    protected array $rules = [];

    public function __construct(
        protected Stemmer $stemmer,
        protected Tokenizer $tokenizer,
        protected StopWords $stopWords,
    ) {
        $this->loadRules();
    }

    /**
     * Analyze a model and return a report.
     */
    public function analyze(Model $model, ?string $locale = null): AnalysisReport
    {
        $locale ??= app()->getLocale();

        $context = $this->buildContext($model, $locale);
        $results = $this->runRules($context);
        $weights = $this->getRuleWeights();

        return AnalysisReport::fromResults($results, $weights, $locale);
    }

    /**
     * Analyze raw content without a model.
     *
     * @param  array<string, mixed>  $seoData
     */
    public function analyzeContent(
        string $htmlContent,
        array $seoData,
        ?string $locale = null,
    ): AnalysisReport {
        $locale ??= app()->getLocale();

        $context = $this->buildContextFromData($htmlContent, $seoData, $locale);
        $results = $this->runRules($context);
        $weights = $this->getRuleWeights();

        return AnalysisReport::fromResults($results, $weights, $locale);
    }

    /**
     * Build analysis context from a model.
     */
    public function buildContext(Model $model, string $locale): AnalysisContext
    {
        // Get content
        $htmlContent = method_exists($model, 'getContentForSEO')
            ? $model->getContentForSEO()
            : ($model->content ?? $model->body ?? '');

        $plainContent = $this->htmlToText($htmlContent);

        // Get SEO data
        $seoMeta = $model->seoMeta ?? null;
        $title = $seoMeta?->title ?? (method_exists($model, 'getSEOTitle') ? $model->getSEOTitle() : null) ?? '';
        $description = $seoMeta?->description ?? (method_exists($model, 'getSEODescription') ? $model->getSEODescription() : null) ?? '';

        // Tokenize and stem
        $tokens = $this->tokenizer->tokenize($plainContent);
        $stemmedTokens = $this->stemmer->stemBatch($tokens, $locale);

        // Prepare keywords
        $focusKeywords = $this->prepareKeywords($seoMeta?->focus_keywords ?? [], $locale);

        // Parse HTML structure
        $headings = $this->extractHeadings($htmlContent);
        $links = $this->extractLinks($htmlContent);
        $images = $this->extractImages($htmlContent);

        // Count metrics
        $wordCount = count($tokens);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $plainContent));
        $paragraphCount = max(1, preg_match_all('/<p\b[^>]*>/i', $htmlContent) ?: substr_count($plainContent, "\n\n") + 1);

        return new AnalysisContext(
            title: $title,
            description: $description,
            content: $plainContent,
            htmlContent: $htmlContent,
            tokens: $tokens,
            stemmedTokens: $stemmedTokens,
            focusKeywords: $focusKeywords,
            headings: $headings,
            links: $links,
            images: $images,
            wordCount: $wordCount,
            sentenceCount: $sentenceCount,
            paragraphCount: $paragraphCount,
            locale: $locale,
            url: method_exists($model, 'getUrlForSEO') ? $model->getUrlForSEO() : null,
            robots: $seoMeta?->robots,
            canonical: $seoMeta?->canonical,
            ogImage: $seoMeta?->og_image,
        );
    }

    /**
     * Build context from raw data.
     *
     * @param  array<string, mixed>  $seoData
     */
    protected function buildContextFromData(string $htmlContent, array $seoData, string $locale): AnalysisContext
    {
        $plainContent = $this->htmlToText($htmlContent);
        $tokens = $this->tokenizer->tokenize($plainContent);
        $stemmedTokens = $this->stemmer->stemBatch($tokens, $locale);
        $focusKeywords = $this->prepareKeywords($seoData['focus_keywords'] ?? [], $locale);

        return new AnalysisContext(
            title: $seoData['title'] ?? '',
            description: $seoData['description'] ?? '',
            content: $plainContent,
            htmlContent: $htmlContent,
            tokens: $tokens,
            stemmedTokens: $stemmedTokens,
            focusKeywords: $focusKeywords,
            headings: $this->extractHeadings($htmlContent),
            links: $this->extractLinks($htmlContent),
            images: $this->extractImages($htmlContent),
            wordCount: count($tokens),
            sentenceCount: max(1, preg_match_all('/[.!?]+/', $plainContent)),
            paragraphCount: 1,
            locale: $locale,
            url: $seoData['url'] ?? null,
            robots: $seoData['robots'] ?? null,
            canonical: $seoData['canonical'] ?? null,
            ogImage: $seoData['og_image'] ?? null,
        );
    }

    /**
     * Convert HTML to plain text.
     */
    protected function htmlToText(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        // Convert to text
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Prepare keywords with stems.
     *
     * @param  array<int, array<string, mixed>>  $keywords
     * @return array<int, array{original: string, stemmed: string, is_primary: bool, synonyms?: array<int, string>}>
     */
    protected function prepareKeywords(array $keywords, string $locale): array
    {
        $prepared = [];

        foreach ($keywords as $keyword) {
            $prepared[] = [
                'original' => $keyword['keyword'] ?? '',
                'stemmed' => $this->stemmer->stemPhrase($keyword['keyword'] ?? '', $locale),
                'is_primary' => $keyword['is_primary'] ?? false,
                'synonyms' => $keyword['synonyms'] ?? [],
            ];
        }

        return $prepared;
    }

    /**
     * Extract headings from HTML.
     *
     * @return array<string, array<int, string>>
     */
    protected function extractHeadings(string $html): array
    {
        $headings = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];

        foreach (array_keys($headings) as $tag) {
            preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $html, $matches);
            $headings[$tag] = array_map(fn ($m) => strip_tags($m), $matches[1] ?? []);
        }

        return $headings;
    }

    /**
     * Extract links from HTML.
     *
     * @return array<int, array{url: string, text: string, is_external: bool, is_nofollow: bool}>
     */
    protected function extractLinks(string $html): array
    {
        $links = [];
        preg_match_all('/<a\s+([^>]*)>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes = $match[1];
            $text = strip_tags($match[2]);

            // Extract href
            if (! preg_match('/href=["\']([^"\']+)["\']/', $attributes, $hrefMatch)) {
                continue;
            }

            $url = $hrefMatch[1];
            $isExternal = $this->isExternalUrl($url);
            $isNofollow = str_contains(strtolower($attributes), 'nofollow');

            $links[] = [
                'url' => $url,
                'text' => $text,
                'is_external' => $isExternal,
                'is_nofollow' => $isNofollow,
            ];
        }

        return $links;
    }

    /**
     * Extract images from HTML.
     *
     * @return array<int, array{src: string, alt: ?string, width: ?int, height: ?int}>
     */
    protected function extractImages(string $html): array
    {
        $images = [];
        preg_match_all('/<img\s+([^>]*)>/is', $html, $matches);

        foreach ($matches[1] as $attributes) {
            // Extract src
            if (! preg_match('/src=["\']([^"\']+)["\']/', $attributes, $srcMatch)) {
                continue;
            }

            // Extract alt
            $alt = null;
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attributes, $altMatch)) {
                $alt = $altMatch[1];
            }

            // Extract dimensions
            $width = null;
            $height = null;
            if (preg_match('/width=["\']?(\d+)/', $attributes, $widthMatch)) {
                $width = (int) $widthMatch[1];
            }
            if (preg_match('/height=["\']?(\d+)/', $attributes, $heightMatch)) {
                $height = (int) $heightMatch[1];
            }

            $images[] = [
                'src' => $srcMatch[1],
                'alt' => $alt,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $images;
    }

    /**
     * Check if URL is external.
     */
    protected function isExternalUrl(string $url): bool
    {
        // Relative URLs are internal
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return false;
        }

        // Parse host
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        $currentHost = parse_url(config('app.url'), PHP_URL_HOST);

        return $host !== $currentHost;
    }

    /**
     * Run all rules against the context.
     *
     * @return array<string, RuleResult>
     */
    protected function runRules(AnalysisContext $context): array
    {
        $results = [];

        foreach ($this->getActiveRules() as $rule) {
            try {
                $results[$rule->getId()] = $rule->analyze($context);
            } catch (\Exception $e) {
                $results[$rule->getId()] = RuleResult::skip(
                    $rule->getId(),
                    'Error: '.$e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Load rules from config paths.
     */
    protected function loadRules(): void
    {
        // Rules will be loaded in Phase 4
        // For now, this is a placeholder
    }

    /**
     * Get active rules (excluding excluded ones).
     *
     * @return array<string, RuleInterface>
     */
    protected function getActiveRules(): array
    {
        $excludeRules = config('seo.analyzer.exclude_rules', []);

        return array_filter(
            $this->rules,
            fn (RuleInterface $rule) => ! in_array($rule->getId(), $excludeRules)
        );
    }

    /**
     * Get rule weights.
     *
     * @return array<string, int>
     */
    protected function getRuleWeights(): array
    {
        $weights = [];

        foreach ($this->rules as $rule) {
            $weights[$rule->getId()] = $rule->getWeight();
        }

        return $weights;
    }

    /**
     * Register a rule.
     */
    public function registerRule(RuleInterface $rule): void
    {
        $this->rules[$rule->getId()] = $rule;
    }
}
