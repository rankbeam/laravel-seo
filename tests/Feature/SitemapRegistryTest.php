<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Fibonoir\LaravelSEO\Facades\SEO;
use Fibonoir\LaravelSEO\Services\Sitemap\SitemapBuilder;
use Fibonoir\LaravelSEO\Services\Sitemap\SitemapRegistry;
use Fibonoir\LaravelSEO\Traits\HasSEO;

class RegistryTestArticle extends Model
{
    use HasSEO;

    protected $table = 'registry_test_articles';

    protected $fillable = ['title', 'slug', 'is_published'];

    public function getUrlForSEO(): string
    {
        return url("/articles/{$this->slug}");
    }
}

beforeEach(function () {
    Storage::fake('public');

    config(['seo.sitemap.disk' => 'public']);
    config(['seo.sitemap.path' => 'sitemap.xml']);
    config(['seo.sitemap.models' => []]);
    config(['seo.features.auto_create_meta' => false]);

    $this->app['db']->connection()->getSchemaBuilder()->create('registry_test_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->boolean('is_published')->default(true);
        $table->timestamps();
    });
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('registry_test_articles');
});

describe('SitemapRegistry', function () {
    it('is exposed as a singleton via SEO::sitemaps()', function () {
        expect(SEO::sitemaps())->toBeInstanceOf(SitemapRegistry::class)
            ->and(SEO::sitemaps())->toBe(app(SitemapRegistry::class))
            ->and(SEO::sitemaps())->toBe(SEO::sitemaps());
    });

    it('registers, lists, retrieves, and forgets sources fluently', function () {
        $registry = SEO::sitemaps();

        $source = fn () => ['/about'];

        expect($registry->register('pages', $source))->toBe($registry)
            ->and($registry->has('pages'))->toBeTrue()
            ->and($registry->get('pages'))->toBe($source)
            ->and($registry->names())->toBe(['pages']);

        $registry->forget('pages');

        expect($registry->has('pages'))->toBeFalse();
    });

    it('replaces the source when the same name is registered twice', function () {
        $registry = SEO::sitemaps();

        $registry->register('pages', ['/old']);
        $registry->register('pages', ['/new']);

        expect($registry->get('pages'))->toBe(['/new'])
            ->and($registry->names())->toBe(['pages']);
    });

    it('rejects names that do not fit the sitemap route constraint', function () {
        SEO::sitemaps()->register('Bad Name!', ['/about']);
    })->throws(InvalidArgumentException::class, 'Invalid sitemap name');

    it('rejects string sources that are not Eloquent model classes', function () {
        SEO::sitemaps()->register('pages', '/not-a-model');
    })->throws(InvalidArgumentException::class, 'Eloquent model class');

    it('throws when retrieving an unregistered name', function () {
        SEO::sitemaps()->get('missing');
    })->throws(InvalidArgumentException::class, 'No sitemap source registered');
});

describe('SitemapBuilder with registered sources', function () {
    it('writes sitemap-{name}.xml and an index for a closure source', function () {
        SEO::sitemaps()->register('pages', fn () => [
            '/about',
            ['url' => '/contact', 'priority' => 0.5, 'changefreq' => 'monthly'],
        ]);

        app(SitemapBuilder::class)->generate();

        Storage::disk('public')->assertExists('sitemap.xml');
        Storage::disk('public')->assertExists('sitemap-pages.xml');

        $index = Storage::disk('public')->get('sitemap.xml');
        $pages = Storage::disk('public')->get('sitemap-pages.xml');

        expect($index)->toContain('<sitemapindex')
            ->and($index)->toContain('sitemap-pages.xml')
            ->and($pages)->toContain('<urlset')
            ->and($pages)->toContain(url('/about'))
            ->and($pages)->toContain(url('/contact'))
            ->and($pages)->toContain('<priority>0.5</priority>')
            ->and($pages)->toContain('<changefreq>monthly</changefreq>');
    });

    it('accepts a plain iterable source', function () {
        SEO::sitemaps()->register('legal', ['/privacy', '/terms']);

        app(SitemapBuilder::class)->generate();

        $legal = Storage::disk('public')->get('sitemap-legal.xml');

        expect($legal)->toContain(url('/privacy'))
            ->and($legal)->toContain(url('/terms'));
    });

    it('accepts Spatie Url tags from a closure source', function () {
        SEO::sitemaps()->register('tagged', fn () => [
            \Spatie\Sitemap\Tags\Url::create(url('/pricing'))->setPriority(0.9),
        ]);

        app(SitemapBuilder::class)->generate();

        $tagged = Storage::disk('public')->get('sitemap-tagged.xml');

        expect($tagged)->toContain(url('/pricing'))
            ->and($tagged)->toContain('<priority>0.9</priority>');
    });

    it('runs an Eloquent model class source through the model pipeline', function () {
        RegistryTestArticle::create(['title' => 'Hello', 'slug' => 'hello', 'is_published' => true]);
        RegistryTestArticle::create(['title' => 'Draft', 'slug' => 'draft', 'is_published' => false]);

        SEO::sitemaps()->register('articles', RegistryTestArticle::class);

        app(SitemapBuilder::class)->generate();

        $articles = Storage::disk('public')->get('sitemap-articles.xml');

        expect($articles)->toContain('/articles/hello')
            ->and($articles)->not->toContain('/articles/draft');
    });

    it('combines config models and registered sources in one index', function () {
        config(['seo.sitemap.models' => [
            RegistryTestArticle::class => ['priority' => 0.8],
        ]]);

        RegistryTestArticle::create(['title' => 'Hello', 'slug' => 'hello', 'is_published' => true]);

        SEO::sitemaps()->register('pages', fn () => ['/about']);

        app(SitemapBuilder::class)->generate();

        $index = Storage::disk('public')->get('sitemap.xml');

        expect($index)->toContain('sitemap-registry-test-article.xml')
            ->and($index)->toContain('sitemap-pages.xml');

        Storage::disk('public')->assertExists('sitemap-registry-test-article.xml');
        Storage::disk('public')->assertExists('sitemap-pages.xml');
    });

    it('serves registered sitemaps over HTTP via the package route', function () {
        SEO::sitemaps()->register('pages', fn () => ['/about']);

        app(SitemapBuilder::class)->generate();

        $this->get('/sitemap-pages.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/about'), escape: false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('sitemap-pages.xml');
    });

    it('deletes registered source sitemaps via delete()', function () {
        SEO::sitemaps()->register('pages', fn () => ['/about']);

        $builder = app(SitemapBuilder::class);
        $builder->generate();

        Storage::disk('public')->assertExists('sitemap-pages.xml');

        $builder->delete();

        Storage::disk('public')->assertMissing('sitemap-pages.xml');
        Storage::disk('public')->assertMissing('sitemap.xml');
    });

    it('registers package routes by default', function () {
        expect(Route::has('seo.sitemap.index'))->toBeTrue()
            ->and(Route::has('seo.sitemap.show'))->toBeTrue();
    });
});
