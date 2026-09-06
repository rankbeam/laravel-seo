<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Script;

describe('Script::detect', function () {
    it('detects the dominant script of a title', function (string $text, string $expected) {
        expect(Script::detect($text))->toBe($expected);
    })->with([
        'latin' => ['The complete guide to Laravel SEO', Script::LATIN],
        'latin with accents' => ['Perché la velocità del sito è così importante', Script::LATIN],
        'japanese' => ['検索エンジン最適化の完全ガイド', Script::CJK],
        'simplified chinese' => ['搜索引擎优化完整指南', Script::CJK],
        'korean' => ['검색 엔진 최적화 완벽 가이드', Script::CJK],
        'cyrillic' => ['Полное руководство по SEO для Laravel', Script::CYRILLIC],
        'greek' => ['Πλήρης οδηγός SEO για Laravel', Script::GREEK],
        'thai' => ['คู่มือ SEO ฉบับสมบูรณ์สำหรับ Laravel', Script::THAI],
        'arabic' => ['الدليل الكامل لتحسين محركات البحث', Script::ARABIC],
        'hebrew' => ['המדריך המלא לקידום אתרים', Script::HEBREW],
        'devanagari' => ['लारवेल एसईओ की पूरी गाइड', Script::DEVANAGARI],
    ]);

    it('weights full-width CJK glyphs double so a mixed title resolves to the wider script', function () {
        // 11 Latin letters vs 6 CJK glyphs (×2 = 12).
        expect(Script::detect('Laravel SEO の完全ガイド'))->toBe(Script::CJK)
            // 20 Latin letters vs 3 CJK glyphs (×2 = 6).
            ->and(Script::detect('Laravel SEO for the 東京 developer'))->toBe(Script::LATIN);
    });

    it('falls back to the locale for text without letters, then to Latin', function () {
        expect(Script::detect('2026', 'ja'))->toBe(Script::CJK)
            ->and(Script::detect('', 'ru'))->toBe(Script::CYRILLIC)
            ->and(Script::detect(null, 'th'))->toBe(Script::THAI)
            ->and(Script::detect('42'))->toBe(Script::LATIN)
            ->and(Script::detect(null))->toBe(Script::LATIN);
    });

    it('uses the locale to break an exact tie', function () {
        // One Latin letter vs one Cyrillic letter.
        expect(Script::detect('a я', 'ru'))->toBe(Script::CYRILLIC)
            ->and(Script::detect('a я', 'en'))->toBe(Script::LATIN);
    });
});

describe('Script::fromLocale', function () {
    it('maps language subtags to script buckets', function (string $locale, ?string $expected) {
        expect(Script::fromLocale($locale))->toBe($expected);
    })->with([
        ['ja', Script::CJK],
        ['zh_CN', Script::CJK],
        ['zh-Hant-TW', Script::CJK],
        ['ko_KR', Script::CJK],
        ['ru', Script::CYRILLIC],
        ['uk-UA', Script::CYRILLIC],
        ['el', Script::GREEK],
        ['th', Script::THAI],
        ['ar_SA', Script::ARABIC],
        ['fa', Script::ARABIC],
        ['he', Script::HEBREW],
        ['hi', Script::DEVANAGARI],
        ['en_US', Script::LATIN],
        ['pt_BR', Script::LATIN],
        ['tr', Script::LATIN],
        ['sr', Script::CYRILLIC],
        ['sr-Latn', Script::LATIN],
        ['', null],
        ['  ', null],
        ['123', null],
    ]);
});

describe('Script::length and graphemes', function () {
    it('counts user-perceived characters, not codepoints or bytes', function () {
        // Thai: 10 codepoints, 9 graphemes — the nonspacing vowel ี attaches
        // to ส (UAX #29); the spacing vowels า are letters of their own.
        expect(Script::length('นครราชสีมา'))->toBe(9)
            ->and(Script::length(str_repeat('สี', 60)))->toBe(60)
            // Decomposed é (e + combining acute) is ONE grapheme.
            ->and(Script::length("e\u{0301}"))->toBe(1)
            // Emoji with a skin-tone modifier is ONE grapheme on every PCRE2.
            ->and(Script::length("\u{1F44B}\u{1F3FD}"))->toBe(1)
            // Plain Latin equals mb_strlen.
            ->and(Script::length('Hello, world'))->toBe(mb_strlen('Hello, world'))
            ->and(Script::length(''))->toBe(0)
            ->and(Script::length(null))->toBe(0);
    });

    it('survives long emoji runs where the PCRE JIT stack would overflow', function () {
        // \X under the JIT blows its stack after a few dozen emoji and returns
        // false — which used to degrade silently to a codepoint count.
        $waves = str_repeat("\u{1F44B}\u{1F3FD}", 500);
        $families = str_repeat("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}", 300);

        expect(Script::length($waves))->toBe(500)
            ->and(count(Script::graphemes($waves)))->toBe(500)
            ->and(Script::length($families))->toBe(Script::length("\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}") * 300)
            ->and(implode('', Script::graphemes($families)))->toBe($families);
    });

    it('splits into graphemes that reassemble to the original', function () {
        $text = "นครราชสีมา e\u{0301} 東京 \u{1F44B}\u{1F3FD}";
        $graphemes = Script::graphemes($text);

        expect(implode('', $graphemes))->toBe($text)
            ->and(count($graphemes))->toBe(Script::length($text))
            ->and(Script::graphemes(''))->toBe([]);
    });

    it('knows which scripts separate words with spaces', function () {
        expect(Script::usesWordSpaces(Script::LATIN))->toBeTrue()
            ->and(Script::usesWordSpaces(Script::CYRILLIC))->toBeTrue()
            ->and(Script::usesWordSpaces(Script::CJK))->toBeFalse()
            ->and(Script::usesWordSpaces(Script::THAI))->toBeFalse();
    });

    it('lists every bucket exactly once', function () {
        expect(Script::all())->toBe([
            Script::CJK, Script::LATIN, Script::CYRILLIC, Script::GREEK,
            Script::THAI, Script::ARABIC, Script::HEBREW, Script::DEVANAGARI,
        ]);
    });
});
