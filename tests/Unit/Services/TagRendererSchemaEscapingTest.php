<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| JSON-LD script breakout regression tests
|--------------------------------------------------------------------------
|
| JSON-LD is emitted inside <script type="application/ld+json"> where HTML
| entity escaping does not apply. If a schema value contains "</script>",
| the browser terminates the script element at that point and parses the
| rest of the payload as HTML — a stored XSS vector. These tests pin the
| JSON_HEX_* escaping that prevents the breakout.
|
*/

function schemaWithHostileValues(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => 'Evil </script><script>alert(1)</script> Title',
        'description' => "Contains </ScRiPt > variants & 'quotes' and \"double quotes\"",
        'author' => [
            '@type' => 'Person',
            'name' => '<!--<script>-->',
        ],
    ];
}

it('does not allow </script> in schema values to break out of the JSON-LD script element', function () {
    $renderer = new TagRenderer();

    $seo = new SEOData(
        title: 'Evil </script><script>alert(1)</script> Title',
        schemaJsonld: schemaWithHostileValues(),
    );

    $html = $renderer->renderSchema($seo);

    expect($html)->toStartWith('<script type="application/ld+json">');
    expect($html)->toEndWith('</script>');

    // The JSON payload between the script tags must contain no raw angle
    // brackets at all, so no value can ever terminate the script element.
    $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));

    expect($json)->not->toContain('<');
    expect($json)->not->toContain('>');
    expect(stripos($json, '</script'))->toBeFalse();

    // The escaped payload must still decode back to the original values.
    $decoded = json_decode($json, true);

    expect($decoded)->toBe(schemaWithHostileValues());
});

it('does not allow </script> in a model title flowing into schema to break out via render()', function () {
    $renderer = new TagRenderer();

    $title = 'Pwned </script><img src=x onerror=alert(1)>';

    $seo = new SEOData(
        title: $title,
        schemaJsonld: [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
        ],
    );

    $html = $renderer->render($seo);

    // The full rendered head must contain exactly one closing script tag:
    // the legitimate one that closes the JSON-LD element.
    expect(substr_count(strtolower($html), '</script'))->toBe(1);
    expect($html)->not->toContain('<img src=x');
});

it('escapes angle brackets in toArray() JSON-LD innerHTML', function () {
    $renderer = new TagRenderer();

    $seo = new SEOData(schemaJsonld: schemaWithHostileValues());

    $array = $renderer->toArray($seo);

    expect($array['script'])->toHaveCount(1);
    expect($array['script'][0]['type'])->toBe('application/ld+json');

    $json = $array['script'][0]['innerHTML'];

    expect($json)->not->toContain('<');
    expect($json)->not->toContain('>');
    expect(json_decode($json, true))->toBe(schemaWithHostileValues());
});
