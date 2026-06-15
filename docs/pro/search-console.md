# Search Console (read-only)

A **read-only** Google Search Console panel: your top queries and pages with
**impressions, clicks, CTR and average position**, joined to the pages the
scanner already knows about — so you can see *"this page has issues **and** is
losing impressions"* in one place. It is **off by default**.

Three things define the design:

- **Strictly read-only.** The integration requests a single OAuth scope —
  `webmasters.readonly` — hard-coded in the package. It can read Search
  Analytics and nothing else: it never submits a sitemap, requests indexing,
  or changes anything in Search Console. There is no config knob to widen the
  scope.
- **Your property, your credentials.** Requests go from *your server* directly
  to Google, authenticated with *your* service-account or OAuth credential.
  Nothing is proxied, metered, or resold, and the package sends no telemetry.
- **Always non-fatal.** A missing credential, a 403, a quota error, or a
  timeout produces an inline message — never a blocked page render or a failed
  command.

## What you get

- **Pages needing attention** — the join that matters: pages with **open scan
  issues** that are **still drawing search traffic**, worst opportunity first
  (most impressions among the broken pages). Fix these before anything else.
- **Top pages** and **Top queries** — the usual Search Analytics tables.

In the Filament dashboard it is a **Search Console** page under the *SEO* nav
group (it only appears when the integration is enabled). Headless, the same
metrics come from the `seo-pro:search-console` command and
`SeoPro::searchConsole()`.

## Setup

You need a Google credential that can read the Search Console property. Two
modes are supported; a **service account** is the simplest for a server.

### Service account (recommended)

1. In Google Cloud, enable the **Search Console API** and create a
   **service account**; download its JSON key.
2. In Search Console → *Settings → Users and permissions*, add the service
   account's email (`…@….iam.gserviceaccount.com`) as a user (Restricted is
   enough for read-only).
3. Point the package at the key and the property:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

If `SEO_PRO_GSC_SITE_URL` is omitted, a URL-prefix property is derived from
`app.url`.

### OAuth (offline refresh token)

If you already have an OAuth client and a long-lived **refresh token**
(ideally minted with the `webmasters.readonly` scope — but even a broader
token is **down-scoped to read-only** on every refresh):

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Publish the token migration

The encrypted access-token cache lives in a `seo_gsc_tokens` table. Publish and
migrate once:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Then confirm the wiring with `php artisan seo:doctor` — it reports whether
Search Console is on and configured (no network call, never prints a secret).

## Headless usage

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Data handling & security

- **Read-only scope, by construction.** Only `webmasters.readonly` is ever
  requested — the service-account JWT pins it, and the OAuth refresh request
  **narrows** the minted token to it (so even an over-scoped refresh token can't
  produce a write-capable token). The package contains no call to any mutating
  Search Console endpoint.
- **Credentials stay in the environment.** The service-account key / OAuth
  secret + refresh token are read from the **named** environment variables at
  call time, exactly like the AI key — so `php artisan config:cache` never
  writes them into `bootstrap/cache/config.php`.
- **Tokens are encrypted at rest.** The short-lived access token minted from
  your credential is stored **encrypted** (app-key encryption) in
  `seo_gsc_tokens` and reused until it nears expiry, so the token exchange does
  not run on every view. The long-lived credential is never stored in the
  database — only in your environment.
- **Every request is SSRF-guarded.** The token exchange and the Search
  Analytics call both go through the shared `SsrfGuard` (HTTPS-only, the host
  must resolve to a public address) with redirects disabled, so a request can
  never be bounced to an internal service.
- **Nothing secret is logged.** Access tokens, keys, and auth headers are never
  written to logs; an API error surfaces only Google's own sanitized,
  length-capped error message.
- **Metrics are cached locally** for `seo-pro.search_console.cache_ttl` seconds
  (default 30 minutes) so the panel doesn't re-hit the API on every render.
  Search Analytics returns aggregated metrics; the package stores none of it
  permanently — only the in-memory/cache report and the encrypted access token.

## Configuration reference

All keys live under `config/seo-pro.php` → `search_console`:

| Key | Default | Purpose |
| --- | --- | --- |
| `enabled` | `false` | Master switch (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` or `oauth`. |
| `site_url` | derived from `app.url` | The property (`https://example.com/` or `sc-domain:example.com`). |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Name** of the env var with the key JSON or its path. |
| `oauth.client_id` | — | OAuth client id (not secret). |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Name** of the env var with the client secret. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Name** of the env var with the refresh token. |
| `default_days` | `28` | Reporting window (ends 3 days back — GSC data lags). |
| `row_limit` | `100` | Top-N rows per report (API max 25000). |
| `cache_ttl` | `1800` | Seconds a fetched report is cached. |
