<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Services\Schema\SchemaGraph;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Services\TagRenderer;
use Rankbeam\Seo\Tests\Fixtures\NonTaggableArrayStore;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| Resolver result cache — integration
|--------------------------------------------------------------------------
|
| Proves the opt-in resolver result cache (seo.cache.resolver.enabled):
|
| - a hit skips the precedence chain (a raw DB write behind the model's back
|   is NOT seen until the cache is invalidated);
| - saving seo_meta, mutating a getSEOContentFields() column, and editing
|   seo_defaults each bust the right entries;
| - caching ON produces byte-identical results to caching OFF (parity);
| - everything works on BOTH a taggable store (array → cache tags) and a
|   non-taggable store (the in-memory NonTaggableArrayStore → version stamp).
|
*/

class CachePage extends Model
{
    use HasSEO;

    protected $table = 'cache_pages';

    protected $fillable = ['title', 'body', 'excerpt', 'headline', 'hero_image', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function getUrlForSEO(): string
    {
        return 'https://example.test/pages/'.$this->getKey();
    }

    // Force og:type=article so published/modified times render — exercises the
    // DateTime round-trip in the rendered HTML parity assertion.
    public function getSEOOgType(): string
    {
        return 'article';
    }
}

class SchemaCachePage extends CachePage
{
    // A getSEOSchema() that composes a webPage() node re-resolves seoData(),
    // re-entering the resolver — the depth guard must keep that nested resolve
    // out of the cache while the outer (with-schema) result is cached.
    public function getSEOSchema(): array
    {
        return SchemaGraph::for($this)->webPage()->toArray();
    }
}

/** Make a non-taggable store available under the name 'seo_nontaggable'. */
function registerNonTaggableStore(): void
{
    app('cache')->extend(
        'seo_nontaggable_driver',
        fn () => app('cache')->repository(new NonTaggableArrayStore)
    );
    config()->set('cache.stores.seo_nontaggable', ['driver' => 'seo_nontaggable_driver']);
}

beforeEach(function () {
    Schema::create('cache_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('headline')->nullable();
        $table->text('body')->nullable();
        $table->text('excerpt')->nullable();
        $table->string('hero_image')->nullable();
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    registerNonTaggableStore();

    config([
        'seo.features.auto_create_meta' => false,
        'seo.title_suffix' => null,
        'seo.cache.resolver.enabled' => true,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('cache_pages');
});

dataset('cache stores', [
    'taggable (array)' => 'array',
    'non-taggable (version stamp)' => 'seo_nontaggable',
]);

function resolveTitle(CachePage $page): ?string
{
    return app(SEOResolver::class)->resolve($page)->title;
}

it('a cache hit skips the precedence chain; saving seo_meta busts it', function (string $store) {
    config(['seo.cache.store' => $store]);

    $page = CachePage::create(['title' => 'Model Title']);
    $page->saveSEO(['title' => 'Stored']);
    $page = $page->fresh();

    // Warm the cache.
    expect(resolveTitle($page))->toBe('Stored');

    // A raw write behind the model's back fires no events → cache stays warm.
    DB::table('seo_meta')->where('seoable_id', $page->id)->update(['title' => 'DirectWrite']);

    expect(resolveTitle(CachePage::find($page->id)))->toBe('Stored'); // hit, chain skipped

    // A proper save through the model busts the entry.
    $page->saveSEO(['title' => 'Proper']);

    expect(resolveTitle(CachePage::find($page->id)))->toBe('Proper');
})->with('cache stores');

it('mutating a getSEOContentFields() column busts the cache', function (string $store) {
    config(['seo.cache.store' => $store]);

    $page = CachePage::create(['title' => 'Original']); // no seo_meta row

    expect(resolveTitle($page))->toBe('Original'); // warm

    // Prove it is cached: a raw write is not seen on the next resolve.
    DB::table('cache_pages')->where('id', $page->id)->update(['title' => 'Sneaky']);
    expect(resolveTitle(CachePage::find($page->id)))->toBe('Original');

    // A real content-field change busts it (title ∈ getSEOContentFields()).
    $page->update(['title' => 'Renamed']);

    expect(resolveTitle(CachePage::find($page->id)))->toBe('Renamed');
})->with('cache stores');

it('busts when any computed-builder fallback field changes', function (string $store) {
    config(['seo.cache.store' => $store]);

    $page = CachePage::create([
        'title' => null,
        'headline' => 'Original Headline',
        'hero_image' => '/images/original.jpg',
    ]);

    $seo = app(SEOResolver::class)->resolve($page);
    expect($seo->title)->toBe('Original Headline')
        ->and($seo->ogImage)->toBe('http://localhost/images/original.jpg');

    DB::table('cache_pages')->where('id', $page->id)->update([
        'headline' => 'Sneaky Headline',
        'hero_image' => '/images/sneaky.jpg',
    ]);

    $cached = app(SEOResolver::class)->resolve(CachePage::find($page->id));
    expect($cached->title)->toBe('Original Headline')
        ->and($cached->ogImage)->toBe('http://localhost/images/original.jpg');

    $page->update([
        'headline' => 'Updated Headline',
        'hero_image' => '/images/updated.jpg',
    ]);

    $fresh = app(SEOResolver::class)->resolve(CachePage::find($page->id));
    expect($fresh->title)->toBe('Updated Headline')
        ->and($fresh->ogImage)->toBe('http://localhost/images/updated.jpg');
})->with('cache stores');

it('editing seo_defaults busts every model', function (string $store) {
    config(['seo.cache.store' => $store]);

    $page = CachePage::create(['title' => 'T']); // no body/excerpt → no computed description

    expect(app(SEOResolver::class)->resolve($page)->description)->toBeNull(); // warm

    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'description_template' => 'Global Desc',
    ]);

    expect(app(SEOResolver::class)->resolve($page->fresh())->description)->toBe('Global Desc');
})->with('cache stores');

it('produces byte-identical results with caching ON vs OFF (parity)', function (string $store) {
    config(['seo.cache.store' => $store]);

    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'og_image_default' => '/global-og.jpg',
    ]);

    $page = CachePage::create([
        'title' => 'Parity Title',
        'body' => '<p>Body copy for the computed description fallback.</p>',
        'published_at' => '2026-01-02 03:04:05',
    ]);
    $page->saveSEO([
        'description' => 'Explicit description',
        'og_title' => 'Explicit OG Title',
        'twitter_card' => 'summary',
        'focus_keywords' => [['keyword' => 'parity', 'is_primary' => true]],
    ]);
    $page = $page->fresh();

    $renderer = app(TagRenderer::class);

    // Truth: compute with caching OFF (always recomputes).
    config(['seo.cache.resolver.enabled' => false]);
    $truth = app(SEOResolver::class)->resolve($page);
    $truthFlat = $truth->toFlatArray();
    $truthHtml = $renderer->render($truth);

    // Now caching ON: a miss (populates) then a hit.
    config(['seo.cache.resolver.enabled' => true]);
    $miss = app(SEOResolver::class)->resolve($page);
    $hit = app(SEOResolver::class)->resolve($page);

    expect($miss->toFlatArray())->toEqual($truthFlat)
        ->and($hit->toFlatArray())->toEqual($truthFlat)
        ->and($renderer->render($hit))->toBe($truthHtml);
})->with('cache stores');

