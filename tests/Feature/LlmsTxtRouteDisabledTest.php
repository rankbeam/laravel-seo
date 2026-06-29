<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Tests\TestCase;

/**
 * Verifies the /llms.txt route is gated the same way the sitemap route is.
 * Route registration happens during provider boot, so this is a plain PHPUnit
 * class with config flipped in the environment hook (a Pest beforeEach would
 * run too late).
 */
class LlmsTxtRouteDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Master switch off: the whole package route group is skipped, taking
        // /llms.txt with it (same gate as /sitemap.xml).
        $app['config']->set('seo.routes.enabled', false);
    }

    public function test_llms_txt_route_is_not_registered_when_routes_are_disabled(): void
    {
        $this->assertFalse(Route::has('seo.llms-txt.index'));
        $this->get('/llms.txt')->assertNotFound();
    }
}
