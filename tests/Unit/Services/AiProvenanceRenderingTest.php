<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\TagRenderer;

it('retains minimal origin information across all output formats without private evidence', function () {
    $data = new SEOData(title: 'AI title', aiProvenance: ['title' => [
        'origin' => 'ai', 'edited' => false, 'generation_id' => 'PRIVATE-ID', 'reviewer' => 'PRIVATE-USER',
    ]]);
    $renderer = app(TagRenderer::class);
    foreach ([$data->toArray(), $data->toFlatArray(), $renderer->toArray($data), $renderer->toInertiaHead($data)] as $output) {
        expect(json_encode($output))->toContain('origin')->not->toContain('PRIVATE-ID', 'PRIVATE-USER');
    }
    expect($renderer->render($data))->toContain('rankbeam:ai-origin')->not->toContain('PRIVATE-ID');
    expect($data->with('title', 'Edited title')->aiProvenance['title']['edited'])->toBeTrue();
    expect($data->with('title', null)->aiProvenance)->toBe([])
        ->and($data->with('title', '')->aiProvenance)->toBe([]);
});

it('does not label independent overrides or untouched ordinary metadata as AI output', function () {
    $data = new SEOData(title: 'Generated', aiProvenance: ['title' => ['origin' => 'ai']]);
    expect($data->merge(new SEOData(title: 'Independent'))->aiProvenance)->toBe([]);
    expect(app(TagRenderer::class)->render(new SEOData(title: 'Ordinary')))->not->toContain('rankbeam:ai-origin');
});

it('drops unrecognized fields and renders origin declarations with attribute escaping', function () {
    $data = SEOData::fromArray(['title' => 'Title', 'ai_provenance' => [
        'title' => ['origin' => 'ai', 'request' => '"><script>alert(1)</script>'],
        'secret' => ['origin' => 'ai'], 'description' => 'invalid',
    ]]);
    expect(array_keys($data->aiProvenance))->toBe(['title']);
    expect(app(TagRenderer::class)->render($data))->toContain('&quot;origin&quot;')->not->toContain('<script>alert');
});
