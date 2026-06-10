<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Fibonoir\LaravelSEO\Tests\TestCase;

/**
 * Verifies that config('seo.routes.enabled') actually gates route
 * registration. Route registration happens during provider boot, so this
 * is a plain PHPUnit class with the config flipped in the environment
 * setup hook (a Pest beforeEach would run too late).
 */
class RoutesDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('seo.routes.enabled', false);
    }

    protected function defineRoutes($router): void
    {
        // Simulates an application that serves its own statically generated
        // sitemap (e.g. spatie/laravel-sitemap writing to public/).
        $router->get('sitemap.xml', fn () => response(
            '<?xml version="1.0"?><urlset><!-- app-owned --></urlset>',
            200,
            ['Content-Type' => 'application/xml']
        ));
    }

    public function test_package_routes_are_not_registered_when_disabled(): void
    {
        $this->assertFalse(Route::has('seo.sitemap.index'));
        $this->assertFalse(Route::has('seo.sitemap.show'));
    }

    public function test_application_owned_sitemap_route_wins_without_collision(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('app-owned', $response->getContent());
    }

    public function test_named_sitemap_route_is_gone(): void
    {
        $this->get('/sitemap-posts.xml')->assertNotFound();
    }
}
