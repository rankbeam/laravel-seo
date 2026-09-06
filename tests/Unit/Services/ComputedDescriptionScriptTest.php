<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Script;
use Rankbeam\Seo\Services\SEOComputedBuilder;

/*
|--------------------------------------------------------------------------
| Computed-description truncation is script-aware (M2)
|--------------------------------------------------------------------------
|
| The configured description_max_length stays a Latin budget; the builder
| scales it per script and cuts at a sentence mark for text without spaces.
|
*/

beforeEach(function () {
    config([
        'seo.computed.description_fields' => ['description'],
        'seo.computed.description_max_length' => 160,
        'seo.length_policy' => null,
    ]);
});

function scriptBuilder(): SEOComputedBuilder
{
    return app(SEOComputedBuilder::class);
}

it('halves the budget for a Japanese description and cuts at a sentence mark', function () {
    $sentence = '検索エンジン最適化はウェブサイトの可視性を高めます。'; // 26 graphemes
    $model = createMockModel(['description' => str_repeat($sentence, 10)]);

    $seo = scriptBuilder()->fromModel($model, 'ja');

    expect(Script::length($seo->description))->toBeLessThanOrEqual(80)
        ->and($seo->description)->toBe(rtrim(str_repeat($sentence, 3), '。'));
});

it('scales a custom Latin budget for CJK', function () {
    config(['seo.computed.description_max_length' => 100]);

    $seo = scriptBuilder()->fromModel(createMockModel(['description' => str_repeat('中', 300)]), 'zh');

    expect(Script::length($seo->description))->toBe(50);
});

it('leaves the Latin behaviour byte-identical', function () {
    $text = trim(str_repeat('word6789 ', 30));

    $seo = scriptBuilder()->fromModel(createMockModel(['description' => $text]), 'en');

    expect(mb_strlen($seo->description))->toBeLessThanOrEqual(160)
        ->and($seo->description)->toEndWith('word6789')
        ->and($seo->description)->not->toContain('...');
});

it('never cuts inside a Thai grapheme cluster', function () {
    $province = 'นครราชสีมา';
    $seo = scriptBuilder()->fromModel(createMockModel(['description' => str_repeat($province, 40)]), 'th');

    // Thai uses the default (Latin-size) budget of 160 graphemes: 17 full
    // repeats (9 graphemes each) + 7 — the 7th grapheme is the "สี" cluster,
    // kept whole.
    expect(Script::length($seo->description))->toBe(160)
        ->and($seo->description)->toBe(str_repeat($province, 17).'นครราชสี');
});

it('strips HTML and decodes entities before measuring CJK text', function () {
    $seo = scriptBuilder()->fromModel(createMockModel(['description' => '<p>東京&amp;大阪</p>']), 'ja');

    expect($seo->description)->toBe('東京&大阪');
});
