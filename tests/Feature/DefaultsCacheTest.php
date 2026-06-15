<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Services\SEODefaultsRepository;

/*
 * Laravel 13 ships cache.serializable_classes => false: objects pulled
 * from a persistent cache store (database is the new default) come back
 * as __PHP_Incomplete_Class. Cached defaults must therefore be pure
 * data. Regression caught on a fresh Laravel 13 app while verifying the
 * Pro headless install.
 */

function defaultsCacheKey(): string
{
    return config('seo.cache.prefix', 'seo_').'defaults:global:en';
}

it('caches scope defaults as a plain array that survives object-restricted unserialization', function () {
    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Cached | {site_name}',
    ]);

    $data = app(SEODefaultsRepository::class)->global('en');

    expect($data?->title)->toContain('Cached');

    $cached = Cache::store(config('seo.cache.store'))->get(defaultsCacheKey());

    $roundTripped = unserialize(serialize($cached), ['allowed_classes' => false]);

    expect($cached)->toBeArray()
        ->and($roundTripped)->toEqual($cached);
});

it('recovers when the cache holds a stale pre-2.1 object payload', function () {
    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Fresh title',
    ]);

    // Upgrade scenario: the old format cached the SEOData object itself.
    Cache::store(config('seo.cache.store'))->put(defaultsCacheKey(), new stdClass, 3600);

    $data = app(SEODefaultsRepository::class)->global('en');

    expect($data?->title)->toBe('Fresh title');
});

/*
 * Per-request memo for null misses.
 *
 * Laravel's Cache::remember() never caches a null payload: get() reads the
 * stored null as a miss, so the callback (loadFromDatabase) re-runs on every
 * call. On the common no-rows install that means every seoData() resolution
 * re-queries the DB for a default that is always null - amplified ~100k times
 * on a 50k-URL sitemap. The repository singleton memoizes hits AND misses for
 * the request so a given scope/locale costs at most one DB round-trip.
 */

/**
 * Count the SEODefault SELECTs issued while $callback runs. The Schema table
 * check passes the table name as a binding, so filtering on the inlined
 * `from "seo_defaults"` isolates loadFromDatabase()'s query.
 */
function countDefaultsQueries(callable $callback): int
{
    $queries = 0;

    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'from "seo_defaults"')) {
            $queries++;
        }
    });

    $callback();

    return $queries;
}

it('resolves a missing scope with a single database query per request', function () {
    $repository = app(SEODefaultsRepository::class);

    $queries = countDefaultsQueries(function () use ($repository) {
        expect($repository->global('en'))->toBeNull()
            ->and($repository->global('en'))->toBeNull();
    });

    // Without the memo each call re-runs loadFromDatabase(); with it the null
    // miss is served from memory on the second resolution.
    expect($queries)->toBe(1);
});

it('invalidates the in-memory memo on clearCache', function () {
    $repository = app(SEODefaultsRepository::class);

    $queries = countDefaultsQueries(function () use ($repository) {
        expect($repository->global('en'))->toBeNull();  // query 1, memoizes null

        $repository->clearCache('global', 'en');         // drops the memo entry

        expect($repository->global('en'))->toBeNull();   // query 2, re-resolves
    });

    expect($queries)->toBe(2);
});

it('does not let a memoized null miss hide a freshly created default', function () {
    $repository = app(SEODefaultsRepository::class);

    // Resolve once with no rows: the null miss is memoized for the request.
    expect($repository->global('en'))->toBeNull();

    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Now configured',
    ]);

    // create() fires the save hook, which clears the memo - so the new default
    // is served immediately rather than the stale memoized null.
    expect($repository->global('en')?->title)->toBe('Now configured');
});

it('caches getForScope as attributes and rehydrates the model', function () {
    $created = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Scoped title',
    ]);

    SEODefault::getForScope('global');
    $fromCache = SEODefault::getForScope('global');

    expect($fromCache)->toBeInstanceOf(SEODefault::class)
        ->and($fromCache->getKey())->toBe($created->getKey())
        ->and($fromCache->title_template)->toBe('Scoped title');

    $cached = Cache::store(config('seo.cache.store'))
        ->get(config('seo.cache.prefix', 'seo_').'default:global:en');

    $roundTripped = unserialize(serialize($cached), ['allowed_classes' => false]);

    expect($cached)->toBeArray()
        ->and($roundTripped)->toEqual($cached);
});

/*
 * Cache invalidation on save/delete.
 *
 * The resolved-defaults cache (`defaults:…`) is separate from the model
 * getForScope() cache (`default:…`). Saving/deleting an SEODefault must
 * invalidate the resolved cache too, or an admin edit serves stale defaults
 * until the 1-hour TTL expires. Because the repository falls back to the 'en'
 * row for a missing locale (caching it under the requested locale's key),
 * editing the 'en' row clears the whole scope.
 */

it('invalidates resolved defaults after a default is updated', function () {
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);

    expect($repository->global('en')?->title)->toBe('Original title');

    $default->title_template = 'Updated title';
    $default->save();

    expect($repository->global('en')?->title)->toBe('Updated title');
});

it('invalidates resolved defaults after a default is deleted', function () {
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);

    expect($repository->global('en')?->title)->toBe('Original title');

    $default->delete();

    expect($repository->global('en'))->toBeNull();
});

it('invalidates locale fallback caches when the English default changes', function () {
    // 'de' has no row of its own, so it caches the 'en' fallback under its own
    // key; editing the 'en' row must invalidate that fallback entry too.
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);

    expect($repository->forScope('global', 'de')?->title)->toBe('Original title');

    $default->title_template = 'Updated title';
    $default->save();

    expect($repository->forScope('global', 'de')?->title)->toBe('Updated title');
});

it('forgets the persistent resolved-defaults cache entry on save', function () {
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'de',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);
    $store = Cache::store(config('seo.cache.store'));
    $key = config('seo.cache.prefix', 'seo_').'defaults:global:de';

    $repository->global('de');

    expect($store->get($key))->not->toBeNull();

    $default->title_template = 'Updated title';
    $default->save();

    expect($store->get($key))->toBeNull();
});
