<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Services\TagRenderer;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| Characterization: resolver precedence, canonical strip, robots policy
|--------------------------------------------------------------------------
|
| Ported from idi-it's DynamicSeoDataResolver against the package precedence
| chain (config → global default → model-type default → route default →
| computed → explicit):
|
| - manual (explicit seo_meta) beats computed model values
| - computed model values beat model-type and global defaults
| - global defaults beat base config
| - canonical derived from the model URL has its query string stripped;
|   an explicit canonical is preserved verbatim
| - robots: explicit > indexability flag > config default
|
*/

class CharacterizationPage extends Model
{
    use HasSEO;

    protected $table = 'characterization_pages';

    protected $fillable = ['title', 'subtitle', 'page_url', 'is_indexable'];

    protected $casts = ['is_indexable' => 'boolean'];

    public function getUrlForSEO(): string
    {
        return $this->page_url ?? url()->current();
    }

    // Defer to the configured description candidate list
    // (seo.computed.description_fields) instead of the trait's built-in
    // excerpt/content scan.
    public function getSEODescription(): ?string
    {
        return null;
    }
}

class RobotsHookPage extends CharacterizationPage
{
    // The optional getSEORobots() hook takes priority over the is_indexable
    // attribute in SEOComputedBuilder::computeRobots(). An advanced directive
    // returned here must survive verbatim all the way to the rendered tag.
    public function getSEORobots(): ?string
    {
        return 'noindex, nofollow, noarchive';
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('characterization_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('subtitle')->nullable();
        $table->string('page_url')->nullable();
        $table->boolean('is_indexable')->nullable();
        $table->timestamps();
    });

    config(['seo.features.auto_create_meta' => false]);
    config(['seo.title_suffix' => null]);
    config(['seo.computed.description_fields' => ['subtitle']]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('characterization_pages');
});

function resolveSeo(Model $model): \Rankbeam\Seo\Data\SEOData
{
    return app(SEOResolver::class)->resolve($model);
}

function renderResolvedSeo(Model $model): string
{
    return app(TagRenderer::class)->render(resolveSeo($model));
}

describe('precedence: manual > computed > defaults > config', function () {
    it('explicit seo_meta title beats the computed model title', function () {
        $page = CharacterizationPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => 'Manual SEO Title']);

        expect(resolveSeo($page->fresh())->title)->toBe('Manual SEO Title');
    });

    it('computed model title beats the model-type default template', function () {
        SEODefault::create([
            'scope' => CharacterizationPage::class,
            'locale' => 'en',
            'title_template' => 'Model-type Default Title',
        ]);

        $page = CharacterizationPage::create(['title' => 'Model Title']);

        expect(resolveSeo($page)->title)->toBe('Model Title');
    });

    it('explicit description beats the computed candidate-field description', function () {
        $page = CharacterizationPage::create([
            'title' => 'T',
            'subtitle' => 'Computed subtitle text',
        ]);
        $page->saveSEO(['description' => 'Manual description']);

        expect(resolveSeo($page->fresh())->description)->toBe('Manual description');
    });

    it('computed candidate-field description beats the global default template', function () {
        SEODefault::create([
            'scope' => 'global',
            'locale' => 'en',
            'description_template' => 'Global fallback description',
        ]);

        $page = CharacterizationPage::create([
            'title' => 'T',
            'subtitle' => 'Computed subtitle text',
        ]);

        expect(resolveSeo($page)->description)->toBe('Computed subtitle text');
    });

    it('global default description applies when the model offers nothing', function () {
        SEODefault::create([
            'scope' => 'global',
            'locale' => 'en',
            'description_template' => 'Global fallback description',
        ]);

        $page = CharacterizationPage::create(['title' => 'T']);

        expect(resolveSeo($page)->description)->toBe('Global fallback description');
    });

});

describe('canonical query-strip', function () {
    it('strips the query string from a model-derived canonical', function () {
        $page = CharacterizationPage::create([
            'title' => 'T',
            'page_url' => 'https://example.com/pages/about?utm_source=newsletter&page=2',
        ]);

        $seo = resolveSeo($page);

        expect($seo->canonical)->toBe('https://example.com/pages/about')
            ->and($seo->ogUrl)->toBe('https://example.com/pages/about');
    });

    it('preserves an explicit canonical verbatim, query string included', function () {
        $page = CharacterizationPage::create([
            'title' => 'T',
            'page_url' => 'https://example.com/pages/about?utm_source=x',
        ]);
        $page->saveSEO(['canonical' => 'https://example.com/canonical?keep=this']);

        expect(resolveSeo($page->fresh())->canonical)->toBe('https://example.com/canonical?keep=this');
    });
});

describe('robots from indexability', function () {
    it('derives noindex, nofollow from a non-indexable model', function () {
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => false]);

        expect(resolveSeo($page)->robots)->toBe('noindex, nofollow');
    });

    it('derives index, follow from an indexable model', function () {
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => true]);

        expect(resolveSeo($page)->robots)->toBe('index, follow');
    });

    it('lets an explicit robots value beat the indexability flag', function () {
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => true]);
        $page->saveSEO(['robots' => 'noindex, nofollow']);

        expect(resolveSeo($page->fresh())->robots)->toBe('noindex, nofollow');
    });

    it('falls back to the config default when the model has no flag', function () {
        config(['seo.default_robots' => 'index,follow']);

        $page = CharacterizationPage::create(['title' => 'T']);

        expect(resolveSeo($page)->robots)->toBe('index,follow');
    });
});

describe('robots: resolved value rendered through the emit policy', function () {
    // Ties computeRobots() to TagRenderer::robotsContent(): the model-derived
    // robots value must survive the full resolve → render path AND respect the
    // emit policy (the tag is suppressed when it equals seo.default_robots,
    // emitted verbatim when it deviates). seo.default_robots is "index,follow"
    // in the test environment.

    it('renders noindex, nofollow for a non-indexable model (deviates from the default)', function () {
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => false]);

        expect(renderResolvedSeo($page))
            ->toContain('<meta name="robots" content="noindex, nofollow">');
    });

    it('emits no robots tag for an indexable model (index, follow equals the default)', function () {
        // is_indexable=true resolves to "index, follow", which normalizes to
        // the site default "index,follow" → the policy suppresses the tag. The
        // page is indexable precisely BY the absence of a directive, which is
        // what a crawler treats as index,follow.
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => true]);

        expect(renderResolvedSeo($page))->not->toContain('name="robots"');
    });

    it('renders an explicit seo_meta robots value verbatim, even over an indexable flag', function () {
        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => true]);
        $page->saveSEO(['robots' => 'noindex, follow']);

        expect(renderResolvedSeo($page->fresh()))
            ->toContain('<meta name="robots" content="noindex, follow">');
    });

    it('renders a getSEORobots() advanced directive verbatim', function () {
        $page = RobotsHookPage::create(['title' => 'T']);

        expect(renderResolvedSeo($page))
            ->toContain('<meta name="robots" content="noindex, nofollow, noarchive">');
    });

    it('still emits a deviating directive when the site default itself is noindex', function () {
        // Honours a non-default site policy: an indexable model now DEVIATES
        // from a noindex site default, so its index, follow must be emitted.
        config(['seo.default_robots' => 'noindex,nofollow']);

        $page = CharacterizationPage::create(['title' => 'T', 'is_indexable' => true]);

        expect(renderResolvedSeo($page))
            ->toContain('<meta name="robots" content="index, follow">');
    });
});
