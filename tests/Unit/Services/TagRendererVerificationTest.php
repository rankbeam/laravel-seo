<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\TagRenderer;

/*
|--------------------------------------------------------------------------
| Search-engine site-verification meta tags (M2)
|--------------------------------------------------------------------------
*/

function verificationRenderer(): TagRenderer
{
    return app(TagRenderer::class);
}

function verificationData(): SEOData
{
    return new SEOData(title: 'Home', description: 'Welcome', canonical: 'https://example.com/');
}

beforeEach(function () {
    config(['seo.verification' => null]);
});

it('emits nothing when no token is configured (byte-identical output)', function () {
    $html = verificationRenderer()->render(verificationData());
    $array = verificationRenderer()->toArray(verificationData());

    expect($html)->not->toContain('verification')
        ->and($html)->not->toContain('msvalidate')
        ->and(collect($array['meta'])->pluck('name')->filter()->all())->not->toContain('google-site-verification');
});

it('renders one meta tag per configured engine, with the engine-specific name', function () {
    config(['seo.verification' => [
        'google' => 'g-token',
        'bing' => 'b-token',
        'yandex' => 'y-token',
        'baidu' => 'bd-token',
        'naver' => 'n-token',
        'seznam' => 's-token',
        'pinterest' => 'p-token',
        'facebook' => 'f-token',
    ]]);

    $html = verificationRenderer()->render(verificationData());

    expect($html)
        ->toContain('<meta name="google-site-verification" content="g-token">')
        ->toContain('<meta name="msvalidate.01" content="b-token">')
        ->toContain('<meta name="yandex-verification" content="y-token">')
        ->toContain('<meta name="baidu-site-verification" content="bd-token">')
        ->toContain('<meta name="naver-site-verification" content="n-token">')
        ->toContain('<meta name="seznam-wmt" content="s-token">')
        ->toContain('<meta name="p:domain_verify" content="p-token">')
        ->toContain('<meta name="facebook-domain-verification" content="f-token">');
});

it('skips blank values and unknown keys, and accepts a list of tokens', function () {
    config(['seo.verification' => [
        'google' => ['one', '', '  two  '],
        'bing' => '',
        'yandex' => null,
        'altavista' => 'nope',
    ]]);

    $array = verificationRenderer()->toArray(verificationData());
    $tags = collect($array['meta'])->where('name', 'google-site-verification')->pluck('content')->values()->all();

    expect($tags)->toBe(['one', 'two'])
        ->and(collect($array['meta'])->pluck('name')->filter()->all())->not->toContain('msvalidate.01', 'yandex-verification');
});

it('escapes the token in HTML and keeps it raw in the array', function () {
    config(['seo.verification' => ['google' => 'a"b<c']]);

    expect(verificationRenderer()->render(verificationData()))->toContain('content="a&quot;b&lt;c"')
        ->and(collect(verificationRenderer()->toArray(verificationData())['meta'])->firstWhere('name', 'google-site-verification')['content'])->toBe('a"b<c');
});

it('gives each verification tag a stable Inertia head-key', function () {
    config(['seo.verification' => ['google' => ['one', 'two'], 'yandex' => 'y']]);

    $head = verificationRenderer()->toInertiaHead(verificationData());
    $keys = collect($head['meta'])->pluck('head-key')->all();

    expect($keys)->toContain('google-site-verification', 'google-site-verification:1', 'yandex-verification');
});
