<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder;
use Rankbeam\Seo\Services\Sitemap\SitemapRegistry;
use Rankbeam\Seo\Traits\HasSEO;

// A HasSEO model whose resolved title/description feed the llms.txt bullets,
// mirroring how the sitemap derives a model's URL from getUrlForSEO().
class LlmsTxtArticle extends Model
{
    use HasSEO;

    protected $table = 'llms_txt_articles';

    protected $fillable = ['title', 'slug', 'content', 'is_published'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/articles/{$this->slug}");
    }
}

beforeEach(function () {
    Storage::fake('public');

    $this->app['db']->connection()->getSchemaBuilder()->create('llms_txt_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('content')->nullable();
        $table->boolean('is_published')->default(true);
        $table->timestamps();
    });

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

    // Clean slate every test.
    config(['seo.llms_txt.enabled' => true]);
    config(['seo.llms_txt.route' => true]);
    config(['seo.llms_txt.disk' => 'public']);
    config(['seo.llms_txt.path' => 'llms.txt']);
    config(['seo.llms_txt.title' => null]);
    config(['seo.llms_txt.description' => null]);
    config(['seo.llms_txt.sources' => []]);
    config(['seo.llms_txt.max_entries_per_section' => 100]);

    config(['seo.sitemap.models' => []]);
    config(['seo.features.auto_create_meta' => false]);

    SEO::sitemaps()->flush();
});

afterEach(function () {
    SEO::sitemaps()->flush();
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('llms_txt_articles');
});

describe('LlmsTxtBuilder markdown shape', function () {
    it('emits a single H1 from the configured title', function () {
        config(['seo.llms_txt.title' => 'Acme Blog']);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toStartWith('# Acme Blog')
            ->and(substr_count($markdown, "\n# "))->toBe(0); // exactly one H1
    });

    it('falls back to the site name for the H1 when no title is configured', function () {
        // The test harness sets seo.site_name = 'Test Site'.
        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toStartWith('# Test Site');
    });

    it('renders the description as a blockquote under the H1, and omits it when absent', function () {
        config(['seo.llms_txt.description' => 'The latest from Acme.']);

        $withSummary = app(LlmsTxtBuilder::class)->build();

        expect($withSummary)->toContain("\n> The latest from Acme.");

        config(['seo.llms_txt.description' => null]);

        expect(app(LlmsTxtBuilder::class)->build())->not->toContain('> ');
    });

    it('renders one section per source with bracketed-link bullets', function () {
        SEO::sitemaps()->register('pages', fn () => [
            ['url' => '/about', 'title' => 'About Us', 'description' => 'Who we are.'],
            '/contact',
        ]);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toContain('## Pages')
            ->and($markdown)->toContain('- [About Us](' . url('/about') . '): Who we are.')
            // A bare string entry gets a URL-derived title and no description.
            ->and($markdown)->toContain('- [Contact](' . url('/contact') . ')')
            ->and($markdown)->not->toContain('- [Contact](' . url('/contact') . '):');
    });
});

describe('LlmsTxtBuilder reuses the sitemap sources', function () {
    it('draws sections from registered named sources', function () {
        SEO::sitemaps()->register('legal', ['/privacy', '/terms']);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toContain('## Legal')
            ->and($markdown)->toContain(url('/privacy'))
            ->and($markdown)->toContain(url('/terms'));
    });

    it('draws a section from a configured sitemap.models entry, using resolved title/description', function () {
        config(['seo.sitemap.models' => [LlmsTxtArticle::class => []]]);

        $article = LlmsTxtArticle::create([
            'title' => 'Hello World',
            'slug' => 'hello',
            'is_published' => true,
        ]);
        $article->saveSEO(['description' => 'A friendly intro.']);

        $markdown = app(LlmsTxtBuilder::class)->build();

        // Heading is the pluralized, headlined model basename.
        expect($markdown)->toContain('## Llms Txt Articles')
            // Title from the resolved SEOData (title suffix applied by the resolver).
            ->and($markdown)->toContain('[Hello World | Test Site](' . url('/articles/hello') . ')')
            ->and($markdown)->toContain(': A friendly intro.');
    });

    it('runs a registered Eloquent model-class source through the model pipeline', function () {
        LlmsTxtArticle::create(['title' => 'Published', 'slug' => 'pub', 'is_published' => true]);
        LlmsTxtArticle::create(['title' => 'Draft', 'slug' => 'draft', 'is_published' => false]);

        SEO::sitemaps()->register('articles', LlmsTxtArticle::class);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toContain('/articles/pub')
            ->and($markdown)->not->toContain('/articles/draft');
    });

    it('shares the registry singleton with SEO::sitemaps()', function () {
        expect(app(LlmsTxtBuilder::class)->sitemaps())
            ->toBe(app(SitemapRegistry::class))
            ->toBe(SEO::sitemaps());
    });
});

