<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;

/**
 * End-to-end smoke test for the Browsershot OG-image renderer against the same
 * hard fixtures as the P6 spike (Italian accents, CJK, long-title truncation),
 * plus — since M2 of the multilingual program — one fixture per non-Latin
 * script (ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he, hi) with a
 * **tofu check**: the card is rendered twice, once with the real title and
 * once with a control title of the same length made of an unassigned code
 * point (U+0378, which no font has a glyph for). If the host has no font for
 * the script, both renders draw identical .notdef boxes and the PNGs come out
 * byte-identical — and the test fails, naming the script and the package to
 * install. That is the "renders every glyph on a fresh server" exit test.
 *
 * SKIPPED by default — it needs a real Chrome + puppeteer, which CI does not
 * have. Run it locally with:
 *
 *   SEO_OG_IMAGE_LIVE_TEST=1 \
 *   SEO_OG_IMAGE_CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe" \
 *   SEO_OG_IMAGE_NODE_MODULES="/path/to/node_modules" \
 *   vendor/bin/pest --filter=BrowsershotSmoke
 *
 * The generated PNGs are copied to SEO_OG_IMAGE_OUT_DIR (if set) for inspection.
 */
$live = getenv('SEO_OG_IMAGE_LIVE_TEST') === '1';

$fixtures = [
    'it_accents' => ['it', 'Perché la velocità del sito è così importante nel 2026'],
    'cjk' => ['ja', '検索エンジン最適化の完全ガイド：日本語のタイトル折り返しの検証'],
    'long_truncate' => ['en', 'The Complete, Exhaustive, and Definitive Guide to Everything You Have Ever Wanted to Know About Technical Search Engine Optimization, Structured Data, and Core Web Vitals in the Modern Web'],
];

/**
 * Per-script glyph fixtures: locale, title, and whether the bundled font
 * already covers the script (Latin, Cyrillic, Greek) — those must pass on any
 * host; the others need a system font and fail with an install hint.
 */
$scripts = [
    'ja' => ['ja', '検索エンジン最適化はウェブサイトの可視性を高めます', 'fonts-noto-cjk'],
    'zh_hans' => ['zh_CN', '搜索引擎优化完整指南：标题与描述的长度', 'fonts-noto-cjk'],
    'zh_hant' => ['zh_TW', '搜尋引擎最佳化完整指南：標題與描述的長度', 'fonts-noto-cjk'],
    'ko' => ['ko', '검색 엔진 최적화 완벽 가이드: 제목과 설명의 길이', 'fonts-noto-cjk'],
    'el' => ['el', 'Πλήρης οδηγός SEO για Laravel: τίτλοι και περιγραφές', null],
    'ru' => ['ru', 'Полное руководство по SEO для Laravel: заголовки и описания', null],
    'tr' => ['tr', 'İstanbul için eksiksiz SEO rehberi: başlıklar ve açıklamalar', null],
    'uk' => ['uk', 'Повний посібник із SEO для Laravel: заголовки, описи та ґрунтовні поради', null],
    'cs' => ['cs', 'Kompletní průvodce SEO pro Laravel: titulky, popisy a řešení chyb', null],
    'th' => ['th', 'คู่มือ SEO ฉบับสมบูรณ์สำหรับ Laravel: ชื่อเรื่องและคำอธิบาย', 'fonts-noto-core'],
    'ar' => ['ar', 'الدليل الكامل لتحسين محركات البحث: العناوين والأوصاف', 'fonts-noto-core'],
    'he' => ['he', 'המדריך המלא לקידום אתרים: כותרות ותיאורים', 'fonts-noto-core'],
    'hi' => ['hi', 'लारवेल एसईओ की पूरी गाइड: शीर्षक और विवरण', 'fonts-noto-core'],
];

function ogSmokeConfigure(): void
{
    $dir = rtrim(sys_get_temp_dir(), '/\\').'/seo-og-smoke';
    @mkdir($dir, 0777, true);

    config([
        'seo.og_image.enabled' => true,
        'seo.og_image.driver' => 'browsershot',
        'seo.og_image.chrome_path' => getenv('SEO_OG_IMAGE_CHROME_PATH') ?: null,
        'seo.og_image.node_binary' => getenv('SEO_OG_IMAGE_NODE_BINARY') ?: null,
        'seo.og_image.npm_module_path' => getenv('SEO_OG_IMAGE_NODE_MODULES') ?: null,
        'seo.og_image.no_sandbox' => getenv('SEO_OG_IMAGE_NO_SANDBOX') === '1',
        'seo.og_image.disk' => 'og_smoke',
        'seo.title_suffix' => '',
        'filesystems.disks.og_smoke' => [
            'driver' => 'local',
            'root' => $dir,
            'url' => 'http://localhost/og',
            'visibility' => 'public',
        ],
    ]);
}

/**
 * Render a card and return [absolute png path, width, height].
 *
 * @return array{0: string, 1: int, 2: int}
 */
function ogSmokeRender(OgImageGenerator $generator, SEOData $data, string $id): array
{
    $url = $generator->generate($data, force: true);

    expect($url)->not->toBeNull("generate() returned null for {$id} — render failed");

    $stored = 'og-images/'.$generator->cacheKey($data).'.png';
    $disk = Storage::disk('og_smoke');
    expect($disk->exists($stored))->toBeTrue();

    $full = $disk->path($stored);
    [$w, $h] = getimagesize($full);

    if ($out = getenv('SEO_OG_IMAGE_OUT_DIR')) {
        @mkdir($out, 0777, true);
        @copy($full, rtrim($out, '/\\')."/{$id}.png");
    }

    return [$full, $w, $h];
}

foreach ($fixtures as $id => [$locale, $title]) {
    test("Browsershot smoke renders {$id} at 1200x630", function () use ($id, $locale, $title) {
        ogSmokeConfigure();

        [, $w, $h] = ogSmokeRender(app(OgImageGenerator::class), new SEOData(title: $title, ogSiteName: 'rankbeam.dev', locale: $locale), $id);

        expect($w)->toBe(1200)->and($h)->toBe(630);
    })->skip(! $live, 'Set SEO_OG_IMAGE_LIVE_TEST=1 (needs Chrome + puppeteer).');
}

foreach ($scripts as $id => [$locale, $title, $package]) {
    test("Browsershot smoke draws real glyphs for {$id}, not tofu", function () use ($id, $locale, $title, $package) {
        ogSmokeConfigure();
        $generator = app(OgImageGenerator::class);

        // The control: same grapheme count, every character an unassigned
        // code point → guaranteed .notdef boxes in any font.
        $control = str_repeat("\u{0378}", mb_strlen($title));

        [$real, $w, $h] = ogSmokeRender($generator, new SEOData(title: $title, ogSiteName: 'rankbeam.dev', locale: $locale), $id);
        [$boxes] = ogSmokeRender($generator, new SEOData(title: $control, ogSiteName: 'rankbeam.dev', locale: $locale), "{$id}_control");

        $hint = $package ? " Install one: apt-get install {$package}." : '';

        expect($w)->toBe(1200)
            ->and($h)->toBe(630)
            ->and(hash_file('sha256', $real))->not->toBe(
                hash_file('sha256', $boxes),
                "The {$id} card rendered as tofu boxes — no installed font covers this script.{$hint}",
            );
    })->skip(! $live, 'Set SEO_OG_IMAGE_LIVE_TEST=1 (needs Chrome + puppeteer).');
}
