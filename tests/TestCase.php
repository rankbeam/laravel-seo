<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Fibonoir\LaravelSEO\SEOServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            SEOServiceProvider::class,
        ];
        
        // Add Spatie Sitemap provider if package is installed
        if (class_exists(\Spatie\Sitemap\SitemapServiceProvider::class)) {
            $providers[] = \Spatie\Sitemap\SitemapServiceProvider::class;
        }
        
        return $providers;
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup SEO config defaults
        $app['config']->set('seo.site_name', 'Test Site');
        $app['config']->set('seo.title_suffix', ' | Test Site');
        $app['config']->set('seo.default_robots', 'index,follow');
        $app['config']->set('seo.default_twitter_card', 'summary_large_image');
        $app['config']->set('seo.twitter_site', '@testsite');
        $app['config']->set('seo.default_og_image', '/default-og.jpg');
        $app['config']->set('seo.cache.store', 'array');
        $app['config']->set('seo.cache.prefix', 'seo_test_');
        
        // Set a default app key for encryption
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }
}
