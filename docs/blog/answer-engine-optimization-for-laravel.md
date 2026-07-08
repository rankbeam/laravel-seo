---
title: "Answer Engine Optimization for Laravel: winning the AI-search game"
description: "The concrete moves that make a Laravel app legible to AI answer engines — server-rendered content, a linked JSON-LD entity graph, llms.txt, per-bot crawler control, IndexNow, and a free answer-readiness audit."
---

# Answer Engine Optimization for Laravel: winning the AI-search game

## It happened to me: the engine answered, and it was wrong

A few days before I sat down to write this, someone asked Google's Gemini about
Rankbeam's API. It answered confidently — and invented it. Method names, a facade, an
artisan command: none of it real. Not because the docs were wrong, but because Gemini
**couldn't read them.** `docs.rankbeam.dev` sat behind an edge bot-rule that returned
**403 to AI fetchers**, and the managed `robots.txt` told the AI crawlers to stay out.
The engine had no ground truth, so it filled the vacuum with plausible fiction — and
handed it to a real user as fact.

That is the entire argument for this article, in one incident. In the classic search
game, being unreadable meant you didn't rank. In the answer-engine game, being unreadable
means you get **misquoted** — the engine answers anyway, just wrong, with your name on it.
So AEO starts one step earlier than any tactic below: **make sure the machine can read the
real thing.** (My fix was a two-parter — open the edge to the citation-seeking fetchers
while still gating the training crawlers, and publish an `llms.txt` + per-page Markdown so
there's a clean source of truth to read. Both are moves 3 and 4 below; the docs you're
reading now ship exactly that.)

---

For fifteen years "SEO" meant one thing for a Laravel app: render a correct `<title>`,
meta, Open Graph and JSON-LD, ship a sitemap, and hope Google ranks you. That game isn't
over — but a second one started, and most Laravel apps aren't playing it.

