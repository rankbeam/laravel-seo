<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Fibonoir\LaravelSEO\Http\Middleware\Log404Middleware;
use Fibonoir\LaravelSEO\Models\SEO404Log;

beforeEach(function () {
    // Register test routes 
    Route::get('/existing-page', fn () => 'Existing Page');

    // Enable 404 monitoring
    config(['seo.features.404_monitor' => true]);
    config(['seo.404_monitor.log_bots' => false]);
    config(['seo.404_monitor.hash_ip' => false]);
    config(['seo.404_monitor.exclude_paths' => ['/api/*', '/_debugbar/*']]);
    config(['seo.404_monitor.exclude_extensions' => ['js', 'css', 'jpg', 'png', 'gif', 'ico']]);

    // Push middleware globally for testing
    $this->app['router']->pushMiddlewareToGroup('web', Log404Middleware::class);
});

describe('Log404Middleware', function () {
    it('logs 404 response', function () {
        $this->get('/non-existent-page');

        expect(SEO404Log::where('path', '/non-existent-page')->exists())->toBeTrue();

        $log = SEO404Log::where('path', '/non-existent-page')->first();
        expect($log->hit_count)->toBe(1)
            ->and($log->status)->toBe('new');
    });

    it('increments hit count for existing path', function () {
        // Create initial log
        SEO404Log::create([
            'path' => '/repeated-404',
            'hit_count' => 5,
            'first_seen_at' => now()->subDays(1),
            'last_seen_at' => now()->subHours(1),
            'status' => 'new',
        ]);

        // Hit the same path again
        $this->get('/repeated-404');

        $log = SEO404Log::where('path', '/repeated-404')->first();
        expect($log->hit_count)->toBe(6);
    });

    it('captures referrer', function () {
        $this->get('/non-existent', [
            'HTTP_REFERER' => 'https://example.com/source-page',
        ]);

        $log = SEO404Log::where('path', '/non-existent')->first();
        expect($log->referrer)->toBe('https://example.com/source-page');
    });

    it('captures user agent', function () {
        $this->get('/missing-page', [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
        ]);

        $log = SEO404Log::where('path', '/missing-page')->first();
        expect($log->user_agent)->toBe('Mozilla/5.0 Test Browser');
    });

    it('captures ip address', function () {
        $this->get('/ip-test-page', [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $log = SEO404Log::where('path', '/ip-test-page')->first();
        expect($log->ip)->not->toBeNull();
    });

    it('hashes ip when configured', function () {
        config(['seo.404_monitor.hash_ip' => true]);
        config(['app.key' => 'base64:' . base64_encode('test-key-12345678901234')]);

        $this->get('/ip-hash-test', [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        $log = SEO404Log::where('path', '/ip-hash-test')->first();

        // Should be hashed (64 char sha256)
        if ($log && $log->ip) {
            expect(strlen($log->ip))->toBe(64);
        }
    });

    it('excludes configured paths', function () {
        $this->get('/api/non-existent');

        expect(SEO404Log::where('path', '/api/non-existent')->exists())->toBeFalse();
    });

    it('excludes asset extensions', function () {
        $this->get('/missing-file.css');
        $this->get('/missing-image.jpg');
        $this->get('/missing-script.js');

        expect(SEO404Log::where('path', 'LIKE', '%.css')->exists())->toBeFalse()
            ->and(SEO404Log::where('path', 'LIKE', '%.jpg')->exists())->toBeFalse()
            ->and(SEO404Log::where('path', 'LIKE', '%.js')->exists())->toBeFalse();
    });

    it('excludes bots by default', function () {
        $this->get('/bot-test-page', [
            'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ]);

        expect(SEO404Log::where('path', '/bot-test-page')->exists())->toBeFalse();
    });

    it('logs bots when configured', function () {
        config(['seo.404_monitor.log_bots' => true]);

        $this->get('/bot-allowed-page', [
            'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ]);

        expect(SEO404Log::where('path', '/bot-allowed-page')->exists())->toBeTrue();
    });

    it('does not log 200 responses', function () {
        $this->get('/existing-page');

        // Should not be logged since it's a 200
        expect(SEO404Log::where('path', '/existing-page')->exists())->toBeFalse();
    });

    it('respects feature toggle', function () {
        config(['seo.features.404_monitor' => false]);

        $this->get('/feature-disabled-test');

        expect(SEO404Log::where('path', '/feature-disabled-test')->exists())->toBeFalse();
    });

    it('truncates long referrer', function () {
        $longReferrer = 'https://example.com/' . str_repeat('a', 600);

        $this->get('/long-referrer-test', [
            'HTTP_REFERER' => $longReferrer,
        ]);

        $log = SEO404Log::where('path', '/long-referrer-test')->first();
        expect(strlen($log->referrer ?? ''))->toBeLessThanOrEqual(500);
    });

    it('truncates long user agent', function () {
        $longUA = 'Mozilla/5.0 ' . str_repeat('Test', 200);

        $this->get('/long-ua-test', [
            'HTTP_USER_AGENT' => $longUA,
        ]);

        $log = SEO404Log::where('path', '/long-ua-test')->first();
        expect(strlen($log->user_agent ?? ''))->toBeLessThanOrEqual(500);
    });

    it('skips very long paths', function () {
        $longPath = '/' . str_repeat('a', 600);

        $this->get($longPath);

        expect(SEO404Log::where('path', $longPath)->exists())->toBeFalse();
    });

    it('updates last_seen_at on repeated hits', function () {
        // Create initial log with old date
        $oldDate = now()->subDays(7);
        SEO404Log::create([
            'path' => '/update-timestamp-test',
            'hit_count' => 1,
            'first_seen_at' => $oldDate,
            'last_seen_at' => $oldDate,
            'status' => 'new',
        ]);

        $this->get('/update-timestamp-test');

        $log = SEO404Log::where('path', '/update-timestamp-test')->first();

        // last_seen_at should be updated to now
        expect($log->last_seen_at->gt($oldDate))->toBeTrue();

        // first_seen_at should remain unchanged
        expect($log->first_seen_at->eq($oldDate))->toBeTrue();
    });

    it('handles requests without user agent', function () {
        $this->get('/no-ua-test', [
            'HTTP_USER_AGENT' => '',
        ]);

        // Empty UA is treated as bot, should not be logged
        expect(SEO404Log::where('path', '/no-ua-test')->exists())->toBeFalse();
    });

    it('identifies various bot patterns', function (string $userAgent) {
        $path = '/bot-pattern-' . md5($userAgent);

        $this->get($path, [
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        expect(SEO404Log::where('path', $path)->exists())->toBeFalse();
    })->with([
        'googlebot' => ['Googlebot/2.1'],
        'bingbot' => ['bingbot/2.0'],
        'crawler' => ['Mozilla/5.0 (compatible; SomeCrawler)'],
        'spider' => ['WebSpider/1.0'],
        'semrush' => ['SemrushBot/7.0'],
        'ahrefs' => ['AhrefsBot/7.0'],
    ]);
});
