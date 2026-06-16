<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| Rendering Contract — renderer-shape proof
|--------------------------------------------------------------------------
|
| These tests pin the output shape of TagRenderer against the Rendering
| Contract Spec (docs/contributing/rendering-contract.md). They are the
| fast, framework-free leg that runs on every push; the reference apps
| verify the same contract in a real browser/SSR.
|
| The assertions cover: exactly-one-title, the robots emit-only-when-
| deviating policy, canonical ≡ og:url parity, no empty tags, the OG /
| Twitter / hreflang / JSON-LD value rules, stable Inertia head-keys, and
| cross-renderer SEMANTIC parity (render() ≡ toArray() ≡ toInertiaHead()
| after normalization — never byte-for-byte).
|
*/

function renderer(): TagRenderer
{
    return new TagRenderer();
}

/**
 * Parse a rendered <head> fragment into a decoded, comparable structure.
 *
 * DOMDocument decodes entities back to their semantic values, so the
 * comparison is per-sink decoded parity, not raw bytes.
 *
 * @return array{title: ?string, meta: array<int, array{key: string, content: string}>, link: array<int, array{rel: ?string, hreflang: ?string, href: ?string}>, script: int}
 */
function parseHead(string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<!DOCTYPE html><html><head>' . $html . '</head></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $titleNodes = $dom->getElementsByTagName('title');
    $title = $titleNodes->length ? $titleNodes->item(0)->textContent : null;

    $meta = [];
    foreach ($dom->getElementsByTagName('meta') as $node) {
        /** @var DOMElement $node */
        $key = $node->getAttribute('name') ?: $node->getAttribute('property');
        $meta[] = ['key' => $key, 'content' => $node->getAttribute('content')];
    }

    $link = [];
    foreach ($dom->getElementsByTagName('link') as $node) {
        /** @var DOMElement $node */
        $link[] = [
            'rel' => $node->getAttribute('rel') ?: null,
            'hreflang' => $node->getAttribute('hreflang') ?: null,
            'href' => $node->getAttribute('href') ?: null,
        ];
    }

    return [
        'title' => $title,
        'meta' => $meta,
        'link' => $link,
        'script' => $dom->getElementsByTagName('script')->length,
    ];
}

/**
 * Reduce a parsed/array head to a sorted multiset of "key=content" meta
 * strings + sorted link strings, so two renderers can be compared for
 * SEMANTIC equality regardless of tag order.
 *
 * @param array<int, array{key: string, content: string}> $meta
 * @param array<int, array<string, mixed>> $link
 * @return array{meta: array<int, string>, link: array<int, string>}
 */
function semanticSet(array $meta, array $link): array
{
    $metaSet = array_map(fn ($m) => $m['key'] . '=' . $m['content'], $meta);
    sort($metaSet);

    $linkSet = array_map(
        fn ($l) => ($l['rel'] ?? '') . '|' . ($l['hreflang'] ?? '') . '|' . ($l['href'] ?? ''),
        $link
    );
    sort($linkSet);

    return ['meta' => $metaSet, 'link' => $linkSet];
}

/** A rich article fixture exercising every contract surface. */
function richSeo(): SEOData
{
    return new SEOData(
        title: 'How Canonical URLs Work | Acme',
        description: 'A practical guide to canonical tags.',
        canonical: 'https://acme.test/blog/canonical',
        robots: 'index,follow', // == site default → must be suppressed
        ogTitle: 'How Canonical URLs Work',
        ogDescription: 'A practical guide to canonical tags.',
        ogImage: 'https://acme.test/img/og.jpg',
        ogType: 'article',
        ogSiteName: 'Acme',
        twitterTitle: 'How Canonical URLs Work',
        twitterImage: 'https://acme.test/img/og.jpg',
        twitterSite: '@acme',
        publishedTime: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        author: 'Jane Doe',
        section: 'SEO',
        tags: ['canonical', 'seo'],
        schemaJsonld: ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'Canonical URLs'],
        locale: 'en-US',
        alternates: [
            ['hreflang' => 'en-US', 'href' => 'https://acme.test/blog/canonical'],
            ['hreflang' => 'fr-FR', 'href' => 'https://acme.test/fr/blog/canonical'],
        ],
    );
}

