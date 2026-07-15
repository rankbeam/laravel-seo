---
description: "Give each page its own 1200×630 Open Graph image, rendered from a Blade template by a headless browser so titles wrap and truncate cleanly. Free, core, off by default."
---

# Generated OG images

A page with no social card falls back to one shared `default_og_image` — the
same picture on every share. This feature gives each page **its own** 1200×630
Open Graph / Twitter card, rendered from a Blade template by a real headless
browser (via [spatie/browsershot](https://github.com/spatie/browsershot)), so the
title wraps across lines, accents render, CJK falls back to the right font, and
overlong titles truncate cleanly — none of which a hand-rolled image library gets
right on its own.

This is a free, core feature, and it's **off by default**. When it's off,
`default_og_image` is used unchanged and the package stays zero-dependency.

::: info Static pre-generation, by design
Cards are generated ahead of time by an artisan command, not on the fly during a
web request. A page only ever links a card that already exists on disk — so a
visitor's request never spawns a browser, and never links a missing (404) image.
There is **no live render endpoint** (see [Caveats](#caveats)).
:::

## Requirements

The browser driver is an optional dependency, so the free core installs without
it. To turn the feature on you need, in your application:

```bash
composer require spatie/browsershot
```

Plus the runtime Browsershot drives:

- **Node.js** on the host.
- **Puppeteer**, installed in your **application root** so Node resolves it:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer downloads its own Chromium by default; in
  production you'll usually point at a system Chrome (see
  [`chrome_path`](#configuration)).

::: warning Install puppeteer in the app root on Windows
On Windows, install `puppeteer` in the application root rather than relying on
`npm_module_path`. That config key maps to Browsershot's `setNodeModulePath()`,
which emits a POSIX `NODE_PATH=…` prefix and is a **no-op on Windows** — Node
there resolves modules by walking up directories from the app, so a root install
is what works. See [Caveats](#caveats).
:::

## Enabling

Publish the config if you haven't (`php artisan vendor:publish --tag=seo-config`)
and flip the switch:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Then **pre-generate** the cards (nothing renders until you do):

```bash
php artisan seo:og-images
```

## How resolution works

Generation never overrides an image you set. When the feature is on, the resolver
fills `og:image` **only when the page has no image of its own** — that is, when
the resolved `og:image` is either empty or still the site-wide static
`default_og_image`. An explicit per-model image (from `getSEOImage()`, a
`seo_meta` row, a content field, …) always wins over a generated card.

To decide the value, the resolver calls the generator's **existence-gated**
lookup: it computes the card's storage path and returns its public URL **only if
that file already exists on the configured disk**. It never renders. That's the
whole safety story:

- A web request **never spawns a browser** — worst case it links the static
  `default_og_image`, exactly as before the feature existed.
- A page **never links a not-yet-generated image**, so there's no window where
  shares point at a 404.

You close the gap between "content changed" and "card exists" by running the
[`seo:og-images`](#the-seo-og-images-command) command — on deploy and/or on a
schedule.

## The `seo:og-images` command

Pre-generates the cards so the resolver has something to serve.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — one or more model classes to warm. Repeatable. When omitted the
  command uses `seo.og_image.models`, falling back to your
  [sitemap models](/guide/sitemaps) (`seo.sitemap.models`) — the same
  share-the-sitemap-sources posture as `seo:llms-txt`.
- `--force` — re-render cards that already exist (use after changing the template
  or brand colors without bumping `cache_version`).
- `--prune` — after warming, delete stored cards under the configured path that
  no longer match any current model's content (see below). For safety it only
  removes files whose names are generated content hashes (never your other
  assets in the same directory), and it is **ignored on a scoped `--model` run**
  (whose keep-set wouldn't cover your other models) — run it without `--model`.

Each model must use the `HasSEO` trait. A record with no title is skipped
(there's nothing to put on the card); the command reports `generated`, `skipped`,
`failed` and (with `--prune`) `pruned` counts.

### Scheduling

Warm on a schedule so cards track your content, and prune the orphans left behind
when titles change:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### The invalidation model

A card's filename is a **hash of everything that affects its pixels** — the
title, site name, template name, driver, dimensions, the brand gradient colors,
the `cache_version` number, **and the installed package version**.

That hash is the cache key, and it has two consequences worth understanding:

- **Change a title → new hash → new file.** The old card is now an *orphan* on
  disk, and the page falls back to the static default until you re-warm. Running
  the command generates the new card; `--prune` deletes the orphan. This is the
  invalidation model — there's no separate "bust one page" step.
- **Bump `cache_version`, or upgrade the package → every hash changes.** Use
  `cache_version` after editing the template or brand colors to invalidate every
  card at once; a package upgrade folds in automatically, so a new release that
  changes the bundled template can't serve stale cards.

## Bundled templates

Three templates ship with the package, all 1200×630 on the same brand gradient:

| Template | Best for | Shows |
| --- | --- | --- |
| `seo::og.default` | Anything | Title + site name |
| `seo::og.article` | Blog posts, news | Section eyebrow + title + author · date byline |
| `seo::og.product` | Products, listings | Brand lockup + category chip + title + description |

Pick one globally with `seo.og_image.template`, or map templates **per model
type** so an article and a product get different cards automatically:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

A model can also override its own template at runtime by defining
`getOgImageTemplate(): ?string` (return a view name, or `null` to fall back to
the map/default). Precedence: the model hook, then the `templates` map, then the
global `template`.

## Customizing the template

The card is a Blade view (`seo::og.default` by default) rendered to a
self-contained HTML document — the bundled font is inlined as a data URI so the
browser needs no network. Two ways to change it:

**Publish and edit the bundled view:**

```bash
php artisan vendor:publish --tag=seo-views
```

Then edit `resources/views/vendor/seo/og/default.blade.php`.

**Or point at your own view:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

The template receives these variables:

| Variable | Type | Notes |
| --- | --- | --- |
| `$title` | `string` | The OG title if set, else the page title. |
| `$siteName` | `?string` | The resolved `og:site_name`. |
| `$fontDataUri` | `string` | The bundled bold font as a `data:` URI (empty string if unavailable — the browser then uses its own sans-serif). |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Output width (default `1200`). |
| `$height` | `int` | Output height (default `630`). |
| `$locale` | `?string` | Resolved page locale, for the `<html lang>` attribute. |
| `$author` | `?string` | Article author (used by `seo::og.article`). |
| `$publishedDate` | `?string` | Publish date, pre-formatted `M j, Y` (used by `seo::og.article`). |
| `$section` | `?string` | Content section / category (article eyebrow, product chip). |
| `$description` | `?string` | The OG description, else the page description (used by `seo::og.product`). |

::: info The template name is part of the cache key
Both the template **name** and the gradient colors feed the content hash, so
switching templates or changing colors invalidates existing cards automatically.
Editing a template *in place* does not (the name is unchanged) — bump
`cache_version` (or run `--force`) after editing.
:::

## Configuration

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox. The standard fix for the "No usable
    // sandbox!" launch failure on default Ubuntu 22.04+/24.04 servers (see
    // "Running on Linux" below). Off by default.
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],
],
```

Most scalar values have a matching env var (`SEO_OG_IMAGE_ENABLED`,
`SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, …) —
see the config file for the full list. The array-shaped keys (`templates`,
`models`, `browsershot_args`) are edited in the config file directly.

The disk must be **publicly served**, because the resolver uses its `url()` as the
`og:image` value. With the `public` disk, run `php artisan storage:link` once so
`public/storage` points at it.

## Running on Linux (the sandbox)

On a default **Ubuntu 22.04+/24.04** server, `php artisan seo:og-images` fails
out of the box with:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

This is not a package bug. Ubuntu ships an AppArmor profile that restricts
**unprivileged user namespaces**, which is exactly the mechanism Chrome's sandbox
uses to isolate itself — so Chrome refuses to launch. There are two ways past it:

**1. Run Chrome with `--no-sandbox` (one line).** The pragmatic fix when the app
runs as a non-root user in a trusted container or VM:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Only the HTML this package generates is ever rendered — never untrusted,
user-supplied web pages — so dropping the sandbox here does not expose you to
hostile page content the way `--no-sandbox` on a general-purpose scraper would.

**2. Keep the sandbox, grant Chrome the namespace (hardened).** Leave
`no_sandbox` off and add an AppArmor profile that allows `userns` for the Chrome
binary Puppeteer drives, e.g.:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

then `sudo apparmor_parser -r /etc/apparmor.d/chrome-og`. This preserves Chrome's
own sandbox — the stronger posture if the host is shared.

::: tip Other flags
For a container that's short on shared memory (the other common Linux
failure — a Chrome crash mid-render), add flags via `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Custom drivers

`browsershot` is the only bundled driver, but the renderer is behind a contract
(`Rankbeam\Seo\Contracts\OgImageRenderer`). Register your own — say a canvas- or
service-based renderer — and select it with `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

A driver only turns a self-contained HTML string into PNG bytes at a given size;
it owns no layout or templating.

## Caveats

Stated honestly, because they bite in production:

- **Pre-generation only — no live render endpoint (v1).** There is no route that
  renders a card on request. Because nothing renders on a web request, there is
  **no signed-URL / SSRF / DoS surface to configure or defend** — the trade-off is
  that you must run [`seo:og-images`](#the-seo-og-images-command) (on deploy
  and/or a schedule) for cards to exist.
- **`npm_module_path` is a no-op on Windows.** It maps to Browsershot's
  `setNodeModulePath()`, which prepends a POSIX `NODE_PATH=…` to the command —
  ignored by Windows. On Windows, install `puppeteer` in the **application root**
  so Node resolves it by walking up directories. (On Linux/macOS the setting works
  as expected.)
- **Non-Latin scripts need a font on the host.** The browser does per-script font
  fallback, but only to fonts **installed on the deploy image**. The bundled font
  is Latin-only (Noto Sans Bold, OFL). Without a CJK/Noto font on the host,
  Chinese/Japanese/Korean (and other non-Latin) titles render as tofu boxes —
  install a font such as Noto Sans CJK on the machine that runs the command.
- **Fails open.** If a render fails (missing package, browser crash, timeout), the
  command reports it and the page simply keeps its static `default_og_image` — a
  broken browser never 500s a page.
