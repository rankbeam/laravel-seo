<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Fibonoir\LaravelSEO\Http\Middleware\RedirectMiddleware;
use Fibonoir\LaravelSEO\Jobs\IncrementRedirectHitJob;
use Fibonoir\LaravelSEO\Models\SEORedirect;

beforeEach(function () {
    // Clear any cached redirects
    Cache::flush();

    // Register test routes with middleware
    Route::middleware([RedirectMiddleware::class])->group(function () {
        Route::get('/test-page', fn () => 'Test Page')->name('test.page');
        Route::get('/new-page', fn () => 'New Page')->name('new.page');
        Route::get('/blog/{slug}', fn ($slug) => "Blog: {$slug}")->name('blog.show');
    });

    // Enable redirects feature
    config(['seo.features.redirects' => true]);
    config(['seo.redirects.cache_enabled' => false]); // Disable cache for tests
    config(['seo.redirects.log_hits' => true]);
});

describe('RedirectMiddleware', function () {
    it('redirects matching path', function () {
        SEORedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page');

        $response->assertRedirect('/new-page');
        $response->assertStatus(301);
    });

    it('redirects with regex', function () {
        SEORedirect::create([
            'source_path' => '/old-blog/(.*)',
            'target_url' => '/blog/$1',
            'status_code' => 301,
            'is_regex' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/old-blog/my-post-slug');

        $response->assertRedirect('/blog/my-post-slug');
    });

    it('handles regex with multiple capture groups', function () {
        SEORedirect::create([
            'source_path' => '/products/(\d+)/(.*)',
            'target_url' => '/shop/$1-$2',
            'status_code' => 301,
            'is_regex' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/products/123/widget-name');

        $response->assertRedirect('/shop/123-widget-name');
    });

    it('preserves query string', function () {
        SEORedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'preserve_query' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page?ref=google&utm_source=test');

        $response->assertRedirect('/new-page?ref=google&utm_source=test');
    });

    it('strips query string when configured', function () {
        SEORedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'preserve_query' => false,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page?ref=google');

        $response->assertRedirect('/new-page');
    });

    it('returns correct status code', function (int $statusCode) {
        SEORedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => $statusCode,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page');

        if ($statusCode === 410) {
            $response->assertStatus(410);
        } else {
            $response->assertRedirect('/new-page');
            $response->assertStatus($statusCode);
        }
    })->with([
        '301 permanent' => [301],
        '302 temporary' => [302],
        '307 temporary' => [307],
        '308 permanent' => [308],
        '410 gone' => [410],
    ]);

    it('increments hit counter', function () {
        Queue::fake();

        SEORedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 0,
        ]);

        $this->get('/old-page');

        Queue::assertPushed(IncrementRedirectHitJob::class);
    });

    it('handles redirect loops', function () {
        SEORedirect::create([
            'source_path' => '/loop-page',
            'target_url' => '/loop-page', // Same path = loop
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/loop-page');

        // Should return error, not redirect
        $response->assertStatus(500);
        $response->assertSee('Redirect loop detected');
    });

    it('caches redirects', function () {
        config(['seo.redirects.cache_enabled' => true]);
        config(['seo.cache.prefix' => 'seo_test_']);

        SEORedirect::create([
            'source_path' => '/cached-redirect',
            'target_url' => '/new-location',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // First request should cache
        $this->get('/cached-redirect');

        // Verify cache was populated
        $cacheKey = config('seo.cache.prefix') . 'redirects';
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('ignores inactive redirects', function () {
        SEORedirect::create([
            'source_path' => '/inactive-redirect',
            'target_url' => '/should-not-redirect',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $response = $this->get('/inactive-redirect');

        // Should get 404 (no route), not a redirect
        $response->assertStatus(404);
    });

    it('skips excluded paths', function () {
        config(['seo.redirects.exclude_paths' => ['/api/*']]);

        SEORedirect::create([
            'source_path' => '/api/old-endpoint',
            'target_url' => '/api/new-endpoint',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/api/old-endpoint');

        // Should NOT redirect - excluded path
        $response->assertStatus(404);
    });

    it('skips asset extensions', function () {
        SEORedirect::create([
            'source_path' => '/old.css',
            'target_url' => '/new.css',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old.css');

        // CSS files should be skipped
        $response->assertStatus(404);
    });

    it('clears cache when redirect is saved', function () {
        config(['seo.redirects.cache_enabled' => true]);
        config(['seo.cache.prefix' => 'seo_test_']);

        $redirect = SEORedirect::create([
            'source_path' => '/test-cache-clear',
            'target_url' => '/new',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // Request to populate cache
        $this->get('/test-cache-clear');

        // Update redirect (should clear cache via model event)
        $redirect->update(['target_url' => '/updated']);

        // Verify cache was cleared
        $cacheKey = config('seo.cache.prefix') . 'redirects';
        expect(Cache::has($cacheKey))->toBeFalse();
    });

    it('respects feature toggle', function () {
        config(['seo.features.redirects' => false]);

        SEORedirect::create([
            'source_path' => '/should-not-redirect',
            'target_url' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/should-not-redirect');

        // Feature disabled, should not redirect
        $response->assertStatus(404);
    });

    it('prioritizes exact matches over regex', function () {
        // Create regex first
        SEORedirect::create([
            'source_path' => '/priority-test/(.*)',
            'target_url' => '/regex-match',
            'status_code' => 301,
            'is_regex' => true,
            'is_active' => true,
        ]);

        // Create exact match
        SEORedirect::create([
            'source_path' => '/priority-test/exact',
            'target_url' => '/exact-match',
            'status_code' => 301,
            'is_regex' => false,
            'is_active' => true,
        ]);

        $response = $this->get('/priority-test/exact');

        // Exact match should win
        $response->assertRedirect('/exact-match');
    });

    it('handles target url with existing query string', function () {
        SEORedirect::create([
            'source_path' => '/old',
            'target_url' => '/new?existing=param',
            'status_code' => 301,
            'preserve_query' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/old?new=param');

        $response->assertRedirect('/new?existing=param&new=param');
    });
});
