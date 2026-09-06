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
 * policy ("allow the AI-search and assistant crawlers, gate the ones that train
 * on you"):
 *
 * - {@see PURPOSE_TRAINING} — collects content to train models. Default: disallow.
 * - {@see PURPOSE_SEARCH} — indexes content to surface it in AI-search answers
 *   (the AI-search referral channel). Default: allow.
 * - {@see PURPOSE_ASSISTANT} — fetches a page in real time on a user's behalf
 *   inside a chat. Default: allow.
 *
 * ## Regional search engines (3.15)
 * A second, separate list — {@see searchEngines()} — holds the classic web
 * search crawlers that matter outside the Google/Bing world: Yandex (RU),
 * Baidu, Sogou and 360 (CN), Naver (KR), Seznam (CZ), Cốc Cốc (VN) and
 * DuckDuckGo. They are tagged {@see PURPOSE_SEARCH_ENGINE} (default: allow)
 * and take part in the robots.txt directives and per-bot overrides — an EU
 * shop that wants Baidu and Sogou off its bandwidth sets
 * `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']`.
 * {@see all()} and {@see match()} stay AI-only unless asked, so the Pro AI-bot
 * log and every "N AI crawlers" count are unchanged.
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

    /** Indexes content to surface it in AI-search answers. */
    public const PURPOSE_SEARCH = 'ai_search';

    /** Fetches a page in real time on a user's behalf inside a chat. */
    public const PURPOSE_ASSISTANT = 'ai_assistant';

    /** A classic web search index (Yandex, Baidu, Naver, …) — not an AI bot. */
    public const PURPOSE_SEARCH_ENGINE = 'search_engine';

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
     * The regional web search engines, same tuple shape as {@see CATALOG}.
     * The `agent` is the robots.txt token each operator documents (Yandex
     * asks for the bare `Yandex`, which covers all of its bots; Sogou's token
     * really does contain spaces).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    protected const SEARCH_ENGINES = [
        ['yandex', 'Yandex', 'Yandex', self::PURPOSE_SEARCH_ENGINE, true, 'https://yandex.com/support/webmaster/robot-workings/check-yandex-robots.html'],
        ['baiduspider', 'Baiduspider', 'Baidu', self::PURPOSE_SEARCH_ENGINE, true, 'https://help.baidu.com/question?prod_id=99&class=0&id=3001'],
        ['yeti', 'Yeti', 'Naver', self::PURPOSE_SEARCH_ENGINE, true, 'https://searchadvisor.naver.com/guide/seo-basic-robots'],
        ['seznambot', 'SeznamBot', 'Seznam', self::PURPOSE_SEARCH_ENGINE, true, 'https://o-seznam.cz/napoveda/vyhledavani/en/seznambot-crawler/'],
        ['sogou', 'Sogou web spider', 'Sogou', self::PURPOSE_SEARCH_ENGINE, true, 'https://www.sogou.com/docs/help/webmasters.htm'],
        ['360spider', '360Spider', 'Qihoo 360', self::PURPOSE_SEARCH_ENGINE, true, null],
        ['coccocbot', 'coccocbot-web', 'Cốc Cốc', self::PURPOSE_SEARCH_ENGINE, true, 'https://help.coccoc.com/en/search-engine/coccoc-robots'],
        ['duckduckbot', 'DuckDuckBot', 'DuckDuckGo', self::PURPOSE_SEARCH_ENGINE, true, 'https://duckduckgo.com/duckduckgo-help-pages/results/duckduckbot/'],
    ];

    /**
     * Memoised catalog of {@see AiCrawler} objects, keyed by id.
     *
     * @var array<string, AiCrawler>|null
     */
    protected ?array $crawlers = null;

    /**
     * Memoised search-engine crawlers, keyed by id.
     *
     * @var array<string, AiCrawler>|null
     */
    protected ?array $searchEngines = null;

    /**
     * The full catalog of known AI crawlers, keyed by id — plus, when asked,
     * the regional search engines. AI-only by default so every "N AI
     * crawlers" count and the Pro AI-bot log keep their meaning.
     *
     * @return array<string, AiCrawler>
     */
    public function all(bool $includeSearchEngines = false): array
    {
        $this->crawlers ??= $this->hydrate(self::CATALOG);

        return $includeSearchEngines
            ? $this->crawlers + $this->searchEngines()
            : $this->crawlers;
    }

    /**
     * The regional web search-engine crawlers, keyed by id.
     *
     * @return array<string, AiCrawler>
     */
    public function searchEngines(): array
    {
        return $this->searchEngines ??= $this->hydrate(self::SEARCH_ENGINES);
    }

    /**
     * Get a single crawler (AI or search engine) by its catalog id, or null
     * when unknown.
     */
    public function get(string $id): ?AiCrawler
    {
        return $this->all(true)[$id] ?? null;
    }

    /**
     * The crawlers tagged with a given purpose, search engines included.
     *
     * @return array<string, AiCrawler>
     */
    public function byPurpose(string $purpose): array
    {
        return array_filter($this->all(true), static fn (AiCrawler $c): bool => $c->purpose === $purpose);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>  $rows
     * @return array<string, AiCrawler>
     */
    protected function hydrate(array $rows): array
    {
        $crawlers = [];

        foreach ($rows as [$id, $agent, $operator, $purpose, $respects, $url]) {
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

        return $crawlers;
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
     * the general rules. In `'all'` mode every catalogued bot — the regional
     * search engines included — gets an explicit allow/disallow line, for an
     * auditable, fully-explicit file.
     *
     * @return array<int, array{crawler: AiCrawler, action: string}>
     */
    public function directives(): array
    {
        $mode = config('seo.ai_crawlers.list', 'blocked');
        $directives = [];

        foreach ($this->all(true) as $crawler) {
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
     * null for a browser or an unknown agent. Reused by the Pro AI-bot hit log,
     * which is why the regional search engines are only considered when asked.
     */
    public function match(?string $userAgent, bool $includeSearchEngines = false): ?AiCrawler
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $haystack = strtolower($userAgent);

        $candidates = $this->all($includeSearchEngines);

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
