<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOImageCandidate;
use Rankbeam\Seo\Services\SEOComputedBuilder;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| Optional dimension-aware social-image selection (improvement plan T10)
|--------------------------------------------------------------------------
|
| The default 'first' strategy is unchanged: getSEOImage() (then fields,
| content, default) wins outright, with no image opened or measured. The
| opt-in 'best' strategy scores an ordered candidate list — getSEOImage()
| first, then the getSEOImages() hook, then the usual fallbacks — by how
| close each LOCAL image's pixel dimensions are to the configured ideal,
| skipping any below the minimum. Remote images are never fetched.
|
*/

function imgSelBuilder(): SEOComputedBuilder
{
    return app(SEOComputedBuilder::class);
}

/**
 * Write a minimal PNG of the given dimensions to the fake public disk.
 * getimagesize() reads width/height straight from the IHDR header, so the
 * pixel data does not need to be valid.
 */
function imgSelPng(string $relativePath, int $width, int $height): void
{
    $ihdrData = pack('N', $width).pack('N', $height)."\x08\x02\x00\x00\x00";

    $png = "\x89PNG\r\n\x1a\n"
        .pack('N', 13).'IHDR'.$ihdrData.pack('N', crc32('IHDR'.$ihdrData))
        .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

    Storage::disk('public')->put($relativePath, $png);
}

/**
 * Build an Eloquent model exposing getSEOImage() / getSEOImages().
 *
 * @param  array<int, SEOImageCandidate|string>  $seoImages
 * @param  array<string, mixed>  $attributes
 */
function imgSelModel(?string $seoImage = null, array $seoImages = [], array $attributes = []): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];

        public ?string $seoImageReturn = null;

        /** @var array<int, SEOImageCandidate|string> */
        public array $seoImagesReturn = [];

        public function getSEOImage(): ?string
        {
            return $this->seoImageReturn;
        }

        public function getSEOImages(): iterable
        {
            return $this->seoImagesReturn;
        }
    };

    $model->seoImageReturn = $seoImage;
    $model->seoImagesReturn = $seoImages;

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

beforeEach(function () {
    Storage::fake('public');
    config(['app.url' => 'http://localhost']);
});

