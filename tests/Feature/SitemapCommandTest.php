<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    config(['seo.sitemap.disk' => 'public']);
    config(['seo.sitemap.path' => 'sitemap.xml']);
    config(['seo.sitemap.models' => []]);

    // Start from a clean slate: neither key set explicitly.
    config(['seo.sitemap.enabled' => null]);
    config(['seo.features.sitemap' => true]);
});

it('disables generation via the canonical features.sitemap key', function () {
    // The env var SEO_SITEMAP_ENABLED wires features.sitemap — the command must
    // honour it. Previously it read the nonexistent seo.sitemap.enabled, so
    // SEO_SITEMAP_ENABLED=false was a silent no-op.
    config(['seo.features.sitemap' => false]);

    $exit = Artisan::call('seo:sitemap');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('disabled');
});

it('generates when features.sitemap is enabled', function () {
    config(['seo.features.sitemap' => true]);

    $exit = Artisan::call('seo:sitemap');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('generated successfully');
});

it('still honours an explicit seo.sitemap.enabled override for back-compat', function () {
    // A host that set the old key wins over the feature flag.
    config(['seo.features.sitemap' => true]);
    config(['seo.sitemap.enabled' => false]);

    $exit = Artisan::call('seo:sitemap');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('disabled');
});
