---
description: "Install laravel-seo-pro: queued site scans with issue tracking, a redirect manager and a 404 monitor on top of the core. Runs on any Laravel 11–13 app; Filament optional."
---

# Installing Pro

`rankbeam/laravel-seo-pro` adds queued site scans with issue tracking, a
redirect manager, and a 404 monitor on top of the core package. The engine runs on **any Laravel 11–13 app** — Blade, Inertia, or pure API.
Filament is an optional UI layer: install it and you get the SEO dashboard,
redirect manager, and 404 monitor as panel pages; skip it and you manage
everything with [artisan commands](/pro/headless).

## Requirements

| | |
|---|---|
| PHP | 8.2+ (8.3+ on Laravel 13) |
| Laravel | 11, 12, or 13 |
| `rankbeam/laravel-seo` | ^3.19 (installed automatically by Pro 2.39+) |
| `filament/filament` | **optional** — 4.x or 5.x, only for the admin UI |
| `rankbeam/laravel-seo-filament` | **optional** — ^1.11 when using the SEO editor with Pro 2.36+ |

Start with an existing Laravel app and a configured database. Complete the
[core Quickstart](/guide/quickstart) first so a model renders metadata and the
core tables exist. A Pro license supplies the Composer credentials below.

For a visual example of the result, see [scan → fix → report](/pro/walkthrough).

## Install the package

Pro is distributed through a private Composer repository tied to your
license. Add the repository once, then require the package — Composer will
ask for your license e-mail (username) and license key (password):

```bash
composer config repositories.rankbeam-pro composer https://laravel-seo-pro.composer.sh
composer require rankbeam/laravel-seo-pro
```

::: details Non-interactive Composer authentication
For CI or non-interactive environments, store the credentials up front:

```bash
composer config http-basic.laravel-seo-pro.composer.sh you@example.com YOUR-LICENSE-KEY
```

:::

Then run the installer:

```bash
php artisan seo-pro:install
```

The installer publishes `config/seo-pro.php` and the Pro migrations, runs
`migrate`, and prints the next steps. The core and Pro tables should now be
present in your app's database.

::: details Manual installation and installer flags
Pro migrations are published into your app; they are not auto-loaded from the
package. The equivalent manual steps are:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

The installer can be rerun. `--no-migrate` publishes files without migrating.
Use `--force` only when you intend to overwrite published files, including
your configuration.
:::

## Register scan targets

Tell the scanner what to scan in a service provider — model classes, named
routes, or everything in your [sitemap registry](/guide/sitemaps):

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Replace `Post` with your own model using `HasSEO`. You need at least one
record to see a model scan result. Route targets must name existing routes;
leave that registration out if you only want to scan models.

## Verify your install

Run the configuration check:

```bash
php artisan seo:doctor
```

Confirm that the core and Pro tables exist, your application URL is correct,
and your scan targets are listed. Follow any reported fixes. A `sync` queue
warning is expected while trying the inline commands below; configure a
worker before scheduling production scans.

::: details Example health-check output
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

`seo:doctor` checks configuration and recent run history without network
calls or printing secrets. It cannot prove that an external cron or worker
is running. Critical failures return a non-zero exit code; warnings do not.
Use `--json` for the machine-readable result.
:::

## Run your first scan {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

The first command completes the scan inline, so this initial check does not
need a queue worker. The second shows the latest run and its results. Expect
a completed run with your registered targets processed; investigate any
failed targets before treating the scan as complete.

Fix one reported field, save it, and run the scan again. The
[walkthrough](/pro/walkthrough) shows this with a missing description and a
report of the change. A [technical score](/pro/scoring) is a diagnostic result,
not a prediction of rankings.

## Headless usage {#path-b-headless}

The engine is ready to use without a panel. [Artisan commands](/pro/headless)
let you scan, inspect issues, create redirects and generate reports. The
redirect and 404 middlewares register automatically by default; their settings
live in `config/seo-pro.php`.

For scheduled work, follow [Production setup](/pro/production) to configure
queues, workers, the scheduler and retention.

## Add a Filament panel (optional) {#path-a-with-a-filament-panel}

For an existing Filament 4 or 5 panel, register the Pro plugin below. If your
app has no panel yet, install the UI packages and create one first:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
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

This adds the **SEO dashboard** (scan-all action,
live progress, issue list with one-click rescan), the **redirect manager**,
and the **404 monitor** with its one-click *Create redirect* action.
`rankbeam/laravel-seo-filament` additionally gives your resource forms the
[SEO fields section](/guide/filament).

## Troubleshooting

| Result | Next step |
|---|---|
| Composer rejects the credentials | Check the license email and key for `laravel-seo-pro.composer.sh`. Keep credentials out of version control. |
| Doctor reports missing tables | Complete the core Quickstart, then run `seo-pro:install` and `migrate` against the same database as the app. |
| A scan processes no targets | Check your provider registration and that the model contains records. |
| A queued scan stays pending | Start the configured queue worker, or use `--sync` for an inline check. |
| A target fails | Check the run details, route names and application URL before rescanning. |
| The dashboard is missing | Register `SeoProPlugin` on the panel you actually use and check access gates. |

See [Production setup](/pro/production) for worker recovery and ongoing operations.
