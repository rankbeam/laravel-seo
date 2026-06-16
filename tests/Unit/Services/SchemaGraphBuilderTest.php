<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\Schema\SchemaGraph;
use Rankbeam\Seo\Services\Schema\SchemaGraphBuilder;

/*
|--------------------------------------------------------------------------
| SchemaGraph::for() — fluent @id-linked graph composition (T11)
|--------------------------------------------------------------------------
|
| The composition layer on TOP of the existing SchemaGraph / BreadcrumbSchema
| primitives. These cover the no-database path (organization / website /
| webPage from a hand-built SEOData); the model-driven path (webPage from
| seoData(), breadcrumbFromAncestors) is exercised in the feature test.
|
*/

beforeEach(function () {
    config(['app.url' => 'https://example.com']);
    config(['seo.site_name' => 'Example Clinic']);
    config(['seo.schema.organization' => [
        'type' => ['Hospital', 'MedicalOrganization'],
        'name' => 'Example Clinic',
        'logo' => '/images/logo.svg',
    ]]);
    config(['seo.schema.website' => ['name' => 'Example Clinic']]);
});

it('returns a builder from SchemaGraph::for()', function () {
    expect(SchemaGraph::for(null))->toBeInstanceOf(SchemaGraphBuilder::class);
});

it('starts empty and stays fluent', function () {
    $builder = SchemaGraph::for(null);

    expect($builder->isEmpty())->toBeTrue()
        ->and($builder->organization())->toBe($builder)
        ->and($builder->isEmpty())->toBeFalse();
});

it('composes a cross-linked organization + website + webPage graph', function () {
    $seo = new SEOData(
        title: 'Chi Siamo',
        description: 'La nostra storia.',
        canonical: 'https://example.com/chi-siamo',
    );

    $nodes = SchemaGraph::for($seo)
        ->organization()
        ->website()
        ->webPage()
        ->toArray();

    expect($nodes)->toHaveCount(3);

    [$org, $site, $page] = $nodes;

    // Stable, deterministic @ids.
    expect($org['@id'])->toBe('https://example.com#organization')
        ->and($site['@id'])->toBe('https://example.com#website')
        ->and($page['@id'])->toBe('https://example.com/chi-siamo#webpage');

    // The graph is cross-linked by @id reference.
    expect($site['publisher'])->toBe(['@id' => 'https://example.com#organization'])
        ->and($page['isPartOf'])->toBe(['@id' => 'https://example.com#website'])
        ->and($page['about'])->toBe(['@id' => 'https://example.com#organization']);

    expect($page['@type'])->toBe('WebPage')
        ->and($page['name'])->toBe('Chi Siamo');
});

it('uses an explicitly supplied SEOData over the subject for webPage()', function () {
    $override = new SEOData(title: 'Override', canonical: 'https://example.com/override');

    $nodes = SchemaGraph::for(new SEOData(title: 'Subject'))
        ->webPage($override)
        ->toArray();

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['name'])->toBe('Override')
        ->and($nodes[0]['@id'])->toBe('https://example.com/override#webpage');
});

it('skips webPage() when there is no data to describe a page from', function () {
    $nodes = SchemaGraph::for(null)
        ->organization()
        ->webPage()
        ->toArray();

    // Only the organization node — webPage added nothing without a subject.
    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['@type'])->toBe(['Hospital', 'MedicalOrganization']);
});

it('skips an empty node passed to add()', function () {
    $builder = SchemaGraph::for(null)->add([]);

    expect($builder->isEmpty())->toBeTrue();
});

it('adds an arbitrary pre-built node verbatim via add()', function () {
    $faq = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [],
    ];

    $nodes = SchemaGraph::for(null)->add($faq)->toArray();

    expect($nodes)->toBe([$faq]);
});

it('exposes the underlying collection for script/json output', function () {
    $collection = SchemaGraph::for(new SEOData(canonical: 'https://example.com/x'))
        ->webPage()
        ->toCollection();

    expect($collection->count())->toBe(1)
        ->and($collection->toScript())->toContain('application/ld+json');
});
