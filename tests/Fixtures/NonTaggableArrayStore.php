<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Fixtures;

use Illuminate\Contracts\Cache\Store;

/**
 * A minimal, in-memory cache store that does NOT support tags.
 *
 * Laravel's array driver extends TaggableStore, so it cannot exercise the
 * resolution cache's non-taggable (version-stamp) fallback. This store backs the
 * "works on a non-taggable store like file/database" tests deterministically and
 * in-memory — without touching the filesystem.
 *
 * Register it per test via:
 *   app('cache')->extend('seo_nontaggable_driver', fn () => app('cache')->repository(new NonTaggableArrayStore));
 *   config()->set('cache.stores.seo_nontaggable', ['driver' => 'seo_nontaggable_driver']);
 *   config()->set('seo.cache.store', 'seo_nontaggable');
 */
class NonTaggableArrayStore implements Store
{
    /** @var array<string, mixed> */
    protected array $storage = [];

    public function get($key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function put($key, $value, $seconds): bool
    {
        $this->storage[$key] = $value;

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, $seconds): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1): int
    {
        return $this->storage[$key] = (int) ($this->storage[$key] ?? 0) + $value;
    }

    public function decrement($key, $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function touch($key, $seconds): bool
    {
        // TTL is not modelled by this in-memory store; nothing to extend.
        return array_key_exists($key, $this->storage);
    }

    public function forget($key): bool
    {
        unset($this->storage[$key]);

        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];

        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }
}
