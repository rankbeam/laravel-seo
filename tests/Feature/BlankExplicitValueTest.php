<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Auditing\AuditIssue;
use Rankbeam\Seo\Auditing\MetadataAuditor;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| T2 — blank explicit-value policy (seo.resolver.blank_is_unset)
|--------------------------------------------------------------------------
|
| A persisted '' / '   ' in a seo_meta string column is an EXPLICIT value, so
| the resolver's "last non-null wins" merge lets it override every lower layer —
| silently blanking a tag or suppressing the computed fallback. The opt-in
| `seo.resolver.blank_is_unset` flag normalizes such blanks to null during the
| explicit-value extraction so they fall through instead.
|
| Default (flag OFF) behavior must stay byte-identical to today; only STRING
| fields are affected (arrays, schema, and the literal "0" are untouched); and
| the condition is observable via the free audit's `blank_explicit_override`
| code even while the flag is off.
|
*/

class BlankPolicyPage extends Model
{
    use HasSEO;

    protected $table = 'blank_policy_pages';

    protected $fillable = ['title', 'subtitle'];

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEODescription(): ?string
    {
        return $this->subtitle;
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('blank_policy_pages', function ($table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('subtitle')->nullable();
        $table->timestamps();
    });

    // Deterministic resolution: no auto-created empty meta, no title suffix so a
    // computed title compares verbatim.
    config(['seo.features.auto_create_meta' => false]);
    config(['seo.title_suffix' => null]);
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('blank_policy_pages');
});

function resolveBlankPage(Model $model): SEOData
{
    return app(SEOResolver::class)->resolve($model);
}

function auditCodes(Model $model): array
{
    return collect(app(MetadataAuditor::class)->audit($model))
        ->map(fn (AuditIssue $i): string => $i->code)
        ->all();
}

describe('flag OFF (default, current behavior)', function () {
    beforeEach(fn () => config(['seo.resolver.blank_is_unset' => false]));

    it('lets a blank explicit title override the computed title (byte-identical to today)', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => '']);

        expect(resolveBlankPage($page->fresh())->title)->toBe('');
    });

    it('lets a whitespace-only explicit description override the computed description', function () {
        $page = BlankPolicyPage::create(['title' => 'T', 'subtitle' => 'Computed description']);
        $page->saveSEO(['description' => '   ']);

        expect(resolveBlankPage($page->fresh())->description)->toBe('   ');
    });
});

describe('flag ON (opt-in)', function () {
    beforeEach(fn () => config(['seo.resolver.blank_is_unset' => true]));

    it('falls through a blank explicit title to the computed title', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => '']);

        expect(resolveBlankPage($page->fresh())->title)->toBe('Model Title');
    });

    it('falls through a whitespace-only explicit title to the computed title', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => "  \t  "]);

        expect(resolveBlankPage($page->fresh())->title)->toBe('Model Title');
    });

    it('falls through a blank explicit description to the computed description', function () {
        $page = BlankPolicyPage::create(['title' => 'T', 'subtitle' => 'Computed description']);
        $page->saveSEO(['description' => '']);

        expect(resolveBlankPage($page->fresh())->description)->toBe('Computed description');
    });

    it('preserves the literal string "0" — empty() but a real value', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => '0']);

        expect(resolveBlankPage($page->fresh())->title)->toBe('0');
    });

    it('still lets a non-empty explicit value override the computed value', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => 'Explicit Title']);

        expect(resolveBlankPage($page->fresh())->title)->toBe('Explicit Title');
    });

    it('never touches array fields — focus_keywords survive', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]]);

        expect(resolveBlankPage($page->fresh())->focusKeywords)
            ->toBe([['keyword' => 'laravel seo', 'is_primary' => true]]);
    });

    it('never touches the JSON-LD schema', function () {
        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['schema_jsonld' => ['@type' => 'Thing', 'name' => 'X']]);

        expect(resolveBlankPage($page->fresh())->schemaJsonld)
            ->toBe(['@type' => 'Thing', 'name' => 'X']);
    });
});

it('clears to null independently of the flag (Filament clear-to-null still works)', function () {
    // A null (not blank-string) explicit value has always fallen through; the
    // new policy must not change that, with the flag off OR on.
    config(['seo.resolver.blank_is_unset' => false]);

    $page = BlankPolicyPage::create(['title' => 'Model Title']);
    $page->saveSEO(['title' => null]);

    expect(resolveBlankPage($page->fresh())->title)->toBe('Model Title');
});

describe('free audit observability (blank_explicit_override)', function () {
    it('reports blank_explicit_override while the flag is off, naming the blank fields', function () {
        config(['seo.resolver.blank_is_unset' => false]);

        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => '', 'og_title' => '   ']);

        $issues = app(MetadataAuditor::class)->audit($page->fresh());
        $issue = collect($issues)->firstWhere('code', 'blank_explicit_override');

        expect($issue)->not->toBeNull()
            ->and($issue->severity)->toBe(AuditIssue::SEVERITY_WARNING)
            ->and($issue->field)->toBeNull()
            ->and($issue->context['fields'])->toContain('title')
            ->and($issue->context['fields'])->toContain('og_title');
    });

    it('does not report blank_explicit_override once the flag is on', function () {
        config(['seo.resolver.blank_is_unset' => true]);

        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => '']);

        expect(auditCodes($page->fresh()))->not->toContain('blank_explicit_override');
    });

    it('does not report when no stored string is blank (real value and "0" are fine)', function () {
        config(['seo.resolver.blank_is_unset' => false]);

        $page = BlankPolicyPage::create(['title' => 'Model Title']);
        $page->saveSEO(['title' => 'A Real Stored Title', 'robots' => '0']);

        expect(auditCodes($page->fresh()))->not->toContain('blank_explicit_override');
    });

    it('does not report for a model with no seo_meta row', function () {
        config(['seo.resolver.blank_is_unset' => false]);

        $page = BlankPolicyPage::create(['title' => 'Model Title']);

        expect(auditCodes($page->fresh()))->not->toContain('blank_explicit_override');
    });
});
