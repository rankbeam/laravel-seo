<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Rankbeam\Seo\Services\SEODefaultsRepository;

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
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
     *
     * Cached as a plain attribute array, never as a model: Laravel 13
     * defaults cache.serializable_classes to false, so objects pulled
     * from a persistent store come back as __PHP_Incomplete_Class.
     */
    public static function getForScope(string $scope, string $locale = 'en'): ?self
    {
        $cacheKey = config('seo.cache.prefix', 'seo_')."default:{$scope}:{$locale}";
        $store = Cache::store(config('seo.cache.store'));

        $attributes = $store->remember($cacheKey, 3600, function () use ($scope, $locale) {
            return static::forScope($scope)->forLocale($locale)->first()?->getAttributes();
        });

        if ($attributes === null) {
            return null;
        }

        // A stale pre-2.1 entry (or one degraded by the restriction
        // above) is not an array - drop it and query directly.
        if (! is_array($attributes)) {
            $store->forget($cacheKey);

            return static::forScope($scope)->forLocale($locale)->first();
        }

        return static::hydrate([$attributes])->first();
    }

    /**
     * Invalidate both default caches when a row is saved or deleted.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $model) => static::forgetCaches($model));
        static::deleted(fn (self $model) => static::forgetCaches($model));
    }

    /**
     * Forget every cache entry that depends on this default.
     *
     * Two layers hold this data: the model-attribute cache written by
     * getForScope() (key `default:…`) and the resolved-defaults cache written
     * by SEODefaultsRepository (key `defaults:…`). Both must be cleared, or an
     * edit keeps serving stale defaults until the 1-hour TTL expires.
     */
    protected static function forgetCaches(self $model): void
    {
        Cache::store(config('seo.cache.store'))->forget(
            config('seo.cache.prefix', 'seo_')."default:{$model->scope}:{$model->locale}"
        );

        $repository = app(SEODefaultsRepository::class);

        // The repository falls back to the 'en' row for a missing locale and
        // caches that result under the *requested* locale's key, so editing the
        // 'en' row can leave other locales stale - clear the whole scope for it.
        if ($model->locale === 'en') {
            $repository->clearCache($model->scope);

            return;
        }

        $repository->clearCache($model->scope, $model->locale);
    }
}
