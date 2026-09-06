<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\CaseFolder;

describe('Turkish and Azeri casing', function () {
    it('lowercases dotted İ to i and dotless I to ı under a Turkic locale', function () {
        expect(CaseFolder::lower('İSTANBUL', 'tr'))->toBe('istanbul')
            ->and(CaseFolder::lower('DIŞ', 'tr'))->toBe('dış')
            ->and(CaseFolder::lower('İstanbul', 'tr_TR'))->toBe('istanbul')
            ->and(CaseFolder::lower('İstanbul', 'az-Latn'))->toBe('istanbul');
    });

    it('keeps the standard mapping outside a Turkic locale', function () {
        expect(CaseFolder::lower('DIŞ', 'en'))->toBe('diş')
            ->and(CaseFolder::lower('DIŞ'))->toBe('diş');
    });

    it('folds İstanbul and istanbul together under every locale', function () {
        expect(CaseFolder::equals('İstanbul', 'istanbul', 'tr'))->toBeTrue()
            ->and(CaseFolder::equals('İstanbul', 'istanbul'))->toBeTrue()
            ->and(CaseFolder::equals('İstanbul', 'ISTANBUL', 'tr'))->toBeFalse()
            ->and(CaseFolder::isTurkic('tr'))->toBeTrue()
            ->and(CaseFolder::isTurkic('TR_tr'))->toBeTrue()
            ->and(CaseFolder::isTurkic('az'))->toBeTrue()
            ->and(CaseFolder::isTurkic('en'))->toBeFalse()
            ->and(CaseFolder::isTurkic(null))->toBeFalse();
    });
});

describe('Greek final sigma and German sharp s', function () {
    it('treats a final sigma and a mid-word sigma as the same letter when folding', function () {
        expect(CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el'))->toBeTrue()
            ->and(CaseFolder::equals('οδός', 'ΟΔΌΣ', 'el'))->toBeTrue()
            ->and(CaseFolder::fold('ΟΔΟΣ'))->toBe('οδοσ')
            // lower() keeps the final form for display.
            ->and(CaseFolder::lower('ΟΔΟΣ'))->toBe('οδος');
    });

    it('folds ß and ẞ to ss', function () {
        expect(CaseFolder::equals('Straße', 'STRASSE', 'de'))->toBeTrue()
            ->and(CaseFolder::equals('STRAẞE', 'strasse', 'de'))->toBeTrue()
            ->and(CaseFolder::lower('STRAẞE', 'de'))->toBe('straße');
    });
});

describe('normalisation', function () {
    it('compares a precomposed and a decomposed accent as equal', function () {
        expect(CaseFolder::equals("\u{00E9}", "e\u{0301}"))->toBeTrue()
            ->and(CaseFolder::contains("Caf\u{00E9} du Nord", "cafe\u{0301}"))->toBeTrue();
    })->skip(! class_exists(\Normalizer::class), 'ext-intl not loaded');

    it('contains() is a folded substring test', function () {
        expect(CaseFolder::contains('İstanbul Hotels', 'istanbul'))->toBeTrue()
            ->and(CaseFolder::contains('Acmestic', 'acme'))->toBeTrue()
            ->and(CaseFolder::contains('anything', ''))->toBeFalse();
    });
});

describe('containsWord', function () {
    it('matches whole words only, case-insensitively', function () {
        expect(CaseFolder::containsWord('Acme · About Us', 'Acme'))->toBeTrue()
            ->and(CaseFolder::containsWord('about acme', 'Acme'))->toBeTrue()
            ->and(CaseFolder::containsWord('Acmestic Studio', 'Acme'))->toBeFalse()
            ->and(CaseFolder::containsWord('The Acme-Corp', 'Acme'))->toBeTrue()
            ->and(CaseFolder::containsWord('Acme_Corp', 'Acme'))->toBeFalse()
            ->and(CaseFolder::containsWord('anything', '  '))->toBeFalse();
    });

    it('uses Unicode word boundaries so accented and non-Latin brands behave like ASCII ones', function () {
        expect(CaseFolder::containsWord('Notizie dalla Città', 'città'))->toBeTrue()
            ->and(CaseFolder::containsWord('Cittàdella oggi', 'Città'))->toBeFalse()
            ->and(CaseFolder::containsWord('Новости Москва сегодня', 'МОСКВА'))->toBeTrue()
            ->and(CaseFolder::containsWord('Московский', 'Москва'))->toBeFalse();
    });

    it('applies the locale rules: a Turkish brand in either i form matches', function () {
        expect(CaseFolder::containsWord('İstanbul Otelleri', 'istanbul', 'tr'))->toBeTrue()
            ->and(CaseFolder::containsWord('ISTANBUL OTELLERİ', 'İstanbul', 'tr'))->toBeFalse()
            ->and(CaseFolder::containsWord('ISTANBUL OTELLERİ', 'Istanbul', 'tr'))->toBeTrue();
    });
});
