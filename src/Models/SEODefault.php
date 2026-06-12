<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Default SEO settings by scope (global, model-type, route).
 *
 * @property int $id
 * @property string $scope
 * @property string $locale
 * @property string|null $title_template
 * @property string|null $description_template
 * @property string|null $og_image_default
 * @property string|null $robots_default
 * @property array|null $schema_defaults
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SEODefault extends Model
{
    protected $table = 'seo_defaults';

    protected $fillable = [
        'scope',
        'locale',
        'title_template',
        'description_template',
        'og_image_default',
        'robots_default',
        'schema_defaults',
    ];

    protected $casts = [
        'schema_defaults' => 'array',
    ];

    protected $attributes = [
        'locale' => 'en',
    ];

    /**
     * Scope to filter by scope.
     */
    public function scopeForScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope to filter by locale.
     */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Get defaults for a scope with caching.
     */
    public static function getForScope(string $scope, string $locale = 'en'): ?self
    {
        $cacheKey = config('seo.cache.prefix', 'seo_')."default:{$scope}:{$locale}";

        return Cache::store(config('seo.cache.store'))
            ->remember($cacheKey, 3600, function () use ($scope, $locale) {
                return static::forScope($scope)->forLocale($locale)->first();
            });
    }

    /**
     * Clear cache when saving.
     */
    protected static function booted(): void
    {
        static::saved(function (self $model) {
            $cacheKey = config('seo.cache.prefix', 'seo_')."default:{$model->scope}:{$model->locale}";
            Cache::store(config('seo.cache.store'))->forget($cacheKey);
        });

        static::deleted(function (self $model) {
            $cacheKey = config('seo.cache.prefix', 'seo_')."default:{$model->scope}:{$model->locale}";
            Cache::store(config('seo.cache.store'))->forget($cacheKey);
        });
    }
}
