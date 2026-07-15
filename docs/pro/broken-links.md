---
description: "A bounded, resumable crawler that records links that don't resolve — dead internal routes, fixable as a one-click redirect, and optional broken external links. Off by default."
---

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

## Typed link inspections

Beyond "is it reachable", every crawled link is run through a set of **typed
inspections** — a URL-hygiene taxonomy that flags trailing-slash inconsistency,
messy encodings, redirect chains, `javascript:` hrefs, broken in-page anchors,
non-descriptive anchor text and more. Each inspection carries a fixed
**severity** (`critical` · `warning` · `notice` — the same vocabulary the
[scan issues](/pro/scan-issues) use, so one CI gate spans both) and is recorded
per crawl in `seo_broken_link_inspections`. Unlike a broken-link *finding*
(confirmed only after several consecutive crawls), an inspection is a per-run
snapshot: it surfaces **immediately, on the first crawl** — which is exactly what
a CI gate needs.

### Inspection reference

| Inspection | Severity | Flags | Applies to |
| --- | --- | --- | --- |
| `broken_link` | critical | Target returned HTTP ≥ 400 | any link |
| `redirect_chain` | notice · warning | Target only resolves through a redirect; `warning` past `redirect_chain_warning_hops` | any link |
| `link_unreachable` | notice | Unreachable this crawl (network error, timeout, blocked) — may be transient | any link |
| `insecure_link` | warning | An `http://` link on an `https` site (downgrade / mixed content) | any link |
| `trailing_slash` | notice | Internal path breaks the declared trailing-slash convention (**off unless `trailing_slash` is set**) | internal |
| `double_slash_url` | warning | Internal path contains a `//` (empty segment) | internal |
| `duplicate_query_param` | notice | A query key repeats (`?a=1&a=2`); `key[]` array syntax is exempt | internal |
| `non_ascii_url` | notice | Internal path has un-encoded non-ASCII characters | internal |
| `uppercase_url` | notice | Internal path has uppercase letters (case-sensitivity → duplicate content) | internal |
| `underscore_in_url` | notice | Internal path uses underscores (hyphens are the SEO-preferred separator) | internal |
| `javascript_link` | warning | Anchor uses a `javascript:` href — not crawlable or keyboard-accessible | any anchor |
| `missing_fragment` | warning | A same-page `#fragment` with no matching `id`/`name` on the page | same-page |
| `non_descriptive_anchor` | notice | Anchor text is generic ("click here", "read more") or a bare URL | any anchor |
| `absolute_internal_link` | notice | An internal link written as an absolute URL instead of a root-relative path | internal |

The hygiene inspections (trailing slash, casing, encoding, double slash…) apply
to **internal** links only — an external site's URL style isn't yours to police.
Redirect, broken, unreachable and insecure inspections apply to every link. Links
to your own framework routes and static assets are skipped so a first run stays
quiet (see `exclude_paths` / `exclude_extensions` below).

Each link is fetched at its **exact authored URL** — only the `#fragment` is
removed — rather than a normalized form, so a server-side canonical redirect such
as `/about/ → /about` is actually observed and surfaces as a `redirect_chain`
instead of being pre-empted by normalization. Every distinct authored form of a
link on a page is inspected, so `/page#ok` and `/page#missing` (or `/a//b` and
`/a/b`) are each judged rather than only the first. The underlying broken-link
*finding* still folds every alias of a target to one identity; inspection rows are
recorded per `(page, target, inspection)`, so a target with several failing in-page
anchors surfaces one `missing_fragment` row (with an exemplar), not one per anchor.

### Tuning the taxonomy

Everything lives under `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**Disable one rule** by removing its class from `rules`; **disable the whole
taxonomy** with `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Two rules are worth
knowing about up front:

- `trailing_slash` is **off until you declare a convention** (`'always'` /
  `'never'`), because a site that serves both `/x` and `/x/` with a `200` has no
  "wrong" style to flag — and where the server *does* canonicalise with a
  redirect, that already surfaces as `redirect_chain`.
- `absolute_internal_link` fires on **every** internal link written as an
  absolute URL. If your site emits absolute internal URLs by convention, that is
  a lot of (harmless, `notice`-tier) rows — drop it from `rules` to silence it.

## Continuous integration

Both the link scan and the [SEO audit](/pro/scan-issues) can **fail a build** and
**write a report artifact**, turning Rankbeam from a dashboard into a quality
gate. `--fail-on-error` maps to the `critical` tier (a broken link, a critical
issue); `--fail-on-warning` fails on `critical` **or** `warning` (there is no
separate "error" tier).

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` writes the artifact (a directory derives the filename);
`--format` is `json` (default), `md` or `html`. JSON is the shape to parse in a
pipeline; HTML is a self-contained page to attach to a run.

### GitHub Actions

The crawler fetches your pages over HTTP, so CI must point it at content it can
reach — a locally served app (below) or a staging URL via
`SEO_PRO_BROKEN_LINKS_BASE_URL`, with your models/sitemap registered so the crawl
has something to seed from.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

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
| `seo-pro:broken-links-status` | Latest crawl summary, open broken findings + this run's inspection counts; **CI gate** (`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | Cancel a running/queued crawl (`{run?}` — defaults to the latest active) |
| `seo-pro:broken-links-recover` | Fail crawls abandoned by a dead worker (stale lease) |
| `seo-pro:broken-links-prune` | Apply the crawler retention policy (old runs + resolved findings) |

## Tuning

The crawl's bounds — pages per run, links per page, per-job caps, the hard time
budget, and per-host politeness delays — are all in `seo-pro.broken_links`. The
defaults are conservative and finite; see the
[batch-tuning table in Production setup](/pro/production) before raising them.