describe('LlmsTxtBuilder excludes the same URLs the sitemap does', function () {
    it('excludes unpublished and resolved-noindex pages', function () {
        config(['seo.sitemap.models' => [LlmsTxtArticle::class => []]]);

        LlmsTxtArticle::create(['title' => 'Visible', 'slug' => 'visible', 'is_published' => true]);
        LlmsTxtArticle::create(['title' => 'Hidden Draft', 'slug' => 'hidden', 'is_published' => false]);

        $noindex = LlmsTxtArticle::create(['title' => 'Noindex', 'slug' => 'noindex', 'is_published' => true]);
        $noindex->saveSEO(['robots' => 'noindex,follow']);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toContain('/articles/visible')
            ->and($markdown)->not->toContain('/articles/hidden')
            ->and($markdown)->not->toContain('/articles/noindex');
    });

    it('bounds each section by max_entries_per_section', function () {
        config(['seo.llms_txt.max_entries_per_section' => 2]);

        SEO::sitemaps()->register('many', ['/a', '/b', '/c', '/d']);

        $markdown = app(LlmsTxtBuilder::class)->build();

        // Only the first two of four bullets survive the cap.
        expect(substr_count($markdown, "\n- ["))->toBe(2);
    });
});

describe('LlmsTxtBuilder absolute-URL handling', function () {
    it('makes relative paths absolute and passes absolute URLs through', function () {
        SEO::sitemaps()->register('mix', [
            '/relative',
            'https://external.example.com/page',
        ]);

        $markdown = app(LlmsTxtBuilder::class)->build();

        expect($markdown)->toContain('(' . url('/relative') . ')')
            ->and($markdown)->toContain('(https://external.example.com/page)');
    });
});

describe('seo:llms-txt command', function () {
    it('writes the file to the configured disk and path', function () {
        SEO::sitemaps()->register('pages', fn () => ['/about']);

        $exit = Artisan::call('seo:llms-txt');

        expect($exit)->toBe(0)
            ->and(Artisan::output())->toContain('generated successfully');

        Storage::disk('public')->assertExists('llms.txt');
        expect(Storage::disk('public')->get('llms.txt'))->toContain(url('/about'));
    });

    it('refuses to run when disabled', function () {
        config(['seo.llms_txt.enabled' => false]);

        $exit = Artisan::call('seo:llms-txt');

        expect($exit)->toBe(1)
            ->and(Artisan::output())->toContain('disabled');

        Storage::disk('public')->assertMissing('llms.txt');
    });

    it('prints to stdout without writing on --print', function () {
        config(['seo.llms_txt.title' => 'Printed Site']);

        $exit = Artisan::call('seo:llms-txt', ['--print' => true]);

        expect($exit)->toBe(0)
            ->and(Artisan::output())->toContain('# Printed Site');

        Storage::disk('public')->assertMissing('llms.txt');
    });

    it('writes to a custom path on the same disk with --output', function () {
        SEO::sitemaps()->register('pages', fn () => ['/about']);

        $exit = Artisan::call('seo:llms-txt', ['--output' => 'ai/llms.txt']);

        expect($exit)->toBe(0);

        Storage::disk('public')->assertExists('ai/llms.txt');
        Storage::disk('public')->assertMissing('llms.txt');
    });
});

describe('SEO::llmsTxt() facade accessor', function () {
    it('resolves the builder via the facade', function () {
        expect(SEO::llmsTxt())->toBeInstanceOf(LlmsTxtBuilder::class);
    });
});

describe('/llms.txt route', function () {
    it('serves the generated file as markdown when it exists', function () {
        SEO::sitemaps()->register('pages', fn () => ['/about']);
        app(LlmsTxtBuilder::class)->generate();

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee(url('/about'), escape: false);
    });

    it('404s when the file has not been generated', function () {
        $this->get('/llms.txt')->assertNotFound();
    });
});
