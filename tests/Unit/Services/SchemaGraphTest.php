<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

/*
|--------------------------------------------------------------------------
| Characterization: JSON-LD @id graph conventions
|--------------------------------------------------------------------------
|
| Ported from production SitewideSchema behavior:
| - Organization @id = {site_url}#organization
| - WebSite @id = {site_url}#website, publisher links to the Organization
| - WebPage @id = {canonical}#webpage, isPartOf links to the WebSite,
|   about links to the Organization
| - the site-wide fallback OG image is suppressed from primaryImageOfPage
|
*/

beforeEach(function () {
    config(['app.url' => 'https://example.com']);
    config(['seo.site_name' => 'Example Clinic']);
    config(['seo.schema.organization' => [
        'type' => ['Hospital', 'MedicalOrganization'],
        'name' => 'Example Clinic',
        'logo' => '/images/logo.svg',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Via Roma 1',
            'addressLocality' => 'Roma',
            'addressCountry' => 'IT',
        ],
    ]]);
    config(['seo.schema.website' => [
        'name' => 'Example Clinic',
    ]]);
});

function graph(): SchemaGraph
{
    return new SchemaGraph();
}

describe('@id conventions', function () {
    it('derives stable node ids from the site url', function () {
        expect(graph()->organizationId())->toBe('https://example.com#organization')
            ->and(graph()->webSiteId())->toBe('https://example.com#website');
    });

    it('strips a trailing slash from app.url before appending the fragment', function () {
        config(['app.url' => 'https://example.com/']);

        expect(graph()->organizationId())->toBe('https://example.com#organization');
    });

    it('derives the webpage id from the canonical url', function () {
        expect(graph()->webPageId('https://example.com/chi-siamo'))
            ->toBe('https://example.com/chi-siamo#webpage');
    });

    it('does not double a trailing # in the webpage id', function () {
        expect(graph()->webPageId('https://example.com/page#'))
            ->toBe('https://example.com/page#webpage');
    });
});

describe('organization node', function () {
    it('builds the organization from config with @id and custom type', function () {
        $org = graph()->organization();

        expect($org['@context'])->toBe('https://schema.org')
            ->and($org['@id'])->toBe('https://example.com#organization')
            ->and($org['@type'])->toBe(['Hospital', 'MedicalOrganization'])
            ->and($org['name'])->toBe('Example Clinic')
            ->and($org['url'])->toBe('https://example.com')
            ->and($org['logo'])->toBe('http://localhost/images/logo.svg')
            ->and($org['address']['streetAddress'])->toBe('Via Roma 1');
    });

    it('filters empty values so partial config stays valid', function () {
        config(['seo.schema.organization' => ['name' => 'Example Clinic']]);

        $org = graph()->organization();

        expect($org)->not->toHaveKeys(['logo', 'sameAs', 'address', 'contactPoint'])
            ->and($org['@type'])->toBe('Organization');
    });
});

describe('website node', function () {
    it('links the website to the organization via publisher @id', function () {
        $site = graph()->webSite();

        expect($site['@id'])->toBe('https://example.com#website')
            ->and($site['@type'])->toBe('WebSite')
            ->and($site['publisher'])->toBe(['@id' => 'https://example.com#organization']);
    });
});

describe('webpage node', function () {
    it('links the page into the graph via isPartOf and about', function () {
        $seo = new SEOData(
            title: 'Chi Siamo',
            description: 'La nostra storia.',
            canonical: 'https://example.com/chi-siamo',
            publishedTime: new DateTimeImmutable('2024-05-01T10:00:00+02:00'),
            modifiedTime: new DateTimeImmutable('2024-06-01T10:00:00+02:00'),
        );

        $page = graph()->webPage($seo);

        expect($page['@id'])->toBe('https://example.com/chi-siamo#webpage')
            ->and($page['@type'])->toBe('WebPage')
            ->and($page['name'])->toBe('Chi Siamo')
            ->and($page['description'])->toBe('La nostra storia.')
            ->and($page['url'])->toBe('https://example.com/chi-siamo')
            ->and($page['isPartOf'])->toBe(['@id' => 'https://example.com#website'])
            ->and($page['about'])->toBe(['@id' => 'https://example.com#organization'])
            ->and($page['datePublished'])->toBe('2024-05-01T10:00:00+02:00')
            ->and($page['dateModified'])->toBe('2024-06-01T10:00:00+02:00');
    });

    it('emits primaryImageOfPage for a page-specific image', function () {
        config(['seo.default_og_image' => '/images/og-default.jpg']);

        $seo = new SEOData(
            canonical: 'https://example.com/news/1',
            ogImage: 'https://example.com/storage/news-hero.jpg',
        );

        $page = graph()->webPage($seo);

        expect($page['primaryImageOfPage'])->toBe([
            '@type' => 'ImageObject',
            'url' => 'https://example.com/storage/news-hero.jpg',
        ]);
    });

    it('suppresses the site-wide fallback image from primaryImageOfPage', function () {
        config(['seo.default_og_image' => '/images/og-default.jpg']);

        $seo = new SEOData(
            canonical: 'https://example.com/news/1',
            ogImage: '/images/og-default.jpg',
        );

        $page = graph()->webPage($seo);

        expect($page)->not->toHaveKey('primaryImageOfPage');
    });
});
