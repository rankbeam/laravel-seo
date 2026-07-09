# Production setup

Pro's day-to-day work — site scans, the broken-link crawler, optional
redirect-hit flushing, 404 pruning — runs on Laravel's queue and scheduler. This is the one
authoritative guide to running it at scale: dedicated queues, a scheduler, retry
and recovery policy, retention, and the telemetry to watch it all. It is the
topology behind a ~20k-visits/day, ~900-page production install, written to be
reproduced.

Everything here is **Filament-independent** — the engine, commands, queues, and
telemetry are identical with or without a panel. If you run Filament it adds
views on top; it changes nothing about how the work is scheduled or processed.

[[toc]]

## Safe rollout order

Do these in order — each step is verifiable before the next:

1. **Install** — publish config + migrations and run them:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` publishes `config/seo-pro.php` and the Pro migrations, then
   runs `migrate`. Pro migrations are **publish-only** (the package never
   auto-loads them), so this is the step that turns a bare `composer require` into
   a working schema. It is idempotent — re-run it any time; add `--force` to
   overwrite published files, `--no-migrate` to publish without migrating.

2. **Register scan targets** in a service provider (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Verify** the wiring before turning on background work:

   ```bash
   php artisan seo:doctor
   ```

   Fix every warning it prints (each carries the exact command/config line). In CI,
   add `--json` and key on the stable check ids.

4. **Configure the queues + scheduler** (below), deploy a queue worker and a cron
   entry for `schedule:run`.

5. **Enable optional features last** — the broken-link crawler, AI assist, Search
   Console are all off by default. The crawler needs its tables migrated (step 1
   already published them) and a dedicated worker (below).

## Dedicated queues per workload

A long scan or crawl must never sit in front of user-facing jobs (mail,
notifications). Give each SEO workload its own queue and its own worker.

The scan pipeline and the broken-link crawler each read a configurable queue:

| Workload | Config | Env | Default queue |
|---|---|---|---|
| On-page scan jobs | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | the default queue |
| Broken-link crawl jobs | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Redis example (the production topology)

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Run a worker per queue (each is a separate process / Supervisor program):

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

The crawl worker's `--timeout` must exceed
`seo-pro.broken_links.batch.hard_time_budget_seconds` (default 180) plus the HTTP
timeout, so a batch is never killed mid-bookkeeping; the job sets its own
`$timeout` to that sum, so align the worker flag with it. Use `--tries=1` for the
crawl: a job that dies is reclaimed by the next continuation (or
`seo-pro:broken-links-recover`), so queue-level retries are unnecessary.

`seo:doctor` reports each workload's queue and warns when one resolves to `sync`
(which would run the work inline and block).

## Scheduler

Laravel 11, 12, and 13 schedule in **`routes/console.php`** (the
`app/Console/Kernel.php` `schedule()` method only exists on apps upgraded from
Laravel 10 — put the same entries there if yours still has it). Add a single
system cron entry so the scheduler ticks every minute:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Then register every recurring command with its recommended cadence:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Recommended cadence at a glance:

| Command | Cadence | Why |
|---|---|---|
| `seo:sitemap` | daily | Refresh the sitemap from current content |
| `seo-pro:scan` | weekly (daily if content moves fast) | Re-audit every target |
| `seo-pro:scan-recover` | hourly | Reclaim runs lost to a dead worker |
| `seo-pro:scan-prune` | daily | Apply the scan-run retention window |
| `seo-pro:redirects-flush-hits` | every 5 min, only when `redirects.hits.flush_immediately=false` | Drain cache-batched hit counters to the DB |
| `seo-pro:404-prune` | daily | Trim the 404 log to retention + row cap |
| `seo-pro:404-recheck` | daily | Re-fetch open 404 paths; mark source-fixed ones (now 200) recovered |
| `seo-pro:broken-links-scan` | weekly | Re-crawl for broken links (confirmation is cross-scan) |
| `seo-pro:broken-links-recover` | hourly | Reclaim crawls lost to a dead worker |
| `seo-pro:broken-links-prune` | daily | Apply the crawler retention windows |

`seo-pro:scan` and `seo-pro:broken-links-scan` only **queue** work; the worker
does it. The recover/prune commands run inline and are cheap.

::: tip Cross-scan broken-link confirmation
A link is flagged broken only after `seo-pro.broken_links.mark_broken_after_failures`
**consecutive scans** fail to reach it (the counter resets on any success). That is
why the crawl is scheduled, not one-shot: a single transient outage never flags a
link. Weekly means ~3 weeks to confirm at the default of 3; tighten the cadence (or
lower the threshold) if you want faster confirmation.
:::

## Batch tuning (broken-link crawler)

The crawl runs across many bounded, self-redispatching jobs. The defaults are
conservative and finite — a misconfiguration can never hammer a site. Tune them in
`seo-pro.broken_links`:

| Key | Default | What it bounds |
|---|---|---|
| `max_pages_per_run` | `2000` | Pages fetched in a whole run. `null` = explicit unlimited opt-in (never the default) |
| `max_links_per_page` | `200` | Links checked per page |
| `max_total_links` | `null` | Optional global cap on link checks across the run |
| `batch.max_pages_per_job` | `50` | Pages per queued job |
| `batch.max_links_per_job` | `1500` | Link checks per queued job |
| `batch.hard_time_budget_seconds` | `180` | After this, the job starts **no new fetch** and re-dispatches a continuation |
| `batch.dispatch_delay_seconds` | `1` | Delay between continuation jobs |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Per-request bounds |
| `http.max_response_bytes` | inherits `seo-pro.http.max_response_bytes` | Streamed cap for page and target-check response bodies |
| `seed.max_response_bytes` | inherits the crawler/shared HTTP cap | Raw sitemap XML / `.gz` bytes fetched during seeding |
| `seed.max_inflated_bytes` | inherits the seed/crawler/shared cap | Decompressed bytes accepted from a `.gz` sitemap |
| `http.per_host_delay_ms` | `0` | Politeness delay between checks (raise for `internal_and_external`) |

Keep `batch.hard_time_budget_seconds` comfortably under the crawl worker's
`--timeout`. An in-flight request can't be aborted mid-flight — it is bounded by
`http.timeout`, which is why the worker timeout = budget + http timeout + headroom.

For an `internal_and_external` crawl, widen `seo-pro.http.scope` (or
`seo-pro.http.allowed_hosts`) so the SsrfGuard permits the outbound checks, and
raise `http.per_host_delay_ms` so a third-party host is never hit too fast.
`seo:doctor` warns when the crawl scope is external but the guard scope would
block every check.

## Horizon / Supervisor

### Supervisor

One program per queue. Example `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` must exceed the worker `--timeout` so a graceful restart never
kills a job mid-batch.

### Horizon

If you run Horizon, define a supervisor per workload in `config/horizon.php` and
let it manage the processes instead of Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Retry & failure handling

The scan target job carries its own retry policy from config — it does **not**
rely on the worker `--tries`:

| Key | Default | Meaning |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Attempts per target job |
| `seo-pro.scan.backoff` | `30` | Seconds between attempts |
| `seo-pro.scan.timeout` | `300` | Per-target job timeout (the overlap lock expires at timeout + 60) |

A target job that exhausts its retries records the target as **failed** and the
run still finishes (`partial` or `failed`) — a failing job can never leave a run
stuck `running`. Failures land in the standard `failed_jobs` table; manage them
the usual way:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Schedule `queue:prune-failed` alongside the SEO entries to keep that table bounded:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

The broken-link crawl uses `--tries=1`: a dead job is reclaimed by its own next
continuation (the lease heartbeat goes stale) or by `seo-pro:broken-links-recover`,
so queue retries would only duplicate work.

## Recovery

A worker that dies mid-job is the one case progress accounting can't self-heal, so
two sweeps close it — schedule both **hourly**:

- `seo-pro:scan-recover` — marks on-page scan runs with no progress for
  `seo-pro.scan.recovery.stuck_scan_timeout_hours` (default 2) as failed.
- `seo-pro:broken-links-recover` — reclaims crawl runs whose lease heartbeat went
  stale (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, default 2),
  marking them failed and freeing the one-active-run-per-scope slot.

`seo:doctor` surfaces this as **recent heartbeat evidence**: once scanning is in
use, it reports any stalled runs and points you at the recover command. It cannot
prove your cron actually runs — no command can — it reports what the run history
shows.

## Retention

Keep the tables bounded. Defaults (all in `seo-pro.*`, `null` disables that prune):

| Data | Config | Default | Command |
|---|---|---|---|
| Scan runs (+ issues) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404 log | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Crawl runs | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Resolved findings | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Operational telemetry

Every finished run — on-page scan **and** broken-link crawl — emits one structured
completion line through the logging stack, so you get a metrics trail with no panel.
The payload carries only counts and timings (no URLs, bodies, headers, or visitor
data):

| Metric | Scan | Crawl |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (SSRF-refused targets) | — | ✓ |
| `transient_failures` (network failures, re-checked next scan) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (queued → first batch) | ✓ | ✓ |

Configure it in `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Point `channel` at a dedicated log channel to ship the lines somewhere real
(Loki / Datadog / CloudWatch) without mixing them into application logs:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

For richer handling, subscribe to the events directly — each exposes the same
`metrics()` payload:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

Telemetry is best-effort: a misconfigured channel can never fail a scan.

## Filament-independent deployment

Nothing on this page needs a panel. The engine, every command, the queues, the
scheduler, recovery, retention, and telemetry are identical headless. The Filament
panel (`SeoProPlugin`) only adds **views** — live scan progress, the issue table,
redirect CRUD, the 404 monitor, the broken-link dashboard. Deploy the engine and
operate it from the CLI + scheduler; add the panel later (or never) without
migrating or redoing anything. See [Headless usage](/pro/headless) for the full
command reference.