describe('title', function () {
    it('renders exactly one resolved title, unchanged by the renderer', function () {
        $html = renderer()->render(new SEOData(title: 'My Page | Site'));

        expect(substr_count($html, '<title>'))->toBe(1);
        expect(parseHead($html)['title'])->toBe('My Page | Site');
    });

    it('emits no title tag when there is no title', function () {
        $html = renderer()->render(new SEOData(description: 'Only a description'));

        expect($html)->not->toContain('<title>');
    });
});

describe('robots policy', function () {
    it('suppresses a robots directive equal to the site default', function () {
        $html = renderer()->render(new SEOData(title: 'T', robots: 'index,follow'));

        expect($html)->not->toContain('name="robots"');
    });

    it('suppresses robots entirely when none is resolved', function () {
        $html = renderer()->render(new SEOData(title: 'T'));

        expect($html)->not->toContain('name="robots"');
    });

    it('emits a deviating directive verbatim, formatting preserved', function () {
        $html = renderer()->render(new SEOData(title: 'T', robots: 'noindex, follow'));

        // Verbatim — the comparison is whitespace-insensitive but the
        // emitted value keeps the admin-entered spacing.
        expect($html)->toContain('<meta name="robots" content="noindex, follow">');
    });

    it('treats a default that only differs in whitespace as redundant', function () {
        // Computed indexable models resolve to "index, follow" (with a space);
        // it must still be recognised as the default and suppressed.
        $html = renderer()->render(new SEOData(title: 'T', robots: 'index, follow'));

        expect($html)->not->toContain('name="robots"');
    });

    it('emits the default when emit_default is enabled', function () {
        config(['seo.robots.emit_default' => true]);

        $html = renderer()->render(new SEOData(title: 'T', robots: 'index,follow'));

        expect($html)->toContain('<meta name="robots" content="index,follow">');
    });

    it('honours a non-default site default for suppression', function () {
        config(['seo.default_robots' => 'noindex,nofollow']);

        // A page matching the (non-default) site policy is suppressed…
        $suppressed = renderer()->render(new SEOData(title: 'T', robots: 'noindex,nofollow'));
        expect($suppressed)->not->toContain('name="robots"');

        // …while a page that deviates from it is emitted.
        $emitted = renderer()->render(new SEOData(title: 'T', robots: 'index,follow'));
        expect($emitted)->toContain('<meta name="robots" content="index,follow">');
    });
});

describe('canonical & og:url', function () {
    it('emits a canonical link', function () {
        $array = renderer()->toArray(new SEOData(title: 'T', canonical: 'https://acme.test/p'));

        expect($array['link'])->toContain(['rel' => 'canonical', 'href' => 'https://acme.test/p']);
    });

    it('keeps canonical and og:url at the same normalized URL (hard invariant)', function () {
        $array = renderer()->toArray(richSeo());

        $canonical = collect($array['link'])->firstWhere('rel', 'canonical')['href'];
        $ogUrl = collect($array['meta'])->firstWhere('property', 'og:url')['content'];

        expect($ogUrl)->toBe($canonical);
    });

    it('never emits an empty canonical tag', function () {
        // No canonical, no request URL available → no empty <link>.
        $renderer = new class extends TagRenderer {
            protected function getCurrentUrl(): string
            {
                return '';
            }
        };

        $array = $renderer->toArray(new SEOData(title: 'T'));
        $html = $renderer->render(new SEOData(title: 'T'));

        expect(collect($array['link'])->firstWhere('rel', 'canonical'))->toBeNull();
        expect($html)->not->toContain('rel="canonical"');
    });
});

