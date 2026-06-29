<?php

declare(strict_types=1);

namespace Rankbeam\Seo\AiCrawlers;

/**
 * Curated catalog of known AI crawlers + the resolved allow/disallow policy.
 *
 * This is the single source of truth for "which AI bots exist, what they are
 * for, and what should we do about them" in the AI-era SEO surface. The core
 * {@see \Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder} renders the policy
 * as robots.txt directives; the Pro AI-bot hit log reuses {@see match()} to
 * label an incoming request's user-agent. Keeping one catalog means the file
 * that controls a bot and the panel that observes it never disagree.
 *
 * ## Purpose taxonomy
 * Every bot is tagged with what it primarily does, which drives the default
 * policy ("allow the bots that cite you, gate the ones that train on you"):
 *
 * - {@see PURPOSE_TRAINING} — collects content to train models. Default: disallow.
 * - {@see PURPOSE_SEARCH} — indexes content to cite / surface it in AI answers
 *   (the AI-search referral channel). Default: allow.
 * - {@see PURPOSE_ASSISTANT} — fetches a page in real time on a user's behalf
 *   inside a chat. Default: allow.
 *
 * ## Honesty note
 * Some "assistant" / user-triggered agents (ChatGPT-User, Perplexity-User) and
 * some training crawlers (Bytespider) are NOT documented to honour robots.txt —
 * a directive against them is advisory. That is recorded per-bot in
 * {@see AiCrawler::$respectsRobots} and surfaced in the generated file's
 * comments, rather than implying a block that won't hold.
 *
 * ## Configuration
 * ```php
 * // config/seo.php
 * 'ai_crawlers' => [
 *     'policy' => [
 *         'ai_training'  => 'disallow',
 *         'ai_search'    => 'allow',
 *         'ai_assistant' => 'allow',
 *     ],
 *     'overrides' => ['gptbot' => 'allow'],  // per-bot, keyed by catalog id
 * ],
 * ```
 *
 * @see \Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder The robots.txt renderer
 */
class AiCrawlerRegistry
{
    /** Collects content to train models. */
    public const PURPOSE_TRAINING = 'ai_training';

    /** Indexes content to cite / surface it in AI search answers. */
    public const PURPOSE_SEARCH = 'ai_search';

    /** Fetches a page in real time on a user's behalf inside a chat. */
    public const PURPOSE_ASSISTANT = 'ai_assistant';

    public const ACTION_ALLOW = 'allow';

    public const ACTION_DISALLOW = 'disallow';

    /**
     * The curated catalog, keyed by stable id.
     *
     * Tokens, operators and purposes are verified against the operators' own
     * documentation (OpenAI, Anthropic, Google, Perplexity, Apple, Common
     * Crawl, Meta, Amazon) and the community ai.robots.txt list. `respectsRobots`
     * is false only where the operator documents that robots.txt may not apply.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    protected const CATALOG = [
        // id, agent, operator, purpose, respectsRobots, url
        ['gptbot', 'GPTBot', 'OpenAI', self::PURPOSE_TRAINING, true, 'https://platform.openai.com/docs/bots'],
        ['oai-searchbot', 'OAI-SearchBot', 'OpenAI', self::PURPOSE_SEARCH, true, 'https://platform.openai.com/docs/bots'],
        ['chatgpt-user', 'ChatGPT-User', 'OpenAI', self::PURPOSE_ASSISTANT, false, 'https://platform.openai.com/docs/bots'],
        ['claudebot', 'ClaudeBot', 'Anthropic', self::PURPOSE_TRAINING, true, 'https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler'],
        ['claude-searchbot', 'Claude-SearchBot', 'Anthropic', self::PURPOSE_SEARCH, true, 'https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler'],
        ['claude-user', 'Claude-User', 'Anthropic', self::PURPOSE_ASSISTANT, true, 'https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler'],
        ['anthropic-ai', 'anthropic-ai', 'Anthropic', self::PURPOSE_TRAINING, true, 'https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler'],
        ['google-extended', 'Google-Extended', 'Google', self::PURPOSE_TRAINING, true, 'https://developers.google.com/search/docs/crawling-indexing/google-extended'],
        ['perplexitybot', 'PerplexityBot', 'Perplexity', self::PURPOSE_SEARCH, true, 'https://docs.perplexity.ai/docs/resources/perplexity-crawlers'],
        ['perplexity-user', 'Perplexity-User', 'Perplexity', self::PURPOSE_ASSISTANT, false, 'https://docs.perplexity.ai/docs/resources/perplexity-crawlers'],
        ['applebot-extended', 'Applebot-Extended', 'Apple', self::PURPOSE_TRAINING, true, null],
        ['ccbot', 'CCBot', 'Common Crawl', self::PURPOSE_TRAINING, true, 'https://commoncrawl.org/ccbot'],
        ['bytespider', 'Bytespider', 'ByteDance', self::PURPOSE_TRAINING, false, null],
        ['meta-externalagent', 'meta-externalagent', 'Meta', self::PURPOSE_TRAINING, true, 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/'],
        ['meta-externalfetcher', 'meta-externalfetcher', 'Meta', self::PURPOSE_ASSISTANT, false, 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/'],
        ['amazonbot', 'Amazonbot', 'Amazon', self::PURPOSE_SEARCH, true, 'https://developer.amazon.com/amazonbot'],
        ['mistralai-user', 'MistralAI-User', 'Mistral AI', self::PURPOSE_ASSISTANT, true, null],
        ['cohere-ai', 'cohere-ai', 'Cohere', self::PURPOSE_ASSISTANT, true, null],
        ['deepseekbot', 'DeepSeekBot', 'DeepSeek', self::PURPOSE_TRAINING, false, null],
        ['youbot', 'YouBot', 'You.com', self::PURPOSE_SEARCH, true, null],
        ['petalbot', 'PetalBot', 'Huawei', self::PURPOSE_SEARCH, true, null],
        ['ai2bot', 'AI2Bot', 'Ai2', self::PURPOSE_TRAINING, true, null],
        ['timpibot', 'Timpibot', 'Timpi', self::PURPOSE_TRAINING, true, null],
        ['omgili', 'omgili', 'Webz.io', self::PURPOSE_TRAINING, true, null],
        ['imagesiftbot', 'ImagesiftBot', 'ImageSift', self::PURPOSE_TRAINING, true, null],
        ['diffbot', 'Diffbot', 'Diffbot', self::PURPOSE_TRAINING, true, null],
        ['duckassistbot', 'DuckAssistBot', 'DuckDuckGo', self::PURPOSE_ASSISTANT, true, null],
        ['kagi-fetcher', 'kagi-fetcher', 'Kagi', self::PURPOSE_ASSISTANT, true, null],
    ];

    /**
     * Memoised catalog of {@see AiCrawler} objects, keyed by id.
     *
     * @var array<string, AiCrawler>|null
     */
    protected ?array $crawlers = null;

