<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Http\Middleware\IndexingGuardHeader;
use Rankbeam\Seo\Services\IndexingGuard;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| Indexing guard (non-production safety net)
|--------------------------------------------------------------------------
|
| When the app runs in an environment NOT in seo.indexing_guard.allowed_environments
| AND the guard is enabled, three surfaces react to one signal:
|   - the resolver forces noindex,nofollow above the whole precedence chain
|     (overriding even an explicit stored robots value);
|   - the robots.txt / ai.txt builder emits a disallow-all document;
|   - seo:audit prints a prominent banner.
| On production, or when disabled, everything is byte-identical.
|
*/

class GuardedPage extends Model
{
    use HasSEO;

    protected $table = 'guarded_pages';

    protected $fillable = ['title', 'page_url'];

    public function getUrlForSEO(): string
    {
        return $this->page_url ?? 'https://example.com/guarded';
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('guarded_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('page_url')->nullable();
        $table->timestamps();
    });

    // A stable, indexable starting point: every test then flips the env and the
    // guard config to observe the effect.
    config()->set('seo.default_robots', 'index,follow');
    config()->set('seo.indexing_guard.enabled', true);
    config()->set('seo.indexing_guard.allowed_environments', ['production']);
});

/**
 * Set the current application environment for the duration of a test. The
 * resolver, guard, and robots builder all read app()->environment() live, so
 * flipping this binding is enough to move between prod / staging / local.
 */
function setEnv(string $environment): void
{
    // app()->environment() reads the container's 'env' binding; rebinding it is
    // enough because the resolver, guard, and robots builder all read it live.
    app()->instance('env', $environment);
}

// --- The guard's own decision -------------------------------------------------

it('setEnv actually changes app()->environment()', function () {
    setEnv('staging');

    expect(app()->environment())->toBe('staging');
});

it('is active on a non-allowed environment and inactive on an allowed one', function () {
    $guard = app(IndexingGuard::class);

    setEnv('staging');
    expect($guard->active())->toBeTrue();

    setEnv('production');
    expect($guard->active())->toBeFalse();
});

it('is never active when disabled, even on a non-allowed environment', function () {
    config()->set('seo.indexing_guard.enabled', false);
    setEnv('staging');

    expect(app(IndexingGuard::class)->active())->toBeFalse();
});

// --- Resolver: force noindex per environment ---------------------------------

it('forces noindex,nofollow on a non-production environment', function () {
    setEnv('staging');
    $page = GuardedPage::create(['title' => 'Hello']);

    expect(SEO::resolve($page)->robots)->toBe('noindex,nofollow');
});

it('leaves robots untouched on production (guard inert)', function () {
    setEnv('production');
    $page = GuardedPage::create(['title' => 'Hello']);

    // No explicit/route directive → resolver carries the config default.
    expect(SEO::resolve($page)->robots)->toBe('index,follow');
});

it('is byte-identical to a package without the feature when disabled', function () {
    config()->set('seo.indexing_guard.enabled', false);
    setEnv('local');
    $page = GuardedPage::create(['title' => 'Hello']);

    expect(SEO::resolve($page)->robots)->toBe('index,follow');
});

// --- Explicit-meta interaction: the guard is a floor the stored value can't
//     punch through --------------------------------------------------------------

it('overrides an explicit stored index,follow on a non-production environment', function () {
    setEnv('staging');
    $page = GuardedPage::create(['title' => 'Hello']);
    $page->saveSEO(['robots' => 'index,follow']);

    // The stored value would normally win (highest layer); the guard sits above it.
    expect(SEO::resolve($page->fresh())->robots)->toBe('noindex,nofollow');
});

it('preserves an explicit stored robots value on production', function () {
    setEnv('production');
    $page = GuardedPage::create(['title' => 'Hello']);
    $page->saveSEO(['robots' => 'index,follow,max-snippet:-1']);

    expect(SEO::resolve($page->fresh())->robots)->toBe('index,follow,max-snippet:-1');
});

// --- Override list + wildcards ------------------------------------------------

it('treats an environment added to the allowed list as indexable', function () {
    config()->set('seo.indexing_guard.allowed_environments', ['production', 'staging']);
    setEnv('staging');
    $page = GuardedPage::create(['title' => 'Hello']);

    expect(SEO::resolve($page)->robots)->toBe('index,follow');
});

it('matches allowed environments by wildcard', function () {
    config()->set('seo.indexing_guard.allowed_environments', ['prod*']);
    $page = GuardedPage::create(['title' => 'Hello']);

    setEnv('prod-eu');
    expect(SEO::resolve($page)->robots)->toBe('index,follow');

    setEnv('staging');
    expect(SEO::resolve($page->fresh())->robots)->toBe('noindex,nofollow');
});

it('guards every environment when the allowed list is empty (fail-safe)', function () {
    config()->set('seo.indexing_guard.allowed_environments', []);
    setEnv('production');
    $page = GuardedPage::create(['title' => 'Hello']);

    expect(SEO::resolve($page)->robots)->toBe('noindex,nofollow');
});

// --- Hand-built SEOData path (listings / search / controller-composed) --------

it('guards a hand-built SEOData rendered without a model', function () {
    setEnv('staging');

    $resolved = app(SEOResolver::class)->resolveSource(
        SEOData::fromArray(['title' => 'Search results', 'robots' => 'index,follow'])
    );

    expect($resolved->robots)->toBe('noindex,nofollow');
});

// --- Rendered tag end-to-end --------------------------------------------------

it('renders a noindex robots meta tag when the guard is active', function () {
    setEnv('staging');
    $page = GuardedPage::create(['title' => 'Hello']);

    expect(SEO::render($page))->toContain('<meta name="robots" content="noindex,nofollow">');
});

