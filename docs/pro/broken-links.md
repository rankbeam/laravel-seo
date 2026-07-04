# Broken-link crawler

A **bounded, resumable crawler** that walks your site, follows the links on each
page, and records the ones that don't resolve — broken **internal** links (a
dead route on your own host, fixable in one click as a redirect) and, optionally,
broken **external** links. It is **off by default**.

Three things define the design:

- **Bounded and resumable.** A crawl runs across many small queued jobs, each
  capped at a handful of pages, that re-dispatch a continuation until the run
  finishes or hits its caps. A whole run is bounded too (2000 pages by default —
  `null` is the explicit opt-in to unlimited, never the default), so a
  misconfiguration can never crawl forever or hammer a site.
- **Safe by default.** The default scope is `internal_only` — it only checks
  links on your own host, no third-party requests. Every fetch (internal or
  external) goes through the shared **SsrfGuard**: scheme allowlist, host scope,
  and private-address rejection. Checking external links is opt-in and still
  guarded.
- **Separate from the SEO score.** Findings live in their own tables and never
  write into `seo_scan_issues` or the 0–100 score — a page's score is unchanged
  whether or not its outbound links are broken. Broken links are an operational
  concern, tracked on their own.

## What you get

In the Filament dashboard (only when enabled):

- **Broken-link summary** — open broken counts (internal vs external) and the
  last crawl, linking through to the findings table.
- **Broken-link crawl** — live progress of the running crawl (pages crawled,
  links checked, broken found).
- **Broken links per scan** — the trend across recent crawls.
- **A findings resource** — every broken `source → target` link, filterable, with
  the internal ones fixable as a redirect.

Headless, the same data comes from the `seo-pro:broken-links-*` commands.

## Why it's off by default

Unlike the passive rendering and scoring features, the crawler **makes network
requests** and needs a little infrastructure — so enabling it is a deliberate
opt-in, not something that should silently start on install:

- Its two tables are **publish-only** (like every Pro migration) — they must be
  migrated before the UI can query them.
- A crawl is **queued on a dedicated queue** and needs a **worker** to run — a
  crawl with no worker never progresses.
- Confirmation is **cross-scan** (below), so it's designed to run **scheduled**
  over weeks, not to produce instant value the moment it's switched on.

## Setup

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Then migrate its two tables — `seo-pro:install` publishes and runs every Pro
migration (idempotent, safe to re-run):

```bash
php artisan seo-pro:install
```

Run a **dedicated worker** for the crawl's queue. It's a separate queue
(`seo-broken-links`) precisely so a long crawl never sits in front of your
user-facing jobs:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Confirm the wiring — `seo:doctor` checks the flag, the tables, and whether the
crawl queue resolves to a real (non-`sync`) connection, each with the exact fix:

```bash
php artisan seo:doctor
```

See [Production setup](/pro/production) for the full multi-queue topology
(Redis, Supervisor, dedicated connections) and batch tuning.

## Running a crawl

Trigger one from the dashboard's **Scan now** action, or headless:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Both commands only **queue** the crawl — the worker does the actual work.

## How a link gets flagged

A link is reported broken only after
`seo-pro.broken_links.mark_broken_after_failures` **consecutive crawls** fail to
reach it (the counter resets on any success; the default is **3**). A single
transient outage never flags a link — which is why the crawl is meant to run
**scheduled**, not one-shot. At weekly cadence and the default threshold, a
genuinely dead link is confirmed in ~3 weeks; tighten the cadence or lower the
threshold if you want faster confirmation.

## Scheduling

Register the crawl and its housekeeping in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Command reference

| Command | What it does |
| --- | --- |
| `seo-pro:broken-links-scan` | Queue a bounded, resumable crawl (`--scope=internal_only\|internal_and_external`, `--url=*` extra seeds) |
| `seo-pro:broken-links-status` | Latest crawl summary + open broken findings (`--limit=20`) |
| `seo-pro:broken-links-cancel` | Cancel a running/queued crawl (`{run?}` — defaults to the latest active) |
| `seo-pro:broken-links-recover` | Fail crawls abandoned by a dead worker (stale lease) |
| `seo-pro:broken-links-prune` | Apply the crawler retention policy (old runs + resolved findings) |

## Tuning

The crawl's bounds — pages per run, links per page, per-job caps, the hard time
budget, and per-host politeness delays — are all in `seo-pro.broken_links`. The
defaults are conservative and finite; see the
[batch-tuning table in Production setup](/pro/production) before raising them.