    /**
     * The full catalog of known AI crawlers, keyed by id.
     *
     * @return array<string, AiCrawler>
     */
    public function all(): array
    {
        if ($this->crawlers !== null) {
            return $this->crawlers;
        }

        $crawlers = [];

        foreach (self::CATALOG as [$id, $agent, $operator, $purpose, $respects, $url]) {
            $crawlers[$id] = new AiCrawler(
                id: $id,
                agent: $agent,
                label: $agent,
                operator: $operator,
                purpose: $purpose,
                respectsRobots: $respects,
                ua: strtolower($agent),
                url: $url,
            );
        }

        return $this->crawlers = $crawlers;
    }

    /**
     * Get a single crawler by its catalog id, or null when unknown.
     */
    public function get(string $id): ?AiCrawler
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * The crawlers tagged with a given purpose.
     *
     * @return array<string, AiCrawler>
     */
    public function byPurpose(string $purpose): array
    {
        return array_filter($this->all(), static fn (AiCrawler $c): bool => $c->purpose === $purpose);
    }

    /**
     * The three purpose constants this catalog tags bots with.
     *
     * @return array<int, string>
     */
    public function purposes(): array
    {
        return [self::PURPOSE_TRAINING, self::PURPOSE_SEARCH, self::PURPOSE_ASSISTANT];
    }

    /**
     * Resolve the allow/disallow action for a bot from config.
     *
     * A per-bot `ai_crawlers.overrides` entry (keyed by catalog id) wins; failing
     * that, the bot's purpose maps through `ai_crawlers.policy`. Anything other
     * than the literal string 'disallow' is treated as allow, so a typo never
     * silently blocks a bot.
     */
    public function actionFor(AiCrawler|string $crawler): string
    {
        $crawler = $crawler instanceof AiCrawler ? $crawler : $this->get($crawler);

        if ($crawler === null) {
            return self::ACTION_ALLOW;
        }

        $overrides = (array) config('seo.ai_crawlers.overrides', []);

        if (array_key_exists($crawler->id, $overrides)) {
            return $this->normalizeAction($overrides[$crawler->id]);
        }

        $policy = (array) config('seo.ai_crawlers.policy', []);

        return $this->normalizeAction($policy[$crawler->purpose] ?? self::ACTION_ALLOW);
    }

    /**
     * The ordered list of directives to render, honouring the `list` mode.
     *
     * In `'blocked'` mode (the default) only disallowed bots get a directive —
     * a lean robots.txt that gates the trainers and leaves everything else to
     * the general rules. In `'all'` mode every catalogued bot gets an explicit
     * allow/disallow line, for an auditable, fully-explicit file.
     *
     * @return array<int, array{crawler: AiCrawler, action: string}>
     */
    public function directives(): array
    {
        $mode = config('seo.ai_crawlers.list', 'blocked');
        $directives = [];

        foreach ($this->all() as $crawler) {
            $action = $this->actionFor($crawler);

            if ($mode !== 'all' && $action !== self::ACTION_DISALLOW) {
                continue;
            }

            $directives[] = ['crawler' => $crawler, 'action' => $action];
        }

        return $directives;
    }

    /**
     * Identify which catalogued bot a raw User-Agent header belongs to.
     *
     * Matches case-insensitively on the longest needle first, so a specific
     * token (e.g. `claude-searchbot`) is preferred over a broader one. Returns
     * null for a browser or an unknown agent. Reused by the Pro AI-bot hit log.
     */
    public function match(?string $userAgent): ?AiCrawler
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $haystack = strtolower($userAgent);

        $candidates = $this->all();

        // Longest needle first so 'claude-searchbot' beats a hypothetical 'claude'.
        uasort($candidates, static fn (AiCrawler $a, AiCrawler $b): int => strlen($b->userAgentNeedle()) <=> strlen($a->userAgentNeedle()));

        foreach ($candidates as $crawler) {
            if (str_contains($haystack, $crawler->userAgentNeedle())) {
                return $crawler;
            }
        }

        return null;
    }

    /**
     * Normalize a configured action to 'allow' or 'disallow'.
     */
    protected function normalizeAction(mixed $action): string
    {
        return $action === self::ACTION_DISALLOW ? self::ACTION_DISALLOW : self::ACTION_ALLOW;
    }
}
