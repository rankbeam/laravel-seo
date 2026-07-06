<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageManager;
use Rankbeam\Seo\Services\OgImage\OgImageRenderException;
use Rankbeam\Seo\Tests\Support\FakeOgImageRenderer;

/*
 * Generator + manager tests. A fake renderer stands in for Browsershot so the
 * suite never needs a browser. Storage::fake gives each test an isolated disk.
 */
beforeEach(function () {
    Storage::fake('og_test', ['url' => 'http://localhost/og', 'visibility' => 'public']);
    config([
        'seo.og_image.enabled' => true,
        'seo.og_image.driver' => 'fake',
        'seo.og_image.disk' => 'og_test',
        'seo.og_image.path' => 'og-images',
        'seo.og_image.cache_version' => 1,
    ]);
    FakeOgImageRenderer::reset();
    app(OgImageManager::class)->extend('fake', fn () => new FakeOgImageRenderer);
});

function ogData(string $title = 'Hello World', ?string $site = 'rankbeam.dev'): SEOData
{
    return new SEOData(title: $title, ogSiteName: $site, locale: 'en');
}

function generator(): OgImageGenerator
{
    return app(OgImageGenerator::class);
}

describe('OgImageManager', function () {
    it('resolves the configured driver and rejects unknown ones', function () {
        $manager = app(OgImageManager::class);

        expect($manager->driver('fake'))->toBeInstanceOf(FakeOgImageRenderer::class);
        expect($manager->available())->toContain('fake')->toContain('browsershot');

        $manager->driver('nope');
    })->throws(InvalidArgumentException::class);
});

describe('OgImageGenerator::urlFor', function () {
    it('returns null when the image has not been generated yet', function () {
        expect(generator()->urlFor(ogData()))->toBeNull();
    });

    it('returns the url once the image exists, without rendering', function () {
        generator()->generate(ogData());
        FakeOgImageRenderer::reset();

        $url = generator()->urlFor(ogData());

        expect($url)->toContain('og-images/')->toEndWith('.png');
        expect(FakeOgImageRenderer::$calls)->toBe(0); // urlFor never renders
    });

    it('returns null when generation is disabled', function () {
        generator()->generate(ogData());
        config(['seo.og_image.enabled' => false]);

        expect(generator()->urlFor(ogData()))->toBeNull();
    });

    it('returns null when there is no renderable title', function () {
        expect(generator()->urlFor(ogData(title: '', site: 'x')))->toBeNull();
    });

    it('fails open (no throw) when the disk is misconfigured', function () {
        generator()->generate(ogData());
        config(['seo.og_image.disk' => 'does-not-exist']);

        expect(generator()->urlFor(ogData()))->toBeNull();
    });
});

describe('OgImageGenerator::generate', function () {
    it('renders once, stores the png, and returns its url', function () {
        $url = generator()->generate(ogData());

        expect($url)->toContain('og-images/')->toEndWith('.png');
        expect(FakeOgImageRenderer::$calls)->toBe(1);

        $path = 'og-images/'.generator()->cacheKey(ogData()).'.png';
        Storage::disk('og_test')->assertExists($path);
    });

    it('is idempotent — a second call reuses the cached file', function () {
        generator()->generate(ogData());
        generator()->generate(ogData());

        expect(FakeOgImageRenderer::$calls)->toBe(1);
    });

    it('re-renders when forced', function () {
        generator()->generate(ogData());
        generator()->generate(ogData(), force: true);

        expect(FakeOgImageRenderer::$calls)->toBe(2);
    });

    it('fails open (returns null) when the renderer throws', function () {
        FakeOgImageRenderer::$throw = true;

        expect(generator()->generate(ogData()))->toBeNull();
        Storage::disk('og_test')->assertMissing('og-images/'.generator()->cacheKey(ogData()).'.png');
    });

    it('re-throws when asked to (the warm command path)', function () {
        FakeOgImageRenderer::$throw = true;

        generator()->generate(ogData(), throwOnError: true);
    })->throws(OgImageRenderException::class);

    it('does nothing when disabled', function () {
        config(['seo.og_image.enabled' => false]);

        expect(generator()->generate(ogData()))->toBeNull();
        expect(FakeOgImageRenderer::$calls)->toBe(0);
    });

    it('forget() deletes a stored image', function () {
        generator()->generate(ogData());
        $path = 'og-images/'.generator()->cacheKey(ogData()).'.png';
        Storage::disk('og_test')->assertExists($path);

        generator()->forget(ogData());

        Storage::disk('og_test')->assertMissing($path);
    });
});

describe('OgImageGenerator::cacheKey', function () {
    it('is stable for identical content', function () {
        expect(generator()->cacheKey(ogData()))->toBe(generator()->cacheKey(ogData()));
    });

    it('changes with the title, the site name, and the cache_version', function () {
        $base = generator()->cacheKey(ogData(title: 'A'));

        expect(generator()->cacheKey(ogData(title: 'B')))->not->toBe($base);
        expect(generator()->cacheKey(ogData(title: 'A', site: 'other.dev')))->not->toBe($base);

        config(['seo.og_image.cache_version' => 2]);
        expect(generator()->cacheKey(ogData(title: 'A')))->not->toBe($base);
    });

    it('changes when the brand gradient changes', function () {
        $base = generator()->cacheKey(ogData());
        config(['seo.og_image.gradient_from' => '#000000']);

        expect(generator()->cacheKey(ogData()))->not->toBe($base);
    });

    it('changes with the locale (same title, different lang -> different card)', function () {
        $en = new SEOData(title: 'A', ogSiteName: 'x', locale: 'en');
        $de = new SEOData(title: 'A', ogSiteName: 'x', locale: 'de');

        expect(generator()->cacheKey($en))->not->toBe(generator()->cacheKey($de));
    });

    it('uses the og title in preference to the page title', function () {
        $withOg = new SEOData(title: 'Page Title', ogTitle: 'OG Title', ogSiteName: 'x');
        $plain = new SEOData(title: 'OG Title', ogSiteName: 'x');

        // Same effective title -> same hash (ogTitle wins on the first).
        expect(generator()->cacheKey($withOg))->toBe(generator()->cacheKey($plain));
    });
});
