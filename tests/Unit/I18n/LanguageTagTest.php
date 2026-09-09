<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Hreflang;
use Rankbeam\Seo\I18n\LanguageTag;

it('accepts registered BCP47 examples including variants and deprecated tags', function (string $tag) {
    expect(LanguageTag::isValid($tag))->toBeTrue();
})->with([
    'de-CH-1901', 'sl-rozaj-biske', 'sl-IT-nedis', 'de-DE-1996', 'es-419',
    'zh-Hans-CN', 'sr-Latn-RS', 'zh-cmn-Hans-CN', 'zh-yue-HK', 'cmn-Hans-CN',
    'en-US', 'EN-us', 'en-Shaw', 'fil', 'yue', 'sfb', 'ast',
    'en-US-u-islamcal', 'en-u-ca-gregory', 'en-t-it', 'en-u-ca-gregory-t-it',
    'en-x-acme', 'x-acme', 'x-1', 'qaa-Qaaa-QM', 'qtz-Qabx-XZ', 'en-ZZ',
    'i-klingon', 'i-default', 'en-GB-oed', 'sgn-BE-FR', 'zh-min', 'iw-IL', 'in-ID', 'ji', 'my-BU',
]);

it('rejects malformed or unregistered BCP47 tags without repairing them', function (string $tag) {
    expect(LanguageTag::isValid($tag))->toBeFalse();
})->with([
    '', 'english', 'en_US', ' en-US', "en-US\n", 'en--US', 'en-', '-en', 'en US',
    'jp', 'xx', 'en-US-US', 'en-Latn-Latn', 'en-US-Latn', 'en-Hnas', 'en-123',
    'de-CH-1901-1901', 'sl-rozaj-ROZAJ', 'en-u-ca-gregory-u-nu-latn',
    'en-u', 'en-u-x-private', 'en-a-foobar', 'en-x', 'x', 'en-x-abcdefghi',
    'en-cmn', 'zh-cmn-yue', 'en-foobar', 'qzz', 'en-Qaby', 'en-QA-QM',
    'en.utf8', 'en@calendar=gregorian', 'ｅｎ',
]);

it('distinguishes HTML unknown-language and Rankbeam fallback-marker policy', function () {
    expect(LanguageTag::isValidHtml(''))->toBeTrue()
        ->and(LanguageTag::isValid('x-default'))->toBeTrue()
        ->and(LanguageTag::isValidHtml('x-default'))->toBeFalse()
        ->and(LanguageTag::isValidHtml('X-DEFAULT'))->toBeFalse()
        ->and(LanguageTag::isValidHtml('x-acme'))->toBeTrue()
        ->and(LanguageTag::isValidHtml(' '))->toBeFalse()
        ->and(LanguageTag::isValidHtml('de-CH-1901'))->toBeTrue()
        ->and(Hreflang::isValid('de-CH-1901'))->toBeFalse()
        ->and(LanguageTag::isValidHtml('es-419'))->toBeTrue()
        ->and(Hreflang::isValid('es-419'))->toBeFalse();
});

it('keeps aliases valid in HTML while mapping application locales explicitly', function () {
    expect(LanguageTag::isValidHtml('iw-IL'))->toBeTrue()
        ->and(LanguageTag::fromLocale('iw_IL'))->toBe('he-IL')
        ->and(Hreflang::isValid('iw-IL'))->toBeFalse()
        ->and(Hreflang::isValid(Hreflang::fromLocale('iw_IL')))->toBeTrue()
        ->and(LanguageTag::fromLocale('en__US'))->toBe('en--US')
        ->and(LanguageTag::fromLocale('en_ US'))->toBe('en- us');
});

it('derives script hints only from the actual script or the registered default', function () {
    expect(LanguageTag::script('en-x-cyrl'))->toBe('Latn')
        ->and(LanguageTag::script('en-u-ca-gregory-x-arab'))->toBe('Latn')
        ->and(LanguageTag::script('x-latn'))->toBeNull()
        ->and(LanguageTag::script('und'))->toBeNull()
        ->and(LanguageTag::script('qaa'))->toBeNull()
        ->and(LanguageTag::script('am'))->toBe('Ethi')
        ->and(LanguageTag::script('sr-Latn-RS'))->toBe('Latn')
        ->and(LanguageTag::script('zh-cmn-Hans-CN'))->toBe('Hans')
        ->and(LanguageTag::script('iw'))->toBe('Hebr')
        ->and(LanguageTag::script('ja_JP'))->toBeNull();
});
