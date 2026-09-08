<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Models\SEODefault;

/**
 * Repository for retrieving SEO defaults from database and config.
 *
 * This repository provides cached access to SEO default templates stored
 * in the `seo_defaults` table. Defaults can be scoped to:
 *
 * - **Global**: Site-wide defaults (scope = 'global')
 * - **Model Type**: Per-model-class defaults (scope = 'App\Models\Post')
 * - **Route**: Per-route defaults (scope = 'blog.index')
 *
 * ## Database Schema
 *
 * The `seo_defaults` table stores templates and defaults:
 * - `scope`: The scope identifier (global, model class, route name)
 * - `locale`: Language code for multi-language support
 * - `title_template`: Title template with placeholders
 * - `description_template`: Description template with placeholders
 * - `og_image_default`: Default Open Graph image
 * - `robots_default`: Default robots directive
 * - `schema_defaults`: Default JSON-LD schema
 *
 * ## Template Placeholders
 *
 * Templates support these placeholders:
 * - `{site_name}`: Site name from config
 * - `{year}`: Current year
 * - `{title}`: Model's title (processed in HasSEO trait)
 *
 * ## Caching
 *
 * All defaults are cached for performance. Cache is automatically
 * invalidated when SEODefault models are saved/deleted.
 *
 * @see SEODefault For the Eloquent model
 * @see SEOResolver For how defaults are used
 */
