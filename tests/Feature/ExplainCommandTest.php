<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Rankbeam\Seo\Explaining\ResolutionExplainer;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| seo:explain — resolver precedence trace
|--------------------------------------------------------------------------
|
| The explainer attributes each field to its winning layer (with the losers it
| overrode), notes post-processing (title suffix, canonical strip, og:url
| derivation, image absolutization, indexing guard), and reports the site-level
| ledger (site name, default locale, canonical host) with each value's source.
| Attribution reuses the resolver's own layer contributions; the final values
| come from the real resolve(), so the trace cannot drift from what renders.
|
*/

class ExplainPage extends Model
{
    use HasSEO;

    protected $table = 'explain_pages';

    protected $fillable = ['title', 'page_url'];

    public function getUrlForSEO(): string
    {
        return $this->page_url ?? 'https://ex.com/default';
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('explain_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('page_url')->nullable();
        $table->timestamps();
    });

    // No auto-created seo_meta, so a model without saveSEO() has no explicit
    // layer — keeps the config/computed-layer attribution scenarios clean.
    config()->set('seo.features.auto_create_meta', false);
    config()->set('app.url', 'https://ex.com');
    config()->set('app.locale', 'en');
    config()->set('seo.site_name', 'Test Site');
    config()->set('seo.title_suffix', ' | Test Site');
    config()->set('seo.default_robots', 'index,follow');
    config()->set('seo.default_og_image', '/default-og.jpg');
});

function explainPage(ExplainPage $page, ?string $route = null, ?string $locale = null): array
{
    return app(ResolutionExplainer::class)->explain($page, $route, $locale);
}

function setExplainEnv(string $environment): void
{
    app()->instance('env', $environment);
}

// --- Layer attribution (≥3 precedence scenarios) -----------------------------

it('attributes an explicit value as the winner and the computed value as a loser', function () {
    $page = ExplainPage::create(['title' => 'Computed Title']);
    $page->saveSEO(['title' => 'Explicit Title']);

    $title = explainPage($page->fresh())['fields']['title'];

    expect($title['winner']['layer'])->toBe('explicit')
        ->and($title['winner']['value'])->toBe('Explicit Title')
        ->and(collect($title['losers'])->pluck('layer'))->toContain('computed');
});

it('attributes the computed layer when nothing higher sets the field', function () {
    $page = ExplainPage::create(['title' => 'Only Computed']);

    $title = explainPage($page)['fields']['title'];

    expect($title['winner']['layer'])->toBe('computed')
        ->and($title['winner']['value'])->toBe('Only Computed');
});

it('attributes the base config layer for a field only config sets', function () {
    config()->set('seo.twitter_site', '@testsite');
    $page = ExplainPage::create(['title' => 'T']);

    $fields = explainPage($page)['fields'];

    // og_site_name / twitter_site have null constructor defaults and are set
    // only by the base config layer, so attribution is unambiguous. (og_type /
    // twitter_card carry non-null SEOData constructor defaults that ride along
    // on every `new SEOData()` layer, so they attribute to the highest such
    // layer — faithful to the merge, but not a clean "config only" probe.)
    expect($fields['og_site_name']['winner']['layer'])->toBe('config')
        ->and($fields['og_site_name']['winner']['value'])->toBe('Test Site')
        ->and($fields['twitter_site']['winner']['layer'])->toBe('config')
        ->and($fields['twitter_site']['winner']['value'])->toBe('@testsite');
});

it('attributes a global SEODefault layer when the model offers nothing', function () {
    SEODefault::create([
        'scope' => 'global',
        'locale' => 'en',
        'description_template' => 'Global fallback description',
    ]);

    $page = ExplainPage::create(['title' => 'T']);

    $description = explainPage($page)['fields']['description'];

    expect($description['winner']['layer'])->toBe('global')
        ->and($description['winner']['value'])->toBe('Global fallback description');
});

it('attributes a route SEODefault layer, overriding config', function () {
    SEODefault::create([
        'scope' => 'pages.show',
        'locale' => 'en',
        'title_template' => 'Route Title',
    ]);

    // No title attribute → computed contributes no title, so the route default wins.
    $page = ExplainPage::create(['title' => null, 'page_url' => 'https://ex.com/p']);

    $title = explainPage($page, 'pages.show')['fields']['title'];

    expect($title['winner']['layer'])->toBe('route')
        ->and($title['winner']['value'])->toBe('Route Title');
});

// --- Post-processing notes ---------------------------------------------------

it('notes the title suffix appended in post-processing', function () {
    $page = ExplainPage::create(['title' => 'My Title']);

    $title = explainPage($page)['fields']['title'];

    expect($title['winner']['value'])->toBe('My Title')
        ->and($title['final'])->toBe('My Title | Test Site')
        ->and($title['notes'])->toContain("title suffix ' | Test Site' appended");
});

it('notes a derived canonical with its query string stripped', function () {
    $page = ExplainPage::create(['title' => 'T', 'page_url' => 'https://ex.com/p?ref=abc']);

    $canonical = explainPage($page)['fields']['canonical'];

    expect($canonical['winner'])->toBeNull()
        ->and($canonical['final'])->toBe('https://ex.com/p')
        ->and($canonical['notes'][0])->toContain('query string stripped');
});

