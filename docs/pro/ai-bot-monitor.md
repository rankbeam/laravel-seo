# AI-bot monitor

The [AI crawler control](/guide/ai-crawlers) feature (core) decides what
`robots.txt` *tells* the AI crawlers. The Pro **AI-bot monitor** is the other
half: it records what they actually *did* — which AI crawlers fetched your site,
how often, and the last URL + HTTP status each touched.

It reuses the 404-monitor plumbing (a terminable global middleware, an
upsert-with-hit-counting model, the same privacy posture) but keys on the **bot**
instead of the path and records on **any** response status — the exact AI
crawlers the 404 monitor deliberately excludes. Bot identification reuses the
core `AiCrawlerRegistry`, so your robots.txt policy and the traffic you observe
share one source of truth.

::: tip Requires core ≥ 3.3
The monitor identifies bots with the core AI-crawler catalog
([`SEO::aiCrawlers()`](/guide/ai-crawlers)). On older core it stays inert.
:::

## Enabling it

Off by default. Turn it on and the global middleware records matching crawlers
after each response (it never delays the page):

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

That's all — the middleware is auto-registered (opt out with
`ai_bots.auto_register_middleware`). One row per known bot is upserted, so the
table is bounded by the catalog.

## Reading the log

### Headless

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Each row exposes `bot`, `label`, `operator`, `purpose`, `hit_count`,
`last_path`, `last_status`, `first_seen_at`, and `last_seen_at`.

### Artisan

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop bots not seen in retention_days
```

### Filament

With the Pro plugin registered, an **AI Bots** table appears under the SEO
navigation group: bot, operator, purpose, hits, last status, last path, last
seen — filterable by purpose, read-only.

## Privacy

The same posture as the 404 monitor: **no IP is stored by default.** The
`ai_bots.hash_ip` opt-in stores a keyed sha256 (`ip_hash`) only — the raw IP is
never written.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // seo-pro:ai-bots-prune; null disables
],
```

## Turning observation into control

The monitor tells you *who* is crawling; the core
[AI crawler control](/guide/ai-crawlers) decides *what they may take*. A trainer
showing up that you'd rather gate?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Remember that some bots are documented not to honour `robots.txt` — the monitor
is how you spot them and decide whether to block them at the edge (firewall /
WAF / Cloudflare).
