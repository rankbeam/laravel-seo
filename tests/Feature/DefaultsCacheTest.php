<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
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
