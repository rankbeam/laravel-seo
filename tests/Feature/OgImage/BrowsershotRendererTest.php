<?php

declare(strict_types=1);

use Rankbeam\Seo\Services\OgImage\BrowsershotRenderer;
use Spatie\Browsershot\Browsershot;

/*
 * Unit tests for the Browsershot renderer's config → Chromium-argument wiring
 * (seo.og_image.no_sandbox + seo.og_image.browsershot_args). We inspect the
 * configured Browsershot instance rather than call ->save(), so no real Chrome
 * is launched — the full render path is covered by the skipped
 * BrowsershotSmokeTest. Assertions target Browsershot::getOptionArgs(), which
 * returns the exact flag list Chrome is invoked with.
 */

/**
 * A thin subclass exposing the two protected seams under test.
 */
function ogRenderer(): BrowsershotRenderer
{
    return new class extends BrowsershotRenderer
    {
        public function argsForConfig(): array
        {
            return $this->chromiumArguments();
        }

        public function build(): Browsershot
        {
            return $this->configureBrowsershot('<p>x</p>', 1200, 630);
        }
    };
}

/**
 * The final list of Chromium CLI flags Browsershot will pass to Chrome
 * (Browsershot::getOptionArgs() is protected; reflect it — this is the real
 * observable contract, including the internally-appended --no-sandbox).
 */
function chromeFlags(Browsershot $shot): array
{
    $method = new ReflectionMethod(Browsershot::class, 'getOptionArgs');
    $method->setAccessible(true);

    return $method->invoke($shot);
}

it('adds no extra Chromium flags by default', function () {
    expect(ogRenderer()->argsForConfig())->toBe([]);
    expect(chromeFlags(ogRenderer()->build()))->toBe([]);
});

it('passes no_sandbox=true through to Chrome as --no-sandbox', function () {
    config(['seo.og_image.no_sandbox' => true]);

    expect(chromeFlags(ogRenderer()->build()))->toContain('--no-sandbox');
});

it('does not pass --no-sandbox when no_sandbox is false', function () {
    config(['seo.og_image.no_sandbox' => false]);

    expect(chromeFlags(ogRenderer()->build()))->not->toContain('--no-sandbox');
});

it('strips an optional leading -- from list-form browsershot_args', function () {
    config(['seo.og_image.browsershot_args' => ['disable-gpu', '--disable-dev-shm-usage']]);

    expect(ogRenderer()->argsForConfig())
        ->toBe(['disable-gpu', 'disable-dev-shm-usage']);

    expect(chromeFlags(ogRenderer()->build()))
        ->toContain('--disable-gpu')
        ->toContain('--disable-dev-shm-usage');
});

it('preserves map-form browsershot_args that carry a value', function () {
    config(['seo.og_image.browsershot_args' => ['--proxy-server' => 'http://localhost:8080']]);

    expect(ogRenderer()->argsForConfig())
        ->toBe(['proxy-server' => 'http://localhost:8080']);

    expect(chromeFlags(ogRenderer()->build()))
        ->toContain('--proxy-server=http://localhost:8080');
});

it('combines no_sandbox and browsershot_args', function () {
    config([
        'seo.og_image.no_sandbox' => true,
        'seo.og_image.browsershot_args' => ['disable-gpu'],
    ]);

    expect(chromeFlags(ogRenderer()->build()))
        ->toContain('--no-sandbox')
        ->toContain('--disable-gpu');
});
