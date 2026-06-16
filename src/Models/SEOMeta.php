<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rankbeam\Seo\Services\SEOResolutionCache;

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
 * @property string|null $og_type
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $twitter_image
 * @property string|null $twitter_card
 * @property array|null $focus_keywords
 * @property array|null $schema_jsonld
 * @property string|null $schema_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
    ];

    /**
     * Invalidate the owning model's cached resolution when its seo_meta row
     * changes.
     *
     * The resolver result cache (seo.cache.resolver.enabled) keys entries by the
     * seoable's class/id, so a saved/deleted seo_meta row must clear them — its
     * explicit values are the highest-priority layer. Centralized here (rather
     * than on the relation) so every write path is covered: saveSEO(), Filament,
     * and a direct SEOMeta::create()/update()/delete(). The lookup is guarded by
     * enabled(), so this is inert when caching is off (the default).
     */
    protected static function booted(): void
    {
        static::saved(fn (self $meta) => static::forgetResolution($meta));
        static::deleted(fn (self $meta) => static::forgetResolution($meta));
    }

    /**
     * Forget the cached resolution for this row's seoable model.
     */
    protected static function forgetResolution(self $meta): void
    {
        $cache = app(SEOResolutionCache::class);

        if (! $cache->enabled()) {
            return;
        }

        if (! $meta->seoable_type || $meta->seoable_id === null) {
            return;
        }

        // Normalize a morph alias back to the FQCN the resolver keys by.
        $class = static::getActualClassNameForMorph($meta->seoable_type);

        $cache->forgetModel($class, $meta->seoable_id);
    }

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