describe('best strategy', function () {
    beforeEach(function () {
        config(['seo.computed.image_selection.strategy' => 'best']);
    });

    it('prefers the candidate closest to the configured ideal', function () {
        imgSelPng('imgs/far.png', 1000, 500);   // distance 56,900
        imgSelPng('imgs/near.png', 1300, 680);  // distance 12,500 — closest
        imgSelPng('imgs/big.png', 2400, 1200);  // distance 1,764,900

        $model = imgSelModel(seoImages: [
            SEOImageCandidate::make('/storage/imgs/far.png')->priority(50),
            SEOImageCandidate::make('/storage/imgs/big.png')->priority(50),
            SEOImageCandidate::make('/storage/imgs/near.png')->priority(50),
        ]);

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/near.png'));
    });

    it('skips an undersized getSEOImage() and picks a larger candidate', function () {
        imgSelPng('imgs/tiny.png', 150, 150); // below the 200x200 minimum
        imgSelPng('imgs/good.png', 1200, 630);

        $model = imgSelModel(
            seoImage: '/storage/imgs/tiny.png',
            seoImages: [SEOImageCandidate::make('/storage/imgs/good.png')],
        );

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/good.png'));
    });

    it('keeps getSEOImage() highest priority on a dimension tie', function () {
        imgSelPng('imgs/a.png', 1200, 630);
        imgSelPng('imgs/b.png', 1200, 630);

        $model = imgSelModel(
            seoImage: '/storage/imgs/a.png',
            seoImages: [SEOImageCandidate::make('/storage/imgs/b.png')->priority(1000)],
        );

        $seo = imgSelBuilder()->fromModel($model, 'en');

        // Equal distance → getSEOImage() (PHP_INT_MAX priority) beats priority 1000.
        expect($seo->ogImage)->toBe(url('/storage/imgs/a.png'));
    });

    it('treats a plain string candidate as priority 0 and still skips undersized', function () {
        imgSelPng('imgs/ideal.png', 1200, 630);
        imgSelPng('imgs/small.png', 150, 150);

        $model = imgSelModel(seoImages: [
            '/storage/imgs/small.png', // plain string, undersized → skipped
            '/storage/imgs/ideal.png', // plain string, priority 0
        ]);

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/ideal.png'));
    });

    it('never fetches a remote candidate, preferring a lower-priority local one', function () {
        imgSelPng('imgs/ideal.png', 1200, 630);

        $model = imgSelModel(seoImages: [
            SEOImageCandidate::make('https://cdn.example.org/remote.png')->priority(1000),
            SEOImageCandidate::make('/storage/imgs/ideal.png')->priority(0),
        ]);

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/ideal.png'))
            ->and($seo->ogImage)->not->toBe('https://cdn.example.org/remote.png');
    });

    it('measures a same-host absolute URL through the /storage mapping', function () {
        imgSelPng('imgs/ideal.png', 1200, 630);

        $model = imgSelModel(
            seoImage: '/storage/imgs/tiny.png', // does not exist → unmeasurable
            seoImages: [SEOImageCandidate::make('http://localhost/storage/imgs/ideal.png')],
        );

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe('http://localhost/storage/imgs/ideal.png');
    });

    it('falls back to first-match when only remote candidates exist', function () {
        $model = imgSelModel(seoImage: 'https://cdn.example.org/only.png');

        $seo = imgSelBuilder()->fromModel($model, 'en');

        // Nothing measurable locally → first-match returns getSEOImage() verbatim.
        expect($seo->ogImage)->toBe('https://cdn.example.org/only.png');
    });

    it('falls back to first-match rather than blanking when every local image is undersized', function () {
        imgSelPng('imgs/tiny.png', 150, 150);

        $model = imgSelModel(seoImage: '/storage/imgs/tiny.png');

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/tiny.png'));
    });

    it('does not disturb og:type or article metadata gating', function () {
        imgSelPng('imgs/ideal.png', 1200, 630);

        $article = new class extends Model
        {
            protected $guarded = [];

            public function getSEOOgType(): string
            {
                return 'article';
            }

            public function getSEOImages(): iterable
            {
                return ['/storage/imgs/ideal.png'];
            }
        };
        $article->setAttribute('published_at', new DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $articleSeo = imgSelBuilder()->fromModel($article, 'en');
        $articleHtml = (new TagRenderer())->render($articleSeo);

        expect($articleSeo->ogType)->toBe('article')
            ->and($articleSeo->ogImage)->toBe(url('/storage/imgs/ideal.png'))
            ->and($articleHtml)->toContain('property="article:published_time"');

        $page = new class extends Model
        {
            protected $guarded = [];

            public function getSEOOgType(): string
            {
                return 'website';
            }

            public function getSEOImages(): iterable
            {
                return ['/storage/imgs/ideal.png'];
            }
        };
        $page->setAttribute('published_at', new DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $pageSeo = imgSelBuilder()->fromModel($page, 'en');
        $pageHtml = (new TagRenderer())->render($pageSeo);

        expect($pageSeo->ogType)->toBe('website')
            ->and($pageHtml)->not->toContain('article:');
    });

    it('honours configured minimum and ideal overrides', function () {
        // Raise the minimum so a 1200x630 image is now "undersized" and a
        // larger one is required.
        config([
            'seo.computed.image_selection.minimum_width' => 1500,
            'seo.computed.image_selection.minimum_height' => 800,
        ]);

        imgSelPng('imgs/standard.png', 1200, 630); // now below the raised minimum
        imgSelPng('imgs/large.png', 2000, 1050);

        $model = imgSelModel(seoImages: [
            SEOImageCandidate::make('/storage/imgs/standard.png'),
            SEOImageCandidate::make('/storage/imgs/large.png'),
        ]);

        $seo = imgSelBuilder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/storage/imgs/large.png'));
    });
});

describe('default (first) strategy', function () {
    it('defaults to the first strategy', function () {
        expect(config('seo.computed.image_selection.strategy'))->toBe('first');
    });

    it('returns getSEOImage() unchanged without measuring or consulting getSEOImages()', function () {
        imgSelPng('imgs/tiny.png', 150, 150);
        imgSelPng('imgs/ideal.png', 1200, 630);

        $model = imgSelModel(
            seoImage: '/storage/imgs/tiny.png',
            seoImages: [SEOImageCandidate::make('/storage/imgs/ideal.png')->priority(1000)],
        );

        $seo = imgSelBuilder()->fromModel($model, 'en');

        // First-match: the (undersized) getSEOImage() wins; getSEOImages() is ignored.
        expect($seo->ogImage)->toBe(url('/storage/imgs/tiny.png'));
    });
});

describe('SEOImageCandidate', function () {
    it('carries a url and defaults to priority 0', function () {
        $candidate = SEOImageCandidate::make('/hero.png');

        expect($candidate->url)->toBe('/hero.png')
            ->and($candidate->priority)->toBe(0);
    });

    it('is immutable: priority() returns a new instance', function () {
        $base = SEOImageCandidate::make('/hero.png');
        $prioritised = $base->priority(100);

        expect($prioritised)->not->toBe($base)
            ->and($prioritised->priority)->toBe(100)
            ->and($prioritised->url)->toBe('/hero.png')
            ->and($base->priority)->toBe(0);
    });

    it('accepts an explicit priority in make()', function () {
        expect(SEOImageCandidate::make('/x.png', 42)->priority)->toBe(42);
    });
});