it('notes an absolutized image', function () {
    $page = ExplainPage::create(['title' => 'T', 'page_url' => 'https://ex.com/p']);
    $page->saveSEO(['og_image' => '/share.jpg']);

    $ogImage = explainPage($page->fresh())['fields']['og_image'];

    expect($ogImage['winner']['layer'])->toBe('explicit')
        ->and($ogImage['final'])->toEndWith('/share.jpg')
        ->and($ogImage['final'])->toStartWith('http')
        ->and($ogImage['notes'])->toContain("absolutized from '/share.jpg'");
});

it('notes the indexing guard overriding robots on a non-production environment', function () {
    config()->set('seo.indexing_guard.enabled', true);
    config()->set('seo.indexing_guard.allowed_environments', ['production']);
    setExplainEnv('staging');

    $page = ExplainPage::create(['title' => 'T', 'page_url' => 'https://ex.com/p']);
    $page->saveSEO(['robots' => 'index,follow']);

    $robots = explainPage($page->fresh())['fields']['robots'];

    expect($robots['winner']['value'])->toBe('index,follow')
        ->and($robots['final'])->toBe('noindex,nofollow')
        ->and($robots['notes'][0])->toContain('indexing guard forced');
});

// --- Site-level ledger -------------------------------------------------------

it('reports the site-level resolution ledger with sources', function () {
    $page = ExplainPage::create(['title' => 'T', 'page_url' => 'https://ex.com/p']);

    $site = explainPage($page)['site_level'];

    expect($site['site_name']['value'])->toBe('Test Site')
        ->and($site['site_name']['source'])->toBe('config (seo.site_name)')
        ->and($site['default_locale']['value'])->toBe('en')
        ->and($site['default_locale']['source'])->toBe('config (app.locale)')
        ->and($site['canonical_host']['value'])->toBe('ex.com')
        ->and($site['canonical_host']['source'])->toContain('getUrlForSEO');
});

it('reports the effective app locale in the site-level ledger', function () {
    app()->setLocale('de');
    $page = ExplainPage::create(['title' => 'T']);

    // The resolve locale (--locale) is separate from the site-wide default; the
    // ledger reports the effective app locale, honestly attributed to config.
    $locale = explainPage($page, null, 'en')['site_level']['default_locale'];

    expect($locale['value'])->toBe('de')
        ->and($locale['source'])->toBe('config (app.locale)');
});

// --- Command surface ---------------------------------------------------------

it('runs seo:explain against a specific record', function () {
    $page = ExplainPage::create(['title' => 'Hello']);

    $this->artisan('seo:explain', ['model' => ExplainPage::class, 'id' => $page->getKey()])
        ->expectsOutputToContain('SEO resolution')
        ->assertExitCode(0);
});

it('defaults to the first record when no id is given', function () {
    ExplainPage::create(['title' => 'First']);

    $this->artisan('seo:explain', ['model' => ExplainPage::class])
        ->expectsOutputToContain('first record')
        ->assertExitCode(0);
});

it('emits a structured JSON trace', function () {
    $page = ExplainPage::create(['title' => 'Hello']);

    $exit = Artisan::call('seo:explain', ['model' => ExplainPage::class, 'id' => $page->getKey(), '--json' => true]);
    $trace = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($trace['target']['model'])->toBe(ExplainPage::class)
        ->and($trace['fields']['title']['winner']['layer'])->toBe('computed')
        ->and($trace['site_level'])->toHaveKey('canonical_host');
});

it('emits valid JSON with --json even when no id is given', function () {
    ExplainPage::create(['title' => 'First']);

    $exit = Artisan::call('seo:explain', ['model' => ExplainPage::class, '--json' => true]);
    $trace = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($trace)->toBeArray()
        ->and($trace)->toHaveKey('fields');
});

it('never drifts: a winner with no post-processing note equals the final value', function () {
    $page = ExplainPage::create(['title' => 'My Post', 'page_url' => 'https://ex.com/p']);
    $page->saveSEO(['description' => 'A hand-written description']);

    foreach (explainPage($page->fresh())['fields'] as $field => $info) {
        if ($info['winner'] !== null && $info['notes'] === []) {
            expect($info['winner']['value'])->toBe($info['final'], "field {$field} drifted from its winner");
        }
    }
});

it('attributes the canonical host to the explicit layer when a canonical is stored', function () {
    $page = ExplainPage::create(['title' => 'T', 'page_url' => 'https://ex.com/p']);
    $page->saveSEO(['canonical' => 'https://cdn.example.org/custom']);

    $host = explainPage($page->fresh())['site_level']['canonical_host'];

    expect($host['value'])->toBe('cdn.example.org')
        ->and($host['source'])->toContain('explicit');
});

it('fails clearly for a class that is not a HasSEO model', function () {
    $this->artisan('seo:explain', ['model' => 'DateTime'])
        ->expectsOutputToContain('Cannot explain')
        ->assertExitCode(1);
});

it('fails clearly when the model has no records', function () {
    $this->artisan('seo:explain', ['model' => ExplainPage::class])
        ->assertExitCode(1);
});
