# IndexNow — push-on-publish indexing

Instead of waiting for a crawler to find a changed page, **IndexNow** lets you
*tell* the search engines the moment a URL is published or updated. Pro submits
to the shared `api.indexnow.org` endpoint, which **propagates to every
participating engine** — Bing, Yandex, Naver, Seznam, Yep — in one call (no
per-engine fan-out).

It is **off by default**. Nothing touches the network until you enable it and a
URL is submitted.

## Setup

### 1. Generate a key

IndexNow proves you own the host with a **key** — 8–128 characters of
`[a-f0-9-]` (a 32-character hex string is ideal). Generate one once and keep it
stable, then expose it via the environment:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip The key is read through config, so it survives `config:cache`
Unlike the Search Console credentials, the IndexNow key is **not a secret** — it
is served publicly at `/{key}.txt` to prove you own the host. So Pro resolves it
through the config layer (`indexnow.key`, which defaults to
`env('SEO_PRO_INDEXNOW_KEY')`). That is deliberate: `env()` outside a config file
returns `null` once `config:cache` has run (the state every normal deploy leaves
the app in), so a key read at call time would silently vanish on production. Read
through config, it is captured by `config:cache` and always available. The
trade-off: **rotating the key means re-running `php artisan config:cache`.** The
key is never logged. See [Config-cached servers](#config-cached-servers) if the
key file 404s in production.
:::

### 2. Serve the key file

IndexNow fetches `https://{host}/{key}.txt` (containing only the key) to verify
ownership. With the `route` toggle on (the default), **Pro serves it for you**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Only the one configured key path serves; any other `*.txt` 404s, and the whole
route 404s when IndexNow is disabled. Prefer to host the file yourself (or on a
CDN)? Turn `route` off and point `key_location` at your URL.

## Submitting URLs

### Automatically, on save (the push-on-publish path)

Add the trait to a model and turn on `auto_submit`. Every save queues a
submission of the model's `getUrlForSEO()`:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

The trait honours a publish gate: implement `shouldSubmitToIndexNow(): bool` for
full control, otherwise it falls back to an `is_published` attribute, otherwise
it submits on every save. Submission is always **queued**, so saving a model
never blocks on the network.

### Manually

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` queues by default; pass `queue: false` to run inline.

### From the command line

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Same-host only
Every URL is validated as `http(s)` **and** belonging to the configured `host`;
anything else is **dropped** (counted, never sent) — you can only submit URLs you
own, and the endpoint would reject a host mismatch anyway. Lists larger than
`max_urls_per_request` (10000, the protocol cap) are chunked automatically.
:::

## Configuration

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## How retries work

The queued `SubmitToIndexNowJob` is resilient about what *should* be retried: a
`429` (rate-limited), `5xx`, or timeout is retried with `backoff` up to `tries`
times; a `400`/`403`/`422` (a permanent client error — bad key, host mismatch)
is logged and **stops** rather than wasting retries. `200` and `202` (received /
pending key check) are both successes.

In production, give the job a **dedicated queue** so a slow endpoint never delays
user-facing work:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Troubleshooting

### Config-cached servers

If `/{key}.txt` 404s in production — or submissions silently do nothing — while
`indexnow.enabled` is clearly `true`, the cause is almost always a key that lives
**only in `.env`** on a server running `php artisan config:cache`. Laravel does
not parse `.env` once config is cached, so `env('SEO_PRO_INDEXNOW_KEY')` returns
`null`, the key-file route never registers, and every submission is rejected as
"not configured".

The default config resolves `indexnow.key` from `env(...)`, so a normal setup is
captured at cache-build time and this just works. It only bites when you have
**published the config and removed the `env(...)` default**, or set the key under
a **custom `key_env` name that exists only in `.env`**. Two ways to fix it:

1. **Keep the key in config** (recommended) — leave `indexnow.key` as
   `env('SEO_PRO_INDEXNOW_KEY')` (or set a literal), then re-run
   `php artisan config:cache`. Rotating the key later means re-caching.
2. **Inject a real environment variable** — set `SEO_PRO_INDEXNOW_KEY` as an
   actual OS/process env var (PHP-FPM pool `env[...]`, systemd `Environment=`,
   or your platform's env-var settings), **not just `.env`**. Real OS env vars
   are readable even when config is cached.

Run `php artisan seo:doctor` to confirm: it reports **"IndexNow is enabled but no
valid key resolves"** with the exact remedy when it detects this state, and Pro
also logs a warning once per process when the app boots config-cached with an
unreadable key.

::: tip Google
Google does **not** participate in IndexNow. For Google, use the
[Search Console](/pro/search-console) integration and a fresh sitemap.
:::
