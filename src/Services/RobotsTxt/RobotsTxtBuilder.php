<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\RobotsTxt;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\AiCrawlers\AiCrawler;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;
use Rankbeam\Seo\Services\IndexingGuard;

/**
 * Service for generating a managed robots.txt (and ai.txt) with AI-crawler
 * directives.
 *
 * robots.txt is the file the major AI crawlers actually read — OpenAI's GPTBot
 * /OAI-SearchBot, Anthropic's ClaudeBot, PerplexityBot and Google-Extended all
 * document robots.txt as the control surface. This builder turns the resolved
 * {@see AiCrawlerRegistry} policy ("allow the bots that cite you, gate the ones
 * that train on you") into directive blocks, then frames them with an optional
 * general `User-agent: *` section, a `Sitemap:` line, and a pointer at the
 * llms.txt content index — so the one file orients both classic and AI crawlers.
 *
 * It deliberately mirrors {@see \Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder}:
 * `build()` renders without writing, `generate()` writes to the configured disk,
 * and the route is opt-in (most apps ship a static public/robots.txt).
 *
 * ## Usage
 * ```php
 * $builder = app(RobotsTxtBuilder::class);
 *
 * // The full robots.txt
 * echo $builder->build();
 *
 * // Just the AI-crawler block, to paste into your own robots.txt
 * echo $builder->aiDirectives();
 *
 * // Write public/robots.txt
 * $builder->generate();
 * ```
 *
 * @see \Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry The catalog + policy
 */
class RobotsTxtBuilder
{
    /**
     * The shared catalog of AI crawlers + the resolved policy.
     */
    protected AiCrawlerRegistry $registry;

    /**
     * The non-production indexing guard. When active, the whole file collapses
     * to a disallow-all so a leaked non-production site is not crawled.
     */
    protected IndexingGuard $guard;

    public function __construct(?AiCrawlerRegistry $registry = null, ?IndexingGuard $guard = null)
    {
        $this->registry = $registry ?? new AiCrawlerRegistry();
        $this->guard = $guard ?? new IndexingGuard();
    }

    /**
     * The catalog of AI crawlers (shared with the Pro AI-bot hit log).
     */
    public function crawlers(): AiCrawlerRegistry
    {
        return $this->registry;
    }

    /**
     * Generate and write the robots.txt to the configured disk.
     */
    public function generate(): void
    {
        $this->getStorage()->put($this->getPath(), $this->build());
    }

    /**
     * Generate and write the ai.txt to the configured disk.
     */
    public function generateAiTxt(): void
    {
        $this->getStorage()->put($this->getAiTxtPath(), $this->buildAiTxt());
    }

