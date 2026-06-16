<?php

declare(strict_types=1);

use Rankbeam\Seo\Services\SEOComputedBuilder;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| Characterization: computed description + robots policies
|--------------------------------------------------------------------------
|
| Ported from production resolver behavior (DynamicSeoDataResolver):
| - description candidate-field list (configurable order, first meaningful
|   text wins, markup-only fields skipped)
| - HTML strip + entity decode + whitespace squish normalization
| - 160-char truncation at a word boundary, no ellipsis, trailing
|   punctuation trimmed
| - robots derived from the model's is_indexable flag
|
*/

function builder(): SEOComputedBuilder
{
    return app(SEOComputedBuilder::class);
}

describe('description candidate fields', function () {
    it('uses the configured candidate order, not attribute presence order', function () {
        config(['seo.computed.description_fields' => [
            'subtitle', 'pathology_description', 'description', 'abstract', 'content',
        ]]);

        $model = createMockModel([
            'content' => 'Content text comes last in priority.',
            'pathology_description' => 'Pathology description wins over content.',
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->description)->toBe('Pathology description wins over content.');
    });

    it('skips candidates containing only markup or whitespace', function () {
        config(['seo.computed.description_fields' => ['subtitle', 'description']]);

        $model = createMockModel([
            'subtitle' => "<p> </p>\n&nbsp;",
            'description' => 'Real description text.',
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->description)->toBe('Real description text.');
    });

    it('falls back to the built-in field list when none is configured', function () {
        config(['seo.computed.description_fields' => []]);

        $model = createMockModel([
            'excerpt' => 'Excerpt text.',
            'content' => 'Content text.',
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->description)->toBe('Excerpt text.');
    });

    it('strips tags, decodes entities, and squishes whitespace', function () {
        config(['seo.computed.description_fields' => ['description']]);

        $model = createMockModel([
            'description' => "<p>L&agrave; dove   c'&egrave;\n\n<strong>cura</strong> &amp; ricerca</p>",
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->description)->toBe("Là dove c'è cura & ricerca");
    });
});

describe('description word-boundary truncation', function () {
    beforeEach(function () {
        config(['seo.computed.description_fields' => ['description']]);
        config(['seo.computed.description_max_length' => 160]);
    });

    it('leaves text at or under the limit untouched', function () {
        $text = str_repeat('a', 158) . ' b'; // exactly 160

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect($seo->description)->toBe($text)
            ->and(mb_strlen($seo->description))->toBe(160);
    });

    it('truncates at the last word boundary within the limit, without an ellipsis', function () {
        // Repeating 9-char chunks ("word6789 ") so a boundary always falls
        // late in the window.
        $text = trim(str_repeat('word6789 ', 30)); // 269 chars

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect(mb_strlen($seo->description))->toBeLessThanOrEqual(160)
            ->and($seo->description)->toEndWith('word6789')
            ->and($seo->description)->not->toContain('...')
            ->and($seo->description)->not->toEndWith(' ');
    });

    it('trims trailing punctuation left by the cut', function () {
        // Make the character right before the boundary a comma.
        $prefix = str_repeat('x', 150);
        $text = $prefix . ' yyyy, zzzzzzzzzzzzzzzzzzzz more words here';

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect($seo->description)->toBe($prefix . ' yyyy');
    });

    it('hard-cuts when no acceptable word boundary exists', function () {
        $text = str_repeat('a', 300); // no spaces at all

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect(mb_strlen($seo->description))->toBe(160);
    });

    it('hard-cuts when the only boundary is before 60% of the limit', function () {
        // One space at position 20, then an unbroken run: boundary < 96 (60%
        // of 160), so the policy prefers a hard cut at the limit.
        $text = str_repeat('a', 20) . ' ' . str_repeat('b', 300);

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect(mb_strlen($seo->description))->toBe(160)
            ->and($seo->description)->toEndWith('b');
    });

    it('counts multibyte characters, not bytes', function () {
        $text = str_repeat('à', 200); // 200 chars, 400 bytes

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect(mb_strlen($seo->description))->toBe(160);
    });

    it('respects a configured max length', function () {
        config(['seo.computed.description_max_length' => 80]);

        $text = trim(str_repeat('word6789 ', 30));

        $seo = builder()->fromModel(createMockModel(['description' => $text]), 'en');

        expect(mb_strlen($seo->description))->toBeLessThanOrEqual(80);
    });
});

describe('image extracted from content HTML', function () {
    it('matches src as an attribute and decodes HTML5 entities', function () {
        $model = createMockModel([
            'content' => '<IMG data-src="/lazy.jpg" SRC = \'/actual.jpg?a=1&AMP;b=2&#38;c=3\'>',
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe(url('/actual.jpg?a=1&b=2&c=3'));
    });

    it('decodes entities in a content <img> src so the URL is not double-encoded', function () {
        // No image fields present, so the builder falls back to the first
        // <img> in the content. An entity-encoded query string ("&amp;") in
        // the markup must decode to a single ampersand — mirroring the decode
        // in truncateDescription() — otherwise the render path escapes it
        // again and the live URL becomes "...&amp;amp;..." (wrong resource).
        $model = createMockModel([
            'content' => '<p>Intro <img src="https://cdn.example.com/img?a=1&amp;b=2" alt="x"> rest</p>',
        ]);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->ogImage)->toBe('https://cdn.example.com/img?a=1&b=2');
    });

    it('renders the content-derived og:image escaped exactly once', function () {
        // End-to-end: the decoded single-ampersand URL is escaped a single
        // time on render — a single "&amp;", never the double-encoded
        // "&amp;amp;" that the raw "&amp;" attribute value would have produced.
        $model = createMockModel([
            'content' => '<img src="https://cdn.example.com/img?a=1&amp;b=2">',
        ]);

        $seo = builder()->fromModel($model, 'en');
        $html = (new TagRenderer())->render($seo);

        expect($html)->toContain('<meta property="og:image" content="https://cdn.example.com/img?a=1&amp;b=2">')
            ->and($html)->not->toContain('&amp;amp;');
    });
});

describe('robots from indexability', function () {
    it('computes noindex, nofollow for a non-indexable model', function () {
        $seo = builder()->fromModel(createMockModel(['is_indexable' => false]), 'en');

        expect($seo->robots)->toBe('noindex, nofollow');
    });

    it('computes index, follow for an explicitly indexable model', function () {
        $seo = builder()->fromModel(createMockModel(['is_indexable' => true]), 'en');

        expect($seo->robots)->toBe('index, follow');
    });

    it('computes no robots when the model has no indexability flag', function () {
        $seo = builder()->fromModel(createMockModel(['title' => 'Anything']), 'en');

        expect($seo->robots)->toBeNull();
    });

    it('prefers a getSEORobots method over the is_indexable attribute', function () {
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];

            public function getSEORobots(): string
            {
                return 'noindex, follow';
            }
        };
        $model->setAttribute('is_indexable', true);

        $seo = builder()->fromModel($model, 'en');

        expect($seo->robots)->toBe('noindex, follow');
    });
});
