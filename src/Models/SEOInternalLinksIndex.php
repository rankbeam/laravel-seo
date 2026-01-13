<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Internal links index for link suggestion functionality.
 *
 * Maintains a searchable index of all pages for finding internal linking
 * opportunities. When editing content, this index is queried to suggest
 * relevant pages to link to based on keyword overlap.
 *
 * Index contains:
 * - Stemmed keywords extracted from page content
 * - Headings for anchor text suggestions
 * - URL and title for display
 *
 * @property int $id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string $locale
 * @property string $url
 * @property string $title
 * @property array $stemmed_keywords
 * @property array $headings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see \Fibonoir\LaravelSEO\Services\InternalLinks\LinkIndexBuilder
 * @see \Fibonoir\LaravelSEO\Services\InternalLinks\LinkSuggester
 */
class SEOInternalLinksIndex extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seo_internal_links_index';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'locale',
        'url',
        'title',
        'stemmed_keywords',
        'headings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'stemmed_keywords' => 'array',
        'headings' => 'array',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en',
        'stemmed_keywords' => '[]',
        'headings' => '{}',
    ];

    /**
     * Get the parent linkable model.
     *
     * @return MorphTo
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by locale.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $locale
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Search for pages matching a stemmed keyword.
     *
     * Note: This performs a JSON search which may be slow for large indexes.
     * For production at scale, consider using a dedicated search engine.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $stemmedKeyword The stemmed keyword to search for
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByKeyword($query, string $stemmedKeyword)
    {
        return $query->whereJsonContains('stemmed_keywords', [
            'stem' => $stemmedKeyword,
        ]);
    }

    /**
     * Find link suggestions based on keyword overlap.
     *
     * @param array<int, string> $stemmedKeywords Array of stemmed keywords from content
     * @param string $locale Locale to search in
     * @param string|null $excludeUrl URL to exclude from results (current page)
     * @param int $limit Maximum suggestions to return
     * @return Collection<int, static> Suggested pages ordered by relevance
     */
    public static function findSuggestions(
        array $stemmedKeywords,
        string $locale = 'en',
        ?string $excludeUrl = null,
        int $limit = 10
    ): Collection {
        if (empty($stemmedKeywords)) {
            return collect();
        }

        // Get all pages in locale
        $query = static::forLocale($locale);

        if ($excludeUrl) {
            $query->where('url', '!=', $excludeUrl);
        }

        $pages = $query->get();

        // Score each page by keyword overlap
        $scored = $pages->map(function (self $page) use ($stemmedKeywords) {
            $pageKeywords = collect($page->stemmed_keywords)
                ->pluck('stem')
                ->toArray();

            $overlap = count(array_intersect($stemmedKeywords, $pageKeywords));
            $totalWeight = 0;

            // Sum weights of matching keywords
            foreach ($page->stemmed_keywords as $kw) {
                if (in_array($kw['stem'], $stemmedKeywords, true)) {
                    $totalWeight += $kw['weight'] ?? 1;
                }
            }

            return [
                'page' => $page,
                'overlap' => $overlap,
                'weight' => $totalWeight,
            ];
        })
            ->filter(fn ($item) => $item['overlap'] > 0)
            ->sortByDesc('weight')
            ->take($limit);

        return $scored->pluck('page')->values();
    }

    /**
     * Get anchor text suggestions from headings.
     *
     * @return array<int, string> Array of possible anchor texts
     */
    public function getAnchorTextSuggestions(): array
    {
        $suggestions = [];

        // Add title as first option
        if ($this->title) {
            $suggestions[] = $this->title;
        }

        // Add H1s
        if (! empty($this->headings['h1'])) {
            foreach ($this->headings['h1'] as $h1) {
                if ($h1 !== $this->title) {
                    $suggestions[] = $h1;
                }
            }
        }

        // Add H2s
        if (! empty($this->headings['h2'])) {
            foreach ($this->headings['h2'] as $h2) {
                $suggestions[] = $h2;
            }
        }

        return array_slice(array_unique($suggestions), 0, 5);
    }

    /**
     * Get top keywords for display.
     *
     * @param int $limit Maximum keywords to return
     * @return array<int, array{stem: string, count: int, weight: float}>
     */
    public function getTopKeywords(int $limit = 10): array
    {
        if (empty($this->stemmed_keywords)) {
            return [];
        }

        $keywords = collect($this->stemmed_keywords)
            ->sortByDesc('weight')
            ->take($limit)
            ->values()
            ->toArray();

        return $keywords;
    }

    /**
     * Update or create index entry for a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The linkable model
     * @param string $locale The locale
     * @param string $url The page URL
     * @param string $title The page title
     * @param array $stemmedKeywords Extracted keywords
     * @param array $headings Extracted headings
     * @return static The created or updated index entry
     */
    public static function updateIndex(
        Model $model,
        string $locale,
        string $url,
        string $title,
        array $stemmedKeywords,
        array $headings
    ): static {
        return static::updateOrCreate(
            [
                'linkable_type' => $model->getMorphClass(),
                'linkable_id' => $model->getKey(),
                'locale' => $locale,
            ],
            [
                'url' => $url,
                'title' => $title,
                'stemmed_keywords' => $stemmedKeywords,
                'headings' => $headings,
            ]
        );
    }

    /**
     * Remove index entry for a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The linkable model
     * @param string|null $locale Specific locale or null for all locales
     * @return int Number of deleted rows
     */
    public static function removeFromIndex(Model $model, ?string $locale = null): int
    {
        $query = static::query()
            ->where('linkable_type', $model->getMorphClass())
            ->where('linkable_id', $model->getKey());

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        return $query->delete();
    }
}
