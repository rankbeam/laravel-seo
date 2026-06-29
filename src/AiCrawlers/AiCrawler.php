<?php

declare(strict_types=1);

namespace Rankbeam\Seo\AiCrawlers;

/**
 * One known AI crawler / user-agent in the curated catalog.
 *
 * An immutable value object describing a single bot the AI-era SEO surface
 * cares about. The same object feeds two consumers:
 *
 * - the core {@see \Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder}, which
 *   turns the resolved allow/disallow policy into robots.txt directives, and
 * - the Pro AI-bot hit log, which identifies an incoming request's user-agent
 *   by matching {@see AiCrawler::$ua} against {@see AiCrawlerRegistry::match()}.
 *
 * Keeping one catalog means the file that controls a bot and the panel that
 * observes it never disagree about which bots exist or what they are for.
 */
final class AiCrawler
{
    /**
     * @param  string  $id  Stable catalog key (kebab-case), used in config overrides.
     * @param  string  $agent  The robots.txt `User-agent` token, in the operator's canonical case.
     * @param  string  $label  Human-readable name for UIs and comments.
     * @param  string  $operator  The company/operator running the crawler.
     * @param  string  $purpose  One of {@see AiCrawlerRegistry}'s PURPOSE_* values.
     * @param  bool  $respectsRobots  Whether the bot is documented to honour robots.txt. When
     *                                false, a robots.txt directive against it is advisory only.
     * @param  string  $ua  Lower-case substring used to identify the bot in a raw User-Agent header.
     * @param  string|null  $url  The operator's documentation URL, when one is published.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $agent,
        public readonly string $label,
        public readonly string $operator,
        public readonly string $purpose,
        public readonly bool $respectsRobots = true,
        public readonly string $ua = '',
        public readonly ?string $url = null,
    ) {}

    /**
     * The substring used to match this bot in a request User-Agent header,
     * defaulting to the lower-cased robots.txt token when none was given.
     */
    public function userAgentNeedle(): string
    {
        return $this->ua !== '' ? $this->ua : strtolower($this->agent);
    }
}
