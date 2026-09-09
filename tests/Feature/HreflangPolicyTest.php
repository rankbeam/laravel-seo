<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Auditing\MetadataAuditor;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;
use Rankbeam\Seo\Services\TagRenderer;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| hreflang hardening (M2): one policy, seen by the tags, the sitemap and
| the audit
|--------------------------------------------------------------------------
*/

class HreflangPolicyPage extends Model
{
    use HasSEO;

    protected $table = 'hreflang_policy_pages';

    protected $fillable = ['title', 'slug', 'alternates_json'];

    public $timestamps = false;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getUrlForSEO(): string
    {
        return url("/it/{$this->slug}");
    }

    public function getSEOAlternates(): ?array
    {
        return $this->alternates_json ? json_decode($this->alternates_json, true) : null;
    }
}

beforeEach(function () {
    Storage::fake('public');

    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->dropIfExists('hreflang_policy_pages');
    $schema->create('hreflang_policy_pages', function ($table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('slug');
        $table->text('alternates_json')->nullable();
    });

    config([
        'app.url' => 'http://localhost',
        'seo.title_suffix' => '',
        'seo.features.auto_create_meta' => false,
        'seo.hreflang.normalize' => true,
        'seo.hreflang.include_self' => false,
        'seo.hreflang.x_default' => null,
        'seo.sitemap.models' => [HreflangPolicyPage::class],
        'seo.sitemap.alternates' => true,
        'seo.sitemap.disk' => 'public',
        'seo.sitemap.path' => 'sitemap.xml',
    ]);
});

function hreflangPage(array $alternates, string $slug = 'pagina'): HreflangPolicyPage
{
    return HreflangPolicyPage::create([
        'title' => 'Una pagina',
        'slug' => $slug,
        'alternates_json' => json_encode($alternates),
    ]);
}

function hreflangCodes(MetadataAuditor $auditor, HreflangPolicyPage $page): array
{
    return array_map(fn ($issue) => $issue->code, $auditor->checkHreflang($page));
}

describe('rendering', function () {
    it('shares normalized aliases and visible invalid codes across every consumer', function () {
        config(['seo.llms_txt.alternates' => true, 'seo.llms_txt.sources' => [], 'seo.hreflang.include_self' => true, 'seo.hreflang.x_default' => 'he']);
        app()->setLocale('it');
        $page = hreflangPage([
            ['hreflang' => 'iw_IL', 'href' => 'http://localhost/he/page'],
            ['hreflang' => 'es-419', 'href' => 'http://localhost/es/page'],
            ['hreflang' => 'en__US', 'href' => 'http://localhost/en/page'],
        ]);
        $html = app(TagRenderer::class)->render($page->seoData());
        app(SitemapBuilder::class)->generate();
        $xml = Storage::disk('public')->get('sitemap.xml');
        $markdown = app(LlmsTxtBuilder::class)->build();
        foreach (['he-IL', 'es-419', 'en--US', 'it', 'x-default'] as $code) {
            expect($html)->toContain('hreflang="'.$code.'"')
                ->and($xml)->toContain('hreflang="'.$code.'"');
        }
        // llms uses the same policy, intentionally omitting self and x-default.
        expect($markdown)->toContain('[he-IL](http://localhost/he/page)', '[es-419](http://localhost/es/page)', '[en--US](http://localhost/en/page)')
            ->and($markdown)->not->toContain('[x-default]');
        $issue = collect(app(MetadataAuditor::class)->checkHreflang($page))->firstWhere('code', 'hreflang_invalid_code');
        expect($issue->context['codes'])->toBe(['es-419', 'en--US']);
    });

    it('audits served separators and whitespace when normalization is disabled', function () {
        config(['seo.hreflang.normalize' => false]);
        $page = hreflangPage([
            ['hreflang' => 'it_IT', 'href' => 'http://localhost/it/pagina'],
            ['hreflang' => ' en ', 'href' => 'http://localhost/en/page'],
        ]);
        expect(app(TagRenderer::class)->render($page->seoData()))->toContain('hreflang="it_IT"', 'hreflang=" en "');
        $issue = collect(app(MetadataAuditor::class)->checkHreflang($page))->firstWhere('code', 'hreflang_invalid_code');
        expect($issue->context['codes'])->toBe(['it_IT', ' en ']);
    });

    it('rewrites Laravel locales to BCP 47 in the link tags and the sitemap', function () {
        $page = hreflangPage([
            ['hreflang' => 'it_IT', 'href' => 'http://localhost/it/pagina'],
            ['hreflang' => 'en_US', 'href' => 'http://localhost/en/page'],
        ]);

        $html = app(TagRenderer::class)->render($page->seoData());

        expect($html)
            ->toContain('<link rel="alternate" hreflang="it-IT" href="http://localhost/it/pagina">')
            ->toContain('<link rel="alternate" hreflang="en-US" href="http://localhost/en/page">')
            ->and($html)->not->toContain('it_IT');

        app(SitemapBuilder::class)->generate();
        $xml = Storage::disk('public')->get('sitemap.xml');

        expect($xml)->toContain('hreflang="it-IT"')
            ->and($xml)->toContain('hreflang="en-US"')
            ->and($xml)->not->toContain('it_IT');
    });

    it('keeps codes verbatim when normalisation is off', function () {
        config(['seo.hreflang.normalize' => false]);

        $page = hreflangPage([['hreflang' => 'it_IT', 'href' => 'http://localhost/it/pagina']]);

        expect(app(TagRenderer::class)->render($page->seoData()))->toContain('hreflang="it_IT"');
    });

    it('adds the self-reference and the x-default per policy, in tags and sitemap alike', function () {
        config(['seo.hreflang.include_self' => true, 'seo.hreflang.x_default' => 'en']);
        app()->setLocale('it');

        $page = hreflangPage([['hreflang' => 'en', 'href' => 'http://localhost/en/page']]);

        $array = app(TagRenderer::class)->toArray($page->seoData());
        $links = collect($array['link'])->where('rel', 'alternate')->map(fn ($l) => $l['hreflang'].' '.$l['href'])->values()->all();

        expect($links)->toBe([
            'en http://localhost/en/page',
            'it http://localhost/it/pagina',
            'x-default http://localhost/en/page',
        ]);

        app(SitemapBuilder::class)->generate();
        $xml = Storage::disk('public')->get('sitemap.xml');

        expect($xml)->toContain('hreflang="it"')
            ->and($xml)->toContain('hreflang="x-default"');
    });

    it('adds nothing to a page without alternates', function () {
        config(['seo.hreflang.include_self' => true, 'seo.hreflang.x_default' => 'en']);

        $page = hreflangPage([]);
        $html = app(TagRenderer::class)->render($page->seoData());

        expect($html)->not->toContain('rel="alternate"');
    });

    it('applies the policy to a hand-built SEOData rendered through the facade', function () {
        config(['seo.hreflang.include_self' => true]);

        $data = new SEOData(
            title: 'Page',
            canonical: 'http://localhost/de/seite',
            locale: 'de_DE',
            alternates: [['hreflang' => 'en', 'href' => 'http://localhost/en/page']],
        );

        $html = app(TagRenderer::class)->render($data);

        expect($html)->toContain('<link rel="alternate" hreflang="de-DE" href="http://localhost/de/seite">');
    });
});