    /**
     * Build the complete robots.txt without writing.
     */
    public function build(): string
    {
        // Non-production safety net: when the indexing guard is active, the AI
        // -crawler policy is irrelevant — the whole site must stay uncrawled, so
        // the file collapses to a disallow-all. This is the same signal that
        // forces noindex on every rendered page.
        if ($this->guard->active()) {
            return $this->disallowAllDocument('robots.txt');
        }

        $blocks = [];

        $blocks[] = "# robots.txt — managed by Rankbeam (AI crawler directives)";

        $general = $this->generalSection();

        if ($general !== null) {
            $blocks[] = $general;
        }

        // Content signals live in a `User-agent: *` group. When the general
        // section IS that default group they are folded into it (one clean
        // group, matching Cloudflare's own layout); otherwise they get a
        // dedicated group here. Null when the feature is off or no signal
        // resolves.
        $contentSignals = $this->contentSignalsSection();

        if ($contentSignals !== null) {
            $blocks[] = $contentSignals;
        }

        $ai = $this->aiDirectives();

        if ($ai !== '') {
            $blocks[] = $ai;
        }

        $footer = $this->footerSection();

        if ($footer !== null) {
            $blocks[] = $footer;
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Build the ai.txt — the same AI-crawler directives under an ai.txt header.
     *
     * ai.txt is a less-adopted convention some operators publish alongside
     * robots.txt; robots.txt remains the file the major crawlers honour. The
     * directive syntax is identical, so the two artifacts state one policy.
     */
    public function buildAiTxt(): string
    {
        if ($this->guard->active()) {
            return $this->disallowAllDocument('ai.txt');
        }

        $blocks = [];

        $blocks[] = "# ai.txt — managed by Rankbeam\n"
            . "# AI-crawler access policy. robots.txt is the primary control surface;\n"
            . "# this mirrors it for clients that read ai.txt.";

        $ai = $this->aiDirectives();

        if ($ai !== '') {
            $blocks[] = $ai;
        }

        $footer = $this->footerSection();

        if ($footer !== null) {
            $blocks[] = $footer;
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * The disallow-all document emitted while the indexing guard is active.
     *
     * A leaked non-production environment must stay entirely out of search
     * results, so the file is the unambiguous robots.txt idiom for "block
     * everything" — `User-agent: *` / `Disallow: /` — with a header explaining
     * why. The AI-crawler policy, Sitemap line, and llms.txt pointer are all
     * deliberately omitted: advertising a sitemap on a site you are blocking
     * would contradict the intent.
     *
     * @param string $artifact 'robots.txt' or 'ai.txt', for the header line
     */
    protected function disallowAllDocument(string $artifact): string
    {
        $environment = $this->guard->currentEnvironment();

        return implode("\n", [
            "# {$artifact} — managed by Rankbeam",
            "# Indexing guard ACTIVE: this app is running in the \"{$environment}\" environment,",
            '# which is not in seo.indexing_guard.allowed_environments. Every crawler is',
            '# disallowed so this non-production site stays out of search results.',
            '# https://rankbeam.dev/guide/indexing-guard',
            '',
            'User-agent: *',
            'Disallow: /',
        ]) . "\n";
    }

    /**
     * The AI-crawler directive block alone, ready to paste into an existing
     * robots.txt. Empty when no directive is emitted (e.g. an all-allow policy
     * in the default `blocked` list mode).
     */
    public function aiDirectives(): string
    {
        $directives = $this->registry->directives();

        if ($directives === []) {
            return '';
        }

        $lines = ['# --- AI crawlers (managed by Rankbeam) ---'];

        foreach ($directives as $directive) {
            /** @var AiCrawler $crawler */
            $crawler = $directive['crawler'];
            $disallow = $directive['action'] === AiCrawlerRegistry::ACTION_DISALLOW;

            $lines[] = '';
            $lines[] = $this->comment($crawler, $disallow);
            $lines[] = 'User-agent: ' . $crawler->agent;
            // An empty Disallow is the robots.txt idiom for "allow everything".
            $lines[] = $disallow ? 'Disallow: /' : 'Disallow:';
        }

        return implode("\n", $lines);
    }

    /**
     * The human-readable comment line above a bot's directive.
     */
    protected function comment(AiCrawler $crawler, bool $disallow): string
    {
        $purpose = $this->purposeLabel($crawler->purpose);
        $note = $disallow && ! $crawler->respectsRobots
            ? ' — advisory: this bot may not honour robots.txt'
            : '';

        return "# {$crawler->label} — {$crawler->operator} ({$purpose}){$note}";
    }

    /**
     * The optional general `User-agent: *` section.
     *
     * `ai_crawlers.general` accepts: true (a permissive default), a raw string
     * (your own general directives, emitted verbatim), or false (omit it).
     */
    protected function generalSection(): ?string
    {
        $general = config('seo.ai_crawlers.general', true);

        if ($general === false || $general === null) {
            return null;
        }

        if (is_string($general)) {
            return trim($general) === '' ? null : trim($general);
        }

        // The default permissive group. When content signals are enabled they
        // are folded in here — their canonical home is the `User-agent: *`
        // group — so an enabled file has a single, Cloudflare-shaped group
        // rather than a duplicate one.
        $lines = [];

        $signal = $this->contentSignalLine();

        if ($signal !== null) {
            $lines[] = $this->contentSignalComment();
        }

        $lines[] = 'User-agent: *';

        if ($signal !== null) {
            $lines[] = $signal;
        }

        $lines[] = 'Disallow:';

        return implode("\n", $lines);
    }

    /**
     * A standalone content-signals group, emitted ONLY when the general section
     * is not the default `User-agent: *` group to fold into — i.e. when
     * `ai_crawlers.general` is a verbatim string or false. In the default case
     * the signal is folded into {@see generalSection()} and this returns null,
     * so the `User-agent: *` group is never duplicated.
     *
     * Content signals are usage preferences (contentsignals.org, championed by
     * Cloudflare): they state how the content may be USED — search, ai-input,
     * ai-train — independently of the Allow/Disallow access rules, and are
     * advisory. Null when the feature is off or no signal resolves.
     */
    protected function contentSignalsSection(): ?string
    {
        if ($this->generalIsDefaultGroup()) {
            return null;
        }

        $signal = $this->contentSignalLine();

        if ($signal === null) {
            return null;
        }

        return implode("\n", [
            $this->contentSignalComment(),
            'User-agent: *',
            $signal,
        ]);
    }

    /**
     * Whether `ai_crawlers.general` resolves to the built-in permissive
     * `User-agent: *` group (rather than false/null = omitted, or a verbatim
     * string). This is exactly the case {@see generalSection()} folds the
     * content signal into.
     */
    protected function generalIsDefaultGroup(): bool
    {
        $general = config('seo.ai_crawlers.general', true);

        return $general !== false && $general !== null && ! is_string($general);
    }

    /**
     * The `Content-Signal:` line derived from the AI-crawler purpose policy, or
     * null when the feature is disabled or the policy expresses no preference.
     *
     * Config-gated on `ai_crawlers.content_signals` (OFF by default, so output
     * stays byte-identical until opted in). Each of the three spec signals maps
     * from one purpose in `ai_crawlers.policy`:
     *   search   ← ai_search      (allow → yes, disallow → no)
     *   ai-input ← ai_assistant   (real-time fetch feeding an AI model)
     *   ai-train ← ai_training
     * A purpose absent from `policy` emits NO pair — the spec's "no preference
     * expressed" (a blank signal), distinct from an explicit yes/no.
     *
     * Syntax verified against contentsignals.org and Cloudflare's Content
     * Signals Policy: one `Content-Signal:` directive, comma-separated
     * `name=value` pairs, values `yes`/`no`, inside a `User-agent:` group.
     */
    protected function contentSignalLine(): ?string
    {
        if (! config('seo.ai_crawlers.content_signals', false)) {
            return null;
        }

        /** @var array<string, mixed> $policy */
        $policy = (array) config('seo.ai_crawlers.policy', []);

        // Emitted in the spec's canonical order: search, ai-input, ai-train.
        $map = [
            'search' => AiCrawlerRegistry::PURPOSE_SEARCH,
            'ai-input' => AiCrawlerRegistry::PURPOSE_ASSISTANT,
            'ai-train' => AiCrawlerRegistry::PURPOSE_TRAINING,
        ];

        $pairs = [];

        foreach ($map as $signal => $purpose) {
            if (! array_key_exists($purpose, $policy)) {
                // No preference for this purpose → omit the signal (spec: blank).
                continue;
            }

            $value = $policy[$purpose] === AiCrawlerRegistry::ACTION_DISALLOW ? 'no' : 'yes';
            $pairs[] = $signal . '=' . $value;
        }

        if ($pairs === []) {
            return null;
        }

        return 'Content-Signal: ' . implode(', ', $pairs);
    }

    /**
     * The explanatory comment printed above a content-signals `User-agent: *`
     * group.
     */
    protected function contentSignalComment(): string
    {
        return "# Content signals (https://contentsignals.org) — how this content may be\n"
            . "# used, independent of the crawl-access rules above. Advisory: honoured by\n"
            . '# participating crawlers.';
    }

    /**
     * The trailing Sitemap line and llms.txt pointer.
     */
    protected function footerSection(): ?string
    {
        $lines = [];

        if (config('seo.ai_crawlers.include_sitemap', true)) {
            $sitemap = $this->sitemapUrl();

            if ($sitemap !== null) {
                $lines[] = 'Sitemap: ' . $sitemap;
            }
        }

        if (config('seo.ai_crawlers.include_llms_txt', true)) {
            $llms = $this->llmsTxtUrl();

            if ($llms !== null) {
                $lines[] = '# AI content index: ' . $llms;
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * The absolute sitemap URL for the `Sitemap:` line.
     *
     * Honours an explicit `ai_crawlers.sitemap_url`, else derives the package
     * sitemap route's URL from the configured route prefix.
     */
    protected function sitemapUrl(): ?string
    {
        $configured = config('seo.ai_crawlers.sitemap_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $prefix = trim((string) config('seo.routes.prefix', ''), '/');
        $path = ($prefix !== '' ? $prefix . '/' : '') . 'sitemap.xml';

        return url($path);
    }

    /**
     * The absolute llms.txt URL, or null when the pointer is not wanted.
     */
    protected function llmsTxtUrl(): ?string
    {
        $path = config('seo.llms_txt.path', 'llms.txt');
        $path = is_string($path) && $path !== '' ? $path : 'llms.txt';

        return url('/' . ltrim($path, '/'));
    }

    /**
     * Human label for a purpose constant.
     */
    protected function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            AiCrawlerRegistry::PURPOSE_TRAINING => 'AI training',
            AiCrawlerRegistry::PURPOSE_SEARCH => 'AI search',
            AiCrawlerRegistry::PURPOSE_ASSISTANT => 'AI assistant',
            default => $purpose,
        };
    }

    /**
     * The robots.txt path on the disk (relative to the disk root).
     */
    protected function getPath(): string
    {
        $path = config('seo.ai_crawlers.path', 'robots.txt');

        return is_string($path) && $path !== '' ? $path : 'robots.txt';
    }

    /**
     * The ai.txt path on the disk (relative to the disk root).
     */
    protected function getAiTxtPath(): string
    {
        $path = config('seo.ai_crawlers.ai_txt_path', 'ai.txt');

        return is_string($path) && $path !== '' ? $path : 'ai.txt';
    }

    /**
     * The storage disk the files are written to.
     */
    protected function getStorage(): FilesystemAdapter
    {
        $disk = config('seo.ai_crawlers.disk', config('seo.sitemap.disk', 'public'));

        return Storage::disk($disk);
    }

    /**
     * The public URL of the generated robots.txt.
     */
    public function getRobotsTxtUrl(): string
    {
        return url($this->getPath());
    }

    /**
     * Whether the robots.txt file exists on the configured disk.
     */
    public function exists(): bool
    {
        return $this->getStorage()->exists($this->getPath());
    }

    /**
     * Delete the generated robots.txt (and ai.txt) files.
     */
    public function delete(): void
    {
        $storage = $this->getStorage();

        foreach ([$this->getPath(), $this->getAiTxtPath()] as $path) {
            if ($storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }
}
