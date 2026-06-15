<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| T1 — render surface accepts Model | SEOData | null
|--------------------------------------------------------------------------
|
| The facade (SEO::render/toArray/forInertia) and the @seo directive now
| accept a hand-built SEOData so model-less pages render without calling
| app(TagRenderer::class)->render($seoData) by hand. A supplied SEOData is
| explicit intent: every set value is preserved and only ABSENT fields are
| filled (canonical/og:url from the URL, the title suffix, absolute images,
| og:site_name, locale). The DB precedence chain is never merged in, and
| TagRenderer stays verbatim — direct callers are unaffected.
|
*/

beforeEach(function () {
    config(['app.url' => 'https://example.test']);
    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');
    config()->set('seo.title_suffix_skip_when_contains', []);
});

function renderSeoDirective(mixed $value): string
{
    $compiled = Blade::compileString('@seo($value)');

    return (function () use ($compiled, $value) {
        ob_start();
        eval('?>'.$compiled);

        return ob_get_clean();
    })();
}

/**
 * A model whose explicit seo_meta is surfaced through a real seoMeta()
 * method (the resolver requires method_exists($model, 'seoMeta')), so the
 * model path genuinely renders the supplied title/description.
 */
class RenderSurfaceModel extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];

    public ?object $seoMetaObject = null;

    public function seoMeta(): ?object
    {
        return $this->seoMetaObject;
    }

    public function getSeoMetaAttribute(): ?object
    {
        return $this->seoMetaObject;
    }
}

function makeRenderSurfaceModel(array $meta): RenderSurfaceModel
{
    $model = new RenderSurfaceModel(['title' => $meta['title'] ?? null]);

    $model->seoMetaObject = (object) array_merge([
        'title' => null,
        'description' => null,
        'canonical' => null,
        'robots' => null,
        'og_title' => null,
        'og_description' => null,
        'og_image' => null,
        'og_type' => 'website',
        'twitter_title' => null,
        'twitter_description' => null,
        'twitter_image' => null,
        'twitter_card' => 'summary_large_image',
        'focus_keywords' => null,
        'schema_jsonld' => null,
        'locale' => 'en',
    ], $meta);

    return $model;
}

it('renders a hand-built SEOData through the facade', function () {
    $html = SEO::render(new SEOData(
        title: 'Listing Page',
        description: 'A model-less listing',
    ));

    expect($html)->toContain('<title>Listing Page | Test Site</title>')
        ->and($html)->toContain('content="A model-less listing"')
        // og:title falls back to the (suffixed) title, mirroring the model path.
        ->and($html)->toContain('property="og:title" content="Listing Page | Test Site"');
});

it('renders the same core tag set for a SEOData as for an equivalent model', function () {
    $model = makeRenderSurfaceModel([
        'title' => 'My Page',
        'description' => 'Shared description',
    ]);

    $modelHtml = SEO::render($model);

    $seoDataHtml = SEO::render(new SEOData(
        title: 'My Page',
        description: 'Shared description',
    ));

    // The shared, data-derived tags are identical. Site-level config defaults
    // (default_og_image / twitter_site) are part of the model's base-config
    // layer and are intentionally NOT injected into an explicit hand-built
    // SEOData — that A/B difference is T1's documented contract, not a bug.
    foreach ([
        '<title>My Page | Test Site</title>',
        'content="Shared description"',
        'property="og:title" content="My Page | Test Site"',
        '<link rel="canonical" href="https://example.test">',
        'property="og:url" content="https://example.test"',
        'property="og:site_name" content="Test Site"',
    ] as $tag) {
        expect($modelHtml)->toContain($tag)
            ->and($seoDataHtml)->toContain($tag);
    }
});

it('preserves every supplied value and only fills absent fields', function () {
    $html = SEO::render(new SEOData(
        title: 'Explicit Title',
        description: 'Explicit description',
        ogTitle: 'Custom OG Title',
        twitterCard: 'summary',
    ));

    // Supplied ogTitle is preserved (not overwritten by title).
    expect($html)->toContain('property="og:title" content="Custom OG Title"')
        // Supplied twitter card preserved.
        ->and($html)->toContain('name="twitter:card" content="summary"')
        // Absent og:site_name filled from config.
        ->and($html)->toContain('property="og:site_name" content="Test Site"');
});

it('derives canonical and og:url from the current URL when absent', function () {
    $this->get('/');

    $array = SEO::toArray(new SEOData(title: 'Home'));

    $canonical = collect($array['link'])->firstWhere('rel', 'canonical');
    $ogUrl = collect($array['meta'])->firstWhere('property', 'og:url');

    expect($canonical['href'])->toBe('https://example.test')
        ->and($ogUrl['content'])->toBe('https://example.test');
});

it('preserves an explicitly supplied canonical verbatim', function () {
    $array = SEO::toArray(new SEOData(
        title: 'Filtered',
        canonical: 'https://example.test/page?ref=keep',
    ));

    $canonical = collect($array['link'])->firstWhere('rel', 'canonical');

    expect($canonical['href'])->toBe('https://example.test/page?ref=keep');
});

it('absolutizes a relative ogImage via url() (not secure_url())', function () {
    URL::forceScheme('http');

    $array = SEO::toArray(new SEOData(
        title: 'Page',
        ogImage: '/images/share.jpg',
    ));

    $ogImage = collect($array['meta'])->firstWhere('property', 'og:image');

    // url() honors the current scheme; secure_url() would force https.
    expect($ogImage['content'])->toBe('http://example.test/images/share.jpg');
});

