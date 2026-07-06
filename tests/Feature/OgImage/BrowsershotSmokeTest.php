<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;

/**
 * End-to-end smoke test for the Browsershot OG-image renderer against the same
 * hard fixtures as the P6 spike (Italian accents, CJK, long-title truncation).
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
    'it_accents' => 'Perché la velocità del sito è così importante nel 2026',
    'cjk' => '検索エンジン最適化の完全ガイド：日本語のタイトル折り返しの検証',
    'long_truncate' => 'The Complete, Exhaustive, and Definitive Guide to Everything You Have Ever Wanted to Know About Technical Search Engine Optimization, Structured Data, and Core Web Vitals in the Modern Web',
];

foreach ($fixtures as $id => $title) {
    test("Browsershot smoke renders {$id} at 1200x630", function () use ($id, $title) {
        $dir = rtrim(sys_get_temp_dir(), '/\\').'/seo-og-smoke';
        @mkdir($dir, 0777, true);

        config([
            'seo.og_image.enabled' => true,
            'seo.og_image.driver' => 'browsershot',
            'seo.og_image.chrome_path' => getenv('SEO_OG_IMAGE_CHROME_PATH') ?: null,
            'seo.og_image.node_binary' => getenv('SEO_OG_IMAGE_NODE_BINARY') ?: null,
            'seo.og_image.npm_module_path' => getenv('SEO_OG_IMAGE_NODE_MODULES') ?: null,
            'seo.og_image.disk' => 'og_smoke',
            'filesystems.disks.og_smoke' => [
                'driver' => 'local',
                'root' => $dir,
                'url' => 'http://localhost/og',
                'visibility' => 'public',
            ],
        ]);

        $generator = app(OgImageGenerator::class);
        $data = new SEOData(title: $title, ogSiteName: 'rankbeam.dev', locale: 'it');

        $url = $generator->generate($data, force: true);

        expect($url)->not->toBeNull("generate() returned null for {$id} — render failed");

        // Locate the stored PNG and verify real 1200x630 dimensions.
        $stored = 'og-images/'.$generator->cacheKey($data).'.png';
        $disk = Storage::disk('og_smoke');
        expect($disk->exists($stored))->toBeTrue();

        $full = $disk->path($stored);
        [$w, $h] = getimagesize($full);
        expect($w)->toBe(1200);
        expect($h)->toBe(630);

        // Copy out for visual inspection, if requested.
        if ($out = getenv('SEO_OG_IMAGE_OUT_DIR')) {
            @mkdir($out, 0777, true);
            @copy($full, rtrim($out, '/\\')."/{$id}.png");
        }
    })->skip(! $live, 'Set SEO_OG_IMAGE_LIVE_TEST=1 (needs Chrome + puppeteer).');
}