class SEODefaultsRepository
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * TTL for the scope and locale trackers. They are written inside the
     * remember() callback, before the entry they describe, so an equal TTL
     * would let a tracker expire moments before its last entry; the buffer
     * keeps "the tracker outlives its entries" true.
     */
    protected const TRACKER_TTL = self::CACHE_TTL + 60;

    /**
     * Whether the seo_defaults table is known to exist (per instance).
     */
    protected bool $tableExists = false;

    /**
     * Per-request memo of resolved defaults, keyed by "scope:locale".
     *
     * Records BOTH hits and null misses so a missing default resolves to a
     * single DB round-trip per scope/locale per request. Laravel's
     * Cache::remember() never caches a null payload (a null get() reads as a
     * miss), so without this layer every seoData() resolution re-queries the
     * common no-rows install. The repository is a singleton, so this array
     * persists for the life of the request/process; clearCache() resets it so
     * admin updates take effect.
     *
     * Uses array_key_exists() (not isset()) on lookup so a memoized null is
     * distinguished from "not yet resolved".
     *
     * @var array<string, SEOData|null>
     */
    protected array $memo = [];

    /**
     * Shared version observed by this instance's memo.
     */
    protected ?string $memoVersion = null;

    /**
     * Get global defaults for a locale.
     *
     * Global defaults apply to all pages as a fallback.
     * Typically set site-wide title template and default og:image.
     *
     * @param  string  $locale  The locale code (e.g., 'en', 'de')
     * @return SEOData|null Global defaults or null if not set
     *
     * @example
     * ```php
     * $global = $repository->global('en');
     * // Returns SEOData with site-wide defaults
     * ```
     */
    public function global(string $locale): ?SEOData
    {
        return $this->getCached('global', $locale);
    }

    /**
     * Get defaults for a specific model type.
     *
     * Model-type defaults apply to all instances of a model class.
     * For example, all Posts could have og:type='article' by default.
     *
     * @param  Model  $model  The Eloquent model instance
     * @param  string  $locale  The locale code
     * @return SEOData|null Model-type defaults or null if not set
     *
     * @example
     * ```php
     * // In seo_defaults: scope = 'App\Models\Post'
     * $defaults = $repository->forModelType($post, 'en');
     * ```
     */
    public function forModelType(Model $model, string $locale): ?SEOData
    {
        $scope = get_class($model);

        return $this->getCached($scope, $locale);
    }

    /**
     * Get defaults for a specific route.
     *
     * Route defaults apply to specific named routes. Useful for:
     * - Archive pages that should be noindex
     * - Static pages with fixed SEO settings
     * - Category/tag pages with templates
     *
     * @param  string  $route  The Laravel route name (e.g., 'blog.index')
     * @param  string  $locale  The locale code
     * @return SEOData|null Route defaults or null if not set
     *
     * @example
     * ```php
     * // In seo_defaults: scope = 'blog.archive'
     * $defaults = $repository->forRoute('blog.archive', 'en');
     * // Might return SEOData with robots='noindex,follow'
     * ```
     */
    public function forRoute(string $route, string $locale): ?SEOData
    {
        return $this->getCached($route, $locale);
    }

    /**
     * Get defaults for any scope (generic method).
     *
     * @param  string  $scope  The scope identifier
     * @param  string  $locale  The locale code
     * @return SEOData|null Defaults for the scope or null
     */
    public function forScope(string $scope, string $locale): ?SEOData
    {
        return $this->getCached($scope, $locale);
    }

    /**
     * Get cached SEO defaults for a scope.
     *
     * Results are cached for performance. Cache is automatically
     * invalidated when SEODefault models are saved/deleted.
     *
     * The cached payload is a plain array, never a SEOData object:
     * Laravel 13 defaults cache.serializable_classes to false, so
     * objects pulled from a persistent store come back as
     * __PHP_Incomplete_Class.
     *
     * @param  string  $scope  The scope identifier
     * @param  string  $locale  The locale code
     * @return SEOData|null Cached defaults or null
     */
    protected function getCached(string $scope, string $locale): ?SEOData
    {
        $this->syncMemoVersion();

        $memoKey = "{$scope}:{$locale}";

        // Short-circuit on the per-request memo, which records null misses too
        // (array_key_exists, not isset). On the common no-rows install this is
        // what keeps repeated seoData() resolutions from re-querying the cache
        // store / DB for a default that is always null.
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        $cacheKey = $this->getCacheKey($scope, $locale);

        $data = Cache::store($this->getCacheStore())
            ->remember($cacheKey, self::CACHE_TTL, function () use ($scope, $locale) {
                return $this->loadFromDatabase($scope, $locale);
            });

        if ($data === null) {
            return $this->memo[$memoKey] = null;
        }

        // A stale pre-2.1 entry (or one degraded by the restriction
        // described above) is not an array - drop it and reload.
        if (! is_array($data)) {
            Cache::store($this->getCacheStore())->forget($cacheKey);

            $data = $this->loadFromDatabase($scope, $locale);
        }

        return $this->memo[$memoKey] = ($data === null ? null : SEOData::fromArray($data));
    }

    /**
     * Load SEO defaults from database.
     *
     * @param  string  $scope  The scope identifier
     * @param  string  $locale  The locale code
     * @return array|null Cacheable SEOData::fromArray() input or null
     */
    protected function loadFromDatabase(string $scope, string $locale): ?array
    {
        // Check if the table exists
        if (! $this->tableExists()) {
            return null;
        }

        try {
            $default = SEODefault::query()
                ->where('scope', $scope)
                ->where('locale', $locale)
                ->first();

            if (! $default) {
                // Try fallback to default locale
                if ($locale !== 'en') {
                    $default = SEODefault::query()
                        ->where('scope', $scope)
                        ->where('locale', 'en')
                        ->first();

                    if ($default) {
                        $this->rememberCachedLocale($scope, $locale);
                    }
                }
            } else {
                // A locale with its own row is cached under its own key, so it
                // has to be tracked too or clearCache($scope) cannot forget it.
                $this->rememberCachedLocale($scope, $locale);
            }

            if (! $default) {
                return null;
            }

            return $this->transformToDefaultsArray($default);
        } catch (\Exception $e) {
            // Log the error but don't throw - graceful degradation
            report($e);

            return null;
        }
    }

    /**
     * Transform an SEODefault model to the cacheable defaults payload.
     *
     * @param  SEODefault  $default  The database model
     * @return array SEOData::fromArray() input
     */
    protected function transformToDefaultsArray(SEODefault $default): array
    {
        return [
            'title' => $this->processTemplate($default->title_template),
            'description' => $this->processTemplate($default->description_template),
            'og_image' => $default->og_image_default,
            'robots' => $default->robots_default,
            'schema_jsonld' => $default->schema_defaults,
        ];
    }

    /**
     * Process a template string with placeholders.
     *
     * Replaces placeholders with actual values:
     * - {site_name}: From config('seo.site_name') or config('app.name')
     * - {year}: Current year
     *
     * Note: Model-specific placeholders like {title} are processed
     * later in the HasSEO trait after the model is available.
     *
     * @param  string|null  $template  The template string
     * @return string|null Processed template or null
     */
    protected function processTemplate(?string $template): ?string
    {
        if (! $template) {
            return null;
        }

        $replacements = [
            '{site_name}' => config('seo.site_name', config('app.name', '')),
            '{year}' => date('Y'),
            '{month}' => date('F'),
            '{day}' => date('j'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    /**
     * Clear cached defaults for a specific scope/locale.
     *
     * With no arguments, every scope is cleared from the cache store as
     * well as from the per-request memo: the scopes with rows in the
     * database plus every scope recorded as cached, so a scope whose rows
     * were deleted is forgotten too.
     *
     * @param  string|null  $scope  The scope to clear (null = all)
     * @param  string|null  $locale  The locale to clear (null = all locales for scope)
     */
    public function clearCache(?string $scope = null, ?string $locale = null): void
    {
        $store = Cache::store($this->getCacheStore());

        if ($scope && $locale) {
            // Clear specific scope/locale
            $store->forget($this->getCacheKey($scope, $locale));
            unset($this->memo["{$scope}:{$locale}"]);

            if ($locale === 'en') {
                $this->clearTrackedLocaleCacheKeys($scope);
            }

            $this->bumpMemoVersion();

            return;
        }

        if ($scope) {
            $this->forgetScope($scope);
            $this->bumpMemoVersion();

            return;
        }

        // Full clear: the store cannot be enumerated, so walk every scope that
        // has rows plus every scope recorded as cached (a scope whose rows were
        // deleted is only in the second list) and forget each one through the
        // per-scope path, which knows every locale it was cached under.
        $pairs = $this->storedScopeLocales();
        $scopes = array_unique(array_merge(array_column($pairs, 0), $this->trackedScopes()));

        foreach ($scopes as $trackedScope) {
            $this->forgetScope($trackedScope);
        }

        // An entry written before the trackers existed is recorded nowhere in
        // the store, but when its locale has a row of its own the table still
        // names the key. (A legacy entry for a locale that only fell back to
        // English cannot be named and expires within its TTL.)
        foreach ($pairs as [$rowScope, $rowLocale]) {
            $store->forget($this->getCacheKey($rowScope, $rowLocale));
        }

        $store->forget($this->cachedScopesKey());

        $this->memo = [];
        $this->bumpMemoVersion();
    }

    /**
     * Forget every cache entry and memo entry for one scope. The caller bumps
     * the memo version afterwards so other workers drop their memo too.
     */
    protected function forgetScope(string $scope): void
    {
        $store = Cache::store($this->getCacheStore());

        // Every locale this scope has actually been cached under is forgotten
        // by clearTrackedLocaleCacheKeys() below. This fixed list stays as the
        // safety net for entries written before that tracking existed (the
        // tracking key is created on the next cache miss).
        foreach (['en', 'de', 'fr', 'es', 'nl', 'pt_BR'] as $loc) {
            $store->forget($this->getCacheKey($scope, $loc));
        }

        $this->clearTrackedLocaleCacheKeys($scope);

        // The memo may hold locales outside the list above, so drop every
        // entry for this scope rather than the fixed set.
        foreach (array_keys($this->memo) as $memoKey) {
            if (str_starts_with($memoKey, "{$scope}:")) {
                unset($this->memo[$memoKey]);
            }
        }
    }

    public function flushMemo(): void
    {
        $this->memo = [];
        $this->memoVersion = $this->currentMemoVersion();
    }

    protected function syncMemoVersion(): void
    {
        $version = $this->currentMemoVersion();

        if ($this->memoVersion === $version) {
            return;
        }

        $this->memo = [];
        $this->memoVersion = $version;
    }

    protected function currentMemoVersion(): string
    {
        return (string) (Cache::store($this->getCacheStore())->get($this->memoVersionKey()) ?? '0');
    }

    protected function bumpMemoVersion(): void
    {
        $version = microtime(true).':'.random_int(1, PHP_INT_MAX);

        Cache::store($this->getCacheStore())->put($this->memoVersionKey(), $version, self::CACHE_TTL);

        $this->memo = [];
        $this->memoVersion = $version;
    }

    protected function memoVersionKey(): string
    {
        return config('seo.cache.prefix', 'seo_').'defaults:memo_version';
    }

    /**
     * Record that this scope has a cache entry under this locale, so
     * clearCache($scope) can forget every locale actually in use rather than a
     * fixed list. Called on the database-load path only, so it costs one cache
     * read on a miss and nothing on a hit. `en` needs no entry: it is the key
     * clearCache() always forgets.
     */
    protected function rememberCachedLocale(string $scope, string $locale): void
    {
        $this->rememberCachedScope($scope);

        if ($locale === 'en') {
            return;
        }

        $store = Cache::store($this->getCacheStore());
        $key = $this->fallbackLocalesKey($scope);
        $locales = $store->get($key, []);
        $locales = is_array($locales) ? $locales : [];

        if (! in_array($locale, $locales, true)) {
            $locales[] = $locale;
        }

        // Rewritten on every cache write, not only when the locale is new: the
        // tracker has to outlive the entries it is responsible for clearing,
        // and an entry re-cached later than the tracker was written would
        // otherwise survive a clearCache($scope) it should not have.
        $store->put($key, array_values($locales), self::TRACKER_TTL);
    }

    protected function clearTrackedLocaleCacheKeys(string $scope): void
    {
        $store = Cache::store($this->getCacheStore());
        $key = $this->fallbackLocalesKey($scope);
        $locales = $store->get($key, []);
        $locales = is_array($locales) ? $locales : [];

        foreach ($locales as $locale) {
            if (is_string($locale) && $locale !== '') {
                $store->forget($this->getCacheKey($scope, $locale));
                unset($this->memo["{$scope}:{$locale}"]);
            }
        }

        $store->forget($key);
    }

    protected function fallbackLocalesKey(string $scope): string
    {
        return config('seo.cache.prefix', 'seo_').'defaults:fallback_locales:'.$scope;
    }

    /**
     * Record that this scope has at least one cache entry, so clearCache()
     * with no arguments can forget it even after its rows are deleted and
     * getAvailableScopes() no longer lists it. Rewritten on every cache
     * write for the same reason as the locale tracker: it has to outlive
     * the entries it is responsible for clearing.
     */
    protected function rememberCachedScope(string $scope): void
    {
        $store = Cache::store($this->getCacheStore());
        $key = $this->cachedScopesKey();
        $scopes = $store->get($key, []);
        $scopes = is_array($scopes) ? $scopes : [];

        if (! in_array($scope, $scopes, true)) {
            $scopes[] = $scope;
        }

        $store->put($key, array_values($scopes), self::TRACKER_TTL);
    }

    /**
     * @return array<int, string>
     */
    protected function trackedScopes(): array
    {
        $scopes = Cache::store($this->getCacheStore())->get($this->cachedScopesKey(), []);

        if (! is_array($scopes)) {
            return [];
        }

        return array_values(array_filter($scopes, fn ($scope) => is_string($scope) && $scope !== ''));
    }

    protected function cachedScopesKey(): string
    {
        return config('seo.cache.prefix', 'seo_').'defaults:cached_scopes';
    }

    /**
     * Every scope/locale pair with a row of its own; the scopes are the same
     * set getAvailableScopes() returns.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function storedScopeLocales(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        try {
            return SEODefault::query()
                ->select(['scope', 'locale'])
                ->distinct()
                ->get()
                ->map(fn (SEODefault $row) => [(string) $row->scope, (string) $row->locale])
                ->all();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Refresh the cache for a specific default.
     *
     * Call this after updating an SEODefault to ensure fresh data.
     *
     * @param  SEODefault  $default  The updated default
     */
    public function refreshCache(SEODefault $default): void
    {
        $this->clearCache($default->scope, $default->locale);

        // Pre-warm the cache
        $this->getCached($default->scope, $default->locale);
    }

    /**
     * Get the cache key for a scope/locale combination.
     *
     * @param  string  $scope  The scope identifier
     * @param  string  $locale  The locale code
     * @return string The cache key
     */
    protected function getCacheKey(string $scope, string $locale): string
    {
        $prefix = config('seo.cache.prefix', 'seo_');

        return "{$prefix}defaults:{$scope}:{$locale}";
    }

    /**
     * Get the cache store name.
     *
     * @return string|null The cache store name
     */
    protected function getCacheStore(): ?string
    {
        return config('seo.cache.store');
    }

    /**
     * Check if the seo_defaults table exists.
     *
     * @return bool True if table exists
     */
    protected function tableExists(): bool
    {
        // Memoize per instance, and only the positive result: a process-wide
        // static latching `false` would permanently disable DB defaults for
        // long-running workers (queue/Octane) that booted before migrations.
        if ($this->tableExists) {
            return true;
        }

        try {
            return $this->tableExists = Schema::hasTable('seo_defaults');
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get all available scopes from the database.
     *
     * Useful for admin interfaces to show what defaults are configured.
     *
     * @return array<int, string> List of unique scopes
     */
    public function getAvailableScopes(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        try {
            return SEODefault::query()
                ->distinct()
                ->pluck('scope')
                ->toArray();
        } catch (\Exception) {
            return [];
        }
    }
}