describe('audit', function () {
    it('emits nothing for a page without alternates', function () {
        expect(hreflangCodes(app(MetadataAuditor::class), hreflangPage([])))->toBe([]);
    });

    it('passes a clean, self-referencing list', function () {
        $page = hreflangPage([
            ['hreflang' => 'it', 'href' => 'http://localhost/it/pagina'],
            ['hreflang' => 'en', 'href' => 'http://localhost/en/page'],
            ['hreflang' => 'x-default', 'href' => 'http://localhost/en/page'],
        ]);

        expect(hreflangCodes(app(MetadataAuditor::class), $page))->toBe([]);
    });

    it('flags an invalid code, a duplicate and a missing self-reference', function () {
        $page = hreflangPage([
            ['hreflang' => 'en-UK', 'href' => 'http://localhost/uk/page'],
            ['hreflang' => 'fr', 'href' => 'http://localhost/fr/page'],
            ['hreflang' => 'FR', 'href' => 'http://localhost/fr/page-2'],
        ]);

        $issues = app(MetadataAuditor::class)->checkHreflang($page);
        $byCode = collect($issues)->keyBy('code');

        expect($byCode->keys()->all())->toBe(['hreflang_invalid_code', 'hreflang_duplicate_code', 'hreflang_missing_self'])
            ->and($byCode['hreflang_invalid_code']->context['codes'])->toBe(['en-UK'])
            ->and($byCode['hreflang_invalid_code']->message)->toContain('en-UK')
            ->and($byCode['hreflang_duplicate_code']->context['codes'])->toBe(['fr'])
            ->and($byCode['hreflang_missing_self']->context['canonical'])->toBe('http://localhost/it/pagina')
            ->and($byCode['hreflang_missing_self']->field)->toBe('alternates');
    });

    it('does not flag a missing self-reference once include_self supplies it', function () {
        config(['seo.hreflang.include_self' => true]);
        app()->setLocale('it');

        $page = hreflangPage([['hreflang' => 'en', 'href' => 'http://localhost/en/page']]);

        expect(hreflangCodes(app(MetadataAuditor::class), $page))->toBe([]);
    });

    it('is part of the full audit and speaks the app locale', function () {
        app()->setLocale('it');

        $page = hreflangPage([['hreflang' => 'jp', 'href' => 'http://localhost/ja/page']]);

        $issues = app(MetadataAuditor::class)->audit($page);
        $codes = array_map(fn ($issue) => $issue->code, $issues);
        $invalid = collect($issues)->firstWhere('code', 'hreflang_invalid_code');

        expect($codes)->toContain('hreflang_invalid_code', 'hreflang_missing_self')
            ->and($invalid->message)->toBe(__('seo::seo.audit.hreflang_invalid_code', ['codes' => 'jp']));
    });

    it('accepts an IDN canonical as valid', function () {
        $page = hreflangPage([]);
        $page->saveSEO(['canonical' => 'https://münchen.example/straße']);

        $codes = array_map(fn ($issue) => $issue->code, app(MetadataAuditor::class)->checkCanonicalConsistency($page->fresh()));

        expect($codes)->not->toContain('invalid_canonical')
            ->and($codes)->toContain('cross_domain_canonical');
    });

    it('still rejects a canonical that is not a URL', function () {
        $page = hreflangPage([]);
        $page->saveSEO(['canonical' => 'not a url']);

        $codes = array_map(fn ($issue) => $issue->code, app(MetadataAuditor::class)->checkCanonicalConsistency($page->fresh()));

        expect($codes)->toBe(['invalid_canonical']);
    });
});
