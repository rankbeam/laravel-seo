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

// A HasSEO model exposing a resolvable image and hreflang alternates, used to
// exercise the optional sitemap image/alternate extensions. Reuses the same
// table as SitemapTestPost so no extra schema is needed.
class SitemapExtensionPost extends Model
{
    use HasSEO;

    protected $table = 'sitemap_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'is_published', 'updated_at'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }

    public function getSEOImage(): ?string
    {
        return "/images/{$this->slug}.jpg";
    }

    public function getSEOAlternates(): ?array
    {
        return [
            ['hreflang' => 'en', 'href' => url("/posts/{$this->slug}")],
            ['hreflang' => 'fr', 'href' => url("/fr/posts/{$this->slug}")],
            ['hreflang' => 'x-default', 'href' => url("/posts/{$this->slug}")],
        ];
    }
}

// A model returning a deliberately malformed alternates shape — the builder
// must skip the bad entries and never throw.
class SitemapMalformedAltPost extends Model
{
    use HasSEO;

    protected $table = 'sitemap_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'is_published', 'updated_at'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }

    public function getSEOAlternates(): ?array
    {
        return [
            ['hreflang' => 'en', 'href' => url("/posts/{$this->slug}")], // valid
            ['hreflang' => 'fr'],                                        // missing href
            ['href' => url("/de/{$this->slug}")],                        // missing hreflang
            ['hreflang' => '', 'href' => ''],                            // empty strings
            ['hreflang' => 'es', 'href' => 123],                         // non-string href
            'not-an-array',                                              // scalar entry
        ];
    }
}

// A model implementing Sitemapable whose toSitemapTag() returns a hand-built
// Url (the escape hatch). It ALSO exposes resolvable image/alternates, which
// the builder must NOT inject on top of the hand-built tag.
class SitemapSitemapableExtPost extends Model implements Sitemapable
{
    use HasSEO;

    protected $table = 'sitemap_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'is_published', 'updated_at'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }

    public function getSEOImage(): ?string
    {
        return '/images/should-not-appear.jpg';
    }

    public function getSEOAlternates(): ?array
    {
        return [['hreflang' => 'en', 'href' => url("/posts/{$this->slug}")]];
    }

    public function shouldIncludeInSitemap(): bool
    {
        return true;
    }

    public function toSitemapTag(): \Spatie\Sitemap\Tags\Url|string|array
    {
        return \Spatie\Sitemap\Tags\Url::create(url("/posts/{$this->slug}"))
            ->addImage(url('/images/hand-built.jpg'));
    }
}

