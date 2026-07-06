<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageManager;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Tests\Support\FakeOgImageRenderer;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * A HasSEO model. getSEOImage() returns an explicit og:image only when the row
 * has one — otherwise the resolver keeps the site default (and the generated
 * card can take over).
 */
class OgArticle extends Model
{
    use HasSEO;

    protected $table = 'og_articles';

    protected $fillable = ['title', 'slug', 'og_image'];

    public $timestamps = false;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEOImage(): ?string
    {
        return $this->og_image;
    }

    public function getUrlForSEO(): string
    {
        return url("/articles/{$this->slug}");
    }
}

beforeEach(function () {
    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->dropIfExists('og_articles');
    $schema->create('og_articles', function ($table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('slug');
        $table->string('og_image')->nullable();
    });

    Storage::fake('og_test', ['url' => 'http://localhost/og', 'visibility' => 'public']);
    config([
        'seo.og_image.enabled' => true,
        'seo.og_image.driver' => 'fake',
        'seo.og_image.disk' => 'og_test',
        'seo.og_image.path' => 'og-images',
        'seo.og_image.models' => [OgArticle::class],
        'seo.default_og_image' => '/default-og.jpg',
        'seo.title_suffix' => '',
    ]);
    FakeOgImageRenderer::reset();
    app(OgImageManager::class)->extend('fake', fn () => new FakeOgImageRenderer);
});

function ogResolver(): SEOResolver
{
    return app(SEOResolver::class);
}

describe('resolver og:image fallback', function () {
    it('keeps the static default when generation is disabled', function () {
        config(['seo.og_image.enabled' => false]);
        $article = OgArticle::create(['title' => 'A Post', 'slug' => 'a-post']);

        expect(ogResolver()->resolve($article)->ogImage)->toBe(url('/default-og.jpg'));
    });

    it('keeps the static default until the image has been generated', function () {
        $article = OgArticle::create(['title' => 'A Post', 'slug' => 'a-post']);

        // No file warmed yet -> existence-gated urlFor() returns null.
        expect(ogResolver()->resolve($article)->ogImage)->toBe(url('/default-og.jpg'));
        expect(FakeOgImageRenderer::$calls)->toBe(0); // resolving never renders
    });

    it('uses the generated card once it has been warmed', function () {
        $article = OgArticle::create(['title' => 'A Post', 'slug' => 'a-post']);

        // Warm it (the command would do this), then resolve again.
        $data = ogResolver()->resolve($article);
        app(OgImageGenerator::class)->generate($data);

        $resolved = ogResolver()->resolve($article);
        expect($resolved->ogImage)->toContain('og-images/')->toEndWith('.png');
    });

    it('never overrides an explicit per-model og:image', function () {
        $article = OgArticle::create([
            'title' => 'A Post',
            'slug' => 'a-post',
            'og_image' => '/custom-share.jpg',
        ]);

        // Even after a card exists for this content, the explicit image wins.
        app(OgImageGenerator::class)->generate(ogResolver()->resolve($article));

        expect(ogResolver()->resolve($article)->ogImage)->toBe(url('/custom-share.jpg'));
    });
});

describe('seo:og-images command', function () {
    it('warms every configured model with a title', function () {
        OgArticle::create(['title' => 'First', 'slug' => 'first']);
        OgArticle::create(['title' => 'Second', 'slug' => 'second']);

        $this->artisan('seo:og-images')
            ->assertExitCode(0);

        expect(FakeOgImageRenderer::$calls)->toBe(2);
        expect(Storage::disk('og_test')->files('og-images'))->toHaveCount(2);
    });

    it('warms and serves the per-model-mapped template', function () {
        config(['seo.og_image.templates' => [OgArticle::class => 'seo::og.article']]);
        $article = OgArticle::create(['title' => 'A Post', 'slug' => 'a-post']);

        $this->artisan('seo:og-images')->assertExitCode(0);

        // The stored file is keyed by the ARTICLE template, and the resolver
        // serves exactly that file for this model.
        $data = ogResolver()->resolve($article);
        $expected = 'og-images/'.app(OgImageGenerator::class)->cacheKey($data, 'seo::og.article').'.png';
        Storage::disk('og_test')->assertExists($expected);
        expect($data->ogImage)->toContain('og-images/');
    });

    it('skips rows with no renderable title', function () {
        OgArticle::create(['title' => 'Has Title', 'slug' => 'a']);
        OgArticle::create(['title' => null, 'slug' => 'b']);

        $this->artisan('seo:og-images')->assertExitCode(0);

        expect(FakeOgImageRenderer::$calls)->toBe(1);
    });

    it('fails and reports when a render throws', function () {
        OgArticle::create(['title' => 'Boom', 'slug' => 'boom']);
        FakeOgImageRenderer::$throw = true;

        $this->artisan('seo:og-images')
            ->assertExitCode(1);
    });

    it('prunes orphaned cards but leaves non-card files untouched', function () {
        OgArticle::create(['title' => 'Keep', 'slug' => 'keep']);
        $orphan = 'og-images/'.str_repeat('a', 64).'.png'; // hash-shaped orphan card
        Storage::disk('og_test')->put($orphan, 'x');
        Storage::disk('og_test')->put('og-images/logo.png', 'x'); // a user's own asset

        $this->artisan('seo:og-images', ['--prune' => true])->assertExitCode(0);

        Storage::disk('og_test')->assertMissing($orphan);              // orphan card removed
        Storage::disk('og_test')->assertExists('og-images/logo.png');  // non-card kept
    });

    it('refuses to prune on a scoped --model run', function () {
        OgArticle::create(['title' => 'Keep', 'slug' => 'keep']);
        $orphan = 'og-images/'.str_repeat('b', 64).'.png';
        Storage::disk('og_test')->put($orphan, 'x');

        $this->artisan('seo:og-images', ['--model' => [OgArticle::class], '--prune' => true])
            ->assertExitCode(0);

        Storage::disk('og_test')->assertExists($orphan); // prune skipped -> orphan kept
    });
});
