<?php

declare(strict_types=1);

use Rankbeam\Seo\AiCrawlers\AiCrawler;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;

beforeEach(function () {
    // Default "allow the AI-search and assistant crawlers, gate the trainers" policy.
    config([
        'seo.ai_crawlers.policy' => [
            'ai_training' => 'disallow',
            'ai_search' => 'allow',
            'ai_assistant' => 'allow',
        ],
        'seo.ai_crawlers.overrides' => [],
        'seo.ai_crawlers.list' => 'blocked',
    ]);
});

describe('AiCrawlerRegistry catalog', function () {
    it('exposes the major AI crawlers as AiCrawler value objects keyed by id', function () {
        $all = app(AiCrawlerRegistry::class)->all();

        expect($all)->toHaveKeys([
            'gptbot', 'oai-searchbot', 'chatgpt-user',
            'claudebot', 'claude-searchbot', 'claude-user',
            'google-extended', 'perplexitybot', 'perplexity-user', 'ccbot',
        ])->and($all['gptbot'])->toBeInstanceOf(AiCrawler::class);
    });

    it('carries the correct robots.txt token, operator and purpose per bot', function () {
        $gptbot = app(AiCrawlerRegistry::class)->get('gptbot');

        expect($gptbot->agent)->toBe('GPTBot')
            ->and($gptbot->operator)->toBe('OpenAI')
            ->and($gptbot->purpose)->toBe(AiCrawlerRegistry::PURPOSE_TRAINING);
    });

    it('tags every catalogued bot with a known purpose and a non-empty token', function () {
        $registry = app(AiCrawlerRegistry::class);

        foreach ($registry->all() as $id => $crawler) {
            expect($crawler->agent)->not->toBe('')
                ->and($crawler->id)->toBe($id)
                ->and($crawler->purpose)->toBeIn($registry->purposes());
        }
    });

    it('records that some user-triggered bots do not honour robots.txt', function () {
        $registry = app(AiCrawlerRegistry::class);

        expect($registry->get('chatgpt-user')->respectsRobots)->toBeFalse()
            ->and($registry->get('perplexity-user')->respectsRobots)->toBeFalse()
            ->and($registry->get('bytespider')->respectsRobots)->toBeFalse()
            ->and($registry->get('gptbot')->respectsRobots)->toBeTrue();
    });

    it('returns null for an unknown id', function () {
        expect(app(AiCrawlerRegistry::class)->get('not-a-bot'))->toBeNull();
    });
});

describe('AiCrawlerRegistry policy resolution', function () {
    it('maps a bot purpose through the configured policy', function () {
        $registry = app(AiCrawlerRegistry::class);

        expect($registry->actionFor('gptbot'))->toBe('disallow')        // training
            ->and($registry->actionFor('perplexitybot'))->toBe('allow') // search
            ->and($registry->actionFor('chatgpt-user'))->toBe('allow'); // assistant
    });

    it('lets a per-bot override win over the purpose policy', function () {
        config(['seo.ai_crawlers.overrides' => ['gptbot' => 'allow', 'perplexitybot' => 'disallow']]);

        $registry = app(AiCrawlerRegistry::class);

        expect($registry->actionFor('gptbot'))->toBe('allow')
            ->and($registry->actionFor('perplexitybot'))->toBe('disallow');
    });

    it('treats any non-"disallow" value as allow, so a typo never silently blocks', function () {
        config(['seo.ai_crawlers.overrides' => ['gptbot' => 'nope']]);

        expect(app(AiCrawlerRegistry::class)->actionFor('gptbot'))->toBe('allow');
    });
});

describe('AiCrawlerRegistry directives()', function () {
    it('emits only disallowed bots in the default "blocked" mode', function () {
        $directives = app(AiCrawlerRegistry::class)->directives();

        $actions = array_column($directives, 'action');

        expect($actions)->not->toBeEmpty()
            ->and(array_unique($actions))->toBe(['disallow']);
    });

    it('emits every catalogued bot with an explicit action in "all" mode', function () {
        config(['seo.ai_crawlers.list' => 'all']);

        $registry = app(AiCrawlerRegistry::class);
        $directives = $registry->directives();

        expect(count($directives))->toBe(count($registry->all()))
            ->and(array_unique(array_column($directives, 'action')))
            ->toContain('allow', 'disallow');
    });
});

describe('AiCrawlerRegistry match()', function () {
    it('identifies a request user-agent as the right catalogued bot', function () {
        $registry = app(AiCrawlerRegistry::class);

        expect($registry->match('Mozilla/5.0 AppleWebKit/537.36 (compatible; GPTBot/1.1; +https://openai.com/gptbot)')?->id)
            ->toBe('gptbot')
            ->and($registry->match('Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)')?->id)
            ->toBe('claudebot')
            ->and($registry->match('Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)')?->id)
            ->toBe('perplexitybot');
    });

    it('returns null for a normal browser or an empty agent', function () {
        $registry = app(AiCrawlerRegistry::class);

        expect($registry->match('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0 Safari/537.36'))->toBeNull()
            ->and($registry->match(''))->toBeNull()
            ->and($registry->match(null))->toBeNull();
    });
});