it('absolutizes a relative twitterImage', function () {
    $array = SEO::toArray(new SEOData(
        title: 'Page',
        twitterImage: 'images/tw.jpg',
    ));

    $twitterImage = collect($array['meta'])->firstWhere('name', 'twitter:image');

    expect($twitterImage['content'])->toBe('https://example.test/images/tw.jpg');
});

it('leaves an absolute ogImage untouched', function () {
    $array = SEO::toArray(new SEOData(
        title: 'Page',
        ogImage: 'https://cdn.example.com/share.jpg',
    ));

    $ogImage = collect($array['meta'])->firstWhere('property', 'og:image');

    expect($ogImage['content'])->toBe('https://cdn.example.com/share.jpg');
});

it('applies the title suffix only when absent', function () {
    $withSuffix = SEO::render(new SEOData(title: 'Plain'));
    $alreadySuffixed = SEO::render(new SEOData(title: 'Plain | Test Site'));

    expect($withSuffix)->toContain('<title>Plain | Test Site</title>')
        // Suffix must never be duplicated in the title.
        ->and($alreadySuffixed)->toContain('<title>Plain | Test Site</title>')
        ->and($alreadySuffixed)->not->toContain('<title>Plain | Test Site | Test Site</title>');
});

it('skips the suffix when the title already contains a brand token (word boundary, case-insensitive)', function () {
    config()->set('seo.title_suffix_skip_when_contains', ['Acme']);

    $branded = SEO::render(new SEOData(title: 'About acme'));

    expect($branded)->toContain('<title>About acme</title>')
        ->and($branded)->not->toContain('About acme | Test Site');
});

it('does not skip the suffix on a partial-word brand match', function () {
    config()->set('seo.title_suffix_skip_when_contains', ['Acme']);

    // "Acmestic" must NOT count as containing the whole word "Acme".
    $html = SEO::render(new SEOData(title: 'Acmestic widgets'));

    expect($html)->toContain('<title>Acmestic widgets | Test Site</title>');
});

it('fills og:site_name from config and og:locale from the app locale when absent', function () {
    app()->setLocale('fr');

    $array = SEO::toArray(new SEOData(title: 'Page'));

    $siteName = collect($array['meta'])->firstWhere('property', 'og:site_name');
    $locale = collect($array['meta'])->firstWhere('property', 'og:locale');

    expect($siteName['content'])->toBe('Test Site')
        ->and($locale['content'])->toBe('fr');
});

it('preserves a supplied og:site_name over the config default', function () {
    $array = SEO::toArray(new SEOData(
        title: 'Page',
        ogSiteName: 'Custom Brand',
    ));

    $siteName = collect($array['meta'])->firstWhere('property', 'og:site_name');

    expect($siteName['content'])->toBe('Custom Brand');
});

it('formats a hand-built SEOData for Inertia with stable head-keys', function () {
    $head = SEO::forInertia(new SEOData(title: 'Inertia Page'));

    expect($head['title'])->toBe('Inertia Page | Test Site')
        ->and($head['meta'])->not->toBeEmpty();

    foreach ($head['meta'] as $meta) {
        expect($meta)->toHaveKey('head-key');
    }
});

it('does NOT merge the DB precedence chain into a hand-built SEOData', function () {
    // A route default exists, but a hand-built SEOData is explicit intent and
    // must not inherit it.
    config()->set('seo.default_robots', 'index,follow');

    $array = SEO::toArray(new SEOData(
        title: 'Explicit',
        robots: 'noindex,nofollow',
    ));

    $robots = collect($array['meta'])->firstWhere('name', 'robots');

    expect($robots['content'])->toBe('noindex,nofollow');
});

it('renders a SEOData through the @seo Blade directive', function () {
    $html = renderSeoDirective(new SEOData(
        title: 'Directive Page',
        description: 'Via the directive',
    ));

    expect($html)->toContain('<title>Directive Page | Test Site</title>')
        ->and($html)->toContain('content="Via the directive"');
});

it('@seo($model) and @seo($seoData) produce the same core tags for equivalent data', function () {
    $model = makeRenderSurfaceModel([
        'title' => 'Parity',
        'description' => 'Same data',
    ]);

    $modelHtml = renderSeoDirective($model);
    $seoDataHtml = renderSeoDirective(new SEOData(title: 'Parity', description: 'Same data'));

    foreach ([
        '<title>Parity | Test Site</title>',
        'content="Same data"',
        'property="og:title" content="Parity | Test Site"',
        '<link rel="canonical" href="https://example.test">',
    ] as $tag) {
        expect($modelHtml)->toContain($tag)
            ->and($seoDataHtml)->toContain($tag);
    }
});

it('@seo() with no argument still renders the current page', function () {
    $html = renderSeoDirective(null);

    // No title resolved, but the call must not error and should emit the
    // canonical for the current (empty) URL context without throwing.
    expect($html)->toBeString();
});

it('leaves direct TagRenderer::render() callers unaffected (no preparation)', function () {
    $renderer = app(TagRenderer::class);

    // A relative image passed straight to the renderer is NOT absolutized —
    // preparation happens before the renderer, never inside it.
    $html = $renderer->render(new SEOData(
        title: 'Direct',
        ogImage: '/images/raw.jpg',
    ));

    expect($html)->toContain('property="og:image" content="/images/raw.jpg"')
        // The renderer also does not apply the title suffix.
        ->and($html)->toContain('<title>Direct</title>');
});

it('resolveSource still runs the full chain for a model', function () {
    $resolver = app(SEOResolver::class);

    $model = createMockModel(['title' => 'Chain'], ['title' => 'Chain']);

    $seo = $resolver->resolveSource($model);

    // Title suffix applied via the model path proves the chain ran.
    expect($seo->title)->toBe('Chain | Test Site');
});
