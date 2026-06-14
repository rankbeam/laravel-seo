<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Traits\HasSEO;

// Create test model for trait testing
class TraitTestPost extends Model
{
    use HasSEO;

    protected $table = 'trait_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'excerpt', 'featured_image'];

    public $timestamps = true;

    public function getContentForSEO(): string
    {
        return $this->content ?? '';
    }

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEODescription(): ?string
    {
        return $this->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($this->content ?? ''), 155);
    }

    public function getSEOImage(): ?string
    {
        return $this->featured_image;
    }

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }

    public function getSEOContentFields(): array
    {
        return ['title', 'content', 'excerpt'];
    }
}

beforeEach(function () {
    Queue::fake();

    // Create test table
    $this->app['db']->connection()->getSchemaBuilder()->create('trait_test_posts', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('content')->nullable();
        $table->text('excerpt')->nullable();
        $table->string('featured_image')->nullable();
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
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id', 'locale']);
        });
    }

    // Create seo_defaults table for SEOResolver
    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_defaults')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_defaults', function ($table) {
            $table->id();
            $table->string('scope');
            $table->string('scope_value')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('title_template')->nullable();
            $table->string('title_suffix')->nullable();
            $table->text('description_template')->nullable();
            $table->string('og_type')->nullable();
            $table->string('twitter_card')->nullable();
            $table->string('robots')->nullable();
            $table->json('schema_template')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'scope_value', 'locale']);
        });
    }

    config(['seo.features.auto_create_meta' => true]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('trait_test_posts');
});

describe('HasSEO Trait', function () {
    it('creates seo meta on model creation', function () {
        config(['seo.features.auto_create_meta' => true]);

        $post = TraitTestPost::create([
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Test content',
        ]);

        expect($post->seoMeta)->toBeInstanceOf(SEOMeta::class)
            ->and($post->seoMeta->locale)->toBe(app()->getLocale());
    });

    it('returns resolved seo data', function () {
        $post = TraitTestPost::create([
            'title' => 'SEO Data Test',
            'slug' => 'seo-data-test',
            'content' => 'Content for SEO data testing',
            'excerpt' => 'This is the excerpt',
            'featured_image' => '/images/feature.jpg',
        ]);

        $seoData = $post->seoData();

        expect($seoData)->toBeInstanceOf(SEOData::class)
            ->and($seoData->title)->not->toBeNull();
    });

    it('saves seo data via saveSEO method', function () {
        config(['seo.features.auto_create_meta' => false]); // Disable auto-create for this test

        $post = TraitTestPost::create([
            'title' => 'Save SEO Test',
            'slug' => 'save-seo-test',
            'content' => 'Test content',
        ]);

        $post->saveSEO([
            'title' => 'Custom SEO Title',
            'description' => 'Custom meta description',
            'focus_keywords' => [
                ['keyword' => 'test keyword', 'is_primary' => true],
            ],
        ]);

        $meta = $post->fresh()->seoMeta;

        expect($meta->title)->toBe('Custom SEO Title')
            ->and($meta->description)->toBe('Custom meta description')
            ->and($meta->focus_keywords)->toBeArray()
            ->and($meta->focus_keywords[0]['keyword'])->toBe('test keyword');
    });

    it('saves seo data for specific locale', function () {
        config(['seo.features.auto_create_meta' => false]);

        $post = TraitTestPost::create([
            'title' => 'Locale Test',
            'slug' => 'locale-test',
            'content' => 'Test content',
        ]);

        $post->saveSEO(['title' => 'English Title'], 'en');
        $post->saveSEO(['title' => 'Titre Français'], 'fr');

        $enMeta = SEOMeta::where('seoable_type', TraitTestPost::class)
            ->where('seoable_id', $post->id)
            ->where('locale', 'en')
            ->first();

        $frMeta = SEOMeta::where('seoable_type', TraitTestPost::class)
            ->where('seoable_id', $post->id)
            ->where('locale', 'fr')
            ->first();

        expect($enMeta->title)->toBe('English Title')
            ->and($frMeta->title)->toBe('Titre Français');
    });

    it('provides computed seo title from model', function () {
        $post = new TraitTestPost();
        $post->title = 'My Post Title';

        expect($post->getSEOTitle())->toBe('My Post Title');
    });

    it('provides computed seo description from excerpt', function () {
        $post = new TraitTestPost();
        $post->excerpt = 'This is the excerpt description';

        expect($post->getSEODescription())->toBe('This is the excerpt description');
    });

    it('falls back to content for description when no excerpt', function () {
        $post = new TraitTestPost();
        $post->content = 'This is the full content of the post that should be truncated for the description.';

        $description = $post->getSEODescription();

        expect($description)->toContain('This is the full content');
    });

    it('provides computed seo image', function () {
        $post = new TraitTestPost();
        $post->featured_image = '/images/featured.jpg';

        expect($post->getSEOImage())->toBe('/images/featured.jpg');
    });

    it('returns focus keywords', function () {
        $post = TraitTestPost::create([
            'title' => 'Keywords Test',
            'slug' => 'keywords-test',
            'content' => 'Test content',
        ]);

        $post->seoMeta()->update([
            'focus_keywords' => [
                ['keyword' => 'primary', 'is_primary' => true],
                ['keyword' => 'secondary', 'is_primary' => false],
            ],
        ]);

        $keywords = $post->fresh()->getFocusKeywords();

        expect($keywords)->toHaveCount(2);
    });

    it('returns primary keyword', function () {
        $post = TraitTestPost::create([
            'title' => 'Primary Keyword Test',
            'slug' => 'primary-keyword-test',
            'content' => 'Test content',
        ]);

        $post->seoMeta()->update([
            'focus_keywords' => [
                ['keyword' => 'secondary', 'is_primary' => false],
                ['keyword' => 'primary keyword', 'is_primary' => true],
            ],
        ]);

        $primary = $post->fresh()->getPrimaryKeyword();

        expect($primary['keyword'])->toBe('primary keyword')
            ->and($primary['is_primary'])->toBeTrue();
    });

    it('checks for explicit seo data', function () {
        $post = TraitTestPost::create([
            'title' => 'Explicit Check',
            'slug' => 'explicit-check',
            'content' => 'Test content',
        ]);

        // Initially no explicit data
        expect($post->hasExplicitSEO())->toBeFalse();

        // Add explicit data
        $post->seoMeta()->update(['title' => 'Explicit Title']);

        expect($post->fresh()->hasExplicitSEO())->toBeTrue();
    });

    it('deletes seo meta when model is deleted', function () {
        $post = TraitTestPost::create([
            'title' => 'Delete Test',
            'slug' => 'delete-test',
            'content' => 'Test content',
        ]);

        $metaId = $post->seoMeta->id;

        expect(SEOMeta::find($metaId))->not->toBeNull();

        $post->delete();

        expect(SEOMeta::find($metaId))->toBeNull();
    });

    it('respects auto create meta feature toggle', function () {
        config(['seo.features.auto_create_meta' => false]);

        $post = TraitTestPost::create([
            'title' => 'No Auto Create',
            'slug' => 'no-auto-create',
            'content' => 'Test content',
        ]);

        // Should have default (from withDefault), but not persisted
        expect(SEOMeta::where('seoable_type', TraitTestPost::class)
            ->where('seoable_id', $post->id)
            ->exists())->toBeFalse();
    });
});