describe('open graph', function () {
    it('emits the core og group with absolute url/image and mapped locale', function () {
        $meta = collect(renderer()->toArray(richSeo())['meta']);

        expect($meta->firstWhere('property', 'og:title')['content'])->toBe('How Canonical URLs Work');
        expect($meta->firstWhere('property', 'og:type')['content'])->toBe('article');
        expect($meta->firstWhere('property', 'og:image')['content'])->toBe('https://acme.test/img/og.jpg');
        expect($meta->firstWhere('property', 'og:site_name')['content'])->toBe('Acme');
        // en-US → en_US (underscore form for the OG spec).
        expect($meta->firstWhere('property', 'og:locale')['content'])->toBe('en_US');
    });

    it('emits article:* only for the article type', function () {
        $article = collect(renderer()->toArray(richSeo())['meta']);
        expect($article->firstWhere('property', 'article:published_time')['content'])
            ->toBe('2026-06-01T10:00:00+00:00');
        expect($article->firstWhere('property', 'article:author')['content'])->toBe('Jane Doe');
        expect($article->where('property', 'article:tag')->pluck('content')->all())
            ->toBe(['canonical', 'seo']);
    });

    it('never fabricates article:* on a non-article page', function () {
        $seo = new SEOData(
            title: 'T',
            ogType: 'website',
            publishedTime: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
            author: 'Jane',
        );

        $meta = collect(renderer()->toArray($seo)['meta']);

        expect($meta->firstWhere('property', 'article:published_time'))->toBeNull();
        expect($meta->firstWhere('property', 'article:author'))->toBeNull();
    });
});

describe('twitter card', function () {
    it('emits card/title/image and keeps site independent', function () {
        $meta = collect(renderer()->toArray(richSeo())['meta']);

        expect($meta->firstWhere('name', 'twitter:card')['content'])->toBe('summary_large_image');
        expect($meta->firstWhere('name', 'twitter:title')['content'])->toBe('How Canonical URLs Work');
        expect($meta->firstWhere('name', 'twitter:image')['content'])->toBe('https://acme.test/img/og.jpg');
        expect($meta->firstWhere('name', 'twitter:site')['content'])->toBe('@acme');
        // creator was never set → must be absent, not fabricated from site.
        expect($meta->firstWhere('name', 'twitter:creator'))->toBeNull();
    });
});

describe('hreflang alternates', function () {
    it('emits one absolute, unique alternate per locale', function () {
        $link = collect(renderer()->toArray(richSeo())['link']);
        $alternates = $link->where('rel', 'alternate');

        expect($alternates)->toHaveCount(2);
        expect($alternates->pluck('hreflang')->all())->toBe(['en-US', 'fr-FR']);
        expect($alternates->pluck('hreflang')->duplicates())->toBeEmpty();
        $alternates->each(fn ($a) => expect($a['href'])->toStartWith('https://'));
    });

    it('skips malformed alternate entries instead of throwing in toArray() and render()', function () {
        // SEOData::fromArray accepts an unvalidated alternates list; a non-array
        // entry or a missing hreflang/href must never throw a TypeError on
        // string-offset access (mirrors the sitemap builder's guard).
        $seo = new SEOData(
            title: 'T',
            alternates: [
                ['hreflang' => 'en', 'href' => 'https://acme.test/en'], // valid
                ['hreflang' => 'fr'],                                   // missing href
                ['href' => 'https://acme.test/de'],                     // missing hreflang
                ['hreflang' => '', 'href' => ''],                       // empty strings
                ['hreflang' => 'es', 'href' => 123],                    // non-string href
                'not-an-array',                                         // scalar entry
            ],
        );

        // toArray(): only the single well-formed alternate survives.
        $alternates = collect(renderer()->toArray($seo)['link'])->where('rel', 'alternate');

        expect($alternates)->toHaveCount(1)
            ->and($alternates->first()['hreflang'])->toBe('en')
            ->and($alternates->first()['href'])->toBe('https://acme.test/en');

        // render() (the HTML path) tolerates the same input and emits one link.
        $html = renderer()->render($seo);

        expect(substr_count($html, 'rel="alternate"'))->toBe(1)
            ->and($html)->toContain('hreflang="en"')
            ->and($html)->toContain('href="https://acme.test/en"');
    });
});

