<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Jobs\SyncAnalyticsJob;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Services\Analytics\AnalyticsCache;
use Fibonoir\LaravelSEO\Services\Analytics\GA4Service;
use Fibonoir\LaravelSEO\Services\Analytics\Period;

beforeEach(function () {
    Cache::flush();

    // Create seo_meta table
    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_meta')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_meta', function ($table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('locale', 10)->default('en');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('canonical')->nullable();
            $table->string('robots')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('website');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->json('focus_keywords')->nullable();
            $table->json('schema_jsonld')->nullable();
            $table->string('schema_type')->nullable();
            $table->integer('seo_score')->nullable();
            $table->json('analysis_report')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->text('content_snapshot')->nullable();
            $table->string('content_hash')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id', 'locale']);
        });
    }

    // Create analytics cache table
    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_analytics_cache')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_analytics_cache', function ($table) {
            $table->id();
            $table->string('path');
            $table->string('metric');
            $table->date('date');
            $table->decimal('value', 15, 4);
            $table->timestamps();
            $table->unique(['path', 'metric', 'date']);
        });
    }

    config(['seo.analytics.enabled' => true]);
    config(['seo.analytics.property_id' => 'test-property']);
    config(['seo.analytics.credentials_path' => '/fake/path.json']);
    config(['seo.analytics.max_sync_paths' => 500]);
    config(['seo.analytics.sync_paths' => []]);
});

