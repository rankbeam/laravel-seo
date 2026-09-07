<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Hreflang;
use Rankbeam\Seo\I18n\LengthPolicy;
use Rankbeam\Seo\I18n\Script;

/*
|--------------------------------------------------------------------------
| The shipped languages, and what the package promises for each (M5)
|--------------------------------------------------------------------------
|
| One list, asserted rather than remembered: every locale the package ships
| strings for, with the script it is written in, the title budget that script
| gets and the hreflang code a Laravel locale maps to. TRANSLATING.md, the
| docs support matrix and the README all quote this list, so a language added
| to resources/lang without a row here (or a row without a language file)
| fails CI instead of shipping a half-supported language.
|
| Tier 1 = the ten launched 2026-09-05; Tier 2 = the seven added 2026-09-07.
|
*/

/** @var array<string, array{0: string, 1: string, 2: int}> locale => [hreflang, script, title budget] */
$languages = [
    // Tier 1
    'en' => ['en', Script::LATIN, 60],
    'it' => ['it', Script::LATIN, 60],
    'de' => ['de', Script::LATIN, 60],
    'fr' => ['fr', Script::LATIN, 60],
    'es' => ['es', Script::LATIN, 60],
    'pt_BR' => ['pt-BR', Script::LATIN, 60],
    'nl' => ['nl', Script::LATIN, 60],
    'tr' => ['tr', Script::LATIN, 60],
    'ru' => ['ru', Script::CYRILLIC, 60],
    'pl' => ['pl', Script::LATIN, 60],
    // Tier 2
    'ja' => ['ja', Script::CJK, 30],
    'zh_CN' => ['zh-CN', Script::CJK, 30],
    'zh_TW' => ['zh-TW', Script::CJK, 30],
    'ko' => ['ko', Script::CJK, 30],
    'el' => ['el', Script::GREEK, 60],
    'uk' => ['uk', Script::CYRILLIC, 60],
    'cs' => ['cs', Script::LATIN, 60],
];

it('ships a language file for exactly the languages it promises, and no others', function () use ($languages) {
    $dirs = array_map('basename', glob(dirname(__DIR__, 3).'/resources/lang/*', GLOB_ONLYDIR) ?: []);
    sort($dirs);
    $expected = array_keys($languages);
    sort($expected);

    expect($dirs)->toBe($expected)
        ->and($dirs)->toHaveCount(17);
});

it('maps every shipped locale to a valid hreflang code', function (string $locale, string $hreflang) {
    expect(Hreflang::fromLocale($locale))->toBe($hreflang)
        ->and(Hreflang::isValid($hreflang))->toBeTrue();
})->with(array_map(fn (string $l, array $row): array => [$l, $row[0]], array_keys($languages), $languages));

it('gives every shipped locale the length budget its script calls for', function (string $locale, string $script, int $titleMax) {
    // The budget an empty editor field starts from: the locale decides, since
    // there is no text to detect a script in yet.
    $policy = LengthPolicy::forLocale($locale);

    expect(Script::fromLocale($locale))->toBe($script)
        ->and($policy->script)->toBe($script)
        ->and($policy->titleMax)->toBe($titleMax);
})->with(array_map(fn (string $l, array $row): array => [$l, $row[1], $row[2]], array_keys($languages), $languages));

it('halves the budget for the CJK languages and keeps the Latin one for Greek and the Cyrillic pair', function () {
    // The visible consequence of the table above: a 45-character title is over
    // budget in Japanese and fine in Greek, Ukrainian or Czech, whose letters
    // are about as wide as Latin ones.
    $long = str_repeat('あ', 45);
    $greek = str_repeat('α', 45);

    expect(LengthPolicy::for($long, 'ja')->titleTooLong($long))->toBeTrue()
        ->and(LengthPolicy::for($greek, 'el')->titleTooLong($greek))->toBeFalse()
        ->and(LengthPolicy::forLocale('uk')->titleMax)->toBe(LengthPolicy::default()->titleMax)
        ->and(LengthPolicy::forLocale('cs')->titleMax)->toBe(LengthPolicy::default()->titleMax);
});

it('reads a Traditional Chinese title as CJK whatever subtag form the app uses', function (string $locale) {
    expect(Script::fromLocale($locale))->toBe(Script::CJK)
        ->and(LengthPolicy::forLocale($locale)->titleMax)->toBe(30);
})->with(['zh_TW', 'zh-Hant', 'zh-Hant-TW', 'zh_Hant_HK', 'zh_CN', 'zh-Hans-CN']);
