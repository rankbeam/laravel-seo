# Headless usage

Every Pro feature — scanning, redirecting, 404 logging — runs headless: it
lives in the engine and needs no Filament. The panel is only a management UI;
these commands are its headless counterpart.

## Command reference

### Setup & health check

| Command | What it does |
|---|---|
| `seo-pro:install` | Publish `config/seo-pro.php` + the Pro migrations and run them, then print the next steps (`--no-migrate`, `--force`) |
| `seo:doctor` | One-shot health check — app URL, core + Pro tables, scan targets, sitemap, per-workload queues, optional features, and operational health, each with the exact fix for any warning (`--json` for monitoring) |

`seo-pro:install` is the documented setup path: Pro migrations are publish-only
(the package never auto-loads them), so the installer is what turns a bare
`composer require` into a working schema. It is idempotent — re-run it any time.

`seo:doctor` makes no network calls and never prints secret values (the AI
check reports only whether the configured key variable is *set*). It validates
configuration + recent run history — it cannot prove an external cron or worker is
actually running. It exits non-zero only on a critical failure — a missing required
table — so a localhost dev box with warnings still exits clean. `--json` gives each
check a stable `id` to key on. Run it right after [install](/pro/installation) and
in CI.

### Scanning

| Command | What it does |
|---|---|
| `seo-pro:scan` | Queue a full scan of every registered target (`--sync` to run inline) |
| `seo-pro:scan-status` | Latest run summary + open issues, most severe first (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Mark runs abandoned by a dead queue worker as failed |
| `seo-pro:scan-prune` | Delete finished runs (and their issues) past the retention window |

### Broken-link crawler

Off by default — enable `seo-pro.broken_links.enabled` and migrate its two tables
(`seo-pro:install` publishes them). The crawl runs across bounded queued jobs; run a
dedicated worker for its queue. See [Production setup](/pro/production) for tuning.

| Command | What it does |
|---|---|
| `seo-pro:broken-links-scan` | Queue a bounded, resumable crawl (`--scope=internal_only\|internal_and_external`, `--url=*` extra seeds) |
| `seo-pro:broken-links-status` | Latest crawl summary + open broken findings (`--limit=20`) |
| `seo-pro:broken-links-cancel` | Cancel a running/queued crawl (`{run?}` — defaults to the latest active) |
| `seo-pro:broken-links-recover` | Fail crawls abandoned by a dead worker (stale lease) |
| `seo-pro:broken-links-prune` | Apply the crawler retention policy (old runs + resolved findings) |

### Redirects & 404s

| Command | What it does |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Create a redirect rule (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Logged 404s, most-hit first (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Write cache-batched redirect hit counters to the database when `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Delete stale 404 entries and enforce the row cap |

### On-page checklist

| Command | What it does |
|---|---|
| `seo-pro:checklist {model} {id}` | Keyword-aware pass/warn/fail checklist for one model (`--json`, `--strict`, `--locale=`) — see [On-page checklist](/pro/on-page-checklist) |

The same checklist is available as `SeoPro::checklistFor($model)`. It is the
editorial loop (keyword placement, length, images, internal links), **not** the
[SEO score](/pro/scoring).

### Search Console (read-only)

| Command | What it does |
|---|---|
| `seo-pro:search-console` | Pages with open issues **and** search traffic, worst opportunity first (`--view=attention`, the default) |
| `seo-pro:search-console --view=pages` | Top pages by impressions/clicks/CTR/position |
| `seo-pro:search-console --view=queries` | Top queries (`--days=`, `--limit=`, `--json`) |

The same metrics are available as `SeoPro::searchConsole()` — see
[Search Console](/pro/search-console). Off by default; strictly read-only.

### AI assist

| Command | What it does |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Title/description suggestions as JSON (`--field=title\|description\|all`) — see [AI assist](/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Plain-language fix explanation for a scan issue, as JSON |

### Resolving a 404 in one step

`--from-404={path}` is the headless version of the 404 monitor's one-click
*Create redirect* action: it creates the rule **and** marks the matching log
entry redirected, linking it to the new rule:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

The command runs the same validators as the Filament form — invalid regex
patterns, oversized values, and non-allowlisted external targets are
refused before anything is written.

## Recommended schedule

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Every recurring command above has a recommended cadence in the
[Production setup](/pro/production) guide, alongside the queue topology, worker
configuration, retry/recovery policy, retention, and the structured **telemetry**
each finished run emits (pages fetched, links checked, blocked URLs, duration,
queue lag).

## What needs the Filament UI?

Nothing functional. The complete engine — scan pipeline, issue tracking,
redirect matching, 404 logging, pruning, recovery — is identical with and
without Filament. The panel adds the *views*: the dashboard with live scan
progress and severity stats, browsing issues with filters and per-page
modals, ignore/reopen buttons, redirect CRUD forms, and the 404 table with
its one-click action. Issue ignore/reopen currently has no dedicated
command — do it from the panel, or via the `SEOScanIssue` model
(`markIgnored()` / `reopen()`) in tinker or your own code.
