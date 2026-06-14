<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SEO metadata for any Eloquent model.
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string $locale
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical
 * @property string|null $robots
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string $og_type
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $twitter_image
 * @property string $twitter_card
 * @property array|null $focus_keywords
 * @property array|null $schema_jsonld
 * @property string|null $schema_type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SEOMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'locale',
        'title',
        'description',
        'canonical',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_card',
        'focus_keywords',
        'schema_jsonld',
        'schema_type',
    ];

    protected $casts = [
        'focus_keywords' => 'array',
        'schema_jsonld' => 'array',
    ];

    protected $attributes = [
        'locale' => 'en',
        'og_type' => 'website',
        'twitter_card' => 'summary_large_image',
    ];

    /**
     * Get the parent seoable model.
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by locale.
     */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Get the primary focus keyword.
     *
     * @return array{keyword: string, is_primary: bool, synonyms?: array<int, string>}|null
     */
    public function getPrimaryKeyword(): ?array
    {
        if (empty($this->focus_keywords)) {
            return null;
        }

        foreach ($this->focus_keywords as $keyword) {
            if ($keyword['is_primary'] ?? false) {
                return $keyword;
            }
        }

        return $this->focus_keywords[0] ?? null;
    }
}
