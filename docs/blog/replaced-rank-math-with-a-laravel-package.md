---
title: "We replaced Rank Math with a Laravel package"
description: "A switching story: moving a content site off WordPress (Rank Math / Yoast) — or off ralphjsmit/laravel-seo — to rankbeam/laravel-seo, with a real importer, an operations engine, and audits as a cutover gate."
---

# We replaced Rank Math with a Laravel package

We moved a content site off WordPress last quarter, and the part everyone
warned me about — SEO — turned out to be the easiest. Not because SEO got
simpler, but because the metadata we'd hand-tuned in Rank Math over years came
across in one `artisan` command, and what we landed on in Laravel does more
than the box we left behind.

This post is the switching story, honestly told. It's for two kinds of people:
the ones leaving WordPress (Rank Math or Yoast) for a Laravel app, and the ones
already on Laravel using [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo)
who are starting to feel its edges. Both moves land in the same place —
[`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) (MIT core) plus
its commercial Pro engine — and both have a real importer, so you don't retype a
year of work.

## The thing you actually lose leaving WordPress

It isn't the meta tags. Any Laravel app can render a `<title>` and an
`og:image`. What you lose is the **editor loop**: the SEO box under the content
with the live 60/160 counters, the Google-snippet preview, and — the
Yoast/Rank Math signature — the **readability score** sitting next to the
keyword check. That feedback-while-you-type is what made non-technical editors
actually fill the fields.

So when we evaluated the move, "can it render tags" was table stakes. The real
question was whether we could rebuild the *operations* — the audits, the
redirects after a slug change, the 404s that pile up after launch, the
readability loop — without bolting five libraries together.

## Ten minutes: install alongside, import, verify

The importer is the reason this wasn't a weekend. You install Rankbeam next to
your old setup, import, check, and only then decommission. Nothing is destructive
until you say so.

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Then point a database connection at the WordPress DB (in `config/database.php`)
and run the importer. WordPress posts and pages usually map to different Laravel
models, so you run it once per content type. **Preview first** — `--dry-run`
writes nothing:

```bash
# Rank Math, reading the live WordPress database
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Looks right? Drop the flag and import for real
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post"
```

What this actually does, from the [migration docs](/guide/migrate-from-wordpress):
it walks the `wp_posts` / `wp_postmeta` tables, maps Rank Math's keys explicitly
(`rank_math_title`, `rank_math_description`, `rank_math_canonical_url`, the
OpenGraph and Twitter overrides, the focus keyword) onto Rankbeam's `seo_meta`
columns, matches each post to your model by slug, and resolves the template
tokens it can derive (`%title%`, `%sitename%`, `%sep%`) while stripping the ones
it can't — so you never inherit a raw `%%token%%` string. It's **idempotent** and,
by default, only ever *fills* empty fields — it will not clobber anything you've
already set. (Coming from Yoast? Same command, `yoast` instead of `rank-math`.
No live DB? `seo:import-from wordpress-csv --file=…` takes a spreadsheet export.)

Two honest details the docs are upfront about, and you should read before a
cutover:

- **Canonicals import verbatim.** If a page pinned its canonical to the *old*
  domain, it imports still pointing there — the importer never rewrites the host.
  Review imported canonicals after a cross-domain move (or clear them and let the
  resolver self-canonicalise).
- **Rank Math redirects** don't go straight into the database — a free importer
  won't write the Pro redirects table. Instead `--redirects-csv=` emits a CSV you
  import into Pro, where every row is validated against loops and unsafe targets.

Then verify before you trust it:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

`--strict` exits non-zero on any issue, so it doubles as a cutover gate in CI.

## On the Laravel side: leaving the tag renderer

If you're already on Laravel, the incumbent is `ralphjsmit/laravel-seo` — north
of 500k installs, genuinely good, and the reason a one-command importer for it
exists in the first place. The honest framing isn't that it's bad; it's that
it's a **tag renderer**, and at some point you outgrow rendering tags.

The model side barely changes. You expose where a model lives, once:

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

```blade
<head>
    @seo($post)
</head>
```

`getUrlForSEO()` feeds the canonical, `og:url`, and the sitemap entry from one
source of truth. Under the hood the `SEOResolver` merges six layers — config →
global defaults → model-type defaults → route defaults → computed model values →
explicit `seo_meta` — and null never overwrites a lower layer, so nothing renders
empty. It serializes the same resolved data for Inertia/API (`SEO::forInertia($post)`)
as it renders into Blade.

The import is the same shape as the WordPress one:

```bash
php artisan seo:import-from ralphjsmit --dry-run   # preview
php artisan seo:import-from ralphjsmit             # then for real
```

It reads ralphjsmit's `seo` table, **re-resolves each morph row to the live
model** (so the relation stays correct under your current morph map), maps the
fields explicitly, and reports anything it truncated or couldn't home. Then you
swap one `use` statement on your models, run `seo:audit`, and drop the old table.

## What "operations engine" actually means

Here's the part that made the switch worth it rather than lateral. The free MIT
core already does more than render — and the Pro engine is where the WordPress
plugin features actually live again.

**In the free core (v3.10.0, MIT):**

- `php artisan seo:audit` — an in-process pass/warn/fail report over your pages:
  missing / over- / under-length titles and descriptions, OG image, robots
  conflicts, canonical format / cross-domain / insecure, focus keyword. No queue,
  no network, no license. It also now surfaces the first **AI-answer-engine (AEO)**
  checks: article-type JSON-LD missing an `author` entity or a publish date —
  the signals AI answer engines lean on for attribution and recency.
- A linked **JSON-LD schema graph** (Organization → WebSite → WebPage cross-referenced
  by stable `@id`s) with typed Article/Product/FAQ/Breadcrumb/LocalBusiness builders.
- **Sitemaps** with optional image and `hreflang` alternate entries, an
  **`llms.txt`** generator (an index for AI crawlers, built from the same sources
  as your sitemap so the two never disagree), and **AI-crawler control**: a managed
  `robots.txt` / `ai.txt` from a catalog of ~28 AI bots tagged by purpose, which
  by default *allows the bots that cite you* and *gates the ones that train on you*.
- **Markdown-for-bots** content negotiation — serve clean markdown to an AI
  crawler instead of HTML, off by default, never touching a normal visitor's
  response.

**In Pro (v2.16.1, commercial, runs headless on any Laravel 11–13):**

- A queued **scan pipeline** with a transparent **0–100 score** — the number a
  Rank Math migrant looks for — where every point traces to a documented issue
  code.
- The **on-page checklist**: focus-keyword checks plus **readability**
  (Flesch-Kincaid for English, **Gulpease for Italian**) as pass/warn/fail. This
  is the Yoast/Rank Math readability box, rebuilt for Laravel.
- A hardened **redirect manager** and a **no-IP 404 monitor** with a one-click
  404→redirect action — the slug-change cleanup you used to do by hand.
- A **broken-link crawler**, **read-only Google Search Console** integration
  (the `webmasters.readonly` scope, nothing that can write), an **IndexNow**
  client to push new URLs to Bing/Yandex on publish, an **AI-bot hit log**, and
  an **MCP server** (`php artisan seo-pro:mcp`) so Claude or Cursor can read — and
  optionally edit — a site's SEO.
- **AI assist**, bring-your-own-key (Anthropic / OpenAI / Google / a local
  OpenAI-compatible server), with **no metering and no token resale** — including
  `seo-pro:ai-fill` to bulk-fill *missing* title/description across a model
  collection (gaps only, never overwriting).

That last contrast is the budget story too. The plugins you're leaving bill
forever — Yoast Premium per year, Rank Math on a credit subscription for the AI.
Rankbeam Pro is a one-time license, and the AI runs on *your* key with no meter
in the middle. Positioned plainly: it's Rank Math / Yoast for Laravel, built for
the AI era.

## Was anything worse?

Honesty check, because a switching post with no downsides is an ad. AI assist is
**bring-your-own-key and deliberately hands-off** — title/description suggestions and
plain-language issue explanations, never auto-applied; you pick, it fills the field, saving
stays explicit. And the
editor-box experience lives in the (free, MIT) Filament package, so if your app
isn't on Filament you wire the SEO fields into your own admin instead of getting
them for free. For us, neither was a dealbreaker against losing the per-year
plugin bills and getting redirects, 404s, and audits in the same engine.

## Where to start

The lowest-risk path is exactly the order above: install alongside, `--dry-run`
the importer, run `seo:audit --strict`, then decommission the old setup.

```bash
composer require rankbeam/laravel-seo
```

The [quickstart](/guide/quickstart) gets you from install
to a rendered head in five minutes; the
[WordPress](/guide/migrate-from-wordpress) and
[other-package](/guide/migrate-from-other-packages)
migration guides cover every flag and field mapping.
