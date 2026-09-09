<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Hreflang;

beforeEach(function () {
    config([
        'seo.hreflang.normalize' => true,
        'seo.hreflang.include_self' => false,
        'seo.hreflang.x_default' => null,
    ]);
});

describe('normalize', function () {
    it('rewrites Laravel locales to the BCP 47 form', function (string $in, string $out) {
        expect(Hreflang::normalize($in))->toBe($out);
    })->with([
        ['it_IT', 'it-IT'],
        ['pt_br', 'pt-BR'],
        ['EN', 'en'],
        ['zh_hans_cn', 'zh-Hans-CN'],
        ['ZH-HANT', 'zh-Hant'],
        ['sr-latn-rs', 'sr-Latn-RS'],
        ['es-419', 'es-419'],
        ['X-DEFAULT', 'x-default'],
        [' en-us ', 'en-US'],
        ['', ''],
        ['en__US', 'en--US'],
        ['iw_IL', 'he-IL'],
        ['in_ID', 'id-ID'],
        ['ji', 'yi'],
        ['my_BU', 'my-MM'],
        ['i-klingon', 'tlh'],
        ['en_u_ca_gregory', 'en-u-ca-gregory'],
    ]);

    it('maps a Laravel locale and returns null for a blank one', function () {
        expect(Hreflang::fromLocale('pt_BR'))->toBe('pt-BR')
            ->and(Hreflang::fromLocale(null))->toBeNull()
            ->and(Hreflang::fromLocale('   '))->toBeNull();
    });

    it('extracts the language subtag', function () {
        expect(Hreflang::language('pt-BR'))->toBe('pt')
            ->and(Hreflang::language('zh_Hant_TW'))->toBe('zh')
            ->and(Hreflang::language('x-default'))->toBeNull()
            ->and(Hreflang::language(''))->toBeNull();
    });
});

describe('validation', function () {
    it('accepts the codes search engines read', function (string $code) {
        expect(Hreflang::isValid($code))->toBeTrue();
    })->with([
        'en', 'it-IT', 'pt-BR', 'zh-Hans', 'zh-Hant-TW', 'sr-Latn-RS', 'x-default',
        'de-CH', 'fr-CA', 'ar-AE', 'ja-JP', 'ko', 'he', 'uk-UA', 'nb-NO', 'EN-us', 'zh-Hans-US', 'en-Shaw',
    ]);

    it('rejects the codes search engines ignore', function (string $code) {
        expect(Hreflang::isValid($code))->toBeFalse();
    })->with([
        'en-UK', 'jp', 'english', 'en-US-x-private', 'zh-Hnas', 'xx', '', 'en-Latn-Latn', 'us-en', 'it-it-IT', 'de-12',
        'pt_BR', 'es-419', 'fil', 'yue', 'iw-IL', 'en-US-Latn', 'de-CH-1901', 'en-u-ca-gregory',
    ]);

    it('parses the subtags', function () {
        expect(Hreflang::parse('zh-Hant-TW'))->toBe(['language' => 'zh', 'script' => 'Hant', 'region' => 'TW', 'x_default' => false])
            ->and(Hreflang::parse(Hreflang::fromLocale('pt_BR')))->toBe(['language' => 'pt', 'script' => null, 'region' => 'BR', 'x_default' => false])
            ->and(Hreflang::parse('pt_BR'))->toBeNull()
            ->and(Hreflang::parse('x-default'))->toBe(['language' => 'x-default', 'script' => null, 'region' => null, 'x_default' => true])
            ->and(Hreflang::parse('en-UK'))->toBeNull();
    });
});

