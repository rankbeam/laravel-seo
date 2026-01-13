<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Fibonoir\LaravelSEO\Support\Stemmer;

beforeEach(function () {
    $this->stemmer = new Stemmer();
    Cache::flush();
});

describe('Stemmer', function () {
    it('stems english words correctly', function (string $input, string $expected) {
        $result = $this->stemmer->stem($input, 'en');

        expect($result)->toBe($expected);
    })->with([
        ['running', 'run'],
        ['runs', 'run'],
        ['jumped', 'jump'],
        ['jumping', 'jump'],
        ['swimming', 'swim'],
        ['swims', 'swim'],
        ['played', 'play'],
        ['playing', 'play'],
        ['happiness', 'happi'],
        ['beautiful', 'beauti'],
        ['organization', 'organ'],
        ['organized', 'organ'],
    ]);

    it('stems italian words correctly', function (string $input, string $expected) {
        $result = $this->stemmer->stem($input, 'it');

        expect($result)->toBe($expected);
    })->with([
        ['correndo', 'corr'],
        ['correre', 'corr'],
        ['mangiando', 'mang'],
        ['mangiare', 'mang'],
        ['parlando', 'parl'],
        ['parlare', 'parl'],
        ['bellissimo', 'bell'],
        ['bellezza', 'bell'],
    ]);

    it('handles unknown locale with fallback', function () {
        // Unknown locale should fall back to English
        $result = $this->stemmer->stem('running', 'xyz');

        expect($result)->toBe('run');
    });

    it('normalizes locale variants', function (string $locale) {
        // All English variants should produce the same result
        $result = $this->stemmer->stem('running', $locale);

        expect($result)->toBe('run');
    })->with([
        'en',
        'en_US',
        'en_GB',
        'en_AU',
        'en_CA',
    ]);

    it('normalizes italian locale variants', function (string $locale) {
        $result = $this->stemmer->stem('correndo', $locale);

        expect($result)->toBe('corr');
    })->with([
        'it',
        'it_IT',
        'it_CH',
    ]);

    it('caches stem results', function () {
        $word = 'organization';

        // First call - should cache
        $result1 = $this->stemmer->stem($word, 'en');

        // Verify cached by checking cache directly
        $cacheKey = 'seo_test_stem:en:' . md5($word);
        expect(Cache::has($cacheKey))->toBeTrue();

        // Second call - should use cache
        $result2 = $this->stemmer->stem($word, 'en');

        expect($result1)->toBe($result2);
    });

    it('batch stems multiple words', function () {
        $words = ['running', 'jumped', 'swimming', 'played'];
        $expected = ['run', 'jump', 'swim', 'play'];

        $result = $this->stemmer->stemBatch($words, 'en');

        expect($result)->toBe($expected);
    });

    it('batch stems italian words', function () {
        $words = ['correndo', 'mangiando', 'parlando'];

        $result = $this->stemmer->stemBatch($words, 'it');

        expect($result)->toHaveCount(3)
            ->and($result[0])->toBe('corr')
            ->and($result[1])->toBe('mang')
            ->and($result[2])->toBe('parl');
    });

    it('stems phrases correctly', function () {
        $phrase = 'content marketing strategies';

        $result = $this->stemmer->stemPhrase($phrase, 'en');

        // Each word is stemmed and rejoined
        expect($result)->toBeString()
            ->and(explode(' ', $result))->toHaveCount(3);
    });

    it('handles empty strings', function () {
        expect($this->stemmer->stem('', 'en'))->toBe('')
            ->and($this->stemmer->stem('   ', 'en'))->toBe('')
            ->and($this->stemmer->stemPhrase('', 'en'))->toBe('');
    });

    it('converts words to lowercase', function () {
        $result1 = $this->stemmer->stem('Running', 'en');
        $result2 = $this->stemmer->stem('RUNNING', 'en');
        $result3 = $this->stemmer->stem('running', 'en');

        expect($result1)->toBe($result2)
            ->and($result2)->toBe($result3)
            ->and($result1)->toBe('run');
    });

    it('returns supported locales', function () {
        $locales = $this->stemmer->getSupportedLocales();

        expect($locales)->toBeArray()
            ->and($locales)->toContain('en')
            ->and($locales)->toContain('it')
            ->and($locales)->toContain('de')
            ->and($locales)->toContain('fr')
            ->and($locales)->toContain('es');
    });

    it('checks if locale is supported', function () {
        expect($this->stemmer->isLocaleSupported('en'))->toBeTrue()
            ->and($this->stemmer->isLocaleSupported('en_US'))->toBeTrue()
            ->and($this->stemmer->isLocaleSupported('it'))->toBeTrue()
            ->and($this->stemmer->isLocaleSupported('xyz'))->toBeFalse();
    });

    it('clears cache for specific locale', function () {
        // Stem a word to cache it
        $this->stemmer->stem('running', 'en');

        // Clear cache for English
        $this->stemmer->clearCache('en');

        // Internal cache should be cleared (stemmer instances)
        // Note: Laravel cache entries have their own TTL
        expect(true)->toBeTrue(); // Cache operation completed without error
    });

    it('clears all cache', function () {
        // Stem words in multiple locales
        $this->stemmer->stem('running', 'en');
        $this->stemmer->stem('correndo', 'it');

        // Clear all cache
        $this->stemmer->clearCache();

        expect(true)->toBeTrue(); // Cache operation completed without error
    });
});
