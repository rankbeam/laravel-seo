<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use DateTimeInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Rankbeam\Seo\Data\SEOData;

/**
 * Caches fully-resolved SEOData for hot frontend requests.
 *
 * The {@see SEOResolver} runs a multi-layer precedence chain (config → global /
 * model-type / route defaults → computed model values → explicit seo_meta →
 * title suffix / canonical / schema) on every render. On a high-traffic site
 * that is several DB reads per page. This service lets the resolver cache the
 * result of that chain per model and skip it on a hit.
 *
 * ## Why an array, never the object
 *
 * The cached payload is a plain array (built from {@see SEOData::toFlatArray()},
 * with the two DateTime fields stored as ISO-8601 so they round-trip across
 * timezones) and is rehydrated with {@see SEOData::fromArray()} on read.
 * Laravel 13 defaults `cache.serializable_classes` to `false`, so an object
 * pulled from a persistent store (database is the L13 default) returns as a
 * `__PHP_Incomplete_Class`. The {@see SEODefaultsRepository} caches arrays for
 * exactly this reason. (Note: `SEOData::fromArray()` reads the *flat* snake_case
 * shape `toFlatArray()` produces — NOT the nested, lossy `toArray()` render
 * shape, which is not its inverse.)
 *
 * ## Invalidation
 *
 * Entries for one model clear without scanning keys:
 *
 * - On a **taggable** store (redis, memcached, array) each entry is tagged with
 *   a per-model tag and a global tag; {@see forgetModel()} flushes the model tag
 *   and {@see flush()} flushes the global tag.
 * - On a **non-taggable** store (file, database) the entry key embeds a global
 *   and a per-model version stamp; bumping a stamp orphans the matching entries
 *   (which then expire by TTL).
 *
 * Both paths are exercised by the test suite. Everything no-ops when the feature
 * is disabled, so the model-event listeners that call {@see forgetModel()} are
 * inert by default.
 *
 * @see SEOResolver For the precedence chain this short-circuits
 * @see SEODefaultsRepository For the array-caching precedent
 */
class SEOResolutionCache
{
    /**
     * Whether the resolver result cache is enabled.
     *
     * OFF by default. When false, get() always misses and every write/forget is
     * a no-op, so the resolver behaves byte-identically to an uncached package
     * and the model-event listeners that call into here do nothing.
     */
    public function enabled(): bool
    {
        return (bool) config('seo.cache.resolver.enabled', false);
    }

    /**
     * Look up a cached resolution.
     *
     * @param  string  $class  The model's fully-qualified class name
     * @param  int|string  $id  The model key
     * @param  string  $locale  The resolved locale
     * @param  string|null  $route  The effective route name (may be null)
     * @param  string|null  $url  The current request URL (null in CLI/sitemap context)
     * @return SEOData|null The cached SEOData, or null on a miss
     */
    public function get(string $class, int|string $id, string $locale, ?string $route, ?string $url): ?SEOData
    {
        if (! $this->enabled()) {
            return null;
        }

        $payload = $this->supportsTags()
            ? $this->taggedRepo($class, $id)->get($this->entryKey($class, $id, $locale, $route, $url))
            : $this->repo()->get($this->versionedKey($class, $id, $locale, $route, $url));

        // A miss, or a stale/degraded entry (e.g. an object that came back as a
        // __PHP_Incomplete_Class under cache.serializable_classes=false): treat
        // anything that is not an array as a miss so the chain recomputes.
        if (! is_array($payload)) {
            return null;
        }

        return SEOData::fromArray($payload);
    }

    /**
     * Store a resolution.
     *
     * @param  string  $class  The model's fully-qualified class name
     * @param  int|string  $id  The model key
     * @param  string  $locale  The resolved locale
     * @param  string|null  $route  The effective route name (may be null)
     * @param  string|null  $url  The current request URL (null in CLI/sitemap context)
     * @param  SEOData  $data  The fully-resolved SEO data to cache
     */
    public function put(string $class, int|string $id, string $locale, ?string $route, ?string $url, SEOData $data): void
    {
        if (! $this->enabled()) {
            return;
        }

        $payload = $this->toPayload($data);
        $ttl = $this->ttl();

        if ($this->supportsTags()) {
            $this->taggedRepo($class, $id)->put($this->entryKey($class, $id, $locale, $route, $url), $payload, $ttl);

            return;
        }

        $this->repo()->put($this->versionedKey($class, $id, $locale, $route, $url), $payload, $ttl);
    }

    /**
     * Invalidate every cached resolution for one model (all locales, routes,
     * and URLs) without scanning keys.
     *
     * @param  string  $class  The model's fully-qualified class name
     * @param  int|string  $id  The model key
     */
    public function forgetModel(string $class, int|string $id): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($this->supportsTags()) {
            $this->repo()->tags([$this->modelTag($class, $id)])->flush();

            return;
        }