describe('json-ld', function () {
    it('appears in render() (tagged) and toArray(), but not toInertiaHead()', function () {
        $seo = richSeo();

        $html = renderer()->render($seo);
        expect($html)->toContain('data-seo-schema');
        expect($html)->toContain('data-seo-url="https://acme.test/blog/canonical"');

        $array = renderer()->toArray($seo);
        expect($array['script'])->toHaveCount(1);
        expect($array['script'][0]['type'])->toBe('application/ld+json');
        // Parseable round-trip.
        expect(json_decode($array['script'][0]['innerHTML'], true)['@type'])->toBe('Article');

        $inertia = renderer()->toInertiaHead($seo);
        expect($inertia)->not->toHaveKey('script');
    });

    it('emits no script tag when there is no schema', function () {
        $array = renderer()->toArray(new SEOData(title: 'T'));

        expect($array['script'])->toBe([]);
    });
});

describe('inertia head-keys', function () {
    it('stamps a stable head-key on every meta and link', function () {
        $inertia = renderer()->toInertiaHead(richSeo());

        foreach ($inertia['meta'] as $m) {
            expect($m)->toHaveKey('head-key');
            // Singleton key is name ?? property.
            if ($m['head-key'] !== '' && ! str_contains($m['head-key'], ':')) {
                expect($m['head-key'])->toBe($m['name'] ?? $m['property']);
            }
        }

        $canonical = collect($inertia['link'])->firstWhere('rel', 'canonical');
        expect($canonical['head-key'])->toBe('canonical');

        $alternates = collect($inertia['link'])->where('rel', 'alternate');
        expect($alternates->pluck('head-key')->all())->toBe(['alternate:en-US', 'alternate:fr-FR']);
    });

    it('disambiguates repeatable tags so head-keys stay unique', function () {
        $inertia = renderer()->toInertiaHead(richSeo());

        $keys = array_column($inertia['meta'], 'head-key');
        expect($keys)->toBe(array_unique($keys));

        // The two article:tag entries get distinct keys.
        $tagKeys = collect($inertia['meta'])->where('property', 'article:tag')->pluck('head-key')->all();
        expect($tagKeys)->toBe(['article:tag', 'article:tag:1']);
    });
});

describe('cross-renderer parity', function () {
    it('render() and toArray() carry the same semantic tag set', function () {
        $seo = richSeo();

        $fromHtml = parseHead(renderer()->render($seo));
        $array = renderer()->toArray($seo);

        // Normalize the array meta to the same {key, content} shape the
        // parsed HTML uses (key = name ?? property).
        $arrayMeta = array_map(
            fn ($m) => ['key' => $m['name'] ?? $m['property'], 'content' => $m['content']],
            $array['meta']
        );

        $htmlSet = semanticSet($fromHtml['meta'], $fromHtml['link']);
        $arraySet = semanticSet($arrayMeta, $array['link']);

        expect($htmlSet['meta'])->toBe($arraySet['meta']);
        expect($htmlSet['link'])->toBe($arraySet['link']);
        expect($fromHtml['title'])->toBe($array['title']);
    });

    it('toInertiaHead() matches toArray() once head-keys and the script are dropped', function () {
        $seo = richSeo();

        $array = renderer()->toArray($seo);
        $inertia = renderer()->toInertiaHead($seo);

        $stripKey = fn (array $rows) => array_map(function ($row) {
            unset($row['head-key']);

            return $row;
        }, $rows);

        expect($stripKey($inertia['meta']))->toBe($array['meta']);
        expect($stripKey($inertia['link']))->toBe($array['link']);
        expect($inertia['title'])->toBe($array['title']);
    });
});

describe('teardown to a bare page', function () {
    it('a metadata-light page emits no robots, description, or schema', function () {
        $array = renderer()->toArray(new SEOData(title: 'Bare', canonical: 'https://acme.test/bare'));
        $meta = collect($array['meta']);

        expect($meta->firstWhere('name', 'robots'))->toBeNull();
        expect($meta->firstWhere('name', 'description'))->toBeNull();
        expect($array['script'])->toBe([]);
        // The singletons that remain are genuinely defaulted, not stale.
        expect($meta->firstWhere('property', 'og:type')['content'])->toBe('website');
    });
});
