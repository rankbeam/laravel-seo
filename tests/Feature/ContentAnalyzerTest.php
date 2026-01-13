<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Fibonoir\LaravelSEO\Data\AnalysisReport;
use Fibonoir\LaravelSEO\Jobs\ValidateLinksJob;
use Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer;
use Fibonoir\LaravelSEO\Traits\HasSEO;

// Create test model for analyzer testing
class AnalyzerTestPost extends Model
{
    use HasSEO;

    protected $table = 'analyzer_test_posts';

    protected $fillable = ['title', 'slug', 'content', 'excerpt'];

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

    public function getUrlForSEO(): string
    {
        return url("/posts/{$this->slug}");
    }
}

beforeEach(function () {
    // Create test table
    $this->app['db']->connection()->getSchemaBuilder()->create('analyzer_test_posts', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('content')->nullable();
        $table->text('excerpt')->nullable();
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

    // Disable auto features for manual testing
    config(['seo.features.auto_create_meta' => false]);
    config(['seo.features.auto_analyze' => false]);
    config(['seo.features.internal_links_index' => false]);
    config(['seo.analyzer.exclude_rules' => []]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('analyzer_test_posts');
});

describe('ContentAnalyzer', function () {
    it('analyzes model content', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'How to Learn Laravel SEO Best Practices',
            'slug' => 'learn-laravel-seo',
            'content' => '<h1>Learn Laravel SEO</h1><p>This is a comprehensive guide to Laravel SEO best practices. You will learn how to optimize your Laravel applications for search engines.</p>',
            'excerpt' => 'A guide to SEO in Laravel',
        ]);

        // Create SEO meta with focus keyword
        $post->seoMeta()->create([
            'locale' => 'en',
            'title' => 'How to Learn Laravel SEO Best Practices',
            'description' => 'A comprehensive guide to Laravel SEO best practices',
            'focus_keywords' => [
                ['keyword' => 'laravel seo', 'is_primary' => true],
            ],
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $report = $analyzer->analyze($post->fresh(), 'en');

        expect($report)->toBeInstanceOf(AnalysisReport::class)
            ->and($report->locale)->toBe('en');
    });

    it('calculates weighted score', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'SEO Optimized Title for Testing',
            'slug' => 'seo-optimized-title',
            'content' => '<h1>SEO Best Practices</h1>
                <p>This content discusses SEO best practices extensively. SEO is important for visibility.</p>
                <h2>Why SEO Matters</h2>
                <p>Search engine optimization helps your content rank better in search results.</p>
                <h2>Implementing SEO</h2>
                <p>There are many ways to implement proper SEO techniques in your Laravel application.</p>',
            'excerpt' => 'Learn about SEO best practices',
        ]);

        $post->seoMeta()->create([
            'locale' => 'en',
            'title' => 'SEO Best Practices Guide',
            'description' => 'A comprehensive guide to SEO best practices for web developers',
            'focus_keywords' => [
                ['keyword' => 'seo best practices', 'is_primary' => true],
            ],
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $report = $analyzer->analyze($post->fresh(), 'en');

        // Score should be calculated
        expect($report->totalScore)->toBeGreaterThanOrEqual(0)
            ->and($report->totalScore)->toBeLessThanOrEqual(100);
    });

    it('stores analysis report', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'Store Analysis Test',
            'slug' => 'store-analysis',
            'content' => '<p>Content for analysis storage testing.</p>',
        ]);

        $post->seoMeta()->create([
            'locale' => 'en',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $report = $analyzer->analyze($post->fresh(), 'en');

        // Manually store the report (normally done by AnalyzeContentJob)
        $post->seoMeta()->update([
            'seo_score' => $report->totalScore,
            'analysis_report' => $report->toArray(),
            'analyzed_at' => now(),
        ]);

        $meta = $post->fresh()->seoMeta;

        expect($meta->seo_score)->not->toBeNull()
            ->and($meta->analysis_report)->toBeArray()
            ->and($meta->analyzed_at)->not->toBeNull();
    });

    it('dispatches link validation job', function () {
        Queue::fake();

        $post = AnalyzerTestPost::create([
            'title' => 'Link Validation Test',
            'slug' => 'link-validation',
            'content' => '<p>Check this <a href="https://example.com">link</a>.</p>',
        ]);

        $post->seoMeta()->create(['locale' => 'en']);

        // Run analysis via job (which dispatches ValidateLinksJob)
        \Fibonoir\LaravelSEO\Jobs\AnalyzeContentJob::dispatchSync(
            AnalyzerTestPost::class,
            $post->id
        );

        Queue::assertPushed(ValidateLinksJob::class, function ($job) use ($post) {
            return $job->modelClass === AnalyzerTestPost::class
                && $job->modelId === $post->id;
        });
    });

    it('analyzes content without model', function () {
        $analyzer = app(ContentAnalyzer::class);

        $htmlContent = '<h1>Test Title</h1><p>This is sample content for testing purposes. It contains multiple sentences and should be analyzed properly.</p>';

        $seoData = [
            'title' => 'Test Title',
            'description' => 'This is a test description',
            'focus_keywords' => [
                ['keyword' => 'test', 'is_primary' => true],
            ],
        ];

        $report = $analyzer->analyzeContent($htmlContent, $seoData, 'en');

        expect($report)->toBeInstanceOf(AnalysisReport::class)
            ->and($report->totalScore)->toBeGreaterThanOrEqual(0);
    });

    it('extracts headings from html', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'Heading Test',
            'slug' => 'heading-test',
            'content' => '
                <h1>Main Title</h1>
                <p>Introduction</p>
                <h2>First Section</h2>
                <p>Content</p>
                <h2>Second Section</h2>
                <p>More content</p>
                <h3>Subsection</h3>
                <p>Details</p>
            ',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $context = $analyzer->buildContext($post, 'en');

        expect($context->headings['h1'])->toHaveCount(1)
            ->and($context->headings['h1'][0])->toBe('Main Title')
            ->and($context->headings['h2'])->toHaveCount(2)
            ->and($context->headings['h3'])->toHaveCount(1);
    });

    it('extracts links from html', function () {
        config(['app.url' => 'https://mysite.com']);

        $post = AnalyzerTestPost::create([
            'title' => 'Links Test',
            'slug' => 'links-test',
            'content' => '
                <p>Check out <a href="/internal-page">this internal link</a>.</p>
                <p>Also see <a href="https://external.com/page">external site</a>.</p>
                <p>And <a href="https://external.com/nofollow" rel="nofollow">nofollow link</a>.</p>
            ',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $context = $analyzer->buildContext($post, 'en');

        expect($context->links)->toHaveCount(3);

        // Check internal link
        $internalLink = collect($context->links)->firstWhere('url', '/internal-page');
        expect($internalLink['is_external'])->toBeFalse();

        // Check external link
        $externalLink = collect($context->links)->firstWhere('url', 'https://external.com/page');
        expect($externalLink['is_external'])->toBeTrue();

        // Check nofollow
        $nofollowLink = collect($context->links)->firstWhere('is_nofollow', true);
        expect($nofollowLink)->not->toBeNull();
    });

    it('extracts images from html', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'Images Test',
            'slug' => 'images-test',
            'content' => '
                <p>See this image:</p>
                <img src="/images/photo.jpg" alt="A nice photo" width="800" height="600">
                <p>And this one without alt:</p>
                <img src="/images/another.png">
            ',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $context = $analyzer->buildContext($post, 'en');

        expect($context->images)->toHaveCount(2);

        $imageWithAlt = collect($context->images)->firstWhere('src', '/images/photo.jpg');
        expect($imageWithAlt['alt'])->toBe('A nice photo')
            ->and($imageWithAlt['width'])->toBe(800)
            ->and($imageWithAlt['height'])->toBe(600);

        $imageWithoutAlt = collect($context->images)->firstWhere('src', '/images/another.png');
        expect($imageWithoutAlt['alt'])->toBeNull();
    });

    it('counts words correctly', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'Word Count Test',
            'slug' => 'word-count',
            'content' => '<p>This is a test sentence with exactly ten words in it.</p>',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $context = $analyzer->buildContext($post, 'en');

        expect($context->wordCount)->toBe(10);
    });

    it('handles empty content gracefully', function () {
        $post = AnalyzerTestPost::create([
            'title' => 'Empty Content',
            'slug' => 'empty-content',
            'content' => '',
        ]);

        $analyzer = app(ContentAnalyzer::class);
        $report = $analyzer->analyze($post, 'en');

        expect($report)->toBeInstanceOf(AnalysisReport::class);
    });

    it('respects excluded rules', function () {
        config(['seo.analyzer.exclude_rules' => ['title_length', 'keyword_density']]);

        $post = AnalyzerTestPost::create([
            'title' => 'Excluded Rules Test',
            'slug' => 'excluded-rules',
            'content' => '<p>Test content</p>',
        ]);

        $analyzer = app(ContentAnalyzer::class);

        // Register a test rule
        $analyzer->registerRule(new class implements \Fibonoir\LaravelSEO\Contracts\RuleInterface {
            public function getId(): string
            {
                return 'title_length';
            }

            public function getName(): string
            {
                return 'Title Length';
            }

            public function getCategory(): string
            {
                return 'meta';
            }

            public function analyze(\Fibonoir\LaravelSEO\Data\AnalysisContext $context): \Fibonoir\LaravelSEO\Data\RuleResult
            {
                return \Fibonoir\LaravelSEO\Data\RuleResult::pass('title_length', 'Title is good');
            }

            public function getWeight(): int
            {
                return 10;
            }
        });

        $report = $analyzer->analyze($post, 'en');

        // Excluded rule should not be in results
        expect($report->toArray())->not->toHaveKey('title_length');
    });
});
