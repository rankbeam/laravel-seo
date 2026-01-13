<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Fibonoir\LaravelSEO\Data\AnalysisReport;
use Fibonoir\LaravelSEO\Jobs\ScanPageJob;
use Fibonoir\LaravelSEO\Jobs\ValidateLinksJob;
use Fibonoir\LaravelSEO\Models\SEOScanIssue;
use Fibonoir\LaravelSEO\Models\SEOScanRun;
use Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer;
use Fibonoir\LaravelSEO\Services\Scanner\PageScanner;
use Fibonoir\LaravelSEO\Traits\HasSEO;

// Test model for scan job tests
class ScanTestModel extends Model
{
    use HasSEO;

    protected $table = 'scan_test_models';

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

    public function getUrlForSEO(): string
    {
        return url("/test/{$this->id}");
    }
}

beforeEach(function () {
    Queue::fake();

    // Create test tables
    $this->app['db']->connection()->getSchemaBuilder()->create('scan_test_models', function ($table) {
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

    // Create scan tables
    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_scan_runs')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_scan_runs', function ($table) {
            $table->id();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->integer('total_pages')->default(0);
            $table->integer('scanned_pages')->default(0);
            $table->integer('issues_found')->default(0);
            $table->json('options')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('seo_scan_issues')) {
        $this->app['db']->connection()->getSchemaBuilder()->create('seo_scan_issues', function ($table) {
            $table->id();
            $table->nullableMorphs('scannable');
            $table->string('url')->nullable();
            $table->string('issue_type');
            $table->string('severity');
            $table->string('field')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('scan_run_id')->nullable();
            $table->timestamps();
        });
    }

    config(['seo.features.auto_create_meta' => false]);
    config(['seo.features.auto_analyze' => false]);
    config(['seo.features.internal_links_index' => false]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('scan_test_models');
});

describe('ScanPageJob', function () {
    it('scans page and creates issues', function () {
        $model = ScanTestModel::create([
            'title' => 'Test Page',
            'content' => '<p>Short content</p>',
        ]);

        // Create SEO meta without title (to trigger missing_title issue)
        $model->seoMeta()->create(['locale' => 'en']);

        // Mock page scanner to return issues
        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')
            ->once()
            ->andReturn([
                [
                    'issue_type' => 'missing_title',
                    'severity' => 'critical',
                    'field' => 'title',
                    'message' => 'Page is missing a title tag.',
                ],
                [
                    'issue_type' => 'thin_content',
                    'severity' => 'warning',
                    'field' => 'content',
                    'message' => 'Content is too short.',
                ],
            ]);

        // Mock content analyzer
        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(
                totalScore: 40,
                results: [],
                analyzedAt: new \DateTimeImmutable(),
                locale: 'en',
            ));

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id);

        // Check issues were created
        $issues = SEOScanIssue::where('scannable_type', ScanTestModel::class)
            ->where('scannable_id', $model->id)
            ->get();

        expect($issues)->toHaveCount(2)
            ->and($issues->firstWhere('issue_type', 'missing_title'))->not->toBeNull()
            ->and($issues->firstWhere('issue_type', 'thin_content'))->not->toBeNull();
    });

    it('updates scan run progress', function () {
        $scanRun = SEOScanRun::create([
            'type' => 'full',
            'status' => 'running',
            'total_pages' => 10,
            'scanned_pages' => 0,
            'issues_found' => 0,
        ]);

        $model = ScanTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content</p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')
            ->andReturn([
                ['issue_type' => 'test_issue', 'severity' => 'warning', 'field' => 'test', 'message' => 'Test'],
            ]);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(totalScore: 50, results: [], analyzedAt: new \DateTimeImmutable(), locale: 'en'));

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id, $scanRun->id);

        $scanRun->refresh();

        expect($scanRun->scanned_pages)->toBe(1)
            ->and($scanRun->issues_found)->toBe(1);
    });

    it('handles duplicate issues', function () {
        $model = ScanTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content</p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        $scanRun = SEOScanRun::create(['type' => 'full', 'status' => 'running']);

        // Pre-create an existing issue
        SEOScanIssue::create([
            'scannable_type' => ScanTestModel::class,
            'scannable_id' => $model->id,
            'issue_type' => 'existing_issue',
            'severity' => 'warning',
            'message' => 'Old issue',
            'status' => 'open',
            'scan_run_id' => $scanRun->id,
        ]);

        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')
            ->andReturn([
                ['issue_type' => 'new_issue', 'severity' => 'warning', 'field' => 'test', 'message' => 'New'],
            ]);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(totalScore: 50, results: [], analyzedAt: new \DateTimeImmutable(), locale: 'en'));

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id, $scanRun->id);

        // Old issues should be deleted, new ones created
        $issues = SEOScanIssue::where('scannable_type', ScanTestModel::class)
            ->where('scannable_id', $model->id)
            ->get();

        expect($issues)->toHaveCount(1)
            ->and($issues->first()->issue_type)->toBe('new_issue');
    });

    it('handles model not found', function () {
        Log::spy();

        ScanPageJob::dispatchSync(ScanTestModel::class, 99999);

        Log::shouldHaveReceived('warning')
            ->with('ScanPageJob: Model not found', Mockery::any());
    });

    it('dispatches link validation jobs', function () {
        $model = ScanTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content with <a href="https://example.com">link</a></p>',
        ]);

        $model->seoMeta()->create(['locale' => 'en']);

        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')->andReturn([]);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->andReturn(new AnalysisReport(totalScore: 50, results: [], analyzedAt: new \DateTimeImmutable(), locale: 'en'));

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id);

        Queue::assertPushed(ValidateLinksJob::class, 2);
    });

    it('skips content analysis when not needed', function () {
        $model = ScanTestModel::create([
            'title' => 'Test',
            'content' => '<p>Content</p>',
        ]);

        // Create seo meta with recent analysis and matching hash
        $model->seoMeta()->create([
            'locale' => 'en',
            'analysis_report' => ['existing' => 'report'],
            'analyzed_at' => now(),
            'content_hash' => md5('<p>Content</p>'),
        ]);

        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')->andReturn([]);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldNotReceive('analyze'); // Should NOT be called

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id);
    });

    it('re-analyzes when content changed', function () {
        $model = ScanTestModel::create([
            'title' => 'Test',
            'content' => '<p>New Content</p>',
        ]);

        // Create seo meta with old hash
        $model->seoMeta()->create([
            'locale' => 'en',
            'analysis_report' => ['old' => 'report'],
            'analyzed_at' => now(),
            'content_hash' => md5('<p>Old Content</p>'), // Different hash
        ]);

        $mockScanner = Mockery::mock(PageScanner::class);
        $mockScanner->shouldReceive('scan')->andReturn([]);

        $mockAnalyzer = Mockery::mock(ContentAnalyzer::class);
        $mockAnalyzer->shouldReceive('analyze')
            ->once() // Should be called
            ->andReturn(new AnalysisReport(totalScore: 50, results: [], analyzedAt: new \DateTimeImmutable(), locale: 'en'));

        $this->app->instance(PageScanner::class, $mockScanner);
        $this->app->instance(ContentAnalyzer::class, $mockAnalyzer);

        ScanPageJob::dispatchSync(ScanTestModel::class, $model->id);
    });

    it('has correct retry settings', function () {
        $job = new ScanPageJob(ScanTestModel::class, 1);

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe(30);
    });

    it('logs failure', function () {
        $job = new ScanPageJob(ScanTestModel::class, 1, 123);

        Log::shouldReceive('error')
            ->once()
            ->with('ScanPageJob failed', Mockery::any());

        $job->failed(new \Exception('Test error'));
    });

    it('updates progress even on failure', function () {
        $scanRun = SEOScanRun::create([
            'type' => 'full',
            'status' => 'running',
            'total_pages' => 10,
            'scanned_pages' => 5,
        ]);

        $job = new ScanPageJob(ScanTestModel::class, 1, $scanRun->id);

        Log::shouldReceive('error')->once();

        $job->failed(new \Exception('Test error'));

        $scanRun->refresh();

        expect($scanRun->scanned_pages)->toBe(6); // Incremented even on failure
    });
});