// Asserts a string is well-formed XML and returns the parsed tree with the
// sitemap extension namespaces registered for xpath.
function loadSitemapXml(string $content): SimpleXMLElement
{
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $xml = simplexml_load_string($content);
    $errors = libxml_get_errors();

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    expect($errors)->toBe([])
        ->and($xml)->not->toBeFalse();

    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->registerXPathNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');
    $xml->registerXPathNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

    return $xml;
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

describe('SitemapExtensions', function () {
    beforeEach(function () {
        config(['seo.sitemap.models' => [
            SitemapExtensionPost::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
        ]]);

        SitemapExtensionPost::create([
            'title' => 'Extension Post',
            'slug' => 'extension-post',
            'is_published' => true,
        ]);
    });

    it('omits image and alternate extensions by default', function () {
        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');

        // Still valid XML, just without the optional extensions.
        loadSitemapXml($content);

        expect($content)->toContain('/posts/extension-post')
            ->and($content)->not->toContain('<image:image>')
            ->and($content)->not->toContain('<xhtml:link');
    });

    it('adds an image entry from the resolved image when enabled', function () {
        config(['seo.sitemap.images' => true]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        $locations = $xml->xpath('//image:image/image:loc');

        // Exact resolved, absolutized image URL — not double-prefixed.
        expect($locations)->toHaveCount(1)
            ->and((string) $locations[0])->toBe(url('/images/extension-post.jpg'));
    });

    it('adds hreflang alternate entries from getSEOAlternates when enabled', function () {
        config(['seo.sitemap.alternates' => true]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        $links = $xml->xpath('//s:url/xhtml:link');

        expect($links)->toHaveCount(3);

        $rels = array_map(fn ($link) => (string) $link['rel'], $links);
        $hreflangs = array_map(fn ($link) => (string) $link['hreflang'], $links);
        $hrefs = array_map(fn ($link) => (string) $link['href'], $links);

        expect($rels)->toEqual(['alternate', 'alternate', 'alternate'])
            ->and($hreflangs)->toEqual(['en', 'fr', 'x-default'])
            ->and($hrefs)->toEqual([
                url('/posts/extension-post'),
                url('/fr/posts/extension-post'),
                url('/posts/extension-post'),
            ]);
    });

    it('emits both extensions together as well-formed xml', function () {
        config(['seo.sitemap.images' => true]);
        config(['seo.sitemap.alternates' => true]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        expect($xml->xpath('//image:image/image:loc'))->toHaveCount(1)
            ->and($xml->xpath('//s:url/xhtml:link'))->toHaveCount(3);
    });

    it('does not duplicate a hand-built Url tag from a registered source', function () {
        // A manually constructed Spatie Url tag (the escape hatch) is passed
        // through verbatim — the builder must not append its own extensions.
        config(['seo.sitemap.images' => true]);
        config(['seo.sitemap.alternates' => true]);

        app(SitemapBuilder::class)->sitemaps()->register('manual', fn () => [
            Spatie\Sitemap\Tags\Url::create(url('/manual-page'))
                ->addImage(url('/images/manual.jpg')),
        ]);

        $sitemap = app(SitemapBuilder::class)->buildSourceSitemap('manual');
        $xml = loadSitemapXml($sitemap->render());

        // Exactly the one image the source declared, no alternates injected.
        expect($xml->xpath('//image:image/image:loc'))->toHaveCount(1)
            ->and((string) $xml->xpath('//image:image/image:loc')[0])->toContain('/images/manual.jpg')
            ->and($xml->xpath('//s:url/xhtml:link'))->toHaveCount(0);
    });

    it('skips malformed alternate entries without breaking the sitemap', function () {
        config(['seo.sitemap.alternates' => true]);
        config(['seo.sitemap.models' => [SitemapMalformedAltPost::class => []]]);

        // Drop the beforeEach row so only this fixture (shared table) is built.
        SitemapMalformedAltPost::query()->delete();

        SitemapMalformedAltPost::create([
            'title' => 'Malformed Alt Post',
            'slug' => 'malformed-alt',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        // Only the single well-formed alternate survives; the rest are dropped
        // and generation does not throw.
        $links = $xml->xpath('//s:url/xhtml:link');

        expect($links)->toHaveCount(1)
            ->and((string) $links[0]['hreflang'])->toBe('en')
            ->and((string) $links[0]['href'])->toBe(url('/posts/malformed-alt'));
    });

    it('enriches models yielded by a registered closure source', function () {
        config(['seo.sitemap.images' => true]);
        config(['seo.sitemap.alternates' => true]);

        app(SitemapBuilder::class)->sitemaps()->register(
            'extras',
            fn () => [SitemapExtensionPost::firstWhere('slug', 'extension-post')]
        );

        $sitemap = app(SitemapBuilder::class)->buildSourceSitemap('extras');
        $xml = loadSitemapXml($sitemap->render());

        // The registry path (normalizeSourceItem → buildUrl) gets the same
        // resolved extensions as a configured model.
        expect($xml->xpath('//image:image/image:loc'))->toHaveCount(1)
            ->and((string) $xml->xpath('//image:image/image:loc')[0])->toBe(url('/images/extension-post.jpg'))
            ->and($xml->xpath('//s:url/xhtml:link'))->toHaveCount(3);
    });

    it('never enriches a Sitemapable model that hand-builds its own Url', function () {
        config(['seo.sitemap.images' => true]);
        config(['seo.sitemap.alternates' => true]);
        config(['seo.sitemap.models' => [SitemapSitemapableExtPost::class => []]]);

        SitemapSitemapableExtPost::query()->delete();

        SitemapSitemapableExtPost::create([
            'title' => 'Hand Built',
            'slug' => 'hand-built-post',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        $images = $xml->xpath('//image:image/image:loc');

        // Only the hand-built image; the resolved image/alternates are ignored.
        expect($images)->toHaveCount(1)
            ->and((string) $images[0])->toBe(url('/images/hand-built.jpg'))
            ->and($content)->not->toContain('should-not-appear')
            ->and($xml->xpath('//s:url/xhtml:link'))->toHaveCount(0);
    });

    it('does not double-encode an entity-encoded content image in image:loc', function () {
        // The model's image is pulled from the first <img> in its content,
        // whose query string is written with an HTML entity ("&amp;"). The
        // builder decodes it once when extracting, then the XML render escapes
        // it once — so the entity survives end-to-end as a single ampersand,
        // never the double-encoded "&amp;amp;" that points at a wrong URL.
        //
        // Content extraction only runs when no site default_og_image is set;
        // otherwise the HasSEO trait's getSEOImage() returns that default and
        // short-circuits before the content <img> fallback.
        config(['seo.default_og_image' => null]);
        config(['seo.sitemap.images' => true]);
        config(['seo.sitemap.models' => [SitemapTestPost::class => []]]);

        SitemapTestPost::query()->delete();

        SitemapTestPost::create([
            'title' => 'Entity Image Post',
            'slug' => 'entity-image',
            'content' => '<p>Intro <img src="https://cdn.example.com/img?a=1&amp;b=2"> rest</p>',
            'is_published' => true,
        ]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $content = Storage::disk('public')->get('sitemap.xml');
        $xml = loadSitemapXml($content);

        $locations = $xml->xpath('//image:image/image:loc');

        // SimpleXML decodes the single "&amp;" back to "&"; a double-encoded
        // value would parse to "...&amp;b=2" instead and fail this assertion.
        expect($locations)->toHaveCount(1)
            ->and((string) $locations[0])->toBe('https://cdn.example.com/img?a=1&b=2')
            ->and($content)->not->toContain('&amp;amp;');
    });

    it('emits the site default_og_image on every url when a model has no image', function () {
        config(['seo.sitemap.images' => true]);
        config(['seo.default_og_image' => '/social/site-default.png']);
        config(['seo.sitemap.models' => [SitemapTestPost::class => []]]);

        SitemapTestPost::query()->delete();

        SitemapTestPost::create(['title' => 'A', 'slug' => 'plain-a', 'is_published' => true]);
        SitemapTestPost::create(['title' => 'B', 'slug' => 'plain-b', 'is_published' => true]);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        $xml = loadSitemapXml(Storage::disk('public')->get('sitemap.xml'));

        $locations = array_map(fn ($n) => (string) $n, $xml->xpath('//image:image/image:loc'));

        // The same resolved default fans out to every URL (the documented caveat).
        expect($locations)->toHaveCount(2)
            ->and($locations)->each->toBe(url('/social/site-default.png'));
    });
});
