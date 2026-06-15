<?php

declare(strict_types=1);

use Rankbeam\Seo\Services\Schema\SchemaValidator;

/**
 * Schema arrays reach the validator straight from user input (and from
 * SEOData::fromArray, which does not validate shapes). A malformed structure —
 * a string `offers`, a scalar `sameAs`, a non-string URL, a scalar breadcrumb
 * item, a string `acceptedAnswer` — must produce a validation error, never a
 * PHP TypeError. These tests pin that "normalize and skip / report" contract.
 */
function schemaValidator(): SchemaValidator
{
    return new SchemaValidator();
}

it('reports invalid instead of throwing on a scalar (string) offers', function () {
    $result = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Widget',
        'image' => 'https://acme.test/widget.png',
        'offers' => '19.99', // malformed: a bare price string, not an Offer object
    ]);

    expect($result->isValid)->toBeFalse()
        ->and(collect($result->errors)->pluck('field'))->toContain('offers');
});

it('does not throw on a scalar (string) sameAs in Organization', function () {
    $result = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Acme',
        'url' => 'https://acme.test',
        'logo' => 'https://acme.test/logo.png',
        'sameAs' => 'not-a-valid-url', // scalar, not a list — foreach would have thrown
    ]);

    expect($result->isValid)->toBeFalse()
        ->and(collect($result->errors)->pluck('field'))->toContain('sameAs[0]');
});

it('does not throw on a non-string url inside sameAs', function () {
    $result = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Acme',
        'url' => 'https://acme.test',
        'logo' => 'https://acme.test/logo.png',
        'sameAs' => [
            'https://twitter.com/acme', // valid
            ['nested' => 'array'],      // non-string → validateUrl(string) would have thrown
            42,                          // non-string scalar
        ],
    ]);

    expect($result->isValid)->toBeFalse();

    $fields = collect($result->errors)->pluck('field');

    expect($fields)->toContain('sameAs[1]')
        ->and($fields)->toContain('sameAs[2]')
        ->and($fields)->not->toContain('sameAs[0]'); // the valid one is accepted
});

it('does not throw on a scalar item in a breadcrumb list', function () {
    $result = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://acme.test'],
            'not-an-object', // scalar entry → string-offset access would have thrown
        ],
    ]);

    expect($result->isValid)->toBeFalse()
        ->and(collect($result->errors)->pluck('field'))->toContain('itemListElement[1]');
});

it('does not throw on a string acceptedAnswer in an FAQ', function () {
    $result = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => 'What is it?',
                'acceptedAnswer' => 'just a string', // not an object with .text
            ],
        ],
    ]);

    expect($result->isValid)->toBeFalse()
        ->and(collect($result->errors)->pluck('field'))
        ->toContain('mainEntity[0].acceptedAnswer.text');
});

it('validates a well-formed Product/Organization without errors (no regression)', function () {
    $product = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Widget',
        'image' => 'https://acme.test/widget.png',
        'offers' => [
            'price' => '19.99',
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
        ],
    ]);

    $org = schemaValidator()->validate([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Acme',
        'url' => 'https://acme.test',
        'logo' => 'https://acme.test/logo.png',
        'sameAs' => ['https://twitter.com/acme', 'https://github.com/acme'],
    ]);

    expect($product->isValid)->toBeTrue()
        ->and($org->isValid)->toBeTrue();
});
