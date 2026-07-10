<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Tests\TestCase;

/**
 * When the styled sitemap is disabled, the /sitemap.xsl route must not be
 * registered at all. Route registration happens during provider boot, so the
 * flag is flipped in the environment setup hook (a Pest beforeEach would run
 * too late) — mirroring RoutesDisabledTest.
 */
class SitemapStylesheetDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('seo.sitemap.stylesheet.enabled', false);
    }

    public function test_stylesheet_route_is_not_registered_when_disabled(): void
    {
        $this->assertFalse(Route::has('seo.sitemap.stylesheet'));
    }

    public function test_stylesheet_url_returns_404_when_disabled(): void
    {
        $this->get('/sitemap.xsl')->assertNotFound();
    }
}
