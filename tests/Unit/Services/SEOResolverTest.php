<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Fibonoir\LaravelSEO\Data\SEOData;
use Fibonoir\LaravelSEO\Services\SEOComputedBuilder;
use Fibonoir\LaravelSEO\Services\SEODefaultsRepository;
use Fibonoir\LaravelSEO\Services\SEOResolver;

beforeEach(function () {
    // Create mock dependencies
    $this->defaultsRepository = Mockery::mock(SEODefaultsRepository::class);
    $this->computedBuilder = Mockery::mock(SEOComputedBuilder::class);

    $this->resolver = new SEOResolver(
        $this->defaultsRepository,
        $this->computedBuilder,
    );

    // Set up default config
    config()->set('seo.site_name', 'Test Site');
    config()->set('seo.title_suffix', ' | Test Site');
    config()->set('seo.default_robots', 'index,follow');
    config()->set('seo.default_twitter_card', 'summary_large_image');
    config()->set('seo.twitter_site', '@testsite');
    config()->set('seo.default_og_image', '/default-og.jpg');
});

afterEach(function () {
    Mockery::close();
});

describe('SEOResolver', function () {
    describe('Precedence Chain', function () {
        it('resolves global defaults', function () {
            // Setup: Global defaults return site-wide values
            $globalDefaults = new SEOData(
                ogImage: '/global-default.jpg',
                robots: 'index,follow',
            );

            $this->defaultsRepository
                ->shouldReceive('global')
                ->with('en')
                ->once()
                ->andReturn($globalDefaults);

            // No model type or route defaults
            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $result = $this->resolver->resolve(null, null, 'en');

            // Should have global default og image
            expect($result->ogImage)->toBe('/global-default.jpg')
                ->and($result->robots)->toBe('index,follow');
        });

        it('model type defaults override global', function () {
            // Setup: Global defaults
            $globalDefaults = new SEOData(
                ogImage: '/global.jpg',
                ogType: 'website',
            );

            // Model type defaults (higher priority)
            $modelTypeDefaults = new SEOData(
                ogType: 'article', // Override
                // ogImage not set - should preserve global
            );

            $this->defaultsRepository
                ->shouldReceive('global')
                ->with('en')
                ->andReturn($globalDefaults);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->once()
                ->andReturn($modelTypeDefaults);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData());

            // Create a mock model
            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->ogType)->toBe('article')  // Model type default applied
                ->and($result->ogImage)->toBe('/global.jpg');  // Global preserved
        });

        it('route defaults override model type', function () {
            $globalDefaults = new SEOData(robots: 'index,follow');
            $modelTypeDefaults = new SEOData(ogType: 'article');
            $routeDefaults = new SEOData(
                robots: 'noindex,follow', // Archive pages should be noindex
            );

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn($globalDefaults);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn($modelTypeDefaults);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->with('blog.archive', 'en')
                ->andReturn($routeDefaults);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData());

            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, 'blog.archive', 'en');

            expect($result->robots)->toBe('noindex,follow')  // Route default applied
                ->and($result->ogType)->toBe('article');      // Model type preserved
        });

        it('computed overrides route', function () {
            $routeDefaults = new SEOData(
                title: 'Default Archive Title',
            );

            $computedValues = new SEOData(
                title: 'Computed Post Title', // From model's title attribute
                description: 'Computed description from excerpt',
            );

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn($routeDefaults);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->once()
                ->andReturn($computedValues);

            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->title)->toContain('Computed Post Title')  // Computed wins
                ->and($result->description)->toBe('Computed description from excerpt');
        });

        it('explicit overrides computed', function () {
            $computedValues = new SEOData(
                title: 'Computed Title',
                description: 'Computed description',
                ogImage: '/computed-image.jpg',
            );

            // Create model with explicit SEO meta
            $model = $this->createMockEloquentModel([
                'title' => 'Explicit SEO Title',  // Stored in seo_meta
                'description' => null,            // Not set - should preserve computed
                'og_image' => '/explicit.jpg',
            ]);

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn($computedValues);

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->title)->toContain('Explicit SEO Title')   // Explicit wins
                ->and($result->description)->toBe('Computed description')  // Computed preserved
                ->and($result->ogImage)->toBe('/explicit.jpg');        // Explicit wins
        });

        it('applies title suffix', function () {
            config()->set('seo.title_suffix', ' | My Site');

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData(title: 'Page Title'));

            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->title)->toBe('Page Title | My Site');
        });

        it('does not duplicate title suffix', function () {
            config()->set('seo.title_suffix', ' | My Site');

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData(title: 'Page Title | My Site'));

            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, null, 'en');

            // Should not add suffix again
            expect($result->title)->toBe('Page Title | My Site');
        });

        it('skips title suffix when not configured', function () {
            config()->set('seo.title_suffix', null);

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData(title: 'Page Title'));

            $model = $this->createMockEloquentModel();

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->title)->toBe('Page Title');
        });
    });

    describe('Route Resolution', function () {
        it('resolves for route without model', function () {
            $routeDefaults = new SEOData(
                title: 'About Us',
                description: 'Learn about our company',
            );

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->with('pages.about', 'en')
                ->andReturn($routeDefaults);

            $result = $this->resolver->resolveForRoute('pages.about', 'en');

            expect($result->title)->toContain('About Us')
                ->and($result->description)->toBe('Learn about our company');
        });

        it('uses app locale when not specified', function () {
            app()->setLocale('de');

            $this->defaultsRepository
                ->shouldReceive('global')
                ->with('de')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->with('home', 'de')
                ->andReturn(null);

            $result = $this->resolver->resolve(null, 'home');

            expect($result->locale)->toBe('de');
        });
    });

    describe('Overrides', function () {
        it('applies explicit overrides', function () {
            $base = new SEOData(
                title: 'Base Title',
                robots: 'index,follow',
            );

            $result = $this->resolver->resolveWithOverrides($base, [
                'title' => 'Override Title',
                'robots' => 'noindex',
            ]);

            expect($result->title)->toBe('Override Title')
                ->and($result->robots)->toBe('noindex');
        });

        it('preserves non-overridden values', function () {
            $base = new SEOData(
                title: 'Base Title',
                description: 'Base Description',
                ogImage: '/base.jpg',
            );

            $result = $this->resolver->resolveWithOverrides($base, [
                'title' => 'New Title',
            ]);

            expect($result->title)->toBe('New Title')
                ->and($result->description)->toBe('Base Description')
                ->and($result->ogImage)->toBe('/base.jpg');
        });
    });

    describe('Batch Resolution', function () {
        it('resolves many models at once', function () {
            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            // Setup computed builder to return different titles
            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturnUsing(function ($model) {
                    return new SEOData(title: 'Post ' . $model->getKey());
                });

            $models = [
                $this->createMockEloquentModel([], 1),
                $this->createMockEloquentModel([], 2),
                $this->createMockEloquentModel([], 3),
            ];

            $results = $this->resolver->resolveMany($models, 'en');

            expect($results)->toHaveCount(3)
                ->and($results)->toHaveKeys([1, 2, 3]);
        });
    });

    describe('Canonical URL', function () {
        it('sets canonical from model getUrlForSEO method', function () {
            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData());

            $model = $this->createMockEloquentModel([], 1, 'https://example.com/posts/1');

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->canonical)->toBe('https://example.com/posts/1')
                ->and($result->ogUrl)->toBe('https://example.com/posts/1');
        });

        it('preserves explicit canonical', function () {
            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forModelType')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $this->computedBuilder
                ->shouldReceive('fromModel')
                ->andReturn(new SEOData());

            $model = $this->createMockEloquentModel([
                'canonical' => 'https://example.com/custom-canonical',
            ], 1, 'https://example.com/posts/1');

            $result = $this->resolver->resolve($model, null, 'en');

            expect($result->canonical)->toBe('https://example.com/custom-canonical');
        });
    });

    describe('Base Config', function () {
        it('applies base config values', function () {
            config()->set('seo.site_name', 'My Site');
            config()->set('seo.twitter_site', '@mysite');
            config()->set('seo.default_twitter_card', 'summary');
            config()->set('seo.default_robots', 'index,follow');

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $result = $this->resolver->resolve(null, null, 'en');

            expect($result->ogSiteName)->toBe('My Site')
                ->and($result->twitterSite)->toBe('@mysite')
                ->and($result->twitterCard)->toBe('summary')
                ->and($result->robots)->toBe('index,follow');
        });

        it('uses app name as fallback site name', function () {
            config()->set('seo.site_name', null);
            config()->set('app.name', 'Laravel App');

            $this->defaultsRepository
                ->shouldReceive('global')
                ->andReturn(null);

            $this->defaultsRepository
                ->shouldReceive('forRoute')
                ->andReturn(null);

            $result = $this->resolver->resolve(null, null, 'en');

            expect($result->ogSiteName)->toBe('Laravel App');
        });
    });
});

// Helper function to create mock Eloquent model
function createMockEloquentModel(array $seoMeta = [], int $key = 1, ?string $seoUrl = null): Model
{
    $model = Mockery::mock(Model::class)->makePartial();

    $model->shouldReceive('getKey')->andReturn($key);

    // Mock seoMeta relationship
    if (empty($seoMeta)) {
        $model->shouldReceive('seoMeta')->andReturn(null);
        $model->seoMeta = null;
    } else {
        $meta = (object) $seoMeta;
        $model->shouldReceive('seoMeta')->andReturn($meta);
        $model->seoMeta = $meta;
    }

    // Mock getUrlForSEO method
    if ($seoUrl) {
        $model->shouldReceive('getUrlForSEO')->andReturn($seoUrl);
    }

    return $model;
}
