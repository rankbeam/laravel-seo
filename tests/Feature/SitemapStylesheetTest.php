<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Sitemap\SitemapBuilder;

/*
|--------------------------------------------------------------------------
| Styled sitemap XSL
|--------------------------------------------------------------------------
|
| The package references an XSL stylesheet from every generated sitemap (via
| an <?xml-stylesheet?> instruction) and serves that stylesheet from its own
| /sitemap.xsl route, so a browser renders sitemaps as a readable, branded
| page. Search engines ignore the instruction. These tests cover: the route
| is served, the index and child sitemaps reference it, it can be disabled or
| pointed elsewhere, and — critically — the stylesheet cannot be turned into an
| XML/HTML-injection vector by hostile URL content (research §3.5).
|
*/

beforeEach(function () {
    Storage::fake('public');

    config([
        'seo.sitemap.disk' => 'public',
        'seo.sitemap.path' => 'sitemap.xml',
        'seo.sitemap.models' => [],
        'seo.sitemap.static_urls' => [],
        'seo.sitemap.stylesheet.enabled' => true,
        'seo.sitemap.stylesheet.url' => null,
    ]);

    SEO::sitemaps()->flush();
});

/** Absolute path to the shipped stylesheet (tests/Feature -> package root). */
function stylesheetPath(): string
{
    return dirname(__DIR__, 2).'/resources/xsl/sitemap.xsl';
}

// ---------------------------------------------------------------------------
// Route serving
// ---------------------------------------------------------------------------

it('serves the XSL stylesheet over HTTP with an XSL content type', function () {
    $response = $this->get('/sitemap.xsl');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xsl; charset=UTF-8')
        ->assertHeader('X-Robots-Tag', 'noindex');

    // Symfony canonicalises the directive order, so assert on the directives
    // rather than a fixed string.
    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=86400');

    expect($response->getContent())
        ->toContain('<xsl:stylesheet')
        ->toContain('match="/s:urlset"')
        ->toContain('match="/s:sitemapindex"');
});

it('registers the stylesheet route by default', function () {
    expect(Route::has('seo.sitemap.stylesheet'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Sitemaps reference the stylesheet
// ---------------------------------------------------------------------------

it('references the stylesheet from a single urlset sitemap', function () {
    // static_urls with no models/sources yields a single <urlset> (not an index).
    config(['seo.sitemap.static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ]]);

    app(SitemapBuilder::class)->generate();

    $xml = Storage::disk('public')->get('sitemap.xml');

    expect($xml)
        ->toContain('<urlset')
        ->toContain('<?xml-stylesheet type="text/xsl"')
        ->toContain('sitemap.xsl');
});

it('references the stylesheet even from an empty sitemap', function () {
    // No models, no registered sources, no static URLs -> an empty <urlset>.
    app(SitemapBuilder::class)->generate();

    $xml = Storage::disk('public')->get('sitemap.xml');

    expect($xml)
        ->toContain('<urlset')
        ->toContain('<?xml-stylesheet type="text/xsl"');
});

it('references the stylesheet from both the index and its child sitemaps', function () {
    SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

    app(SitemapBuilder::class)->generate();

    $index = Storage::disk('public')->get('sitemap.xml');
    $child = Storage::disk('public')->get('sitemap-pages.xml');

    // Index (<sitemapindex>) carries the instruction...
    expect($index)
        ->toContain('<sitemapindex')
        ->toContain('<?xml-stylesheet type="text/xsl"')
        ->toContain('sitemap.xsl');

    // ...and so does the child (<urlset>).
    expect($child)
        ->toContain('<urlset')
        ->toContain('<?xml-stylesheet type="text/xsl"')
        ->toContain('sitemap.xsl');
});

it('points the instruction at the package /sitemap.xsl route by default', function () {
    config(['seo.sitemap.static_urls' => [['url' => '/']]]);

    app(SitemapBuilder::class)->generate();

    $xml = Storage::disk('public')->get('sitemap.xml');

    expect($xml)->toContain('href="'.route('seo.sitemap.stylesheet').'"');
});

// ---------------------------------------------------------------------------
// Disabling / overriding
// ---------------------------------------------------------------------------

it('omits the instruction when the styled sitemap is disabled', function () {
    config(['seo.sitemap.stylesheet.enabled' => false]);
    config(['seo.sitemap.static_urls' => [['url' => '/']]]);

    app(SitemapBuilder::class)->generate();

    $xml = Storage::disk('public')->get('sitemap.xml');

    expect($xml)
        ->toContain('<urlset')
        ->not->toContain('xml-stylesheet');
});

it('honours an explicit stylesheet.url override (for self-hosted / CDN copies)', function () {
    config(['seo.sitemap.stylesheet.url' => 'https://cdn.example.com/assets/sitemap.xsl']);
    config(['seo.sitemap.static_urls' => [['url' => '/']]]);

    app(SitemapBuilder::class)->generate();

    $xml = Storage::disk('public')->get('sitemap.xml');

    expect($xml)->toContain('href="https://cdn.example.com/assets/sitemap.xsl"');
});

// ---------------------------------------------------------------------------
// Escaping — structural (always runs; needs only ext-dom)
// ---------------------------------------------------------------------------

it('never disables output escaping and emits values through value-of', function () {
    $xsl = file_get_contents(stylesheetPath());

    // A single occurrence would let hostile URL content pass through unescaped.
    expect(substr_count($xsl, 'disable-output-escaping'))->toBe(0);
    expect($xsl)->toContain('<xsl:value-of');

    // The file itself is well-formed XML.
    $dom = new DOMDocument();
    expect(@$dom->load(stylesheetPath()))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Escaping — real transform (runs wherever ext-xsl is present, e.g. CI)
// ---------------------------------------------------------------------------

it('escapes hostile URL content so no script/img element can materialise', function () {
    // A well-formed sitemap whose loc values, once parsed, contain live-markup
    // payloads: one non-http loc (rendered as text) and one http loc carrying an
    // attribute-breakout attempt (rendered as an <a href>).
    $sitemapXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://ok.test/?x=%22&gt;&lt;img src=x onerror=alert(1)&gt;</loc>
    <lastmod>2026-07-10</lastmod>
  </url>
  <url>
    <loc>ftp://evil.test/&lt;script&gt;alert(1)&lt;/script&gt;</loc>
  </url>
</urlset>
XML;

    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($sitemapXml);

    $xslDoc = new DOMDocument();
    $xslDoc->load(stylesheetPath());

    $proc = new XSLTProcessor();
    $proc->importStylesheet($xslDoc);
    $out = $proc->transformToXml($xmlDoc);

    expect($out)->toBeString()->not->toBeEmpty();

    // Definitive check: parse the rendered HTML and confirm NO injected
    // executable elements materialised. The stylesheet emits no <script>/<img>
    // of its own, so any that appear would be injection.
    $result = new DOMDocument();
    @$result->loadHTML($out);

    expect($result->getElementsByTagName('script')->length)->toBe(0);
    expect($result->getElementsByTagName('img')->length)->toBe(0);

    // And the hostile text was rendered (escaped), not silently dropped.
    expect($out)->toContain('&lt;script&gt;');
})->skip(fn () => ! extension_loaded('xsl'), 'ext-xsl not loaded — HTML-escaping transform check skipped.');
