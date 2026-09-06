<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Script;
use Rankbeam\Seo\I18n\Truncator;

describe('Latin text (byte-identical to the previous word-boundary rule)', function () {
    it('leaves text at or under the limit untouched', function () {
        $text = str_repeat('a', 158).' b';

        expect(Truncator::truncate($text, 160))->toBe($text);
    });

    it('cuts at the last word boundary within the limit, without an ellipsis', function () {
        $text = trim(str_repeat('word6789 ', 30));
        $cut = Truncator::truncate($text, 160);

        expect(mb_strlen($cut))->toBeLessThanOrEqual(160)
            ->and($cut)->toEndWith('word6789')
            ->and($cut)->not->toContain('...')
            ->and($cut)->not->toEndWith(' ');
    });

    it('keeps a word that ends exactly at the limit', function () {
        $text = str_repeat('a', 155).' bbbb cccc';

        expect(Truncator::truncate($text, 160))->toBe(str_repeat('a', 155).' bbbb');
    });

    it('trims trailing punctuation left by the cut', function () {
        $prefix = str_repeat('x', 150);

        expect(Truncator::truncate($prefix.' yyyy, zzzzzzzzzzzzzzzzzzzz more', 160))->toBe($prefix.' yyyy');
    });

    it('hard-cuts when no acceptable word boundary exists', function () {
        expect(mb_strlen(Truncator::truncate(str_repeat('a', 300), 160)))->toBe(160);
    });

    it('hard-cuts when the only boundary is before 60% of the limit', function () {
        $cut = Truncator::truncate(str_repeat('a', 20).' '.str_repeat('b', 300), 160);

        expect(mb_strlen($cut))->toBe(160)->and($cut)->toEndWith('b');
    });

    it('counts multibyte characters, not bytes', function () {
        expect(mb_strlen(Truncator::truncate(str_repeat('à', 200), 160)))->toBe(160);
    });

    it('returns the text untouched for a non-positive limit', function () {
        expect(Truncator::truncate('hello world', 0))->toBe('hello world');
    });
});

describe('scripts without word spaces', function () {
    it('cuts Japanese at the last sentence mark inside the limit', function () {
        $sentence = '検索エンジン最適化はウェブサイトの可視性を高めます。'; // 26 graphemes
        $text = str_repeat($sentence, 5);

        $cut = Truncator::truncate($text, 80);

        // Three full sentences fit in 80 (78 graphemes); the 。 is trimmed as
        // trailing punctuation, like a Latin trailing period.
        expect($cut)->toBe(rtrim(str_repeat($sentence, 3), '。'))
            ->and(Script::length($cut))->toBeLessThanOrEqual(80);
    });

    it('cuts at a clause mark when no sentence ends inside the limit', function () {
        $text = str_repeat('漢', 60).'、'.str_repeat('字', 60);

        expect(Truncator::truncate($text, 80))->toBe(str_repeat('漢', 60));
    });

    it('hard-cuts Chinese with no punctuation at all', function () {
        $cut = Truncator::truncate(str_repeat('中', 120), 80);

        expect(Script::length($cut))->toBe(80);
    });

    it('ignores a clause mark that falls before 60% of the limit', function () {
        $text = str_repeat('漢', 10).'。'.str_repeat('字', 120);

        expect(Script::length(Truncator::truncate($text, 80)))->toBe(80);
    });

    it('uses spaces for Korean, which is CJK-width but spaced', function () {
        $word = '가나다라마'; // 5 graphemes
        $text = trim(str_repeat($word.' ', 40));

        $cut = Truncator::truncate($text, 80);

        expect(Script::length($cut))->toBeLessThanOrEqual(80)
            ->and($cut)->toEndWith($word)
            ->and($cut)->not->toEndWith(' ');
    });

    it('never splits a Thai grapheme cluster on a hard cut', function () {
        $province = 'นครราชสีมา'; // 9 graphemes, 10 codepoints (สี is one cluster)
        $text = str_repeat($province, 20);

        $cut = Truncator::truncate($text, 80);

        expect(Script::length($cut))->toBe(80)
            // 80 graphemes = 8 full repeats + 8 graphemes; the 8th grapheme
            // is "ม", right after the "สี" cluster — the vowel mark ี is never
            // separated from ส. A codepoint cut at 80 would have landed
            // inside the 8th repeat with a dangling mark.
            ->and($cut)->toBe(str_repeat($province, 8).'นครราชสีม')
            ->and(mb_strlen($cut))->toBe(89);
    });

    it('trims full-width trailing punctuation and the ideographic space', function () {
        expect(Truncator::truncate(str_repeat('漢', 78)."，\u{3000}".str_repeat('字', 30), 80))->toBe(str_repeat('漢', 78));
    });
});

describe('graphemes everywhere', function () {
    it('never separates an emoji skin-tone modifier from its base', function () {
        $wave = "\u{1F44B}\u{1F3FD}"; // one grapheme, 2 codepoints, on every PCRE2
        $text = str_repeat($wave, 30);

        $cut = Truncator::truncate($text, 20, Script::LATIN);

        expect($cut)->toBe(str_repeat($wave, 20));
    });

    it('never cuts inside an emoji ZWJ sequence, on every PCRE2 version', function () {
        $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";
        $text = str_repeat($family, 30);

        $cut = Truncator::truncate($text, 20, Script::LATIN);

        expect($cut)->toBe(str_repeat($family, 20))
            ->and(str_ends_with($cut, "\u{200D}"))->toBeFalse();
    });

    it('counts a run of plain emoji one by one when cutting', function () {
        expect(Truncator::truncate(str_repeat("\u{1F525}", 30), 20, Script::LATIN))->toBe(str_repeat("\u{1F525}", 20));
    });

    it('never separates a combining accent from its letter', function () {
        $text = str_repeat("e\u{0301}", 50);

        $cut = Truncator::truncate($text, 20, Script::LATIN);

        expect($cut)->toBe(str_repeat("e\u{0301}", 20))
            ->and(mb_substr($cut, -1))->toBe("\u{0301}");
    });

    it('accepts an explicit script and detects one otherwise', function () {
        $text = str_repeat('漢', 60).'。'.str_repeat('字', 60);

        expect(Truncator::truncate($text, 80))->toBe(str_repeat('漢', 60))
            ->and(Truncator::truncate($text, 80, Script::CJK))->toBe(str_repeat('漢', 60))
            // Forced Latin: no spaces to cut at, so a hard cut at 80.
            ->and(Script::length(Truncator::truncate($text, 80, Script::LATIN)))->toBe(80);
    });
});