        $this->bumpVersion($this->modelVersionKey($class, $id));
    }

    /**
     * Invalidate every cached resolution (used when seo_defaults change, since
     * a default can affect any model's resolution).
     */
    public function flush(): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($this->supportsTags()) {
            $this->repo()->tags([$this->globalTag()])->flush();

            return;
        }

        $this->bumpVersion($this->globalVersionKey());
    }

    /**
     * Build the cacheable payload from a resolved SEOData.
     *
     * Starts from toFlatArray() (the snake_case shape fromArray() consumes) and
     * overrides the two DateTime fields with an ISO-8601 string carrying the
     * offset, so published/modified times round-trip losslessly to the second
     * across timezones (toFlatArray() alone formats them as 'Y-m-d H:i:s',
     * dropping the offset).
     *
     * @return array<string, mixed>
     */
    protected function toPayload(SEOData $data): array
    {
        $payload = $data->toFlatArray();

        $payload['published_time'] = $data->publishedTime?->format(DateTimeInterface::ATOM);
        $payload['modified_time'] = $data->modifiedTime?->format(DateTimeInterface::ATOM);

        return $payload;
    }

    /**
     * The configured cache repository (respects seo.cache.store).
     */
    protected function repo(): Repository
    {
        return Cache::store(config('seo.cache.store'));
    }

    /**
     * The repository scoped to a model's tags, for the taggable-store path.
     *
     * Each entry carries both the global tag and the per-model tag, so flushing
     * EITHER tag invalidates it (Laravel invalidates an entry when any tag in
     * its set is flushed).
     */
    protected function taggedRepo(string $class, int|string $id): Repository
    {
        return $this->repo()->tags([$this->globalTag(), $this->modelTag($class, $id)]);
    }

    /**
     * Whether the configured store supports cache tags.
     */
    protected function supportsTags(): bool
    {
        return $this->repo()->getStore() instanceof TaggableStore;
    }

    /**
     * TTL for cached resolutions, in seconds.
     */
    protected function ttl(): int
    {
        return (int) config('seo.cache.resolver.ttl', 3600);
    }

    /**
     * The cache-key prefix shared with the rest of the package.
     */
    protected function prefix(): string
    {
        return (string) config('seo.cache.prefix', 'seo_');
    }

    /**
     * The identity string an entry is keyed by, before hashing.
     */
    protected function identity(string $class, int|string $id, string $locale, ?string $route, ?string $url): string
    {
        return implode('|', [$class, (string) $id, $locale, $route ?? '', $url ?? '']);
    }

    /**
     * Entry key for the taggable-store path (tags carry the versioning).
     */
    protected function entryKey(string $class, int|string $id, string $locale, ?string $route, ?string $url): string
    {
        return $this->prefix().'resolved:'.sha1($this->identity($class, $id, $locale, $route, $url));
    }

    /**
     * Entry key for the non-taggable path: the global and per-model version
     * stamps are folded into the hash, so bumping a stamp makes the old keys
     * unreachable.
     */
    protected function versionedKey(string $class, int|string $id, string $locale, ?string $route, ?string $url): string
    {
        $global = $this->version($this->globalVersionKey());
        $model = $this->version($this->modelVersionKey($class, $id));

        $identity = $this->identity($class, $id, $locale, $route, $url).'|g='.$global.'|m='.$model;

        return $this->prefix().'resolved:'.sha1($identity);
    }

    /**
     * Read a version stamp (defaulting to '0' when unset).
     */
    protected function version(string $key): string
    {
        return (string) ($this->repo()->get($key) ?? '0');
    }

    /**
     * Bump a version stamp to a fresh unique token, orphaning entries keyed
     * under the previous value.
     */
    protected function bumpVersion(string $key): void
    {
        $token = microtime(true).':'.random_int(1, PHP_INT_MAX);

        $this->repo()->put($key, $token, $this->ttl());
    }

    /**
     * The global tag shared by every resolution entry.
     */
    protected function globalTag(): string
    {
        return $this->prefix().'resolved:tag:global';
    }

    /**
     * The per-model tag.
     */
    protected function modelTag(string $class, int|string $id): string
    {
        return $this->prefix().'resolved:tag:'.sha1($class.'|'.$id);
    }

    /**
     * The global version-stamp key (non-taggable path).
     */
    protected function globalVersionKey(): string
    {
        return $this->prefix().'resolved:ver:global';
    }

    /**
     * The per-model version-stamp key (non-taggable path).
     */
    protected function modelVersionKey(string $class, int|string $id): string
    {
        return $this->prefix().'resolved:ver:'.sha1($class.'|'.$id);
    }
}
