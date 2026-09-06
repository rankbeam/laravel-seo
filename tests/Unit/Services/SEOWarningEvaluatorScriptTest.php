<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

/*
|--------------------------------------------------------------------------
| Editor warnings are script-aware (M2)
|--------------------------------------------------------------------------
|
| The 60/160 thresholds are the Latin budget. The evaluator asks the
| LengthPolicy for the budget of the script in front of it, so a Japanese
| title is judged against ~30 and counted in graphemes.
|
*/

function scriptEvaluator(): SEOWarningEvaluator
{
    return new SEOWarningEvaluator;
}

function scriptWarningKeys(array $warnings): array
{
    return array_column($warnings, 'key');
}

beforeEach(function () {
    config(['seo.length_policy' => null]);
});

it('warns on a 45-character Japanese title but not on a 45-character Latin one', function () {
    $japanese = mb_substr(str_repeat('検索エンジン最適化の完全ガイド', 4), 0, 45);
    $latin = str_repeat('a', 45);

    $ja = scriptEvaluator()->evaluateTitle($japanese, 'manual');
    $la = scriptEvaluator()->evaluateTitle($latin, 'manual');

    expect(scriptWarningKeys($ja))->toContain('title_too_long')
        ->and($ja[0]['message'])->toContain('45')
        ->and($ja[0]['message'])->toContain('30')
        ->and(scriptWarningKeys($la))->not->toContain('title_too_long');
});

it('does not warn at exactly the CJK budget', function () {
    $title = mb_substr(str_repeat('検索エンジン最適化の完全ガイド', 3), 0, 30);

    expect(scriptWarningKeys(scriptEvaluator()->evaluateTitle($title, 'manual')))->not->toContain('title_too_long');
});

it('warns on a 100-character Chinese description against the 80 budget', function () {
    $description = str_repeat('搜索引擎优化', 17); // 102 graphemes

    $warnings = scriptEvaluator()->evaluateDescription($description, 'manual');

    expect(scriptWarningKeys($warnings))->toContain('description_too_long')
        ->and($warnings[0]['message'])->toContain('102')
        ->and($warnings[0]['message'])->toContain('80');
});

it('counts graphemes, so Thai vowel marks do not trigger a false warning', function () {
    $thai = str_repeat('สี', 60); // 120 codepoints, 60 graphemes

    expect(mb_strlen($thai))->toBe(120)
        ->and(scriptWarningKeys(scriptEvaluator()->evaluateTitle($thai, 'manual')))->not->toContain('title_too_long');
});

it('reads the locale from the resolved data through evaluate()', function () {
    $resolved = new SEOData(title: str_repeat('日', 31), description: 'short', ogImage: '/x.png', locale: 'ja');

    expect(scriptWarningKeys(scriptEvaluator()->evaluate($resolved, $resolved)))->toContain('title_too_long');
});

it('honours a configured per-script row', function () {
    config(['seo.length_policy' => ['cjk' => ['title_max' => 50]]]);

    $title = str_repeat('日', 45);

    expect(scriptWarningKeys(scriptEvaluator()->evaluateTitle($title, 'manual')))->not->toContain('title_too_long');
});
