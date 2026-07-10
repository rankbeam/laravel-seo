# Scan scheduling & delta

Run a full SEO scan **on a schedule**, and see **what changed since the last
scan** — the issues that appeared, came back, or were fixed — ordered by impact,
on the dashboard and (optionally) in a summary e-mail.

Two features, one page, because they work together: the delta is what makes a
scheduled scan worth receiving.

## What changed since the last scan

Every completed scan freezes its **open-issue set** into a lightweight snapshot
(`seo_scan_run_issues`). Comparing two runs' snapshots gives an exact, three-way
diff:

- **New** — a problem that wasn't open before and is now, and was never open in
  any earlier scan: a genuinely first-time finding.
- **Regressed** — a problem that had been fixed and has **come back**. (Not
  "got more severe": a severity is fixed per issue type, so the meaningful
  regression is a return, exactly what the issue
  [lifecycle](/pro/scan-issues#issue-lifecycle) calls a reopen.)
- **Fixed** — a problem that was open in the earlier scan and is gone now.

Each bucket is [impact-ordered](#impact-ordering) so the list leads with what
matters.

### Why a snapshot, not the issues table

Issues carry a fixed / reopened [lifecycle](/pro/scan-issues#issue-lifecycle):
the same row is updated in place across scans (its `scan_run_id` is re-stamped to
the newest run each time it's still open). That's great for a durable issue
history, but it means the live table can't tell you *which issues were open at
the end of run N* — a persistent issue only ever points at the latest run.

So each run snapshots its open set, keyed by a stable **issue fingerprint** —
`issue_type | target | field` (the same identity the
[white-label report](/pro/reports) diffs on). The delta is then plain set
arithmetic over two frozen fingerprint sets, correct for **any** two runs, not
just consecutive ones.

### Edge cases, handled honestly

- **A page drops out of the scan set.** Its open issues are never re-scanned, so
  they stay open and are re-snapshotted every run — they show as **still open**,
  never a false "fixed". (We don't claim a page is fixed just because we stopped
  looking at it.)
- **A check is turned off between scans.** Its issues stop being emitted, the
  lifecycle marks them fixed, and they leave the open set — so they show as
  **fixed**. That honestly reflects the scanner's current opinion; there's no
  issue-level way to distinguish "you fixed it" from "you disabled the check".
- **The first scan after upgrading.** Runs from before this feature carry no
  snapshot, so they're never chosen as a baseline — the first snapshotted scan is
  a **baseline** (current state, no delta) rather than reporting your whole site
  as "new". From the second snapshotted scan on, the delta is live.

### On the dashboard

The **"What changed since the last scan"** widget on the
[SEO dashboard](/pro/installation) shows the new / regressed / fixed counts and
the top issues in each bucket, impact-ordered, comparing the two most recent
completed scans. Until two scans have been snapshotted it shows a short
"baseline" note.

## Impact ordering

Each delta bucket is ordered by an **impact** score so the biggest problems lead:

```
impact = severity_weight × page_importance
```

- **severity_weight** reuses the published [score rubric](/pro/scoring): a
  critical is worth `40`, a warning `15`, a notice `5`. Severity is the product's
  stated opinion of how much a defect matters, so the ranking reads it rather than
  inventing a second scale.
- **page_importance** is driven by **real search demand** — how many impressions
  the page earns in [Search Console](/pro/search-console) — because that's the
  signal that actually discriminates one page from another:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Impressions are log-scaled (a page with 10× the traffic isn't 10× more
  important) and normalised to your busiest page, so the formula behaves the same
  on a small blog and a large catalogue. Sitemap `<priority>` is only a **weak
  secondary** signal — it's unset by default and a flat value even when set, so it
  can't carry the ranking; it nudges when you've configured per-type priorities in
  `seo.sitemap.models`.

**No Search Console, no problem.** With no synced GSC history and no configured
priorities, `page_importance` is `1` for every page and impact is a pure
**severity ordering** — a sensible default, not a fabricated one. Sync
[GSC history](/pro/search-console#historical-metrics) (`seo-pro:gsc-sync`) to turn
on demand-weighting.

Tune the weights and window under `seo-pro.scan.delta.impact`.

## Scheduling a scan

The package **schedules nothing by default**. Turn it on:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

or set a full cron expression for complete control (it wins over `frequency`):

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

That's it — the package registers `seo-pro:scan` with Laravel's scheduler
(`withoutOverlapping`, so a slow scan never stacks on the next tick). The
registration only runs in a scheduler/console context, so it adds **no
web-request overhead**.

::: warning Needs a running scheduler
Package scheduling is inert unless Laravel's scheduler is running — the standard
one-line cron (`* * * * * php artisan schedule:run`) or `php artisan
schedule:work` in development. See [Production setup](/pro/production#scheduler).
:::

Prefer to wire it yourself? Leave `schedule.enabled` off and schedule the command
from your own console kernel instead — the delta and summary still work:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` runs the scan inline instead of queueing one job per target (fine for a
small site with no queue worker; keep it off in production).

## Summary e-mail

Opt in to receive a **"what changed since the last scan"** e-mail when a
scheduled scan finishes — a branded HTML summary of the new / regressed / fixed
issues, impact-ordered:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

It reuses the [white-label report](/pro/reports) branding and mail setup, so your
agency name, logo, and accent carry over, and the recipients fall back to your
report recipients when you don't set separate ones. `only_on_change` skips the
e-mail when a scan changed nothing (the first baseline scan is always sent).

The summary is only sent for a run started with `--notify` (which the scheduler
adds automatically when `notify.enabled` is on) — an ad-hoc `seo-pro:scan` never
e-mails anyone.

::: tip Another channel?
Want Slack, a webhook, or a custom digest instead of e-mail? Subscribe to the
`Rankbeam\Seo\Pro\Events\SeoScanCompleted` event — it fires once per finished run
and carries the run, so you can build the delta with
`Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` and route it wherever you like.
:::

## Retention

Snapshots cascade-delete with their run, so
[`seo-pro:scan-prune`](/pro/production#scheduler) ages them out automatically —
there's nothing new to schedule. A run is only pruned once it carries no open
issue, so a recent run's snapshot is always available to diff against.

Turn snapshotting off entirely (no delta, no summary) with
`seo-pro.scan.delta.snapshot => false`.
