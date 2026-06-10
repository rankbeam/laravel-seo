<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Fibonoir\LaravelSEO\Jobs\GenerateSitemapJob;
use Fibonoir\LaravelSEO\Services\Sitemap\SitemapBuilder;

beforeEach(function () {
    Storage::fake('public');

    // Note: no catch-all Http::fake() here. Laravel resolves stubs
    // first-match-wins in registration order, so a catch-all registered in
    // beforeEach would shadow the per-test 500/error stubs below and the
    // failure paths would never execute. Tests that make HTTP requests
    // register their own stubs.

    config(['seo.sitemap.enabled' => true]);
    config(['seo.sitemap.disk' => 'public']);
    config(['seo.sitemap.path' => 'sitemap.xml']);
    config(['seo.sitemap.models' => []]);
    config(['seo.sitemap.ping_search_engines' => true]);
});

describe('GenerateSitemapJob', function () {
    it('generates sitemap file', function () {
        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')
            ->with('GenerateSitemapJob: Starting sitemap generation')
            ->once();

        Log::shouldReceive('info')
            ->with('GenerateSitemapJob: Sitemap generated successfully', Mockery::any())
            ->once();

        GenerateSitemapJob::dispatchSync();
    });

    it('pings search engines when enabled', function () {
        Http::fake([
            'google.com/*' => Http::response('OK', 200),
            'bing.com/*' => Http::response('OK', 200),
        ]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')->atLeast()->times(1);

        // Dispatch with pinging enabled
        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);

        // Verify HTTP calls were made
        Http::assertSentCount(2);
    });

    it('does not ping when disabled', function () {
        Http::fake();

        config(['seo.sitemap.ping_search_engines' => false]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')->atLeast()->times(1);

        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);

        // Should not make any HTTP calls
        Http::assertNothingSent();
    });

    it('handles ping failures gracefully', function () {
        Http::fake([
            'google.com/*' => Http::response('Error', 500),
            'bing.com/*' => Http::sequence()
                ->pushStatus(500)
                ->push('OK', 200),
        ]);

        config(['seo.sitemap.ping_search_engines' => true]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::spy();

        // Should not throw exception
        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);

        // Should have logged warnings for failed pings
        Log::shouldHaveReceived('warning');
    });

    it('handles connection errors during ping', function () {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        config(['seo.sitemap.ping_search_engines' => true]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::spy();

        // Should not throw
        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);

        // Should have logged warnings for connection errors
        Log::shouldHaveReceived('warning');
    });

    it('throws and logs on generation failure', function () {
        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')
            ->once()
            ->andThrow(new \Exception('Storage error'));

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')
            ->with('GenerateSitemapJob: Failed to generate sitemap', Mockery::any())
            ->once();

        expect(fn () => GenerateSitemapJob::dispatchSync())->toThrow(\Exception::class, 'Storage error');
    });

    it('has unique job constraint', function () {
        $job = new GenerateSitemapJob();

        expect($job->uniqueId())->toBe('generate-sitemap')
            ->and($job->uniqueFor)->toBe(3600);
    });

    it('has correct retry settings', function () {
        $job = new GenerateSitemapJob();

        expect($job->tries)->toBe(3)
            ->and($job->timeout)->toBe(600);
    });

    it('has correct job tags', function () {
        $job = new GenerateSitemapJob();

        expect($job->tags())->toContain('seo')
            ->and($job->tags())->toContain('sitemap');
    });

    it('logs failure', function () {
        $job = new GenerateSitemapJob();

        Log::shouldReceive('error')
            ->once()
            ->with('GenerateSitemapJob failed', Mockery::any());

        $job->failed(new \Exception('Test error'));
    });

    it('encodes sitemap url for ping', function () {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        config(['seo.sitemap.ping_search_engines' => true]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml?param=value');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')->atLeast()->times(1);

        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);

        // Should encode the URL
        Http::assertSent(function ($request) {
            return str_contains($request->url(), urlencode('https://mysite.com/sitemap.xml'));
        });
    });

    it('logs successful pings', function () {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        config(['seo.sitemap.ping_search_engines' => true]);

        $mockBuilder = Mockery::mock(SitemapBuilder::class);
        $mockBuilder->shouldReceive('generate')->once();
        $mockBuilder->shouldReceive('getSitemapUrl')->andReturn('https://mysite.com/sitemap.xml');

        $this->app->instance(SitemapBuilder::class, $mockBuilder);

        Log::shouldReceive('info')->atLeast()->times(1);

        (new GenerateSitemapJob(pingSearchEngines: true))->handle($mockBuilder);
    });

    it('can be scheduled', function () {
        $job = new GenerateSitemapJob(pingSearchEngines: true);

        // Just ensure the job can be created with all settings
        expect($job->pingSearchEngines)->toBeTrue();
    });
});