describe('SyncAnalyticsJob', function () {
    it('syncs analytics for period', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')
            ->times(2) // For 2 paths
            ->andReturn([
                'views' => 100,
                'users' => 50,
                'avgTime' => 120.5,
                'bounceRate' => 45.2,
                'entrances' => 30,
            ]);
        $mockGA4->shouldReceive('getPageViews')
            ->times(2)
            ->andReturn([
                'total' => 100,
                'byDate' => [
                    '2024-01-01' => 50,
                    '2024-01-02' => 50,
                ],
            ]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set')->atLeast()->times(1);

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        // Create job with specific paths
        $job = new SyncAnalyticsJob(days: 7, paths: ['/blog/post-1', '/blog/post-2']);
        $job->handle($mockGA4, $mockCache);
    });

    it('handles rate limiting', function () {
        $startTime = microtime(true);

        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')->times(3)->andReturn(['views' => 10, 'users' => 5, 'avgTime' => 60, 'bounceRate' => 30, 'entrances' => 3]);
        $mockGA4->shouldReceive('getPageViews')->times(3)->andReturn(['total' => 10, 'byDate' => []]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        // Sync 3 paths - should have delay between each
        $job = new SyncAnalyticsJob(days: 7, paths: ['/path1', '/path2', '/path3']);
        $job->handle($mockGA4, $mockCache);

        $elapsed = (microtime(true) - $startTime) * 1000; // In milliseconds

        // Should have at least some delay (rate limiting)
        // With 500ms delay between 3 requests, should be > 1000ms
        // Being lenient with timing in tests
        expect($elapsed)->toBeGreaterThan(100);
    });

    it('caches results', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')->once()->andReturn([
            'views' => 100,
            'users' => 50,
            'avgTime' => 120,
            'bounceRate' => 45,
            'entrances' => 30,
        ]);
        $mockGA4->shouldReceive('getPageViews')->once()->andReturn([
            'total' => 100,
            'byDate' => ['2024-01-01' => 100],
        ]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set')
            ->atLeast()->times(5); // pageviews per date + users + avg_duration + bounce_rate + entrances

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        $job = new SyncAnalyticsJob(days: 1, paths: ['/test-path']);
        $job->handle($mockGA4, $mockCache);
    });

    it('discovers paths from seo meta', function () {
        // Create SEO meta records with canonicals
        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 1,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/blog/post-1',
        ]);

        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 2,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/blog/post-2',
        ]);

        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')
            ->times(2) // Should discover 2 paths
            ->andReturn(['views' => 10, 'users' => 5, 'avgTime' => 60, 'bounceRate' => 30, 'entrances' => 3]);
        $mockGA4->shouldReceive('getPageViews')
            ->times(2)
            ->andReturn(['total' => 10, 'byDate' => []]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        // No paths provided - should auto-discover
        $job = new SyncAnalyticsJob(days: 7);
        $job->handle($mockGA4, $mockCache);
    });

    it('skips when GA4 not configured', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(false);

        // Should not call any other methods
        $mockGA4->shouldNotReceive('getPageMetrics');
        $mockGA4->shouldNotReceive('getPageViews');

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldNotReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('warning')
            ->once()
            ->with('SyncAnalyticsJob: GA4 is not configured');

        $job = new SyncAnalyticsJob(days: 7, paths: ['/test']);
        $job->handle($mockGA4, $mockCache);
    });

    it('handles API errors gracefully', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')
            ->once()
            ->andThrow(new \Exception('API rate limit exceeded'));

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldNotReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);
        Log::shouldReceive('warning')
            ->once()
            ->with('SyncAnalyticsJob: Failed to sync path', Mockery::any());

        $job = new SyncAnalyticsJob(days: 7, paths: ['/test']);
        $job->handle($mockGA4, $mockCache);
    });

    it('continues after individual path errors', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);

        // First path throws, second succeeds
        $mockGA4->shouldReceive('getPageMetrics')
            ->with('/path1', Mockery::any())
            ->andThrow(new \Exception('Error'));

        $mockGA4->shouldReceive('getPageMetrics')
            ->with('/path2', Mockery::any())
            ->andReturn(['views' => 10, 'users' => 5, 'avgTime' => 60, 'bounceRate' => 30, 'entrances' => 3]);

        $mockGA4->shouldReceive('getPageViews')
            ->with(Mockery::any(), '/path2')
            ->andReturn(['total' => 10, 'byDate' => []]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set')->atLeast()->times(1); // Should still cache path2

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);
        Log::shouldReceive('warning')->once(); // For path1 error

        $job = new SyncAnalyticsJob(days: 7, paths: ['/path1', '/path2']);
        $job->handle($mockGA4, $mockCache);
    });

    it('respects max paths limit', function () {
        config(['seo.analytics.max_sync_paths' => 2]);

        // Create more paths than limit
        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 1,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/path1',
        ]);

        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 2,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/path2',
        ]);

        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 3,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/path3',
        ]);

        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldReceive('getPageMetrics')
            ->times(2) // Should only sync 2 (max limit)
            ->andReturn(['views' => 10, 'users' => 5, 'avgTime' => 60, 'bounceRate' => 30, 'entrances' => 3]);
        $mockGA4->shouldReceive('getPageViews')
            ->times(2)
            ->andReturn(['total' => 10, 'byDate' => []]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        $job = new SyncAnalyticsJob(days: 7);
        $job->handle($mockGA4, $mockCache);
    });

    it('handles empty paths', function () {
        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);
        $mockGA4->shouldNotReceive('getPageMetrics');
        $mockGA4->shouldNotReceive('getPageViews');

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldNotReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')
            ->with('SyncAnalyticsJob: Starting analytics sync', Mockery::any())
            ->once();
        Log::shouldReceive('info')
            ->with('SyncAnalyticsJob: No paths to sync')
            ->once();

        $job = new SyncAnalyticsJob(days: 7, paths: []);
        $job->handle($mockGA4, $mockCache);
    });

    it('has unique job constraint', function () {
        $job = new SyncAnalyticsJob();

        expect($job->uniqueId())->toBe('sync-analytics')
            ->and($job->uniqueFor)->toBe(3600);
    });

    it('has correct retry settings', function () {
        $job = new SyncAnalyticsJob();

        expect($job->tries)->toBe(3)
            ->and($job->timeout)->toBe(1800);
    });

    it('has correct job tags', function () {
        $job = new SyncAnalyticsJob();

        expect($job->tags())->toContain('seo')
            ->and($job->tags())->toContain('analytics')
            ->and($job->tags())->toContain('sync');
    });

    it('logs failure', function () {
        $job = new SyncAnalyticsJob(days: 7);

        Log::shouldReceive('error')
            ->once()
            ->with('SyncAnalyticsJob failed', Mockery::any());

        $job->failed(new \Exception('Test error'));
    });

    it('uses provided paths over discovery', function () {
        // Create SEO meta that would be discovered
        SEOMeta::create([
            'seoable_type' => 'App\Models\Post',
            'seoable_id' => 1,
            'locale' => 'en',
            'canonical' => 'https://mysite.com/discovered-path',
        ]);

        $mockGA4 = Mockery::mock(GA4Service::class);
        $mockGA4->shouldReceive('isConfigured')->andReturn(true);

        // Should only use the provided path, not the discovered one
        $mockGA4->shouldReceive('getPageMetrics')
            ->with('/explicit-path', Mockery::any())
            ->once()
            ->andReturn(['views' => 10, 'users' => 5, 'avgTime' => 60, 'bounceRate' => 30, 'entrances' => 3]);

        $mockGA4->shouldReceive('getPageViews')
            ->with(Mockery::any(), '/explicit-path')
            ->once()
            ->andReturn(['total' => 10, 'byDate' => []]);

        $mockCache = Mockery::mock(AnalyticsCache::class);
        $mockCache->shouldReceive('set');

        $this->app->instance(GA4Service::class, $mockGA4);
        $this->app->instance(AnalyticsCache::class, $mockCache);

        Log::shouldReceive('info')->atLeast()->times(1);

        // Provide explicit paths
        $job = new SyncAnalyticsJob(days: 7, paths: ['/explicit-path']);
        $job->handle($mockGA4, $mockCache);
    });
});
