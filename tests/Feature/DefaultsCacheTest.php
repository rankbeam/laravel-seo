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

it('does not serve stale memoized defaults after another worker clears cache', function () {
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);

    expect($repository->global('en')?->title)->toBe('Original title');

    $default->newQuery()
        ->whereKey($default->getKey())
        ->update(['title_template' => 'Updated elsewhere']);

    (new SEODefaultsRepository)->clearCache('global', 'en');

    expect($repository->global('en')?->title)->toBe('Updated elsewhere');
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

it('invalidates English fallback caches for arbitrary locales', function () {
    $default = SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'title_template' => 'Original title',
    ]);
    $repository = app(SEODefaultsRepository::class);

    expect($repository->forScope('global', 'it')?->title)->toBe('Original title');

    $default->newQuery()
        ->whereKey($default->getKey())
        ->update(['title_template' => 'Updated title']);

    $repository->clearCache('global', 'en');

    expect($repository->forScope('global', 'it')?->title)->toBe('Updated title');
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

describe('clearing a scope forgets every locale it was cached under (3.16)', function () {
    it('forgets a locale that has its own defaults row, not only the six the old fixed list named', function () {
        // Japanese and Italian both have their own row, and neither was in the
        // fixed list clearCache() walked, so their cache entries survived a
        // clear and the app kept serving stale defaults until the TTL ran out.
        foreach (['ja', 'it'] as $locale) {
            SEODefault::create([
                'scope' => 'global',
                'locale' => $locale,
                'title_template' => "Before {$locale}",
            ]);
        }

        $repository = app(SEODefaultsRepository::class);

        foreach (['ja', 'it'] as $locale) {
            expect($repository->global($locale)?->title)->toBe("Before {$locale}");
        }

        DB::table('seo_defaults')->where('scope', 'global')->update(['title_template' => 'After']);
        $repository->clearCache('global');

        // A fresh repository carries no per-request memo, so this reads the
        // cache store — the layer that used to hold the stale value.
        $fresh = new SEODefaultsRepository;

        expect($fresh->global('ja')?->title)->toBe('After')
            ->and($fresh->global('it')?->title)->toBe('After');
    });

    it('still forgets a locale that falls back to the English row', function () {
        SEODefault::create(['scope' => 'global', 'locale' => 'en', 'title_template' => 'Before']);

        $repository = app(SEODefaultsRepository::class);

        expect($repository->global('cs')?->title)->toBe('Before');

        DB::table('seo_defaults')->where('scope', 'global')->update(['title_template' => 'After']);
        $repository->clearCache('global');

        expect((new SEODefaultsRepository)->global('cs')?->title)->toBe('After');
    });
});

it('keeps the locale tracker alive as long as the entries it is responsible for clearing', function () {
    // The tracker used to be written only when a locale was added to it, so
    // with two locales it expired while an entry cached later was still live:
    //   T+0    ja and it cached; tracker [ja, it] expires T+3600
    //   T+1800 ja re-cached after its entry expired; entry now lives to T+5400,
    //          but ja is already listed, so the tracker keeps its T+3600 expiry
    //   T+3700 tracker gone — clearCache('global') can no longer forget ja,
    //          which is not in the fixed en/de/fr/es/nl/pt_BR list either.
    SEODefault::create(['scope' => 'global', 'locale' => 'ja', 'title_template' => 'Before']);
    SEODefault::create(['scope' => 'global', 'locale' => 'it', 'title_template' => 'Before']);

    $prefix = config('seo.cache.prefix', 'seo_');
    $store = Cache::store(config('seo.cache.store'));

    app(SEODefaultsRepository::class)->global('ja');
    app(SEODefaultsRepository::class)->global('it');

    // Halfway through the TTL, `ja` expires and the next request re-caches it.
    $this->travel(1800)->seconds();
    $store->forget($prefix.'defaults:global:ja');
    expect((new SEODefaultsRepository)->global('ja')?->title)->toBe('Before');

    // Past the tracker's original expiry, while the re-cached `ja` entry lives.
    $this->travel(1900)->seconds();
    expect($store->get($prefix.'defaults:global:ja'))->not->toBeNull('the ja entry should still be cached here');

    DB::table('seo_defaults')->where('scope', 'global')->update(['title_template' => 'After']);
    (new SEODefaultsRepository)->clearCache('global');

    expect((new SEODefaultsRepository)->global('ja')?->title)->toBe('After');

    $this->travelBack();
});

describe('clearing everything forgets the cache store, not only the per-request memo (3.16.1)', function () {
    it('forgets every scope and locale that was cached', function () {
        // clearCache() with no arguments used to empty the memo and bump its
        // version and stop there, so every entry in the persistent store kept
        // serving stale defaults until the TTL expired — a full clear was not
        // a clear.
        $pairs = [
            ['global', 'en'],
            ['global', 'ja'],
            ['blog.index', 'en'],
            ['blog.index', 'it'],
            ['App\Models\Post', 'de'],
        ];

        foreach ($pairs as [$scope, $locale]) {
            SEODefault::create([
                'scope' => $scope,
                'locale' => $locale,
                'title_template' => "Before {$scope} {$locale}",
            ]);
        }

        $repository = app(SEODefaultsRepository::class);

        foreach ($pairs as [$scope, $locale]) {
            expect($repository->forScope($scope, $locale)?->title)->toBe("Before {$scope} {$locale}");
        }

        // A locale that falls back to the English row is cached under its own key too.
        expect($repository->forScope('blog.index', 'cs')?->title)->toBe('Before blog.index en');

        DB::table('seo_defaults')->update(['title_template' => 'After']);
        $repository->clearCache();

        // A fresh repository carries no per-request memo, so this reads the
        // cache store — the layer the old full clear never touched.
        $fresh = new SEODefaultsRepository;

        foreach ($pairs as [$scope, $locale]) {
            expect($fresh->forScope($scope, $locale)?->title)->toBe('After', "{$scope}:{$locale} still served the stale entry");
        }

        expect($fresh->forScope('blog.index', 'cs')?->title)->toBe('After');
    });

    it('forgets a scope whose rows are gone, which getAvailableScopes() no longer lists', function () {
        SEODefault::create(['scope' => 'blog.index', 'locale' => 'en', 'title_template' => 'Before']);

        expect(app(SEODefaultsRepository::class)->forRoute('blog.index', 'en')?->title)->toBe('Before');

        DB::table('seo_defaults')->where('scope', 'blog.index')->delete();
        app(SEODefaultsRepository::class)->clearCache();

        expect((new SEODefaultsRepository)->forRoute('blog.index', 'en'))->toBeNull();
    });
});
