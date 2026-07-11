<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;
use Rankbeam\Seo\Tests\TestCase;

/**
 * When the package routes are disabled (the app serves its own sitemaps) and no
 * explicit stylesheet URL is configured, generated sitemaps must NOT reference a
 * /sitemap.xsl the package no longer serves — they stay plain XML. Route
 * registration is gated at boot, so seo.routes.enabled is flipped in the
 * environment setup hook.
 */
class SitemapStylesheetRoutesDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Routes off, but the styled sitemap left ON (its default) and no
        // explicit url — the case that previously emitted a dead PI href.
        $app['config']->set('seo.routes.enabled', false);
        $app['config']->set('seo.sitemap.stylesheet.enabled', true);
        $app['config']->set('seo.sitemap.stylesheet.url', null);
    }

    public function test_no_stylesheet_reference_when_routes_disabled_and_no_url(): void
    {
        Storage::fake('public');

        config([
            'seo.sitemap.disk' => 'public',
            'seo.sitemap.path' => 'sitemap.xml',
            'seo.sitemap.models' => [],
            'seo.sitemap.static_urls' => [['url' => '/']],
        ]);

        app(SitemapBuilder::class)->generate();

        $xml = Storage::disk('public')->get('sitemap.xml');

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringNotContainsString('xml-stylesheet', $xml);
    }

    public function test_explicit_url_is_still_honoured_when_routes_disabled(): void
    {
        if (! method_exists(\Spatie\Sitemap\Sitemap::class, 'setStylesheet')) {
            $this->markTestSkipped('spatie/laravel-sitemap <8.1 — no setStylesheet(); the PI is not emitted.');
        }

        Storage::fake('public');

        config([
            'seo.sitemap.disk' => 'public',
            'seo.sitemap.path' => 'sitemap.xml',
            'seo.sitemap.models' => [],
            'seo.sitemap.static_urls' => [['url' => '/']],
            // A self-hoster points at their own copy — this must still be emitted
            // even with the package routes off.
            'seo.sitemap.stylesheet.url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
        ]);

        app(SitemapBuilder::class)->generate();

        $xml = Storage::disk('public')->get('sitemap.xml');

        $this->assertStringContainsString(
            'href="https://cdn.example.com/vendor/seo/sitemap.xsl"',
            $xml
        );
    }
}
