<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\SEOResolutionCache;
use Rankbeam\Seo\Tests\Fixtures\NonTaggableArrayStore;

/*
|--------------------------------------------------------------------------
| SEOResolutionCache — unit
|--------------------------------------------------------------------------
|
| The resolver result cache in isolation: the enabled() gate, lossless
| array round-trip (including timezone-aware DateTime fields), and targeted
| invalidation on BOTH a taggable store (array) and a non-taggable store
| (the in-memory NonTaggableArrayStore, which exercises the version-stamp
| fallback).
|
*/

/** Register an in-memory, non-taggable cache store for the version-stamp path. */
function useNonTaggableStore(): void
{
    app('cache')->extend(
        'seo_nontaggable_driver',
        fn () => app('cache')->repository(new NonTaggableArrayStore)
    );
    config()->set('cache.stores.seo_nontaggable', ['driver' => 'seo_nontaggable_driver']);
    config()->set('seo.cache.store', 'seo_nontaggable');
}

function richSeoData(): SEOData
{
    return new SEOData(
        title: 'Cached Title',
        description: 'Cached description',
        canonical: 'https://example.test/p/1',
        robots: 'noindex, follow',
        ogTitle: 'OG Title',
        ogImage: 'https://example.test/og.jpg',
        ogType: 'article',
        ogSiteName: 'Example',
        ogUrl: 'https://example.test/p/1',
        twitterCard: 'summary',
        publishedTime: new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
        modifiedTime: new DateTimeImmutable('2026-02-03T06:07:08+02:00'),
        author: 'Ada Lovelace',
        section: 'Engineering',
        tags: ['a', 'b'],
        focusKeywords: [['keyword' => 'caching', 'is_primary' => true]],
        schemaJsonld: ['@context' => 'https://schema.org', '@type' => 'Article'],
        locale: 'en',
        alternates: [['hreflang' => 'fr', 'href' => 'https://example.test/fr/p/1']],
    );
}

beforeEach(function () {
    config()->set('seo.cache.resolver.enabled', true);
    config()->set('seo.cache.store', 'array');
});

it('is a no-op when disabled — get misses, put/forget do nothing', function () {
    config()->set('seo.cache.resolver.enabled', false);

    $cache = app(SEOResolutionCache::class);

    expect($cache->enabled())->toBeFalse();

    $cache->put('App\\Post', 1, 'en', 'posts.show', 'https://x.test/p/1', richSeoData());

    expect($cache->get('App\\Post', 1, 'en', 'posts.show', 'https://x.test/p/1'))->toBeNull();
});

dataset('cache stores', [
    'taggable (array)' => 'array',
    'non-taggable (version stamp)' => 'nontaggable',
]);

it('round-trips a full SEOData losslessly', function (string $store) {
    if ($store === 'nontaggable') {
        useNonTaggableStore();
    }

    $cache = app(SEOResolutionCache::class);
    $data = richSeoData();

    $cache->put('App\\Post', 7, 'en', 'posts.show', 'https://x.test/p/7', $data);
    $got = $cache->get('App\\Post', 7, 'en', 'posts.show', 'https://x.test/p/7');

    expect($got)->toBeInstanceOf(SEOData::class)
        ->and($got->toFlatArray())->toEqual($data->toFlatArray());
})->with('cache stores');

it('preserves DateTime fields to the second across timezones', function (string $store) {
    if ($store === 'nontaggable') {
        useNonTaggableStore();
    }

    $cache = app(SEOResolutionCache::class);
    $data = richSeoData();

    $cache->put('App\\Post', 9, 'en', null, null, $data);
    $got = $cache->get('App\\Post', 9, 'en', null, null);

    // toArray() formats datetimes with format('c') (offset included), so an
    // identical rendered representation proves the instant + offset survived.
    expect($got->toArray()['article']['published_time'])
        ->toBe($data->toArray()['article']['published_time'])
        ->and($got->toArray()['article']['modified_time'])
        ->toBe($data->toArray()['article']['modified_time']);
})->with('cache stores');

it('keys entries by class, id, locale, route, and url', function (string $store) {
    if ($store === 'nontaggable') {
        useNonTaggableStore();
    }

    $cache = app(SEOResolutionCache::class);
    $cache->put('App\\Post', 1, 'en', 'posts.show', 'https://x.test/a', richSeoData()->with('title', 'A'));

    // Same model, different url → different entry (miss).
    expect($cache->get('App\\Post', 1, 'en', 'posts.show', 'https://x.test/b'))->toBeNull();
    // Different locale → miss.
    expect($cache->get('App\\Post', 1, 'fr', 'posts.show', 'https://x.test/a'))->toBeNull();
    // Exact identity → hit.
    expect($cache->get('App\\Post', 1, 'en', 'posts.show', 'https://x.test/a')->title)->toBe('A');
})->with('cache stores');

it('forgetModel clears only the targeted model', function (string $store) {
    if ($store === 'nontaggable') {
        useNonTaggableStore();
    }

    $cache = app(SEOResolutionCache::class);
    $cache->put('App\\Post', 1, 'en', null, null, richSeoData()->with('title', 'One'));
    $cache->put('App\\Post', 2, 'en', null, null, richSeoData()->with('title', 'Two'));

    $cache->forgetModel('App\\Post', 1);

    expect($cache->get('App\\Post', 1, 'en', null, null))->toBeNull()
        ->and($cache->get('App\\Post', 2, 'en', null, null)->title)->toBe('Two');
})->with('cache stores');

it('flush clears every model', function (string $store) {
    if ($store === 'nontaggable') {
        useNonTaggableStore();
    }

    $cache = app(SEOResolutionCache::class);
    $cache->put('App\\Post', 1, 'en', null, null, richSeoData());
    $cache->put('App\\Page', 5, 'en', null, null, richSeoData());

    $cache->flush();

    expect($cache->get('App\\Post', 1, 'en', null, null))->toBeNull()
        ->and($cache->get('App\\Page', 5, 'en', null, null))->toBeNull();
})->with('cache stores');
