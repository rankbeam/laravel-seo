---
description: "Install laravel-seo-pro: queued site scans with issue tracking, a redirect manager and a 404 monitor on top of the core. Runs on any Laravel 11–13 app; Filament optional."
---

# Installing Pro

`rankbeam/laravel-seo-pro` adds queued site scans with issue tracking, a
redirect manager, and a 404 monitor on top of the core package. Since v1.1.0
the engine runs on **any Laravel 11–13 app** — Blade, Inertia, or pure API.
Filament is an optional UI layer: install it and you get the SEO dashboard,
redirect manager, and 404 monitor as panel pages; skip it and you manage
everything with [artisan commands](/pro/headless).

## Requirements

| | |
|---|---|
| PHP | 8.2+ |
| Laravel | 11, 12, or 13 |
| `rankbeam/laravel-seo` | ^2.0 (installed automatically) |
| `filament/filament` | **optional** — 4.x or 5.x, only for the admin UI |

## Install the package

Pro is distributed through a private Composer repository tied to your
license. Add the repository once, then require the package — Composer will
ask for your license e-mail (username) and license key (password):

```bash
composer config repositories.rankbeam-pro composer https://laravel-seo-pro.composer.sh
composer require rankbeam/laravel-seo-pro
```

For CI or non-interactive environments, store the credentials up front:

```bash
composer config http-basic.laravel-seo-pro.composer.sh you@example.com YOUR-LICENSE-KEY
```

Then run the installer:

```bash
php artisan seo-pro:install
```

`seo-pro:install` publishes `config/seo-pro.php` and the Pro migrations and runs
`migrate` — then prints the next steps. Pro migrations are **publish-only** (the
package never auto-loads them, so a future re-publish or upgrade can't create a
duplicate migration history); the installer is the step that turns a bare
`composer require` into a working schema. It is idempotent — re-run it any time.
Useful flags: `--force` overwrites already-published files, `--no-migrate`
publishes without migrating (run `php artisan migrate` yourself). The equivalent
manual steps are:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

That is the whole baseline install. Both middlewares (redirects, 404
logging) register themselves globally — a redirect rule you create is live
immediately, and 404s start being logged without any further wiring. Both
auto-registrations can be turned off in `config/seo-pro.php` if you want to
place the middleware yourself.

For running Pro at scale — dedicated queues, the scheduler, recovery, retention,
and telemetry — see the [Production setup](/pro/production) guide.

## Register scan targets

Tell the scanner what to scan in a service provider — model classes, named
routes, or everything in your [sitemap registry](/guide/sitemaps):

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // or: SeoPro::targets()->fromSitemaps();
}
```

## Verify your install

`seo:doctor` confirms the wiring in one shot — tables, app URL, scan targets,
sitemap, per-workload queues, the optional features, and operational health
(leftover state from a previous SEO stack, runs abandoned by a dead worker) — and
prints the exact fix for any warning:

```bash
php artisan seo:doctor
```

```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

It makes no network calls and never prints secret values. It validates
configuration and recent run history — it cannot prove an external cron or worker
is actually running. It exits non-zero only on a critical failure (a missing
required table), so it is safe in CI; add `--json` for monitoring (each check
carries a stable `id` to key on). See [Headless usage](/pro/headless) for the full
command reference and the [Production setup](/pro/production) guide for queues and
scheduling.

## Five-minute Pro tour

With the baseline install done, here is the whole loop without a panel:

```bash
# 1. Confirm the wiring.
php artisan seo:doctor

# 2. Scan every registered target (inline; drop --sync to queue it).
php artisan seo-pro:scan --sync

# 3. Read the latest run: summary, average score, open issues by severity.
php artisan seo-pro:scan-status

# 4. Recover a dead URL the moment you see it in the 404 monitor.
php artisan seo-pro:404-list
php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing
```

That is the complete operations loop — scan, triage, redirect — from the
command line. The [Filament panel](#path-a-with-a-filament-panel) puts the
same engine behind a dashboard if you want one.

## Path A — with a Filament panel

Add the UI packages (Filament 4 and 5 are both supported), then register
the plugin on your panel:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

This adds three pages to the panel: the **SEO dashboard** (scan-all action,
live progress, issue list with one-click rescan), the **redirect manager**,
and the **404 monitor** with its one-click *Create redirect* action.
`rankbeam/laravel-seo-filament` additionally gives your resource forms the
[SEO fields section](/guide/filament).

## Path B — headless

Nothing more to install. Run scans, read results, and manage redirects and
404s entirely from the command line or your scheduler — see
[Headless usage](/pro/headless) for the command reference and recommended
schedule.

You can add Filament later (Path A) at any time; the underlying tables and
config are identical, so nothing needs to be migrated or re-done.
