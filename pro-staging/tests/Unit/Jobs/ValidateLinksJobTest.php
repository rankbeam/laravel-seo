<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Fibonoir\LaravelSEO\Jobs\ValidateLinksJob;
use Fibonoir\LaravelSEO\Services\Scanner\BrokenLinkChecker;
use Fibonoir\LaravelSEO\Traits\HasSEO;

// Test model for link validation tests
class LinkTestModel extends Model
{
    use HasSEO;

    protected $table = 'link_test_models';

    protected $fillable = ['title', 'content'];

    public $timestamps = true;

    public function getContentForSEO(): string
    {
        return $this->content ?? '';
    }

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }
}

beforeEach(function () {
    Http::fake();

    // Create test tables
    $this->app['db']->connection()->getSchemaBuilder()->create('link_test_models', function ($table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
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

    config(['seo.features.auto_create_meta' => false]);
    config(['seo.features.auto_analyze' => false]);
    config(['seo.features.internal_links_index' => false]);
    config(['app.url' => 'https://mysite.com']);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('link_test_models');
});

describe('ValidateLinksJob', function () {
    it('validates internal links', function () {
        Http::fake([
            'mysite.com/*' => Http::response('OK', 200),
        ]);

        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<p>Check <a href="https://mysite.com/page1">internal link</a></p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        // Mock checker to return no broken links
        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->once()
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');

        $meta = $model->fresh()->seoMeta;

        expect($meta->analysis_report)->toHaveKey('brokenLinks')
            ->and($meta->analysis_report['brokenLinks'])->toBe([]);
    });

    it('validates external links', function () {
        Http::fake([
            'external.com/*' => Http::response('OK', 200),
        ]);

        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<p>Check <a href="https://external.com/page">external link</a></p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->once()
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');
    });

    it('stores broken links in report', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<p>Check <a href="https://broken.com/404">broken</a> and <a href="https://down.com">down</a></p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->once()
            ->andReturn([
                ['url' => 'https://broken.com/404', 'status' => 404],
                ['url' => 'https://down.com', 'status' => 0],
            ]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');

        $meta = $model->fresh()->seoMeta;

        expect($meta->analysis_report['brokenLinks'])->toHaveCount(2);

        $brokenUrls = collect($meta->analysis_report['brokenLinks'])->pluck('url')->toArray();
        expect($brokenUrls)->toContain('https://broken.com/404')
            ->and($brokenUrls)->toContain('https://down.com');
    });

    it('validates images', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<img src="https://mysite.com/image.jpg" alt="test"><img src="https://broken.com/missing.png">',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkImages')
            ->once()
            ->andReturn([
                ['src' => 'https://broken.com/missing.png', 'status' => 404],
            ]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'images');

        $meta = $model->fresh()->seoMeta;

        expect($meta->analysis_report)->toHaveKey('brokenImages')
            ->and($meta->analysis_report['brokenImages'])->toHaveCount(1);
    });

    it('handles model not found', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('ValidateLinksJob: Model not found', Mockery::any());

        ValidateLinksJob::dispatchSync(LinkTestModel::class, 99999, 'links');
    });

    it('handles empty content', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        // Should not call checker when no content
        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldNotReceive('checkLinks');
        $mockChecker->shouldNotReceive('checkImages');

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');
    });

    it('skips mailto and tel links', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<a href="mailto:test@example.com">Email</a><a href="tel:+1234567890">Call</a><a href="https://valid.com">Valid</a>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->withArgs(function ($links) {
                // Should only contain the valid URL, not mailto or tel
                return count($links) === 1 && $links[0] === 'https://valid.com';
            })
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');
    });

    it('skips anchor links', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<a href="#section1">Section 1</a><a href="https://valid.com">Valid</a>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->withArgs(function ($links) {
                return count($links) === 1;
            })
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');
    });

    it('skips data URIs for images', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<img src="data:image/png;base64,iVBORw0KGgo="><img src="https://valid.com/img.jpg">',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkImages')
            ->withArgs(function ($images) {
                return count($images) === 1 && str_contains($images[0], 'valid.com');
            })
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'images');
    });

    it('converts relative URLs to absolute', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<a href="/relative/page">Relative</a>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')
            ->withArgs(function ($links) {
                return $links[0] === 'https://mysite.com/relative/page';
            })
            ->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');
    });

    it('stores timestamp with results', function () {
        $model = LinkTestModel::create([
            'title' => 'Test',
            'content' => '<a href="https://example.com">Link</a>',
        ]);

        $model->seoMeta()->create(['locale' => 'en', 'analysis_report' => []]);

        $mockChecker = Mockery::mock(BrokenLinkChecker::class);
        $mockChecker->shouldReceive('checkLinks')->andReturn([]);

        $this->app->instance(BrokenLinkChecker::class, $mockChecker);

        ValidateLinksJob::dispatchSync(LinkTestModel::class, $model->id, 'links');

        $meta = $model->fresh()->seoMeta;

        expect($meta->analysis_report)->toHaveKey('brokenLinks_checked_at')
            ->and($meta->analysis_report['brokenLinks_checked_at'])->not->toBeNull();
    });

    it('has correct retry settings', function () {
        $job = new ValidateLinksJob(LinkTestModel::class, 1, 'links');

        expect($job->tries)->toBe(2)
            ->and($job->backoff)->toBe(120)
            ->and($job->timeout)->toBe(300);
    });

    it('logs failure', function () {
        $job = new ValidateLinksJob(LinkTestModel::class, 1, 'links');

        Log::shouldReceive('error')
            ->once()
            ->with('ValidateLinksJob failed', Mockery::any());

        $job->failed(new \Exception('Test error'));
    });
});
