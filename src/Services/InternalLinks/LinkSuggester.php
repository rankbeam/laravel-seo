<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\InternalLinks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Fibonoir\LaravelSEO\Models\SEOInternalLinksIndex;
use Fibonoir\LaravelSEO\Support\Stemmer;
use Fibonoir\LaravelSEO\Support\StopWords;
use Fibonoir\LaravelSEO\Support\Tokenizer;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Suggests relevant internal links for content.
 *
 * Uses the internal links index to find pages with related keywords
 * and suggests optimal anchor text for linking.
 *
 * ## Usage
 * ```php
 * $suggester = app(LinkSuggester::class);
 *
 * // Get suggestions for a model
 * $suggestions = $suggester->suggest($post, 5);
 *
 * // Get suggestions for raw content
 * $suggestions = $suggester->suggestForContent($content, 'en', 5);
 *
 * // Get anchor text suggestions between two pages
 * $anchors = $suggester->suggestAnchorText($sourcePost, $targetPost);
 * ```
 *
 * ## Suggestion Format
 * ```php
 * [
 *     [
 *         'page' => Model,
 *         'url' => '/blog/related-post',
 *         'title' => 'Related Post Title',
 *         'relevance_score' => 0.75,
 *         'suggested_anchors' => ['anchor text 1', 'anchor text 2'],
 *         'matching_keywords' => ['keyword1', 'keyword2'],
 *     ],
 * ]
 * ```
 */
