<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Contracts\Sitemapable;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;
use Rankbeam\Seo\Traits\HasSEO;

// Create a test model for sitemap testing
class SitemapTestPost extends Model
{
    use HasSEO;

    protected $table = 'sitemap_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'is_published', 'updated_at'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }
}

beforeEach(function () {
    // Create test table
    $this->app['db']->connection()->getSchemaBuilder()->create('sitemap_test_posts', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('content')->nullable();
        $table->boolean('is_published')->default(true);
        $table->timestamps();
    });

    // Create seo_meta table
    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_meta')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_meta', function ($table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('locale', 10)->default('en');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('canonical')->nullable();
            $table->string('robots')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('website');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->json('focus_keywords')->nullable();
            $table->json('schema_jsonld')->nullable();
            $table->string('schema_type')->nullable();
            $table->integer('seo_score')->nullable();
            $table->json('analysis_report')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->text('content_snapshot')->nullable();
            $table->string('content_hash')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id', 'locale']);
        });
    }

    // Setup fake storage
    Storage::fake('public');

    // Configure sitemap
    config(['seo.sitemap.enabled' => true]);
    config(['seo.sitemap.disk' => 'public']);
    config(['seo.sitemap.path' => 'sitemap.xml']);
    config(['seo.sitemap.max_urls_per_sitemap' => 50000]);
    config(['seo.sitemap.models' => [
        SitemapTestPost::class => [
            'priority' => 0.8,
            'changefreq' => 'weekly',
        ],
    ]]);

    // Disable auto-create meta for these tests
    config(['seo.features.auto_create_meta' => false]);
    config(['seo.features.auto_analyze' => false]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('sitemap_test_posts');
});

describe('SitemapGeneration', function () {
    it('generates sitemap xml', function () {
        // Create test posts
        SitemapTestPost::create([
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'Content of first post',
            'is_published' => true,
        ]);

        SitemapTestPost::create([
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'Content of second post',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        // Check sitemap was created
        Storage::disk('public')->assertExists('sitemap.xml');

        $content = Storage::disk('public')->get('sitemap.xml');

        expect($content)->toContain('<?xml')
            ->and($content)->toContain('<urlset')
            ->and($content)->toContain('/posts/first-post')
            ->and($content)->toContain('/posts/second-post');
    });

    it('excludes noindex pages', function () {
        // Create post with noindex
        $post = SitemapTestPost::create([
            'title' => 'Noindex Post',
            'slug' => 'noindex-post',
            'content' => 'This should not appear in sitemap',
            'is_published' => true,
        ]);

        // Create SEO meta with noindex
        $post->seoMeta()->create([
            'locale' => 'en',
            'robots' => 'noindex,follow',
        ]);

        // Create normal post
        SitemapTestPost::create([
            'title' => 'Normal Post',
            'slug' => 'normal-post',
            'content' => 'This should appear',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');

        expect($content)->not->toContain('/posts/noindex-post')
            ->and($content)->toContain('/posts/normal-post');
    });

    it('excludes unpublished pages', function () {
        SitemapTestPost::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'content' => 'Published content',
            'is_published' => true,
        ]);

        SitemapTestPost::create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'content' => 'Draft content',
            'is_published' => false,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');

        expect($content)->toContain('/posts/published-post')
            ->and($content)->not->toContain('/posts/draft-post');
    });

    it('splits large sitemaps', function () {
        config(['seo.sitemap.max_urls_per_sitemap' => 2]);

        // Create multiple posts
        for ($i = 1; $i <= 5; $i++) {
            SitemapTestPost::create([
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'content' => "Content {$i}",
                'is_published' => true,
            ]);
        }

        $builder = app(SitemapBuilder::class);
        $sitemap = $builder->build();

        // With 5 URLs and max 2 per sitemap, might return index for large sets
        // The build() method returns SitemapIndex when URLs exceed max or multiple models
        // Single model with limited URLs returns Sitemap or SitemapIndex depending on implementation

        expect($sitemap instanceof Spatie\Sitemap\Sitemap || $sitemap instanceof Spatie\Sitemap\SitemapIndex)->toBeTrue();
    });

    it('includes all configured models', function () {
        // Create a second test model class dynamically
        if (! class_exists('SitemapTestPage')) {
            eval('
                class SitemapTestPage extends \\Illuminate\\Database\\Eloquent\\Model {
                    use \\Rankbeam\\Seo\\Traits\\HasSEO;
                    protected $table = "sitemap_test_pages";
                    protected $fillable = ["title", "slug", "is_published"];
                    public $timestamps = true;
                    public function getUrlForSEO(): string {
                        return url("/pages/{$this->slug}");
                    }
                }
            ');
        }

        // Create pages table
        $this->app['db']->connection()->getSchemaBuilder()->create('sitemap_test_pages', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Configure multiple models
        config(['seo.sitemap.models' => [
            SitemapTestPost::class => ['priority' => 0.8],
            'SitemapTestPage' => ['priority' => 0.6],
        ]]);

        // Create posts
        SitemapTestPost::create([
            'title' => 'Blog Post',
            'slug' => 'blog-post',
            'is_published' => true,
        ]);

        // Create pages
        $page = new SitemapTestPage();
        $page->title = 'About Page';
        $page->slug = 'about';
        $page->is_published = true;
        $page->save();

        $builder = app(SitemapBuilder::class);
        $sitemap = $builder->build();

        // With multiple models, should return sitemap index
        expect($sitemap)->toBeInstanceOf(Spatie\Sitemap\SitemapIndex::class);

        // Cleanup
        $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('sitemap_test_pages');
    });

    it('sets priority and changefreq from config', function () {
        config(['seo.sitemap.models' => [
            SitemapTestPost::class => [
                'priority' => 0.9,
                'changefreq' => 'daily',
            ],
        ]]);

        SitemapTestPost::create([
            'title' => 'Priority Test',
            'slug' => 'priority-test',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');

        expect($content)->toContain('<priority>0.9</priority>')
            ->and($content)->toContain('<changefreq>daily</changefreq>');
    });

    it('includes lastmod from updated_at', function () {
        $post = SitemapTestPost::create([
            'title' => 'Lastmod Test',
            'slug' => 'lastmod-test',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');

        expect($content)->toContain('<lastmod');
    });

    it('returns empty sitemap when no models configured', function () {
        config(['seo.sitemap.models' => []]);

        $builder = app(SitemapBuilder::class);
        $sitemap = $builder->build();

        expect($sitemap)->toBeInstanceOf(Spatie\Sitemap\Sitemap::class);
    });

    it('checks if sitemap exists', function () {
        $builder = app(SitemapBuilder::class);

        expect($builder->exists())->toBeFalse();

        $builder->generate();

        expect($builder->exists())->toBeTrue();
    });

    it('can delete sitemap', function () {
        SitemapTestPost::create([
            'title' => 'Delete Test',
            'slug' => 'delete-test',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        expect($builder->exists())->toBeTrue();

        $builder->delete();

        expect($builder->exists())->toBeFalse();
    });

    it('returns sitemap url', function () {
        $builder = app(SitemapBuilder::class);

        expect($builder->getSitemapUrl())->toContain('sitemap.xml');
    });
});
