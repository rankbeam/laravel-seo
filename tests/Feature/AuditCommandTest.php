<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * A HasSEO model whose computed fallbacks are deliberately empty unless an
 * attribute is set, so the audit's missing/fallback logic is controllable.
 */
class AuditPage extends Model
{
    use HasSEO;

    protected $table = 'audit_pages';

    protected $fillable = ['title', 'slug'];

    public $timestamps = true;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEODescription(): ?string
    {
        return null;
    }

    public function getSEOImage(): ?string
    {
        // No content image; the config default still applies in HasSEO.
        return null;
    }

    public function getUrlForSEO(): string
    {
        return url("/page/{$this->slug}");
    }
}

/** A plain Eloquent model without the HasSEO trait — must be skipped. */
class PlainAuditModel extends Model
{
    protected $table = 'plain_audit_models';

    protected $guarded = [];
}

beforeEach(function () {
    // Predictable lengths: no title suffix, no default OG image so the
    // missing_og_image notice is reachable when we want it.
    config(['seo.title_suffix' => '']);
    config(['seo.default_og_image' => null]);
    config(['seo.keywords.enabled' => false]);

    $schema = $this->app['db']->connection()->getSchemaBuilder();

    $schema->create('audit_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->unique();
        $table->timestamps();
    });
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('audit_pages');
});

/**
 * @return array{0: int, 1: string}
 */
function runAudit(array $parameters = []): array
{
    $exit = Artisan::call('seo:audit', $parameters);

    return [$exit, Artisan::output()];
}

function makeAuditPage(string $slug, ?string $title, array $seo = []): AuditPage
{
    config(['seo.features.auto_create_meta' => false]);

    $page = AuditPage::create(['title' => $title, 'slug' => $slug]);

    if ($seo !== []) {
        $page->saveSEO($seo);
    }

    return $page->fresh();
}

it('errors with guidance when no models are configured', function () {
    [$exit, $output] = runAudit();

    expect($exit)->toBe(1)
        ->and($output)->toContain('No models to audit')
        ->and($output)->toContain('seo.audit.models');
});