class LinkSuggester
{
    /**
     * Minimum relevance score to include in suggestions.
     */
    protected float $minRelevanceScore = 0.1;

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Stemmer $stemmer,
        protected StopWords $stopWords,
    ) {}

    /**
     * Get link suggestions for a model.
     *
     * @param Model $model The source model to find links for
     * @param int $limit Maximum number of suggestions
     * @return array<int, array{page: Model, url: string, title: string, relevance_score: float, suggested_anchors: array, matching_keywords: array}>
     */
    public function suggest(Model $model, int $limit = 5): array
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return [];
        }

        // Get content and locale
        $content = method_exists($model, 'getContentForSEO')
            ? $model->getContentForSEO()
            : '';

        $locale = $model->seoMeta?->locale ?? config('app.locale');

        // Get model's keywords (stemmed)
        $sourceKeywords = $this->extractKeywords($content, $locale);

        if (empty($sourceKeywords)) {
            return [];
        }

        // Find related pages
        $relatedPages = $this->findRelatedPages($sourceKeywords, $locale, $limit * 3);

        // Filter out the source model
        $relatedPages = $relatedPages->filter(function ($indexEntry) use ($model) {
            return ! (
                $indexEntry->linkable_type === get_class($model)
                && $indexEntry->linkable_id == $model->getKey()
            );
        });

        // Filter out pages already linked in content
        $linkedUrls = $this->extractLinkedUrls($content);
        $relatedPages = $relatedPages->filter(function ($indexEntry) use ($linkedUrls) {
            return ! in_array($indexEntry->url, $linkedUrls, true);
        });

        // Build suggestions
        $suggestions = [];

        foreach ($relatedPages->take($limit) as $indexEntry) {
            $targetKeywords = $this->parseStoredKeywords($indexEntry->stemmed_keywords);

            $suggestions[] = [
                'page' => $indexEntry->linkable,
                'url' => $indexEntry->url,
                'title' => $indexEntry->title,
                'relevance_score' => $this->calculateRelevanceScore($sourceKeywords, $targetKeywords),
                'suggested_anchors' => $this->findAnchorPhrases($content, $targetKeywords, $locale),
                'matching_keywords' => array_values(array_intersect(
                    array_column($sourceKeywords, 'stem'),
                    array_column($targetKeywords, 'stem')
                )),
            ];
        }

        // Sort by relevance score (highest first)
        usort($suggestions, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $suggestions;
    }

    /**
     * Get link suggestions for raw content (without a model).
     *
     * @param string $content The content to analyze
     * @param string $locale The content locale
     * @param int $limit Maximum number of suggestions
     * @return array<int, array{page: Model|null, url: string, title: string, relevance_score: float, suggested_anchors: array, matching_keywords: array}>
     */
    public function suggestForContent(string $content, string $locale, int $limit = 5): array
    {
        $sourceKeywords = $this->extractKeywords($content, $locale);

        if (empty($sourceKeywords)) {
            return [];
        }

        // Find related pages
        $relatedPages = $this->findRelatedPages($sourceKeywords, $locale, $limit * 2);

        // Filter out pages already linked
        $linkedUrls = $this->extractLinkedUrls($content);
        $relatedPages = $relatedPages->filter(function ($indexEntry) use ($linkedUrls) {
            return ! in_array($indexEntry->url, $linkedUrls, true);
        });

        // Build suggestions
        $suggestions = [];

        foreach ($relatedPages->take($limit) as $indexEntry) {
            $targetKeywords = $this->parseStoredKeywords($indexEntry->stemmed_keywords);

            $suggestions[] = [
                'page' => $indexEntry->linkable,
                'url' => $indexEntry->url,
                'title' => $indexEntry->title,
                'relevance_score' => $this->calculateRelevanceScore($sourceKeywords, $targetKeywords),
                'suggested_anchors' => $this->findAnchorPhrases($content, $targetKeywords, $locale),
                'matching_keywords' => array_values(array_intersect(
                    array_column($sourceKeywords, 'stem'),
                    array_column($targetKeywords, 'stem')
                )),
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $suggestions;
    }

    /**
     * Suggest anchor text for linking from source to target.
     *
     * @param Model $source The page linking from
     * @param Model $target The page linking to
     * @return array<int, string> Suggested anchor phrases
     */
    public function suggestAnchorText(Model $source, Model $target): array
    {
        // Get source content
        $content = method_exists($source, 'getContentForSEO')
            ? $source->getContentForSEO()
            : '';

        $locale = $source->seoMeta?->locale ?? config('app.locale');

        // Get target's index entry
        $targetIndex = SEOInternalLinksIndex::where('linkable_type', get_class($target))
            ->where('linkable_id', $target->getKey())
            ->first();

        if (! $targetIndex) {
            // Fallback to target's title
            return [$target->title ?? $target->name ?? ''];
        }

        $targetKeywords = $this->parseStoredKeywords($targetIndex->stemmed_keywords);

        return $this->findAnchorPhrases($content, $targetKeywords, $locale);
    }

    /**
     * Find related pages based on keyword overlap.
     *
     * @param array<int, array{word: string, stem: string, count: int}> $stemmedKeywords
     * @param string $locale
     * @param int $limit
     * @return Collection<int, SEOInternalLinksIndex>
     */
    public function findRelatedPages(array $stemmedKeywords, string $locale, int $limit): Collection
    {
        if (empty($stemmedKeywords)) {
            return collect();
        }

        // Get all stems
        $stems = array_column($stemmedKeywords, 'stem');

        // Build weighted stems (give more weight to frequent keywords)
        $stemWeights = [];
        foreach ($stemmedKeywords as $kw) {
            $stemWeights[$kw['stem']] = $kw['count'];
        }

        // Query index - using LIKE for JSON search (simple approach)
        // For production, consider full-text search or dedicated search engine
        $results = SEOInternalLinksIndex::forLocale($locale)
            ->get()
            ->map(function ($indexEntry) use ($stemWeights) {
                $targetKeywords = $this->parseStoredKeywords($indexEntry->stemmed_keywords);
                $targetStems = array_column($targetKeywords, 'stem');

                // Calculate overlap score
                $score = 0;
                foreach ($stemWeights as $stem => $weight) {
                    if (in_array($stem, $targetStems, true)) {
                        // Find the target's weight for this keyword
                        $targetWeight = 1;
                        foreach ($targetKeywords as $tk) {
                            if ($tk['stem'] === $stem) {
                                $targetWeight = $tk['count'];
                                break;
                            }
                        }
                        // Combined score: sqrt of product (geometric mean like)
                        $score += sqrt($weight * $targetWeight);
                    }
                }

                $indexEntry->relevance_score = $score;

                return $indexEntry;
            })
            ->filter(fn ($entry) => $entry->relevance_score >= $this->minRelevanceScore)
            ->sortByDesc('relevance_score')
            ->take($limit);

        return $results;
    }

    /**
     * Calculate relevance score between two keyword sets.
     *
     * Uses a weighted Jaccard-like similarity based on keyword counts.
     */
    protected function calculateRelevanceScore(array $sourceKeywords, array $targetKeywords): float
    {
        if (empty($sourceKeywords) || empty($targetKeywords)) {
            return 0.0;
        }

        $sourceStems = [];
        foreach ($sourceKeywords as $kw) {
            $sourceStems[$kw['stem']] = $kw['count'];
        }

        $targetStems = [];
        foreach ($targetKeywords as $kw) {
            $targetStems[$kw['stem']] = $kw['count'];
        }

        // Calculate weighted intersection
        $intersection = 0;
        $union = 0;

        $allStems = array_unique(array_merge(array_keys($sourceStems), array_keys($targetStems)));

        foreach ($allStems as $stem) {
            $sourceWeight = $sourceStems[$stem] ?? 0;
            $targetWeight = $targetStems[$stem] ?? 0;

            $intersection += min($sourceWeight, $targetWeight);
            $union += max($sourceWeight, $targetWeight);
        }

        if ($union === 0) {
            return 0.0;
        }

        return round($intersection / $union, 3);
    }

    /**
     * Find anchor phrases in content that match target keywords.
     *
     * @return array<int, string>
     */
    protected function findAnchorPhrases(string $content, array $targetKeywords, string $locale): array
    {
        if (empty($targetKeywords) || empty($content)) {
            return [];
        }

        $text = strip_tags($content);
        $anchors = [];

        // Get target stems for matching
        $targetStems = array_column($targetKeywords, 'stem');
        $targetWords = array_column($targetKeywords, 'word');

        // Split into sentences
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) < 10 || mb_strlen($sentence) > 100) {
                continue;
            }

            // Check if sentence contains target keywords
            $tokens = $this->tokenizer->tokenize($sentence);
            $stemmed = array_map(fn ($t) => $this->stemmer->stem($t, $locale), $tokens);

            $matchCount = count(array_intersect($stemmed, $targetStems));

            if ($matchCount >= 1) {
                // Extract the relevant phrase (2-5 words around the match)
                $phrase = $this->extractRelevantPhrase($sentence, $targetWords, $targetStems, $locale);
                if ($phrase && ! in_array($phrase, $anchors, true)) {
                    $anchors[] = $phrase;
                }
            }

            if (count($anchors) >= 3) {
                break;
            }
        }

        // Always include target's top keyword as fallback
        if (empty($anchors) && ! empty($targetKeywords)) {
            $anchors[] = $targetKeywords[0]['word'];
        }

        return array_slice($anchors, 0, 3);
    }

    /**
     * Extract a relevant phrase around matching keywords.
     */
    protected function extractRelevantPhrase(
        string $sentence,
        array $targetWords,
        array $targetStems,
        string $locale,
    ): ?string {
        $words = preg_split('/\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) <= 5) {
            return trim($sentence);
        }

        // Find position of first matching word
        $matchIndex = null;
        foreach ($words as $i => $word) {
            $stem = $this->stemmer->stem(mb_strtolower($word), $locale);
            if (in_array($stem, $targetStems, true)) {
                $matchIndex = $i;
                break;
            }
        }

        if ($matchIndex === null) {
            return null;
        }

        // Extract 2-5 words around the match
        $start = max(0, $matchIndex - 2);
        $end = min(count($words) - 1, $matchIndex + 2);

        $phrase = array_slice($words, $start, $end - $start + 1);
        $phraseText = implode(' ', $phrase);

        // Clean up punctuation at edges
        $phraseText = trim($phraseText, '.,;:!?"\'');

        // Ensure minimum length
        if (mb_strlen($phraseText) < 3) {
            return null;
        }

        return $phraseText;
    }

    /**
     * Extract keywords from content.
     *
     * @return array<int, array{word: string, stem: string, count: int}>
     */
    protected function extractKeywords(string $content, string $locale): array
    {
        $text = strip_tags($content);
        $tokens = $this->tokenizer->tokenize($text);
        $tokens = $this->stopWords->removeStopWords($tokens, $locale);

        $stemCounts = [];
        $wordToStem = [];

        foreach ($tokens as $token) {
            $stem = $this->stemmer->stem($token, $locale);

            if (mb_strlen($stem) < 3) {
                continue;
            }

            if (! isset($stemCounts[$stem])) {
                $stemCounts[$stem] = 0;
                $wordToStem[$stem] = $token;
            }

            $stemCounts[$stem]++;
        }

        arsort($stemCounts);

        $keywords = [];
        foreach (array_slice($stemCounts, 0, 15, true) as $stem => $count) {
            $keywords[] = [
                'word' => $wordToStem[$stem],
                'stem' => $stem,
                'count' => $count,
            ];
        }

        return $keywords;
    }

    /**
     * Parse stored keywords from JSON.
     *
     * @return array<int, array{word: string, stem: string, count: int}>
     */
    protected function parseStoredKeywords(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (! is_array($stored)) {
            return [];
        }

        return $stored;
    }

    /**
     * Extract URLs already linked in content.
     *
     * @return array<int, string>
     */
    protected function extractLinkedUrls(string $html): array
    {
        $urls = [];

        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                // Normalize URL
                $parsed = parse_url($url);
                if (isset($parsed['path'])) {
                    $urls[] = $parsed['path'];
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Set minimum relevance score threshold.
     */
    public function setMinRelevanceScore(float $score): self
    {
        $this->minRelevanceScore = $score;

        return $this;
    }
}
