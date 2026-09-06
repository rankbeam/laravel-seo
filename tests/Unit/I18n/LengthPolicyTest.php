<?php

declare(strict_types=1);

use Rankbeam\Seo\Auditing\MetadataIssues;
use Rankbeam\Seo\I18n\LengthPolicy;
use Rankbeam\Seo\I18n\Script;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

beforeEach(function () {
    config(['seo.length_policy' => null]);
});

describe('built-in limits', function () {
    it('keeps the historical Latin budget as the default row', function () {
        $policy = LengthPolicy::default();

        expect($policy->script)->toBe('default')
            ->and($policy->titleMax)->toBe(SEOWarningEvaluator::TITLE_MAX_LENGTH)
            ->and($policy->descriptionMax)->toBe(SEOWarningEvaluator::DESCRIPTION_MAX_LENGTH)
            ->and($policy->titleMin)->toBe(MetadataIssues::TITLE_MIN_LENGTH)
            ->and($policy->descriptionMin)->toBe(MetadataIssues::DESCRIPTION_MIN_LENGTH)
            ->and($policy->isDefault)->toBeTrue();
    });

    it('halves the budget for CJK even with the config unpublished', function () {
        $policy = LengthPolicy::forScript(Script::CJK);

        expect($policy->titleMax)->toBe(30)
            ->and($policy->descriptionMax)->toBe(80)
            ->and($policy->titleMin)->toBe(15)
            ->and($policy->descriptionMin)->toBe(35)
            ->and($policy->isDefault)->toBeFalse();
    });

    it('gives every unlisted script the default row', function () {
        foreach ([Script::LATIN, Script::CYRILLIC, Script::GREEK, Script::THAI, Script::ARABIC, Script::HEBREW, Script::DEVANAGARI] as $script) {
            $policy = LengthPolicy::forScript($script);

            expect($policy->script)->toBe($script)
                ->and($policy->titleMax)->toBe(60)
                ->and($policy->descriptionMax)->toBe(160)
                ->and($policy->isDefault)->toBeTrue();
        }
    });
});

describe('detection', function () {
    it('picks the policy from the text', function () {
        expect(LengthPolicy::for('検索エンジン最適化の完全ガイド')->titleMax)->toBe(30)
            ->and(LengthPolicy::for('The complete guide to SEO')->titleMax)->toBe(60)
            ->and(LengthPolicy::for('Полное руководство')->script)->toBe(Script::CYRILLIC);
    });

    it('uses the locale when the text has no letters', function () {
        expect(LengthPolicy::for('', 'ja')->titleMax)->toBe(30)
            ->and(LengthPolicy::for(null, 'en')->titleMax)->toBe(60)
            ->and(LengthPolicy::forLocale('zh_CN')->titleMax)->toBe(30)
            ->and(LengthPolicy::forLocale('it_IT')->titleMax)->toBe(60)
            ->and(LengthPolicy::forLocale(null)->script)->toBe(Script::LATIN);
    });

    it('is the W2 exit test: a 45-character Japanese title is too long, the same length in Latin is not', function () {
        $japanese = mb_substr(str_repeat('検索エンジン最適化の完全ガイド', 4), 0, 45);
        $latin = str_repeat('a', 45);

        expect(mb_strlen($japanese))->toBe(45)
            ->and(LengthPolicy::for($japanese)->titleTooLong($japanese))->toBeTrue()
            ->and(LengthPolicy::for($latin)->titleTooLong($latin))->toBeFalse();
    });

    it('measures graphemes so combining marks do not inflate the count', function () {
        $thai = str_repeat('สี', 60); // 120 codepoints, 60 graphemes
        $policy = LengthPolicy::for($thai);

        expect(mb_strlen($thai))->toBe(120)
            ->and($policy->length($thai))->toBe(60)
            ->and($policy->titleTooLong($thai))->toBeFalse()
            ->and($policy->titleTooLong($thai.'x'))->toBeTrue();
    });
});

describe('config', function () {
    it('lets a row override some keys and inherit the rest from default', function () {
        config(['seo.length_policy' => ['thai' => ['title_max' => 55]]]);

        $thai = LengthPolicy::forScript(Script::THAI);

        expect($thai->titleMax)->toBe(55)
            ->and($thai->descriptionMax)->toBe(160)
            ->and($thai->titleMin)->toBe(30)
            ->and($thai->isDefault)->toBeFalse();
    });

    it('lets default be changed and flows it into the built-in cjk row only where cjk is silent', function () {
        config(['seo.length_policy' => ['default' => ['title_max' => 65, 'description_max' => 155]]]);

        expect(LengthPolicy::default()->titleMax)->toBe(65)
            ->and(LengthPolicy::forScript(Script::LATIN)->descriptionMax)->toBe(155)
            // cjk keeps its own built-in values.
            ->and(LengthPolicy::forScript(Script::CJK)->titleMax)->toBe(30);
    });

    it('overrides the built-in cjk row', function () {
        config(['seo.length_policy' => ['cjk' => ['title_max' => 32, 'description_max' => 120]]]);

        $cjk = LengthPolicy::forScript(Script::CJK);

        expect($cjk->titleMax)->toBe(32)
            ->and($cjk->descriptionMax)->toBe(120)
            ->and($cjk->titleMin)->toBe(15);
    });

    it('ignores junk values and never lets a minimum exceed its maximum', function () {
        config(['seo.length_policy' => [
            'default' => ['title_max' => 'lots', 'title_min' => -4, 'description_max' => 0],
            'greek' => ['title_min' => 80, 'title_max' => 50],
            'nope' => 'not-a-row',
        ]]);

        $default = LengthPolicy::default();
        $greek = LengthPolicy::forScript(Script::GREEK);

        expect($default->titleMax)->toBe(60)
            ->and($default->titleMin)->toBe(30)
            ->and($default->descriptionMax)->toBe(160)
            ->and($greek->titleMax)->toBe(50)
            ->and($greek->titleMin)->toBe(50);
    });

    it('survives a non-array config value', function () {
        config(['seo.length_policy' => 'oops']);

        expect(LengthPolicy::default()->titleMax)->toBe(60);
    });
});

describe('scaleDescription', function () {
    it('returns the configured Latin budget unchanged for the default row', function () {
        expect(LengthPolicy::default()->scaleDescription(155))->toBe(155)
            ->and(LengthPolicy::forScript(Script::THAI)->scaleDescription(155))->toBe(155);
    });

    it('scales the budget by the script ratio', function () {
        expect(LengthPolicy::forScript(Script::CJK)->scaleDescription(160))->toBe(80)
            ->and(LengthPolicy::forScript(Script::CJK)->scaleDescription(155))->toBe(78)
            ->and(LengthPolicy::forScript(Script::CJK)->scaleDescription(1))->toBe(1);
    });

    it('exports its values as an array', function () {
        expect(LengthPolicy::forScript(Script::CJK)->toArray())->toBe([
            'script' => 'cjk',
            'title_min' => 15,
            'title_max' => 30,
            'description_min' => 35,
            'description_max' => 80,
        ]);
    });
});
