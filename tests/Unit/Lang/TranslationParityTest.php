<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

/*
 * Translation contract for the core package.
 *
 * - Every language file carries exactly the keys `en` carries (parity), so a
 *   translator cannot ship a half-translated file and a new English string
 *   cannot land without every language noticing in CI.
 * - Every `seo::seo.*` key referenced from src/ exists in `en`, so a typo in
 *   a key renders text, never the raw key.
 * - Switching the app locale switches the messages the package emits.
 */

$langDir = dirname(__DIR__, 3).'/resources/lang';

function seoFlattenLang(array $lines, string $prefix = ''): array
{
    $flat = [];
    foreach ($lines as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $flat += seoFlattenLang($value, $full);
        } else {
            $flat[$full] = $value;
        }
    }

    return $flat;
}

it('keeps every language file in parity with en', function () use ($langDir) {
    $en = seoFlattenLang(require $langDir.'/en/seo.php');
    expect($en)->not->toBeEmpty();

    foreach (glob($langDir.'/*/seo.php') ?: [] as $file) {
        $locale = basename(dirname($file));
        $lines = seoFlattenLang(require $file);

        $missing = array_diff_key($en, $lines);
        $orphans = array_diff_key($lines, $en);

        expect($missing)->toBe([], "[{$locale}] missing keys: ".implode(', ', array_keys($missing)));
        expect($orphans)->toBe([], "[{$locale}] keys not in en: ".implode(', ', array_keys($orphans)));

        foreach ($lines as $key => $value) {
            expect(is_string($value) && trim($value) !== '')->toBeTrue("[{$locale}] {$key} is empty");
            // A placeholder present in en must survive translation.
            preg_match_all('/:[a-z_]+/', (string) $en[$key], $expected);
            foreach ($expected[0] as $placeholder) {
                expect(str_contains((string) $value, $placeholder))->toBeTrue("[{$locale}] {$key} lost placeholder {$placeholder}");
            }
        }
    }
});

it('references only keys that exist in en', function () use ($langDir) {
    $en = seoFlattenLang(require $langDir.'/en/seo.php');
    $src = dirname(__DIR__, 3).'/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
    $referenced = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        preg_match_all("/__\\('seo::seo\\.([a-z0-9_.]+)'/", (string) file_get_contents($file->getPathname()), $m);
        foreach ($m[1] as $key) {
            $referenced[$key] = true;
        }
    }

    expect($referenced)->not->toBeEmpty();

    foreach (array_keys($referenced) as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("src references seo::seo.{$key} which is not in resources/lang/en/seo.php");
    }
});

it('emits messages in the app locale', function () {
    Lang::addLines(['seo.warnings.title_is_fallback' => 'Nessun titolo SEO impostato.'], 'it', 'seo');

    App::setLocale('it');
    $warnings = app(SEOWarningEvaluator::class)->evaluateTitle('Hello', null);
    App::setLocale('en');

    expect(collect($warnings)->firstWhere('key', 'title_is_fallback')['message'])
        ->toBe('Nessun titolo SEO impostato.');

    // English is untouched and the placeholders are filled.
    $long = app(SEOWarningEvaluator::class)->evaluateTitle(str_repeat('a', 70), 'x');
    expect(collect($long)->firstWhere('key', 'title_too_long')['message'])
        ->toBe('The title is 70 characters long (recommended max: 60). It may be truncated on Google.');
});