it('does not cache when disabled (off by default)', function () {
    config(['seo.cache.store' => 'array', 'seo.cache.resolver.enabled' => false]);

    $page = CachePage::create(['title' => 'X']);
    $page->saveSEO(['title' => 'First']);
    $page = $page->fresh();

    expect(resolveTitle($page))->toBe('First');

    // With no cache, a raw write is reflected on the very next resolve.
    DB::table('seo_meta')->where('seoable_id', $page->id)->update(['title' => 'Second']);

    expect(resolveTitle(CachePage::find($page->id)))->toBe('Second');
});

it('leaves model-less route resolves uncached and working with caching on', function () {
    config(['seo.cache.store' => 'array']);

    // No model → not cached; must still resolve without error.
    $seo = app(SEOResolver::class)->resolveForRoute('some.route');

    expect($seo)->toBeInstanceOf(\Rankbeam\Seo\Data\SEOData::class);
});

it('caches a computed schema graph (re-entrant webPage) with full parity', function () {
    config(['seo.cache.store' => 'array']);

    $page = SchemaCachePage::create(['title' => 'Schema Page']);

    // Truth with caching off (the schema layer runs every time).
    config(['seo.cache.resolver.enabled' => false]);
    $truth = app(SEOResolver::class)->resolve($page);

    expect($truth->schemaJsonld)->not->toBeNull(); // sanity: the hook produced a graph

    // Caching on: a miss populates (the nested webPage resolve must NOT cache a
    // schema-less entry under the same key), then a hit returns the full graph.
    config(['seo.cache.resolver.enabled' => true]);
    $miss = app(SEOResolver::class)->resolve($page);
    $hit = app(SEOResolver::class)->resolve($page);

    expect($miss->toFlatArray())->toEqual($truth->toFlatArray())
        ->and($hit->toFlatArray())->toEqual($truth->toFlatArray())
        ->and($hit->schemaJsonld)->toEqual($truth->schemaJsonld);
});

it('busts via the seoable morph alias, not the raw type string', function () {
    config(['seo.cache.store' => 'array']);

    Relation::morphMap(['cache_page' => CachePage::class]);

    try {
        $page = CachePage::create(['title' => 'Aliased']);
        $page->saveSEO(['title' => 'Stored']);
        $page = $page->fresh();

        // The stored row carries the alias, not the FQCN.
        expect(DB::table('seo_meta')->where('seoable_id', $page->id)->value('seoable_type'))
            ->toBe('cache_page');

        expect(resolveTitle($page))->toBe('Stored'); // warm

        DB::table('seo_meta')->where('seoable_id', $page->id)->update(['title' => 'DirectWrite']);
        expect(resolveTitle(CachePage::find($page->id)))->toBe('Stored'); // hit

        // saveSEO → SEOMeta saved → forgetModel must normalize 'cache_page' to
        // the FQCN the resolver keyed by, or the bust would miss.
        $page->saveSEO(['title' => 'Proper']);
        expect(resolveTitle(CachePage::find($page->id)))->toBe('Proper');
    } finally {
        Relation::$morphMap = [];
    }
});
