<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Tests\TestCase;

/**
 * The /robots.txt route is OFF by default, so enabling it must happen in the
 * environment hook (route registration runs during provider boot, before a Pest
 * beforeEach). This verifies the opt-in route serves the dynamic robots.txt.
 */
class RobotsTxtRouteTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('seo.ai_crawlers.route', true);
        $app['config']->set('seo.ai_crawlers.policy', [
            'ai_training' => 'disallow',
            'ai_search' => 'allow',
            'ai_assistant' => 'allow',
        ]);
        $app['config']->set('seo.ai_crawlers.list', 'blocked');
    }

    public function test_robots_txt_route_is_registered_and_serves_directives_when_enabled(): void
    {
        $this->assertTrue(Route::has('seo.robots-txt.index'));

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: GPTBot', escape: false);
    }
}