People increasingly get their answer *without* a click. They ask ChatGPT, Perplexity, or
Google's AI Overviews, and an engine synthesizes a response from pages it has read,
citing a handful of them. Gartner predicted in 2024 that traditional search engine volume
would drop **25% by 2026** as users shift queries to AI chatbots and virtual agents
([Gartner](https://www.gartner.com/en/newsroom/press-releases/2024-02-19-gartner-predicts-search-engine-volume-will-drop-25-percent-by-2026-due-to-ai-chatbots-and-other-virtual-agents))
— a 2024 forecast (and as of mid-2026 there's no clear public evidence the drop has
actually materialized), so weigh it as a projection, not a measured fact. And here's the part
that should interest you as a builder: being *cited* is not the same as *ranking*. An
Ahrefs study of 15,000 queries found only **12%** of pages cited by ChatGPT, Gemini and
Copilot rank in Google's top 10 for the same prompt — the vast majority of AI-cited pages
sit **outside** the classic top 10
([Ahrefs](https://ahrefs.com/blog/ai-search-overlap/)). Citation is a different, winnable
game — and it's won with structure, not backlinks.

This is **Answer Engine Optimization** (AEO, sometimes GEO — Generative Engine
Optimization). Here's what it actually means for a Laravel developer, and the concrete
moves that make your app legible to the engines doing the answering. I'll show how I wire
each one — I maintain an open-source SEO toolkit for Laravel
([Rankbeam](https://rankbeam.dev)) — but every move here is something you can do with or
without it.

## 1. Your content has to be in the HTML, server-side

An AI crawler is, mostly, a fast and impatient reader. If your content only appears after
a client-side render, many crawlers won't see it. This is the one place a lot of modern
Laravel apps quietly lose: a Vue/React SPA that paints the article after hydration is
invisible to a reader that doesn't run your JavaScript.

If you're on Blade or Livewire, you're already server-rendering. If you're on Inertia,
make sure the content and the structured data are in the **initial HTML response**, not
just the page props. The fix is boring and effective: render the important text and your
JSON-LD in the document the server returns.

```php
// Inertia root view — the JSON-LD is in the first byte the crawler reads
@foreach ($page['props']['seo']['schema'] ?? [] as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG) !!}</script>
@endforeach
```

Rankbeam resolves the same `SEOData` for Blade, Inertia and a JSON API, and emits the
JSON-LD server-side with `</script>`-safe escaping (`JSON_HEX_TAG` and friends, so a
hostile title can't break out of the script element). But the principle stands whatever
you use: **server-render the substance.**

## 2. Give the engine a clean entity graph (JSON-LD with linked `@id`)

AI engines extract *entities* — your organization, the author, the article, the product —
and the relationships between them. A flat pile of disconnected JSON-LD blobs is far less
useful than a small **graph** where the WebPage links to the Organization and the Article
links to its author by stable `@id`.

```json
{
  "@context": "https://schema.org",
  "@graph": [
    { "@type": "Organization", "@id": "https://acme.test/#org", "name": "Acme",
      "sameAs": ["https://www.linkedin.com/company/acme", "https://x.com/acme"] },
    { "@type": "WebPage", "@id": "https://acme.test/guide#page",
      "isPartOf": { "@id": "https://acme.test/#org" } },
    { "@type": "Article", "mainEntityOfPage": { "@id": "https://acme.test/guide#page" },
      "author": { "@type": "Person", "name": "Jane Dev" } }
  ]
}
```

Two things move the needle here in 2026: an **Organization** node with `sameAs` (your real
LinkedIn/X) and `knowsAbout`, so engines can resolve you to a known entity; and an
identifiable **author**. Entity disambiguation — "who is this, really?" — is widely
regarded as one of the highest-leverage structured-data investments for AI citation,
because the engine has to decide whom to trust before it decides whom to quote.

Rankbeam's free core builds exactly this graph: a `SchemaGraph` cross-links
Organization → WebSite → WebPage by stable `@id`, and typed builders (`ArticleSchema`,
`ProductSchema`, `BreadcrumbSchema`) hang richer nodes off it. The same value object feeds
your meta tags and your JSON-LD, so the two can never disagree on the same page.

## 3. Ship an `llms.txt`

`llms.txt` ([llmstxt.org](https://llmstxt.org)) aims to be to AI crawlers what `sitemap.xml`
is to search crawlers: a plain **Markdown index** of your key content at `/llms.txt`. Its
value is genuinely contested. Google has said flatly it doesn't use it — its AI-features
guidance (updated mid-2026) states Google Search doesn't consume `llms.txt` or any "AI text"
file — and Ahrefs, analysing 137k domains, found **~97% of published `llms.txt` files get
zero bot requests** ([Ahrefs](https://ahrefs.com/blog/llmstxt-study/)). So treat it as
**cheap insurance, not a proven signal**: it costs almost nothing because you already have
the data — it's the same content your sitemap covers — but don't sell it, to yourself or a
client, as the thing that gets you cited. The load-bearing moves are crawler *access* (§4)
and clean *structure* (§§1–2); `llms.txt` is a cheap extra on top.

```bash
php artisan seo:llms-txt        # writes public/llms.txt from your sitemap sources
```

```markdown
# Acme Blog
> Engineering notes from the Acme team.

## Posts
- [Shipping fast without breaking SEO](https://acme.test/posts/shipping): how we deploy 20×/day.
- [Our JSON-LD entity graph](https://acme.test/posts/schema)
```

Generate it from the **same source as your sitemap** so the two never disagree about
what's on your site, and regenerate it on a schedule. (Rankbeam's `seo:llms-txt` reuses
the sitemap registry directly — same sources, same noindex exclusions; if you're rolling
your own, drive it off whatever already feeds your sitemap.) The docs site you're reading
now does the same at build time: [docs.rankbeam.dev/llms.txt](https://docs.rankbeam.dev/llms.txt)
is generated from the sidebar, and every page is also served as raw Markdown at its own
URL with a `.md` suffix — the fix to the very incident this article opens with.

## 4. Decide which AI bots you actually want

AI crawlers are now a measurable slice of traffic — and the mix shifts fast, not uniformly
upward. Cloudflare reports OpenAI's **GPTBot grew ~305% year-over-year** in requests
(May 2024→May 2025), lifting its share of crawler traffic from **2.2% to 7.7%** (rank #9 to
#3) — while Anthropic's **ClaudeBot went the other way, down ~46%** over the same window
(from 11.7% to 5.4% share)
([Cloudflare](https://blog.cloudflare.com/from-googlebot-to-gptbot-whos-crawling-your-site-in-2025/)).
That churn is exactly the point: this isn't a policy you set once.
You may **want** the citation-seeking bots in (so you get cited) while gating the
training bots — and that's a real, per-bot decision, declared in `robots.txt` (and the
emerging `ai.txt`) for `GPTBot`, `ClaudeBot`, `PerplexityBot`, `Google-Extended` and
friends.

The trap is that you have to know each bot's *purpose* to make that call — and the catalog
shifts. Rankbeam ships a doc-verified catalog tagged by purpose and renders the policy for
you, with a sane default: **allow the bots that cite you** (`ai_search`, `ai_assistant`),
**disallow the ones that train on you** (`ai_training`).

```bash
php artisan seo:robots-txt --print   # preview the managed AI-crawler block
php artisan seo:robots-txt           # write public/robots.txt (--ai-txt also writes ai.txt)
```

Already maintain your own `robots.txt`? Grab just the managed block with
`SEO::robotsTxt()->aiDirectives()` and paste it in. One honest caveat the tool bakes in:
robots.txt is a request, not a fence — a few agents (`Bytespider`, `ChatGPT-User`) are
documented not to honour it, and those lines are marked *advisory* rather than implying a
block that won't hold. To see which bots actually hit you (and which to block at the edge),
the Pro AI-bot hit log records every AI-crawler fetch with no IP stored by default.

## 5. Index fast: IndexNow

When you publish, you want engines to know **now**, not on their next crawl.
[IndexNow](https://www.indexnow.org/faq) is a simple push protocol — submit a URL and it
propagates to Bing, Yandex, Naver, Seznam, Amazon and Yep in one call (not Google, which
keeps its own mechanism). It's broadly adopted and trivial to wire to your publish flow.

```php
// On publish/update — push the URL so Bing/Yandex pick it up immediately
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submitModel($post);   // queued by default
```

The pattern that matters: submit **on the model event**, queued, not on a nightly cron.
The whole point is latency. (Rankbeam Pro ships an `IndexNow` client + a
`SubmitsToIndexNow` trait that does exactly this on save; if you're hand-rolling it, it's
one authenticated POST and a key file at `/{key}.txt`.)

## 6. Write for a reader — including the machine one

AI engines favor content that's easy to parse: clear question-and-answer structure, real
headings, short sentences, definitional passages they can lift. This is also just good
writing. A **readability** signal (Flesch-Kincaid for English, or Gulpease for Italian)
won't rank you by itself, but it's a useful editorial check that your content is
extractable rather than a wall of subordinate clauses — the same loop Yoast and Rank Math
users will remember, now native to Laravel.

```bash
php artisan seo-pro:checklist "App\Models\Post" 42   # keyword + readability, pass/warn/fail
```

## 7. Then check the machine-readiness — for free

The first six moves are things you *do*. The seventh is the loop that keeps them honest.
Rankbeam's free core ships an **answer-readiness (AEO) check** inside the free
`seo:audit` — no Pro license, no queue, no network. It reads the resolved JSON-LD graph and,
**only when a page declares article-type structured data** (`Article`, `BlogPosting`,
`NewsArticle`, …), flags the two attribution signals AI answer engines lean on:

```bash
php artisan seo:audit
```

```text
⚠ aeo_missing_author         article structured data has no author entity
⚠ aeo_article_missing_date   article has no datePublished / dateModified
```

A page with no article schema is never flagged — the check stays quiet where AEO doesn't
apply. It's deliberately narrow (article attribution and recency, not a sweeping "is this
page AI-extractable?" verdict — that broader scoring is roadmap). Run it with `--strict`
in CI and a missing author fails the build before it ships.

## Putting it together

None of this is exotic. AEO for a Laravel app is mostly the disciplined version of things
you already half-do:

1. **Server-render** the substance (and your JSON-LD).
2. Ship a **clean entity graph** — Organization `sameAs`, identifiable author, linked `@id`s.
3. Generate an **`llms.txt`** from your sitemap sources.
4. **Decide your AI-bot policy** in `robots.txt`.
5. **Push new URLs** with IndexNow on publish.
6. Keep content **structured and readable**.
7. **Audit answer-readiness** in CI so the article signals never regress.

The encouraging part, again: citation is winnable independently of rank. A small Laravel
site with a clean entity graph and parseable content can be the thing an AI engine quotes,
even when it's nowhere near the top of a classic results page. The substrate is good
content plus clean structure plus *telling the crawlers it exists* — and Laravel, being
server-rendered and model-driven, is unusually well-placed to do all three.

```bash
composer require rankbeam/laravel-seo
```

The [quickstart](/guide/quickstart) gets you to a rendered head in five minutes; the free
[`seo:audit`](/guide/audit) — including the AEO answer-readiness checks — needs no license.
