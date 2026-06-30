<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Auditing\MetadataAuditor;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * A HasSEO model whose resolved schema graph is whatever we store on it, so the
 * AEO (answer-readiness) checks can be exercised against known JSON-LD.
 */
class AeoAuditPage extends Model
{
    use HasSEO;

    protected $table = 'aeo_audit_pages';

    protected $fillable = ['title', 'slug'];

    public $timestamps = true;

    public function getUrlForSEO(): string
    {
        return url("/aeo/{$this->slug}");
    }
}

beforeEach(function () {
    config(['seo.title_suffix' => '']);

    $schema = $this->app['db']->connection()->getSchemaBuilder();

    if (! $schema->hasTable('aeo_audit_pages')) {
        $schema->create('aeo_audit_pages', function ($table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('aeo_audit_pages');
});

function aeoAuditCodes(Model $model): array
{
    return collect(app(MetadataAuditor::class)->audit($model->fresh()))
        ->map(fn ($issue): string => $issue->code)
        ->all();
}

it('flags an article with no author and no date', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Post', 'slug' => 'post']);
    $page->saveSEO(['schema_jsonld' => ['@type' => 'BlogPosting', 'headline' => 'A Post']]);

    $codes = aeoAuditCodes($page);

    expect($codes)->toContain('aeo_missing_author')
        ->and($codes)->toContain('aeo_article_missing_date');
});

it('does not flag an article that has an author and a publish date', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Post', 'slug' => 'complete']);
    $page->saveSEO(['schema_jsonld' => [
        '@type' => 'Article',
        'headline' => 'A Complete Article',
        'author' => ['@type' => 'Person', 'name' => 'Ada Lovelace'],
        'datePublished' => '2026-06-30',
    ]]);

    $codes = aeoAuditCodes($page);

    expect($codes)->not->toContain('aeo_missing_author')
        ->and($codes)->not->toContain('aeo_article_missing_date');
});

it('accepts dateModified in place of datePublished', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Post', 'slug' => 'modified']);
    $page->saveSEO(['schema_jsonld' => [
        '@type' => 'Article',
        'author' => 'Jane Roe',
        'dateModified' => '2026-06-30',
    ]]);

    expect(aeoAuditCodes($page))->not->toContain('aeo_article_missing_date');
});

it('finds an article node inside an @graph', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Post', 'slug' => 'graph']);
    $page->saveSEO(['schema_jsonld' => [
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'WebSite', 'name' => 'Site'],
            ['@type' => 'NewsArticle', 'headline' => 'Breaking'],
        ],
    ]]);

    expect(aeoAuditCodes($page))->toContain('aeo_missing_author');
});

it('ignores non-article structured data', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Product', 'slug' => 'product']);
    $page->saveSEO(['schema_jsonld' => ['@type' => 'Product', 'name' => 'Widget']]);

    $codes = aeoAuditCodes($page);

    expect($codes)->not->toContain('aeo_missing_author')
        ->and($codes)->not->toContain('aeo_article_missing_date');
});

it('does not flag a page with no structured data at all', function () {
    $page = AeoAuditPage::query()->create(['title' => 'Plain', 'slug' => 'plain']);
    $page->saveSEO(['title' => 'Plain Page']);

    $codes = aeoAuditCodes($page);

    expect($codes)->not->toContain('aeo_missing_author')
        ->and($codes)->not->toContain('aeo_article_missing_date');
});
