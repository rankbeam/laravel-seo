<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| Resolver result cache — benchmark (T12)
|--------------------------------------------------------------------------
|
| Shows the win at the per-request level the reference app sees ~20k×/day:
| a warm cache hit issues ZERO database queries (it skips the whole
| precedence chain), where each uncached resolve re-reads the model's
| seo_meta. Asserts the deterministic query-count win and prints wall-clock.
|
*/

class BenchPage extends Model
{
    use HasSEO;

    protected $table = 'bench_pages';

    protected $fillable = ['title', 'body'];

    public function getUrlForSEO(): string
    {
        return 'https://example.test/bench/'.$this->getKey();
    }
}

beforeEach(function () {
    Schema::create('bench_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->text('body')->nullable();
        $table->timestamps();
    });

    config([
        'seo.features.auto_create_meta' => false,
        'seo.title_suffix' => null,
        'seo.cache.store' => 'array',
    ]);
});

afterEach(function () {
    Schema::dropIfExists('bench_pages');
});

it('serves warm cache hits with zero database queries', function () {
    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'description_template' => 'Global default description',
    ]);

    $page = BenchPage::create(['title' => 'Bench', 'body' => 'Body copy.']);
    $page->saveSEO(['description' => 'Explicit description']);
    $page = $page->fresh();

    $resolver = app(SEOResolver::class);
    $iterations = 25;

    // --- Uncached: every resolve re-reads the model's seo_meta. ---
    config(['seo.cache.resolver.enabled' => false]);
    $resolver->resolve($page); // prime the defaults-repo memo (one-time) so we measure steady state

    DB::flushQueryLog();
    DB::enableQueryLog();
    $startUncached = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $resolver->resolve($page);
    }
    $uncachedMs = (microtime(true) - $startUncached) * 1000;
    $uncachedQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // --- Cached: warm once, then every resolve is a hit. ---
    config(['seo.cache.resolver.enabled' => true]);
    $resolver->resolve($page); // warm

    DB::flushQueryLog();
    DB::enableQueryLog();
    $startCached = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $resolver->resolve($page);
    }
    $cachedMs = (microtime(true) - $startCached) * 1000;
    $cachedQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    fwrite(STDOUT, sprintf(
        "\n[T12 benchmark] %d resolves — uncached: %d queries / %.2f ms · cached: %d queries / %.2f ms\n",
        $iterations,
        $uncachedQueries,
        $uncachedMs,
        $cachedQueries,
        $cachedMs,
    ));

    // Deterministic win: a warm hit touches the DB zero times; uncached resolves
    // each re-read seo_meta.
    expect($cachedQueries)->toBe(0)
        ->and($uncachedQueries)->toBeGreaterThanOrEqual($iterations);
});
