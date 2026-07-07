<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\SEOResolver;

/*
|--------------------------------------------------------------------------
| Canonical query whitelist (seo.canonical.query_whitelist)
|--------------------------------------------------------------------------
|
| A canonical the resolver DERIVES from the request (or a model URL) strips
| its query string by default. The whitelist keeps named params — typically
| `page` for paginated archives — so a genuinely distinct page keeps its own
| canonical, while an explicitly set canonical is always emitted verbatim.
|
| These tests exercise the REQUEST-derived path end-to-end (the model-path
| filtering is covered in tests/Unit/Services/SEOResolverTest.php), plus the
| resolver-cache key: two requests differing only by a whitelisted param must
| not collide on one cache entry.
|
*/

class PagedListing extends Model
{
    protected $table = 'paged_listings';

    protected $fillable = ['title'];

    public $timestamps = false;

    // Derive the canonical from the live request so a whitelisted query param
    // reaches the canonical policy (the default HasSEO getUrlForSEO() would
    // already have stripped it via url()->current()).
    public function getUrlForSEO(): string
    {
        return request()->fullUrl();
    }
}

beforeEach(function () {
    config(['seo.title_suffix' => null]);

    Route::middleware('web')->group(function () {
        Route::get('/archive', fn () => response()->json([
            'canonical' => app(SEOResolver::class)->resolve()->canonical,
        ]));

        Route::get('/paged/{id}', fn ($id) => response()->json([
            'canonical' => app(SEOResolver::class)->resolve(PagedListing::find($id))->canonical,
        ]));
    });
});

afterEach(function () {
    Schema::dropIfExists('paged_listings');
});

it('strips every query param from a request-derived canonical by default', function () {
    // No whitelist configured (the default).
    $this->getJson('/archive?page=2&utm_source=news')
        ->assertOk()
        ->assertJson(['canonical' => 'http://localhost/archive']);
});

it('keeps a whitelisted param in a request-derived canonical and drops the rest', function () {
    config(['seo.canonical.query_whitelist' => ['page']]);

    $this->getJson('/archive?utm_source=news&page=2&sort=asc')
        ->assertOk()
        ->assertJson(['canonical' => 'http://localhost/archive?page=2']);
});

it('leaves an explicit canonical (with its own query) verbatim regardless of the whitelist', function () {
    config(['seo.canonical.query_whitelist' => ['page']]);

    $resolved = app(SEOResolver::class)->resolveSource(
        SEOData::fromArray(['title' => 'Listing', 'canonical' => 'https://example.com/x?ref=abc&y=1'])
    );

    expect($resolved->canonical)->toBe('https://example.com/x?ref=abc&y=1');
});

it('does not collide on the resolver cache for two whitelisted-param values', function () {
    // With the cache ON, the cache key must vary by the whitelisted param —
    // otherwise ?page=1 would be served for ?page=2. Regression for the
    // currentRequestUrl() cache-key change.
    Schema::create('paged_listings', function ($table) {
        $table->id();
        $table->string('title')->nullable();
    });

    config([
        'seo.canonical.query_whitelist' => ['page'],
        'seo.cache.resolver.enabled' => true,
        'seo.cache.store' => 'array',
    ]);

    $listing = PagedListing::create(['title' => 'Products']);

    // Warm the cache on page=1, then read page=2 — must recompute, not collide.
    $this->getJson("/paged/{$listing->id}?page=1")
        ->assertOk()
        ->assertJson(['canonical' => "http://localhost/paged/{$listing->id}?page=1"]);

    $this->getJson("/paged/{$listing->id}?page=2")
        ->assertOk()
        ->assertJson(['canonical' => "http://localhost/paged/{$listing->id}?page=2"]);
});
