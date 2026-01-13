<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Fibonoir\LaravelSEO\Data\AnalysisReport;
use Fibonoir\LaravelSEO\Jobs\AnalyzeContentJob;
use Fibonoir\LaravelSEO\Jobs\ValidateLinksJob;
use Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer;
use Fibonoir\LaravelSEO\Traits\HasSEO;

// Test model for job tests
class JobTestModel extends Model
{
    use HasSEO;

    protected $table = 'job_test_models';

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

// Non-SEO model for testing
class NonSEOModel extends Model
{
    protected $table = 'non_seo_models';

    protected $fillable = ['title'];
}

beforeEach(function () {
    Queue::fake();

    // Create test tables
    $this->app['db']->connection()->getSchemaBuilder()->create('job_test_models', function ($table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->timestamps();
    });

    $this->app['db']->connection()->getSchemaBuilder()->create('non_seo_models', function ($table) {
        $table->id();
        $table->string('title');
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
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('job_test_models');
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('non_seo_models');
});

describe('AnalyzeContentJob', function () {
    it('analyzes model and stores score', function () {
        $model = JobTestModel::create([
            'title' => 'Test Article Title',
            'content' => '<h1>Test Title</h1><p>This is sample content for testing the SEO analysis. It contains multiple sentences and should produce a valid score.</p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        // Create a mock analyzer
        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(new AnalysisReport(
                totalScore: 75,
                results: [],
                weights: [],
                locale: 'en',
            ));

        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        // Run job synchronously
        AnalyzeContentJob::dispatchSync(JobTestModel::class, $model->id);

        // Check score was stored
        $meta = $model->fresh()->seoMeta;
        expect($meta->seo_score)->toBe(75)
            ->and($meta->analyzed_at)->not->toBeNull()
            ->and($meta->content_hash)->not->toBeNull();
    });

    it('handles model not found', function () {
        Log::shouldReceive('warning')
            ->once()
            ->with('AnalyzeContentJob: Model not found', Mockery::any());

        // Dispatch with non-existent ID
        AnalyzeContentJob::dispatchSync(JobTestModel::class, 99999);

        // Should not throw, just log warning
    });

    it('handles model without HasSEO trait', function () {
        $model = NonSEOModel::create(['title' => 'Test']);

        Log::shouldReceive('warning')
            ->once()
            ->with('AnalyzeContentJob: Model does not use HasSEO trait', Mockery::any());

        AnalyzeContentJob::dispatchSync(NonSEOModel::class, $model->id);

        // Should not throw, just log warning
    });

    it('dispatches link validation', function () {
        $model = JobTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content with <a href="https://example.com">link</a></p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        // Create a mock analyzer
        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(
                totalScore: 50,
                results: [],
                weights: [],
                locale: 'en',
            ));

        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        AnalyzeContentJob::dispatchSync(JobTestModel::class, $model->id);

        // Should dispatch ValidateLinksJob for both links and images
        Queue::assertPushed(ValidateLinksJob::class, function ($job) use ($model) {
            return $job->modelClass === JobTestModel::class
                && $job->modelId === $model->id
                && $job->linkType === 'links';
        });

        Queue::assertPushed(ValidateLinksJob::class, function ($job) use ($model) {
            return $job->modelClass === JobTestModel::class
                && $job->modelId === $model->id
                && $job->linkType === 'images';
        });
    });

    it('creates seo meta if not exists', function () {
        $model = JobTestModel::create([
            'title' => 'New Model',
            'content' => '<p>Content</p>',
        ]);

        // No seoMeta created yet

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(
                totalScore: 60,
                results: [],
                weights: [],
                locale: 'en',
            ));

        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        AnalyzeContentJob::dispatchSync(JobTestModel::class, $model->id);

        $meta = $model->fresh()->seoMeta;
        expect($meta)->not->toBeNull()
            ->and($meta->seo_score)->toBe(60);
    });

    it('uses correct locale', function () {
        $model = JobTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content</p>',
        ]);

        $model->seoMeta()->create(['locale' => 'de']);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->withArgs(function ($m, $locale) {
                return $locale === 'de';
            })
            ->andReturn(new AnalysisReport(
                totalScore: 50,
                results: [],
                weights: [],
                locale: 'de',
            ));

        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        AnalyzeContentJob::dispatchSync(JobTestModel::class, $model->id);
    });

    it('stores content hash for change detection', function () {
        $model = JobTestModel::create([
            'title' => 'Test',
            'content' => 'Unique content for hashing',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(
                totalScore: 50,
                results: [],
                weights: [],
                locale: 'en',
            ));

        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        AnalyzeContentJob::dispatchSync(JobTestModel::class, $model->id);

        $meta = $model->fresh()->seoMeta;
        expect($meta->content_hash)->toBe(md5('Unique content for hashing'));
    });

    it('has correct job tags', function () {
        $job = new AnalyzeContentJob(JobTestModel::class, 123);

        $tags = $job->tags();

        expect($tags)->toContain('seo')
            ->and($tags)->toContain('analyze')
            ->and($tags)->toContain(JobTestModel::class)
            ->and($tags)->toContain(JobTestModel::class . ':123');
    });

    it('retries on failure', function () {
        $job = new AnalyzeContentJob(JobTestModel::class, 1);

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(60);
    });

    it('logs failure', function () {
        $job = new AnalyzeContentJob(JobTestModel::class, 1, 'en');

        Log::shouldReceive('error')
            ->once()
            ->with('AnalyzeContentJob failed', Mockery::any());

        $job->failed(new \Exception('Test error'));
    });
});
