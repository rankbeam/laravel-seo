# AI crawler control (robots.txt / ai.txt)

The AI answer engines crawl the web with named bots, and they read **robots.txt**
to decide what they may use. Rankbeam ships a curated catalog of those bots and
renders a managed `robots.txt` (and optional `ai.txt`) from a simple allow /
disallow policy — so you can **let the bots that cite you in, and gate the ones
that train on your content.**

This is a free, core feature. The Pro package adds the other half —
[observability: an AI-bot hit log](/pro/ai-bot-monitor) showing which AI crawlers
actually visited.

## The default policy

Every catalogued bot is tagged with what it primarily does:

| Purpose | What it does | Default |
| --- | --- | --- |
| `ai_search` | Indexes your pages to **cite** them in AI answers (the AI referral channel) | **allow** |
| `ai_assistant` | Fetches a page in real time on a **user's** behalf inside a chat | **allow** |
| `ai_training` | Collects content to **train** a model | **disallow** |

That mirrors how most publishers want to treat the AI era: stay visible in
ChatGPT search, Perplexity and friends, while opting out of being training data.
Change any of it in config.

## Quick start

Print the AI-crawler block to see what you'd publish:

```bash
php artisan seo:robots-txt --print
```

You have two ways to use it.

### Option A — paste the block into your existing robots.txt

If you already maintain `public/robots.txt`, grab just the managed block and
paste it in:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Option B — let Rankbeam manage the whole file

Generate a complete `robots.txt` (general section + AI directives + `Sitemap:`
line + a pointer to your [llms.txt](/guide/sitemaps)):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Schedule it so the file tracks your policy:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Or serve it dynamically — set `seo.ai_crawlers.route` to `true` and the package
answers `/robots.txt` from the live config (no generate step needed):

::: warning A static file wins
Most apps already ship `public/robots.txt`, which your web server serves before
Laravel ever routes the request. The dynamic route is **off by default** so it
can't silently shadow — or be shadowed by — a file you forgot about. Use the
route only when there is no static `robots.txt`.
:::

## Honesty about enforcement

robots.txt is a request, not a fence. Most of the catalogued bots are documented
to honour it, but a few user-triggered agents (`ChatGPT-User`, `Perplexity-User`)
and some training crawlers (`Bytespider`) are **not** — Rankbeam marks those
lines `advisory` rather than implying a block that won't hold. To actually stop a
non-compliant bot you need server- or edge-level blocking (firewall, WAF,
Cloudflare bot rules); the [Pro AI-bot hit log](/pro/ai-bot-monitor) tells you
which ones to worry about.

## Content signals (usage preferences)

`Allow` / `Disallow` control **access** — whether a bot may fetch the page.
[Content signals](https://contentsignals.org) (the standard championed by
Cloudflare) are the other axis: they state how the content, once fetched, may be
**used**. One `Content-Signal:` line in the `User-agent: *` group carries three
preferences:

| Signal | Derived from policy purpose | Meaning |
| --- | --- | --- |
| `search` | `ai_search` | Building a search index (links + short excerpts) |
| `ai-input` | `ai_assistant` | Feeding the page into an AI model in real time (RAG / grounding) |
| `ai-train` | `ai_training` | Training or fine-tuning an AI model |

It's **off by default** (the file stays byte-identical until you opt in). Turn it
on and Rankbeam derives the line straight from your existing `policy` — `allow`
becomes `yes`, `disallow` becomes `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Remove a purpose from `policy` entirely and its signal is **omitted** — the
spec's "no preference expressed", distinct from an explicit `yes`/`no`.

::: warning Advisory, like robots.txt itself
Content signals express a preference; they are **not** a technical control. A
crawler can ignore them. They sit alongside — not instead of — the access rules
above and any edge-level blocking.
:::

## Configuration

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose.
    'policy' => [
        'ai_training'  => 'disallow',
        'ai_search'    => 'allow',
        'ai_assistant' => 'allow',
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

Override a single bot regardless of its purpose with `overrides`, keyed by the
catalog **id** (e.g. `gptbot`, `claudebot`, `perplexitybot`, `google-extended`).

## The catalog

`SEO::aiCrawlers()` is the source of truth — the same catalog the Pro hit log
uses to identify visitors, so the file that controls a bot and the panel that
observes it never disagree.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

It covers the major operators — OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User),
Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended),
Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon,
ByteDance and more — each with its documented purpose and robots.txt token.
