<?php

declare(strict_types=1);

use Rankbeam\Seo\AiCrawlers\AiCrawler;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;

/*
|--------------------------------------------------------------------------
| Regional search engines in the crawler catalog (M2)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config([
        'seo.ai_crawlers.enabled' => true,
        'seo.ai_crawlers.policy' => [
            'ai_training' => 'disallow',
            'ai_search' => 'allow',
            'ai_assistant' => 'allow',
        ],
        'seo.ai_crawlers.overrides' => [],
        'seo.ai_crawlers.list' => 'blocked',
        'seo.ai_crawlers.general' => true,
        'seo.ai_crawlers.include_sitemap' => false,
        'seo.ai_crawlers.include_llms_txt' => false,
        'seo.ai_crawlers.content_signals' => false,
        'seo.indexing_guard.enabled' => false,
    ]);
});

function crawlers(): AiCrawlerRegistry
{
    return app(AiCrawlerRegistry::class);
}

it('lists the regional search engines separately from the AI crawlers', function () {
    $engines = crawlers()->searchEngines();

    expect($engines)->toHaveKeys(['yandex', 'baiduspider', 'yeti', 'seznambot', 'sogou', '360spider', 'coccocbot', 'duckduckbot'])
        ->and($engines['yeti'])->toBeInstanceOf(AiCrawler::class)
        ->and($engines['yeti']->operator)->toBe('Naver')
        ->and($engines['yeti']->purpose)->toBe(AiCrawlerRegistry::PURPOSE_SEARCH_ENGINE)
        ->and($engines['sogou']->agent)->toBe('Sogou web spider');

    foreach ($engines as $engine) {
        expect($engine->purpose)->toBe(AiCrawlerRegistry::PURPOSE_SEARCH_ENGINE)
            ->and($engine->respectsRobots)->toBeTrue();
    }
});

it('keeps all() AI-only so every AI-crawler count is unchanged', function () {
    $ai = crawlers()->all();

    expect($ai)->toHaveCount(28)
        ->and($ai)->not->toHaveKey('yandex')
        ->and(crawlers()->all(true))->toHaveCount(36)
        ->and(crawlers()->all(true))->toHaveKey('yandex')
        ->and(crawlers()->byPurpose(AiCrawlerRegistry::PURPOSE_SEARCH_ENGINE))->toHaveCount(8)
        ->and(crawlers()->purposes())->toBe([AiCrawlerRegistry::PURPOSE_TRAINING, AiCrawlerRegistry::PURPOSE_SEARCH, AiCrawlerRegistry::PURPOSE_ASSISTANT]);
});

it('finds a search engine by id', function () {
    expect(crawlers()->get('baiduspider')?->operator)->toBe('Baidu')
        ->and(crawlers()->get('gptbot')?->operator)->toBe('OpenAI')
        ->and(crawlers()->get('nope'))->toBeNull();
});

it('matches a search-engine user agent only when asked', function () {
    $ua = 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)';

    expect(crawlers()->match($ua))->toBeNull()
        ->and(crawlers()->match($ua, true)?->id)->toBe('yandex')
        ->and(crawlers()->match('Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)', true)?->id)->toBe('baiduspider')
        ->and(crawlers()->match('Yeti/1.1 (Naver Corp.; +http://help.naver.com/robots/)', true)?->id)->toBe('yeti')
        ->and(crawlers()->match('Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)', true)?->id)->toBe('sogou')
        ->and(crawlers()->match('coccocbot-web/1.0 (+http://help.coccoc.com/searchengine)', true)?->id)->toBe('coccocbot')
        ->and(crawlers()->match('DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', true)?->id)->toBe('duckduckbot')
        // AI bots still match with the flag on.
        ->and(crawlers()->match('Mozilla/5.0 GPTBot/1.0', true)?->id)->toBe('gptbot');
});

it('allows search engines by default, so the blocked-mode robots.txt is byte-identical', function () {
    $robots = app(RobotsTxtBuilder::class)->build();

    expect(crawlers()->actionFor('yandex'))->toBe('allow')
        ->and(crawlers()->actionFor('baiduspider'))->toBe('allow')
        ->and($robots)->not->toContain('Yandex')
        ->and($robots)->not->toContain('Baiduspider');
});

it('honours a purpose policy and per-bot overrides for search engines', function () {
    config([
        'seo.ai_crawlers.policy' => ['ai_training' => 'disallow', 'search_engine' => 'disallow'],
        'seo.ai_crawlers.overrides' => ['duckduckbot' => 'allow'],
    ]);

    $robots = app(RobotsTxtBuilder::class)->build();

    expect(crawlers()->actionFor('yandex'))->toBe('disallow')
        ->and(crawlers()->actionFor('duckduckbot'))->toBe('allow')
        ->and($robots)->toContain("User-agent: Yandex\nDisallow: /")
        ->and($robots)->toContain("User-agent: Sogou web spider\nDisallow: /")
        ->and($robots)->not->toContain('DuckDuckBot')
        ->and($robots)->toContain('(search engine)');
});

it('lets a site keep two engines off its bandwidth with overrides alone', function () {
    config(['seo.ai_crawlers.overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']]);

    $robots = app(RobotsTxtBuilder::class)->build();

    expect($robots)->toContain("User-agent: Baiduspider\nDisallow: /")
        ->and($robots)->toContain("User-agent: Sogou web spider\nDisallow: /")
        ->and($robots)->not->toContain('Yandex');
});

it('gives every engine an explicit allow directive in "all" mode', function () {
    config(['seo.ai_crawlers.list' => 'all']);

    $robots = app(RobotsTxtBuilder::class)->build();

    foreach (crawlers()->searchEngines() as $engine) {
        // An empty Disallow is the robots.txt idiom for "allow everything".
        expect($robots)->toContain("User-agent: {$engine->agent}\nDisallow:\n")
            ->and($robots)->not->toContain("User-agent: {$engine->agent}\nDisallow: /");
    }
});

it('leaves the content-signal line to the three AI purposes', function () {
    config([
        'seo.ai_crawlers.content_signals' => true,
        'seo.ai_crawlers.policy' => ['ai_training' => 'disallow', 'ai_search' => 'allow', 'ai_assistant' => 'allow', 'search_engine' => 'disallow'],
    ]);

    $robots = app(RobotsTxtBuilder::class)->build();

    expect($robots)->toContain('Content-Signal: search=yes, ai-input=yes, ai-train=no')
        ->and($robots)->not->toContain('search_engine');
});
