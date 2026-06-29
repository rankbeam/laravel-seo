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

::: tip The key never enters cached config
Config stores the env var **name** (`indexnow.key_env`), not the key itself, so
`config:cache` never writes the secret to `bootstrap/cache`. The key is read at
call time and never logged — the same posture as the Search Console credentials.
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
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // NAME of the env var holding the key
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

::: tip Google
Google does **not** participate in IndexNow. For Google, use the
[Search Console](/pro/search-console) integration and a fresh sitemap.
:::
