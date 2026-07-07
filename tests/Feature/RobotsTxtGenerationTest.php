<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;

beforeEach(function () {
    Storage::fake('public');

    config([
        'seo.ai_crawlers.enabled' => true,
        'seo.ai_crawlers.route' => false,
        'seo.ai_crawlers.disk' => 'public',
        'seo.ai_crawlers.path' => 'robots.txt',
        'seo.ai_crawlers.ai_txt_path' => 'ai.txt',
        'seo.ai_crawlers.policy' => [
            'ai_training' => 'disallow',
            'ai_search' => 'allow',
            'ai_assistant' => 'allow',
        ],
        'seo.ai_crawlers.overrides' => [],
        'seo.ai_crawlers.list' => 'blocked',
        'seo.ai_crawlers.general' => true,
        'seo.ai_crawlers.include_sitemap' => true,
        'seo.ai_crawlers.sitemap_url' => null,
        'seo.ai_crawlers.include_llms_txt' => true,
    ]);
});

describe('RobotsTxtBuilder output shape', function () {
    it('blocks training crawlers and leaves citation crawlers unlisted in blocked mode', function () {
        $robots = app(RobotsTxtBuilder::class)->build();

        // GPTBot (training) is disallowed; PerplexityBot (search) is allowed and
        // therefore omitted from the lean "blocked" file.
        expect($robots)->toContain("User-agent: GPTBot")
            ->and($robots)->toContain('Disallow: /')
            ->and($robots)->not->toContain('User-agent: PerplexityBot');
    });

    it('frames the directives with a general section, a Sitemap line and an llms.txt pointer', function () {
        $robots = app(RobotsTxtBuilder::class)->build();

        expect($robots)->toContain("User-agent: *")
            ->and($robots)->toContain('Sitemap: ' . url('/sitemap.xml'))
            ->and($robots)->toContain('# AI content index: ' . url('/llms.txt'));
    });

    it('flags bots that do not honour robots.txt as advisory', function () {
        $robots = app(RobotsTxtBuilder::class)->build();

        // Bytespider (training, does not respect robots.txt) is disallowed but
        // marked advisory so the file does not imply a block that will not hold.
        expect($robots)->toContain('Bytespider')
            ->and($robots)->toContain('advisory');
    });

    it('emits an explicit allow/disallow for every bot in "all" mode', function () {
        config(['seo.ai_crawlers.list' => 'all']);

        $robots = app(RobotsTxtBuilder::class)->build();

        // An allowed bot now appears with an empty Disallow (allow-all idiom).
        expect($robots)->toContain('User-agent: PerplexityBot')
            ->and($robots)->toContain('User-agent: GPTBot');
    });

    it('honours a per-bot override', function () {
        config(['seo.ai_crawlers.overrides' => ['gptbot' => 'allow']]);

        // GPTBot is now allowed, so it drops out of the blocked-only file.
        expect(app(RobotsTxtBuilder::class)->build())->not->toContain('User-agent: GPTBot');
    });

    it('exposes the AI block alone for pasting into an existing robots.txt', function () {
        $block = app(RobotsTxtBuilder::class)->aiDirectives();

        expect($block)->toStartWith('# --- AI crawlers')
            ->and($block)->toContain('User-agent: GPTBot')
            ->and($block)->not->toContain('User-agent: *'); // no general section
    });

    it('omits the general section and footer when configured off', function () {
        config([
            'seo.ai_crawlers.general' => false,
            'seo.ai_crawlers.include_sitemap' => false,
            'seo.ai_crawlers.include_llms_txt' => false,
        ]);

        $robots = app(RobotsTxtBuilder::class)->build();

        expect($robots)->not->toContain('User-agent: *')
            ->and($robots)->not->toContain('Sitemap:')
            ->and($robots)->not->toContain('AI content index');
    });

    it('prepends a custom general section verbatim', function () {
        config(['seo.ai_crawlers.general' => "User-agent: *\nDisallow: /admin"]);

        expect(app(RobotsTxtBuilder::class)->build())->toContain("User-agent: *\nDisallow: /admin");
    });
});

describe('RobotsTxtBuilder ai.txt', function () {
    it('renders the same directives under an ai.txt header', function () {
        $aiTxt = app(RobotsTxtBuilder::class)->buildAiTxt();

        expect($aiTxt)->toContain('# ai.txt')
            ->and($aiTxt)->toContain('User-agent: GPTBot');
    });
});

describe('RobotsTxtBuilder file generation', function () {
    it('writes robots.txt to the configured disk and path', function () {
        app(RobotsTxtBuilder::class)->generate();

        Storage::disk('public')->assertExists('robots.txt');
        expect(Storage::disk('public')->get('robots.txt'))->toContain('User-agent: GPTBot');
    });

    it('writes ai.txt and reports existence/deletion', function () {
        $builder = app(RobotsTxtBuilder::class);

        $builder->generate();
        $builder->generateAiTxt();

        expect($builder->exists())->toBeTrue();
        Storage::disk('public')->assertExists('ai.txt');

        $builder->delete();

        expect($builder->exists())->toBeFalse();
        Storage::disk('public')->assertMissing('ai.txt');
    });
});