it('fails a page with no title and no computable fallback', function () {
    makeAuditPage('bare', null, []);

    [$exit, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('FAIL')
        ->and($output)->toContain('missing_title')
        ->and($output)->toContain('missing_description')
        ->and($exit)->toBe(0); // not strict -> always succeeds
});

it('passes a fully populated page', function () {
    makeAuditPage('good', 'A Practical Guide to Laravel SEO', [
        'title' => 'A Practical Guide to Laravel SEO',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/good',
        'og_image' => '/images/share.jpg',
    ]);

    [$exit, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('PASS')
        ->and($output)->toContain('1 passed')
        ->and($exit)->toBe(0);
});

it('warns (does not fail) on a short title with no critical issue', function () {
    makeAuditPage('short', 'Hi', [
        'title' => 'Hi',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/short',
        'og_image' => '/images/share.jpg',
    ]);

    [$exit, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('WARN')
        ->and($output)->toContain('title_too_short')
        ->and($exit)->toBe(0);
});

it('flags duplicate titles across pages', function () {
    makeAuditPage('a', 'An Identical Title Shared By Two Pages', ['title' => 'An Identical Title Shared By Two Pages']);
    makeAuditPage('b', 'An Identical Title Shared By Two Pages', ['title' => 'An Identical Title Shared By Two Pages']);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('duplicate_title');
});

it('flags an invalid canonical as a critical failure', function () {
    makeAuditPage('bad-canonical', 'A Reasonable Looking Title Here', [
        'title' => 'A Reasonable Looking Title Here',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'not a url',
        'og_image' => '/images/share.jpg',
    ]);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('invalid_canonical')
        ->and($output)->toContain('FAIL');
});

it('keeps the missing-focus-keyword notice off by default', function () {
    makeAuditPage('nokw', 'A Reasonable Looking Title Here', [
        'title' => 'A Reasonable Looking Title Here',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/nokw',
        'og_image' => '/images/share.jpg',
    ]);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->not->toContain('missing_focus_keyword');
});

it('flags a missing focus keyword once the workflow gate is enabled', function () {
    config(['seo.keywords.enabled' => true]);

    makeAuditPage('nokw', 'A Reasonable Looking Title Here', [
        'title' => 'A Reasonable Looking Title Here',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/nokw',
        'og_image' => '/images/share.jpg',
    ]);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('missing_focus_keyword');
});

it('does not nag when a focus keyword is set and the gate is on', function () {
    config(['seo.keywords.enabled' => true]);

    makeAuditPage('haskw', 'A Reasonable Looking Title Here', [
        'title' => 'A Reasonable Looking Title Here',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/haskw',
        'og_image' => '/images/share.jpg',
        'focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]],
    ]);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->not->toContain('missing_focus_keyword');
});

it('returns a non-zero exit in strict mode when an issue exists', function () {
    makeAuditPage('bare', null, []);

    [$exit] = runAudit(['--model' => [AuditPage::class], '--strict' => true]);

    expect($exit)->toBe(1);
});

it('returns zero in strict mode when every page passes', function () {
    makeAuditPage('good', 'A Practical Guide to Laravel SEO', [
        'title' => 'A Practical Guide to Laravel SEO',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/good',
        'og_image' => '/images/share.jpg',
    ]);

    [$exit] = runAudit(['--model' => [AuditPage::class], '--strict' => true]);

    expect($exit)->toBe(0);
});

it('hides passing pages with --issues-only', function () {
    makeAuditPage('good', 'A Practical Guide to Laravel SEO', [
        'title' => 'A Practical Guide to Laravel SEO',
        'description' => 'A thorough, hand-written meta description that comfortably sits inside the recommended length window for search snippets.',
        'canonical' => 'http://localhost/page/good',
        'og_image' => '/images/share.jpg',
    ]);
    makeAuditPage('bare', null, []);

    [, $output] = runAudit(['--model' => [AuditPage::class], '--issues-only' => true]);

    expect($output)->toContain('AuditPage #2')   // the failing page
        ->and($output)->not->toContain('AuditPage #1'); // the passing page is hidden
});

it('emits structured JSON with --json', function () {
    makeAuditPage('bare', null, []);

    [, $output] = runAudit(['--model' => [AuditPage::class], '--json' => true]);

    $payload = json_decode($output, true);

    expect($payload)->toBeArray()
        ->and($payload['summary']['pages'])->toBe(1)
        ->and($payload['summary']['failed'])->toBe(1)
        ->and($payload['pages'][0]['status'])->toBe('fail')
        ->and(collect($payload['pages'][0]['issues'])->pluck('code'))->toContain('missing_title')
        ->and($payload['coverage']['executes'])->toBe('metadata');
});

it('prints the capability matrix every run', function () {
    makeAuditPage('good', 'A Practical Guide to Laravel SEO', [
        'title' => 'A Practical Guide to Laravel SEO',
    ]);

    [, $output] = runAudit(['--model' => [AuditPage::class]]);

    expect($output)->toContain('Coverage')
        ->and($output)->toContain('Needs the Pro scan')
        ->and($output)->toContain('rankbeam.dev/pro/scan-issues');
});

it('reads models from the seo.audit.models config', function () {
    config(['seo.audit.models' => [AuditPage::class => []]]);
    makeAuditPage('bare', null, []);

    [, $output] = runAudit();

    expect($output)->toContain('missing_title');
});

it('reads models from a numeric (list) seo.audit.models config', function () {
    // The documented [Post::class, Page::class] list form: array_keys() would
    // hand integer positions to the strict string audit param and crash.
    config(['seo.audit.models' => [AuditPage::class]]);
    makeAuditPage('bare', null, []);

    [$exit, $output] = runAudit();

    expect($exit)->toBe(0)
        ->and($output)->toContain('missing_title')
        ->and($output)->not->toContain('class not found');
});

it('reads models from a numeric seo.sitemap.models config when audit is empty', function () {
    config(['seo.audit.models' => []]);
    config(['seo.sitemap.models' => [AuditPage::class]]);
    makeAuditPage('bare', null, []);

    [$exit, $output] = runAudit();

    expect($exit)->toBe(0)
        ->and($output)->toContain('missing_title');
});

it('skips a configured class that does not use HasSEO', function () {
    [, $output] = runAudit(['--model' => [PlainAuditModel::class]]);

    expect($output)->toContain('Skipped')
        ->and($output)->toContain('does not use the HasSEO trait');
});

it('skips a class that is not an Eloquent model', function () {
    [, $output] = runAudit(['--model' => [\Rankbeam\Seo\Data\SEOData::class]]);

    expect($output)->toContain('Skipped')
        ->and($output)->toContain('not an Eloquent model');
});
