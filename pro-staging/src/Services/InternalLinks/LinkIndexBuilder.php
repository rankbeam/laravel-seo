<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\InternalLinks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Models\SEOInternalLinksIndex;
use Fibonoir\LaravelSEO\Support\Stemmer;
use Fibonoir\LaravelSEO\Support\StopWords;
use Fibonoir\LaravelSEO\Support\Tokenizer;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Builds and maintains the internal links suggestion index.
 *
 * The index stores stemmed keywords and headings for each page,
 * enabling intelligent internal linking suggestions.
 *
 * ## Usage
 * ```php
 * $builder = app(LinkIndexBuilder::class);
 *
 * // Build entire index
 * $builder->buildIndex();
 *
 * // Index single model
 * $builder->indexModel($post);
 *
 * // Update on change
 * $builder->updateIndex($post);
 *
 * // Remove from index
 * $builder->deleteFromIndex($post);
 * ```
 *
 * ## How It Works
 * 1. Extracts content from model
 * 2. Tokenizes and removes stop words
 * 3. Stems tokens for fuzzy matching
 * 4. Calculates top keywords by frequency
 * 5. Extracts headings (H1-H3)
 * 6. Stores in seo_internal_links_index table
 */
class LinkIndexBuilder
{
    /**
     * Default number of top keywords to store.
     */
    protected int $keywordLimit = 20;

    public function __construct(
        protected Tokenizer $tokenizer,
        protected Stemmer $stemmer,
        protected StopWords $stopWords,
    ) {}

    /**
     * Build the complete index for all SEO-enabled models.
     */
    public function buildIndex(): void
    {
        $models = $this->getIndexableModels();

        Log::info('LinkIndexBuilder: Starting full index build', [
            'model_types' => $models->count(),
        ]);

        $indexed = 0;

        foreach ($models as $modelClass) {
            $modelClass::query()->chunk(100, function ($items) use (&$indexed) {
                foreach ($items as $model) {
                    try {
                        $this->indexModel($model);
                        $indexed++;
                    } catch (\Exception $e) {
                        Log::warning('LinkIndexBuilder: Failed to index model', [
                            'class' => get_class($model),
                            'id' => $model->getKey(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        Log::info('LinkIndexBuilder: Index build complete', [
            'indexed' => $indexed,
        ]);
    }

    /**
     * Index a single model.
     */
    public function indexModel(Model $model): void
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return;
        }

        // Get content
        $content = method_exists($model, 'getContentForSEO')
            ? $model->getContentForSEO()
            : '';

        if (empty($content)) {
            return;
        }

        // Get locale
        $locale = $model->seoMeta?->locale ?? config('app.locale');

        // Get URL
        $url = method_exists($model, 'getUrlForSEO')
            ? $model->getUrlForSEO()
            : null;

        if (! $url) {
            return;
        }

        // Get title
        $title = $model->seoMeta?->title
            ?? $model->title
            ?? $model->name
            ?? '';

        // Extract keywords
        $keywords = $this->extractKeywords($content, $locale, $this->keywordLimit);

        // Extract headings
        $headings = $this->extractHeadings($content);

        // Get stemmed keywords as JSON-able array
        $stemmedKeywords = array_map(
            fn ($kw) => [
                'word' => $kw['word'],
                'stem' => $kw['stem'],
                'count' => $kw['count'],
            ],
            $keywords
        );

        // Upsert into index
        SEOInternalLinksIndex::updateIndex(
            $model,
            $locale,
            $url,
            $title,
            $stemmedKeywords,
            $headings
        );
    }

    /**
     * Update index for a model (delete and reindex).
     */
    public function updateIndex(Model $model): void
    {
        $this->indexModel($model);
    }

    /**
     * Delete a model from the index.
     */
    public function deleteFromIndex(Model $model): void
    {
        SEOInternalLinksIndex::removeFromIndex($model);
    }

    /**
     * Extract top keywords from content.
     *
     * @return array<int, array{word: string, stem: string, count: int}>
     */
    protected function extractKeywords(string $content, string $locale, int $limit = 20): array
    {
        // Strip HTML
        $text = strip_tags($content);

        // Tokenize
        $tokens = $this->tokenizer->tokenize($text);

        // Remove stop words
        $tokens = $this->stopWords->removeStopWords($tokens, $locale);

        // Stem tokens and count
        $stemCounts = [];
        $wordToStem = [];

        foreach ($tokens as $token) {
            $stem = $this->stemmer->stem($token, $locale);

            if (mb_strlen($stem) < 3) {
                continue;
            }

            if (! isset($stemCounts[$stem])) {
                $stemCounts[$stem] = 0;
                $wordToStem[$stem] = $token; // Keep original word
            }

            $stemCounts[$stem]++;

            // Keep the most common original form
            if (mb_strlen($token) < mb_strlen($wordToStem[$stem])) {
                $wordToStem[$stem] = $token;
            }
        }

        // Sort by count descending
        arsort($stemCounts);

        // Get top N
        $topStems = array_slice($stemCounts, 0, $limit, true);

        // Build result
        $keywords = [];
        foreach ($topStems as $stem => $count) {
            $keywords[] = [
                'word' => $wordToStem[$stem],
                'stem' => $stem,
                'count' => $count,
            ];
        }

        return $keywords;
    }

    /**
     * Extract headings from HTML content.
     *
     * @return array{h1: array<int, string>, h2: array<int, string>, h3: array<int, string>}
     */
    protected function extractHeadings(string $html): array
    {
        $headings = [
            'h1' => [],
            'h2' => [],
            'h3' => [],
        ];

        foreach (['h1', 'h2', 'h3'] as $tag) {
            if (preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $text = trim(strip_tags($match));
                    if (! empty($text)) {
                        $headings[$tag][] = $text;
                    }
                }
            }
        }

        return $headings;
    }

    /**
     * Get all models that should be indexed.
     *
     * @return Collection<int, class-string<Model>>
     */
    protected function getIndexableModels(): Collection
    {
        // Check config first
        $configured = config('seo.internal_links.models', []);

        if (! empty($configured)) {
            return collect($configured)->filter(function ($class) {
                return class_exists($class)
                    && in_array(HasSEO::class, class_uses_recursive($class), true);
            });
        }

        // Auto-discover
        return $this->discoverModels();
    }

    /**
     * Auto-discover models with HasSEO trait.
     *
     * @return Collection<int, class-string<Model>>
     */
    protected function discoverModels(): Collection
    {
        $models = collect();
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return $models;
        }

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            $class = 'App\\Models\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (
                class_exists($class)
                && is_subclass_of($class, Model::class)
                && in_array(HasSEO::class, class_uses_recursive($class), true)
            ) {
                $models->push($class);
            }
        }

        return $models;
    }

    /**
     * Set the keyword limit.
     */
    public function setKeywordLimit(int $limit): self
    {
        $this->keywordLimit = $limit;

        return $this;
    }

    /**
     * Get index statistics.
     *
     * @return array{total_entries: int, by_type: array<string, int>}
     */
    public function getStats(): array
    {
        $total = SEOInternalLinksIndex::count();

        $byType = SEOInternalLinksIndex::query()
            ->selectRaw('linkable_type, COUNT(*) as count')
            ->groupBy('linkable_type')
            ->pluck('count', 'linkable_type')
            ->toArray();

        return [
            'total_entries' => $total,
            'by_type' => $byType,
        ];
    }
}