describe('seo:robots-txt command', function () {
    it('writes robots.txt to the configured disk', function () {
        $exit = Artisan::call('seo:robots-txt');

        expect($exit)->toBe(0)
            ->and(Artisan::output())->toContain('generated successfully');

        Storage::disk('public')->assertExists('robots.txt');
    });

    it('refuses to run when disabled', function () {
        config(['seo.ai_crawlers.enabled' => false]);

        $exit = Artisan::call('seo:robots-txt');

        expect($exit)->toBe(1)
            ->and(Artisan::output())->toContain('disabled');

        Storage::disk('public')->assertMissing('robots.txt');
    });

    it('prints to stdout without writing on --print', function () {
        $exit = Artisan::call('seo:robots-txt', ['--print' => true]);

        expect($exit)->toBe(0)
            ->and(Artisan::output())->toContain('User-agent: GPTBot');

        Storage::disk('public')->assertMissing('robots.txt');
    });

    it('writes to a custom path with --output', function () {
        $exit = Artisan::call('seo:robots-txt', ['--output' => 'static/robots.txt']);

        expect($exit)->toBe(0);

        Storage::disk('public')->assertExists('static/robots.txt');
        Storage::disk('public')->assertMissing('robots.txt');
    });

    it('generates ai.txt with --ai-txt', function () {
        $exit = Artisan::call('seo:robots-txt', ['--ai-txt' => true]);

        expect($exit)->toBe(0);

        Storage::disk('public')->assertExists('ai.txt');
        Storage::disk('public')->assertMissing('robots.txt');
    });
});

describe('SEO facade accessors', function () {
    it('resolves the builder and the registry via the facade', function () {
        expect(SEO::robotsTxt())->toBeInstanceOf(RobotsTxtBuilder::class)
            ->and(SEO::aiCrawlers())->toBeInstanceOf(AiCrawlerRegistry::class);
    });

    it('shares the registry singleton between the builder and the facade', function () {
        expect(app(RobotsTxtBuilder::class)->crawlers())
            ->toBe(app(AiCrawlerRegistry::class))
            ->toBe(SEO::aiCrawlers());
    });
});

describe('/robots.txt route gating', function () {
    it('is not registered by default (apps usually ship a static robots.txt)', function () {
        expect(Route::has('seo.robots-txt.index'))->toBeFalse();
    });
});

describe('Content signals (contentsignals.org)', function () {
    it('emits no Content-Signal line by default (byte-identical until opted in)', function () {
        expect(app(RobotsTxtBuilder::class)->build())->not->toContain('Content-Signal');
    });

    it('folds the Content-Signal into the default User-agent: * group when enabled', function () {
        config(['seo.ai_crawlers.content_signals' => true]);

        $robots = app(RobotsTxtBuilder::class)->build();

        // Default policy: ai_search=allow, ai_assistant=allow, ai_training=disallow
        // → search=yes, ai-input=yes, ai-train=no, in the spec's canonical order.
        expect($robots)->toContain('Content-Signal: search=yes, ai-input=yes, ai-train=no')
            ->and($robots)->toContain('# Content signals (https://contentsignals.org)')
            // One User-agent: * group, not two.
            ->and(substr_count($robots, 'User-agent: *'))->toBe(1);
    });

    it('maps a disallowed purpose to no and a removed purpose to an omitted signal', function () {
        config([
            'seo.ai_crawlers.content_signals' => true,
            // ai_assistant removed entirely → the ai-input signal is omitted
            // (the spec's "no preference"); ai_search flipped to disallow → no.
            'seo.ai_crawlers.policy' => [
                'ai_training' => 'disallow',
                'ai_search' => 'disallow',
            ],
        ]);

        $robots = app(RobotsTxtBuilder::class)->build();

        expect($robots)->toContain('Content-Signal: search=no, ai-train=no')
            ->and($robots)->not->toContain('ai-input');
    });

    it('emits a standalone content-signals group when general is a verbatim string', function () {
        config([
            'seo.ai_crawlers.content_signals' => true,
            'seo.ai_crawlers.general' => "User-agent: SomeBot\nDisallow: /private",
        ]);

        $robots = app(RobotsTxtBuilder::class)->build();

        // The verbatim general string is preserved, AND the signal still gets a
        // User-agent: * group of its own to live in.
        expect($robots)->toContain('User-agent: SomeBot')
            ->and($robots)->toContain('Disallow: /private')
            ->and($robots)->toContain('User-agent: *')
            ->and($robots)->toContain('Content-Signal: search=yes, ai-input=yes, ai-train=no');
    });

    it('still emits a content-signals group when the general section is off', function () {
        config([
            'seo.ai_crawlers.content_signals' => true,
            'seo.ai_crawlers.general' => false,
        ]);

        $robots = app(RobotsTxtBuilder::class)->build();

        expect($robots)->toContain('User-agent: *')
            ->and($robots)->toContain('Content-Signal: search=yes, ai-input=yes, ai-train=no');
    });
});
