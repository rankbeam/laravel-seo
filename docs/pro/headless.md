# Headless usage

Every Pro feature that *does work* — scanning, redirecting, 404 logging —
lives in the engine and runs without Filament. The panel UI is a management
convenience; these commands are its headless counterpart.

## Command reference

### Scanning

| Command | What it does |
|---|---|
| `seo-pro:scan` | Queue a full scan of every registered target (`--sync` to run inline) |
| `seo-pro:scan-status` | Latest run summary + open issues, most severe first (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Mark runs abandoned by a dead queue worker as failed |
| `seo-pro:scan-prune` | Delete finished runs (and their issues) past the retention window |

### Redirects & 404s

| Command | What it does |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Create a redirect rule (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Logged 404s, most-hit first (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Write cache-batched redirect hit counters to the database |
| `seo-pro:404-prune` | Delete stale 404 entries and enforce the row cap |

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

// Redirect hit counters are batched through the cache; flush them
// to the database regularly.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();
```

## What needs the Filament UI?

Nothing functional. The complete engine — scan pipeline, issue tracking,
redirect matching, 404 logging, pruning, recovery — is identical with and
without Filament. The panel adds the *views*: the dashboard with live scan
progress and severity stats, browsing issues with filters and per-page
modals, ignore/reopen buttons, redirect CRUD forms, and the 404 table with
its one-click action. Issue ignore/reopen currently has no dedicated
command — do it from the panel, or via the `SEOScanIssue` model
(`markIgnored()` / `reopen()`) in tinker or your own code.
