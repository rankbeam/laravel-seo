<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Feature;

use Rankbeam\Seo\SEOServiceProvider;
use Rankbeam\Seo\Tests\TestCase;

/**
 * A client who published config/seo.php BEFORE a release that adds new nested
 * keys must still receive those nested defaults (and their env vars must work),
 * while every value the client set is preserved. Laravel's shallow
 * mergeConfigFrom() would let the published `sitemap` array mask the new
 * sitemap.images / sitemap.alternates leaves; mergeConfigRecursivelyFrom()
 * fixes that. These tests pin the merge semantics.
 */
class DeepConfigMergeTest extends TestCase
{
    /**
     * Drive the provider's recursive merge against an arbitrary "published"
     * config array, returning the resolved `seo` config. This mirrors exactly
     * what register() does, but lets us seed a partial published file first.
     *
     * @param  array<string, mixed>  $published
     * @return array<string, mixed>
     */
    private function mergeAgainstPublished(array $published): array
    {
        $this->app['config']->set('seo', $published);

        $provider = new class($this->app) extends SEOServiceProvider
        {
            public function runMerge(string $path): void
            {
                $this->mergeConfigRecursivelyFrom($path, 'seo');
            }
        };

        $provider->runMerge(dirname(__DIR__, 2).'/config/seo.php');

        return $this->app['config']->get('seo');
    }

    public function test_new_nested_sitemap_defaults_reach_a_stale_published_config(): void
    {
        // A config published before the RT15 sitemap image/hreflang release:
        // it has a `sitemap` array, but none of the new leaves.
        $merged = $this->mergeAgainstPublished([
            'site_name' => 'Client Site',
            'sitemap' => [
                'disk' => 'public',
                'path' => 'sitemap.xml',
                'models' => [\App\Models\Post::class],
            ],
        ]);

        // The new nested defaults are now present...
        $this->assertSame(false, $merged['sitemap']['images']);
        $this->assertSame(false, $merged['sitemap']['alternates']);
        $this->assertArrayHasKey('max_urls_per_sitemap', $merged['sitemap']);
        $this->assertArrayHasKey('static_urls', $merged['sitemap']);

        // ...without disturbing what the client set.
        $this->assertSame('Client Site', $merged['site_name']);
        $this->assertSame([\App\Models\Post::class], $merged['sitemap']['models']);
    }

    public function test_new_top_level_keys_reach_a_stale_published_config(): void
    {
        $merged = $this->mergeAgainstPublished([
            'site_name' => 'Client Site',
            'default_robots' => 'index,follow',
        ]);

        // robots.emit_default / keywords / audit were added after the first
        // published config; deep merge brings them in.
        $this->assertArrayHasKey('robots', $merged);
        $this->assertSame(false, $merged['robots']['emit_default']);
        $this->assertArrayHasKey('keywords', $merged);
        $this->assertArrayHasKey('audit', $merged);
    }

    public function test_user_overrides_win_at_every_leaf_including_falsey_values(): void
    {
        $merged = $this->mergeAgainstPublished([
            'site_name' => 'Client Site',
            'features' => [
                // The client turned sitemap OFF; deep merge must NOT re-seed
                // the package default (true) over their explicit false.
                'sitemap' => false,
                'schema' => true,
            ],
            'sitemap' => [
                'images' => true, // explicitly opted in — must survive
            ],
        ]);

        $this->assertFalse($merged['features']['sitemap']);
        $this->assertTrue($merged['sitemap']['images']);

        // A leaf the client omitted inside an array they DID set is still
        // backfilled from the package defaults.
        $this->assertArrayHasKey('auto_create_meta', $merged['features']);
        $this->assertArrayHasKey('alternates', $merged['sitemap']);
    }

    public function test_a_user_replaced_list_is_kept_verbatim_not_appended(): void
    {
        $merged = $this->mergeAgainstPublished([
            'computed' => [
                'description_fields' => ['subtitle', 'abstract'],
            ],
        ]);

        // The package default for description_fields is []; the client's
        // explicit list must be preserved exactly (not merged/duplicated).
        $this->assertSame(['subtitle', 'abstract'], $merged['computed']['description_fields']);
        // ...and sibling leaves still arrive.
        $this->assertArrayHasKey('description_max_length', $merged['computed']);
    }
}