// --- Resolver result cache stays environment-agnostic -------------------------

it('applies the guard on a resolver-cache hit, keeping the cache env-agnostic', function () {
    config()->set('seo.cache.resolver.enabled', true);
    $page = GuardedPage::create(['title' => 'Hello']);

    // Warm the cache on production (stores an un-guarded, index,follow entry).
    setEnv('production');
    expect(SEO::resolve($page)->robots)->toBe('index,follow');

    // Same cached entry, now read on staging — the guard is re-evaluated live.
    setEnv('staging');
    expect(SEO::resolve($page)->robots)->toBe('noindex,nofollow');
});

// --- robots.txt / ai.txt: disallow-all ---------------------------------------

it('emits a disallow-all robots.txt when the guard is active', function () {
    setEnv('staging');

    $robots = app(RobotsTxtBuilder::class)->build();

    expect($robots)->toContain('User-agent: *')
        ->toContain('Disallow: /')
        ->toContain('Indexing guard ACTIVE')
        ->not->toContain('GPTBot');
});

it('emits a disallow-all ai.txt when the guard is active', function () {
    setEnv('staging');

    expect(app(RobotsTxtBuilder::class)->buildAiTxt())
        ->toContain('User-agent: *')
        ->toContain('Disallow: /')
        ->not->toContain('GPTBot');
});

it('generates a disallow-all robots.txt from the command even when AI crawlers are disabled', function () {
    // A user who turned off AI-crawler management but armed the guard must
    // still be able to write the protective robots.txt — the command gate must
    // not shadow the guard.
    config()->set('seo.ai_crawlers.enabled', false);
    setEnv('staging');

    $this->artisan('seo:robots-txt --print')
        ->expectsOutputToContain('Disallow: /')
        ->assertExitCode(0);
});

it('still refuses generation when AI crawlers are disabled and the guard is inactive', function () {
    config()->set('seo.ai_crawlers.enabled', false);
    setEnv('production');

    $this->artisan('seo:robots-txt --print')
        ->assertExitCode(1);
});

it('emits the normal AI-crawler robots.txt on production', function () {
    setEnv('production');
    config()->set('seo.ai_crawlers.policy.ai_training', 'disallow');
    config()->set('seo.ai_crawlers.list', 'blocked');

    $robots = app(RobotsTxtBuilder::class)->build();

    expect($robots)->toContain('AI crawlers (managed by Rankbeam)')
        ->not->toContain('Indexing guard ACTIVE');
});

// --- seo:audit banner ---------------------------------------------------------

it('prints a prominent banner in seo:audit when the guard is active', function () {
    config()->set('seo.audit.models', [GuardedPage::class]);
    setEnv('staging');
    GuardedPage::create(['title' => 'A sufficiently long page title here']);

    $this->artisan('seo:audit')
        ->expectsOutputToContain('INDEXING GUARD ACTIVE')
        ->assertExitCode(0);
});

it('does not print the banner on production', function () {
    config()->set('seo.audit.models', [GuardedPage::class]);
    setEnv('production');
    GuardedPage::create(['title' => 'A sufficiently long page title here']);

    $this->artisan('seo:audit')
        ->doesntExpectOutputToContain('INDEXING GUARD ACTIVE')
        ->assertExitCode(0);
});

it('carries the guard state in the seo:audit --json payload', function () {
    config()->set('seo.audit.models', [GuardedPage::class]);
    setEnv('staging');
    GuardedPage::create(['title' => 'A sufficiently long page title here']);

    $exit = Artisan::call('seo:audit', ['--json' => true]);
    $payload = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($payload['indexing_guard']['active'])->toBeTrue()
        ->and($payload['indexing_guard']['environment'])->toBe('staging')
        ->and($payload['indexing_guard']['directive'])->toBe('noindex,nofollow')
        ->and($payload['indexing_guard']['allowed_environments'])->toBe(['production']);
});

// --- X-Robots-Tag header: covers PDFs/feeds/images that carry no meta tag -----
//
// The meta tag only reaches crawlers that parse HTML. The IndexingGuardHeader
// middleware adds the same noindex signal as an HTTP header, so a non-HTML
// response routed through the app is held out of the index too. Routes here
// attach the middleware explicitly (the provider registers it globally only
// when the guard is enabled at boot).

describe('X-Robots-Tag header middleware', function () {
    beforeEach(function () {
        Route::middleware(['web', IndexingGuardHeader::class])->group(function () {
            Route::get('/guard/page', fn () => response('<html><body>Hi</body></html>'))
                ->name('guard.page');
            Route::get('/guard/report.pdf', fn () => response('%PDF-1.4 …', 200, [
                'Content-Type' => 'application/pdf',
            ]))->name('guard.pdf');
        });
    });

    it('stamps noindex on an HTML response when the guard is active', function () {
        setEnv('staging');

        $this->get('/guard/page')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex,nofollow');
    });

    it('stamps noindex on a non-HTML PDF response — the case a meta tag cannot reach', function () {
        setEnv('staging');

        $this->get('/guard/report.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex,nofollow');
    });

    it('mirrors the resolver meta directive exactly (single source of truth)', function () {
        setEnv('staging');

        $this->get('/guard/page')->assertHeader('X-Robots-Tag', IndexingGuard::DIRECTIVE);
    });

    it('sends no header on production (guard inert)', function () {
        setEnv('production');

        $this->get('/guard/page')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    });

    it('sends no header when the guard is disabled, even off-production', function () {
        config()->set('seo.indexing_guard.enabled', false);
        setEnv('staging');

        $this->get('/guard/page')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    });
});