describe('alternatesFor policies', function () {
    $raw = [
        ['hreflang' => 'en_US', 'href' => 'https://example.com/en/page'],
        ['hreflang' => 'it_IT', 'href' => 'https://example.com/it/page'],
    ];

    it('drops malformed entries exactly as the renderers always did', function () {
        $alternates = Hreflang::alternatesFor([
            'nope',
            ['hreflang' => 'en', 'href' => 'https://example.com/en'],
            ['hreflang' => '', 'href' => 'https://example.com/blank'],
            ['href' => 'https://example.com/no-code'],
            ['hreflang' => 'fr'],
            ['hreflang' => ['fr'], 'href' => 'https://example.com/array'],
        ]);

        expect($alternates)->toBe([['hreflang' => 'en', 'href' => 'https://example.com/en']])
            ->and(Hreflang::alternatesFor(null))->toBe([])
            ->and(Hreflang::alternatesFor('en'))->toBe([])
            ->and(Hreflang::alternatesFor([]))->toBe([]);
    });

    it('normalises codes by default and leaves them verbatim when off', function () use ($raw) {
        expect(array_column(Hreflang::alternatesFor($raw), 'hreflang'))->toBe(['en-US', 'it-IT']);

        config(['seo.hreflang.normalize' => false]);

        expect(array_column(Hreflang::alternatesFor($raw), 'hreflang'))->toBe(['en_US', 'it_IT']);
    });

    it('appends the page itself when include_self is on and the list omits it', function () use ($raw) {
        config(['seo.hreflang.include_self' => true]);

        $alternates = Hreflang::alternatesFor($raw, 'https://example.com/de/page', 'de_DE');

        expect($alternates)->toHaveCount(3)
            ->and($alternates[2])->toBe(['hreflang' => 'de-DE', 'href' => 'https://example.com/de/page']);
    });

    it('does not add a self-reference that is already there, by href or by code', function () use ($raw) {
        config(['seo.hreflang.include_self' => true]);

        // Same href (trailing slash tolerant).
        expect(Hreflang::alternatesFor($raw, 'https://example.com/it/page/', 'it_IT'))->toHaveCount(2)
            // Same code, different href — ambiguous, leave it alone.
            ->and(Hreflang::alternatesFor($raw, 'https://example.com/it/other', 'it-IT'))->toHaveCount(2)
            // No canonical or no locale — nothing to add.
            ->and(Hreflang::alternatesFor($raw, null, 'de'))->toHaveCount(2)
            ->and(Hreflang::alternatesFor($raw, 'https://example.com/de/page', null))->toHaveCount(2);
    });

    it('adds an x-default for the configured language, exact code first then the bare language', function () use ($raw) {
        config(['seo.hreflang.x_default' => 'en']);

        $alternates = Hreflang::alternatesFor($raw);

        expect($alternates)->toHaveCount(3)
            ->and($alternates[2])->toBe(['hreflang' => 'x-default', 'href' => 'https://example.com/en/page']);

        config(['seo.hreflang.x_default' => 'it_IT']);

        expect(Hreflang::alternatesFor($raw)[2])->toBe(['hreflang' => 'x-default', 'href' => 'https://example.com/it/page']);
    });

    it('adds no x-default when the language is absent or one is already listed', function () use ($raw) {
        config(['seo.hreflang.x_default' => 'de']);

        expect(Hreflang::alternatesFor($raw))->toHaveCount(2);

        config(['seo.hreflang.x_default' => 'en']);
        $withDefault = array_merge($raw, [['hreflang' => 'X-Default', 'href' => 'https://example.com/']]);

        $alternates = Hreflang::alternatesFor($withDefault);

        expect($alternates)->toHaveCount(3)
            ->and($alternates[2]['hreflang'])->toBe('x-default')
            ->and($alternates[2]['href'])->toBe('https://example.com/');
    });

    it('keeps an empty list empty under every policy', function () {
        config(['seo.hreflang.include_self' => true, 'seo.hreflang.x_default' => 'en']);

        expect(Hreflang::alternatesFor([], 'https://example.com/', 'en'))->toBe([])
            ->and(Hreflang::alternatesFor(null, 'https://example.com/', 'en'))->toBe([]);
    });

    it('applies self before x-default so the page can be the x-default target', function () {
        config(['seo.hreflang.include_self' => true, 'seo.hreflang.x_default' => 'en']);

        $alternates = Hreflang::alternatesFor(
            [['hreflang' => 'it', 'href' => 'https://example.com/it/']],
            'https://example.com/',
            'en',
        );

        expect(array_column($alternates, 'hreflang'))->toBe(['it', 'en', 'x-default'])
            ->and($alternates[2]['href'])->toBe('https://example.com/');
    });
});

describe('issues', function () {
    it('reports invalid and duplicated codes once each', function () {
        $problems = Hreflang::issues([
            ['hreflang' => 'en-UK', 'href' => 'https://example.com/uk'],
            ['hreflang' => 'en-UK', 'href' => 'https://example.com/uk2'],
            ['hreflang' => 'it', 'href' => 'https://example.com/it'],
            ['hreflang' => 'IT', 'href' => 'https://example.com/it2'],
            ['hreflang' => 'x-default', 'href' => 'https://example.com/'],
        ]);

        expect($problems)->toBe(['invalid' => ['en-UK'], 'duplicate' => ['en-UK', 'IT']]);
    });

    it('reports nothing for a clean list', function () {
        expect(Hreflang::issues([
            ['hreflang' => 'en', 'href' => 'https://example.com/en'],
            ['hreflang' => 'pt-BR', 'href' => 'https://example.com/br'],
            ['hreflang' => 'pt-PT', 'href' => 'https://example.com/pt'],
            ['hreflang' => 'x-default', 'href' => 'https://example.com/'],
        ]))->toBe(['invalid' => [], 'duplicate' => []]);
    });
});
