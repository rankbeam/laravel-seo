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

Then publish the config and migrations and migrate:

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
