# AI assist (beta)

Optional, **bring-your-own-key** AI assistance: title and meta-description
suggestions, plain-language explanations of scan issues, a one-click
**description rewrite**, and a **structured-data (schema.org) suggestion**.
It is **off by default** — with the flag off, no AI code path runs at all.

Three things define the design:

- **Your key, your provider.** Requests go from *your server* directly
  to the provider *you* configure — Anthropic, OpenAI, Google, or a
  local / OpenAI-compatible server — billed to your account (or free).
  Nothing is proxied, metered, or resold, and the package sends no
  telemetry anywhere.
- **Suggestions, never silent changes.** The model proposes; you pick.
  A picked suggestion or fix is only ever *applied by an explicit action*
  — it fills a form field, or writes one reviewed value when you click
  Apply — and the regular validation (60/160 counters, evaluator
  warnings, the schema validator) applies to it like any hand-typed value.
- **Always non-fatal.** A missing key, an invalid key, a rate limit, or
  a timeout produces an inline message. It can never block saving,
  rendering, or scanning.

## Setup

Enable the feature and put your provider key in the environment:

::: code-group

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Google (free tier)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

:::

Any model your key/server can access works — set a smaller model to cut
the cost per suggestion. The config file (`config/seo-pro.php`, `ai`
block) exposes `timeout` (also the cap on the synchronous call the
Filament modal makes while it opens — keep it short), `max_input_chars`
(caps what is sent), `max_output_tokens` (the base cap on what is
generated), `token_budgets` (per-task output caps for `suggestions` /
`explanation`), `reasoning_models` + `reasoning_min_output_tokens` (raise
the cap for thinking models — see below), `suggestion_count`, `retry`
(automatic retry for transient failures — see below), and the `local`
sub-block (below).

## Zero-cost providers

Two providers run the AI demo at **$0 marginal cost**, so you can try it
without a billed account:

- **Google (recommended).** Google's free Gemini tier bills nothing per
  suggestion. The default model is `gemini-2.5-flash`; the open
  `gemma-3-*` models work the same way. Both are *thinking* models — they
  spend hidden reasoning tokens before any visible output — so they are
  listed in `reasoning_models` and get the higher output-token floor
  automatically (a small budget would truncate them before they answer).
- **Local / OpenAI-compatible.** Point `provider=local` at any server
  speaking the OpenAI Chat Completions API: **Ollama**, **LM Studio**,
  **vLLM**, **LocalAI** (all local, all free), or a remote gateway like
  **OpenRouter**. Set `SEO_PRO_AI_LOCAL_BASE_URL` to the server's API root
  (`/chat/completions` is appended) and `SEO_PRO_AI_MODEL` to a model the
  server has loaded.

::: warning Local `base_url` is validated — opt in for localhost
The `base_url` is a privileged setting, validated through the same
`SsrfGuard` every other outbound fetch uses: http/https only, no
userinfo, and — by default — it must resolve to a **public** address, so
a stray or hostile `base_url` can't be turned into a probe of internal
services. A genuinely local server lives on `127.0.0.1` (a private
address), so a local provider needs the explicit opt-in
`seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`).
Leave it off for a public gateway (OpenRouter). The request path is fixed
and redirects are never followed, so the key can't be bounced to another
host.

For **Ollama** specifically, you can add `['think' => false]` to
`seo-pro.ai.local.extra_body` to skip hidden reasoning on the strict-JSON
suggestion calls and cut latency.
:::

No `temperature` is sent: some reasoning models reject it, so there is
intentionally no knob for it.

::: warning Key handling with a cached config
The config stores only the **name** of the environment variable
(`api_key_env`), never the key — so `php artisan config:cache` never
writes your key to `bootstrap/cache/config.php`. The flip side: with a
cached config, `.env` is not loaded, so set `SEO_PRO_AI_API_KEY` as a
real environment variable on the server.
:::

## In Filament

With the optional Filament packages installed
(`rankbeam/laravel-seo-filament` >= 1.1), enabling AI assist adds:

- **Suggest with AI (beta)** on the SEO title and description fields of
  every resource using the SEO section (on edit pages). The modal shows
  the generated alternatives with character counts; picking one fills
  the field for review.
- **Explain (AI)** on the dashboard issue table: a short plain-language
  explanation of the issue and the concrete fix.
- **Rewrite description (AI)** on the dashboard issue table (next to
  Explain): proposes one improved meta description, always within the
  160-character limit. Review it in the modal; clicking **Apply rewrite**
  writes it to the page's `seo_meta` record. Nothing is written until you
  apply it.
- **Suggest structured data (AI)** on the dashboard issue table: proposes
  the best schema.org rich-result type (Product, Article, or Breadcrumb)
  and shows the JSON-LD built for it. Clicking **Apply structured data**
  adds it to the page's `seo_meta.schema_jsonld` — the same column the
  optional [structured-data editor](../guide/filament#structured-data-schema-org)
  manages, so it round-trips and stays editable there. An incomplete
  suggestion (e.g. a Product with no price) is shown with the missing
  fields and is **not** applied.

These two are bounded fixes: see [Bounded fixes](#bounded-fixes-propose-never-auto-apply).

## Bounded fixes (propose, never auto-apply)

Two assist actions go one step beyond a suggestion — they produce a
single, *constrained* value you can apply in one click. Both still
**propose only**: nothing is persisted until you explicitly accept.

- **Rewrite description** (`SeoSuggestionService::rewriteDescription($model, $issue?)`)
  returns one meta description **always within the core
  `DESCRIPTION_MAX_LENGTH` (160)**. If the model overshoots, the text is
  trimmed deterministically at a sentence (then word) boundary, so an
  accepted rewrite can never itself trip the `description_too_long`
  warning. Passing the scan issue steers the rewrite (e.g. *too long* vs
  *missing*).
- **Suggest structured data** (`SeoSuggestionService::suggestSchemaType($model)`)
  asks the model only for a **type recommendation and leaf field values** —
  never raw JSON-LD. Deterministic code then assembles the document with
  the core schema builders (`ProductSchema` / `ArticleSchema` /
  `BreadcrumbSchema`) and validates it with the core `SchemaValidator`, so
  a hallucinated `@type`, `@context`, or structure can never reach the
  page. Structural facts that must be real — an Article's image / author /
  dates, a Breadcrumb's ancestor chain — are read from the model, not from
  the model's text output.

## Headless

The same capability as JSON, for scripts and non-Filament apps:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

Output includes the suggestions (or the recommended type, the built
JSON-LD, and whether it validates), the model used, and the token usage
per request. The command exits non-zero on any failure, with the error
in the JSON envelope. Like every assist surface, `seo-pro:suggest-schema`
is **propose-only** — it prints the document and writes nothing; piping it
into `seo_meta.schema_jsonld` is your decision.

## Bulk-fill missing metadata

Everything above is propose-only. The one batch surface that **writes** is
`seo-pro:ai-fill` (and `SeoPro::aiFill()`): the "fill everything" pass for a
whole model collection.

```bash
# preview what would be written (no changes saved)
php artisan seo-pro:ai-fill "App\Models\Post" --dry-run

# fill missing descriptions across all configured models
php artisan seo-pro:ai-fill --field=description

# all configured (seo.audit.models / seo.sitemap.models) models, all fields
php artisan seo-pro:ai-fill --force
```

It iterates the records, finds the ones whose **title or description is missing**
— no explicit value *and* no computed fallback, the **same definition the
[audit](/guide/audit) uses** — generates one with the suggester, and saves it.

It is deliberately conservative:

- **Only gaps are filled.** A record that already has (or can derive) the field
  is skipped; an existing value is **never overwritten**.
- **`--dry-run`** generates and prints the values without saving, so you can
  review the spend and the output first.
- **It writes**, so in production it asks for confirmation unless you pass
  `--force`. `--field` (title | description | all) and `--limit` scope the run.
- Each filled field is one suggester call billed to your key, and it runs only
  when AI assist is enabled.

### At scale: pacing, a cost estimate, and crash-resume

Filling hundreds or thousands of models is a long, **paid** operation, so the
command is built to be safe to run against a large collection:

- **Paced calls.** `seo-pro.ai.fill.throttle_ms` (default `200`) inserts a delay
  between provider calls so a big run doesn't burst into the provider's rate
  limit — the driver's own [429 retry/backoff](#how-replies-are-handled) is the
  net; this is the throttle that keeps you from needing it. Set it to `0` for a
  local or free provider that wants maximum speed, or raise it on a low tier.
- **A cost estimate you confirm first.** A run that will touch at least
  `seo-pro.ai.fill.confirm_over` records (default `100`) prints an estimate and
  asks before making a single call:

  ```text
  About to fill ~890 missing fields via anthropic (claude-opus-4-8) across 948 records.
  Estimated ~667,500 tokens ≈ $18.02 (rough, ±50%).
  Continue? (yes/no) [no]
  ```

  The dollar figure comes from the `seo-pro.ai.pricing` table (USD per 1M
  tokens, matched to the configured model). It is deliberately honest to about
  **±50%** — real input size and output length vary — and the defaults are
  approximate: **override the pricing entries with your provider's current
  prices** for an accurate number. A model with no pricing entry (e.g. a local
  model) shows the token estimate with no invented dollar figure. `--force`
  skips the prompt for automation; a dry run confirms too, because it makes the
  same paid calls.
- **Resumable — an interrupted run never re-pays.** Progress is checkpointed to
  storage after **every** record, so if the run is killed (a worker timeout, a
  deploy, Ctrl-C) the next run of the same command **resumes**: records already
  filled are skipped, not billed again. A transient failure (rate limit,
  timeout) leaves its record un-checkpointed so the resume retries exactly it.
  A clean, complete run clears its checkpoint; pass `--fresh` to ignore a prior
  run's checkpoint and start over.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'unauthorized' => 'Anthropic: authentication failed…'), and the
// seo-pro:ai-fill command prints those reasons — so a run never fails silently.
```

## How replies are handled

Every call returns one provider-neutral envelope, so the behaviour is the
same across providers (and across the ones added later):

- **Structured output where the provider supports it.** OpenAI (native
  Structured Outputs) and Google (Gemini `responseSchema`) have the JSON
  shape enforced by the API rather than coaxed by the prompt. A local /
  OpenAI-compatible server is asked for it too (`response_format`), but
  best-effort: a server that ignores the field still returns usable text,
  which is tolerantly parsed instead of failing. Prompt-only providers
  (Anthropic) always fall back to that tolerant parser. Either way you get
  a clean list of suggestions or a clear failure — never a half-parsed
  reply.
- **Truncation is an explicit, actionable error.** If a reply is cut off
  at the output-token cap, you get a `truncated` error that says to raise
  `seo-pro.ai.max_output_tokens` — not a silently shortened title. This is
  most common with **reasoning / thinking models**, which spend hidden
  tokens before producing any visible output; those models
  (`reasoning_models` patterns) automatically get a higher floor
  (`reasoning_min_output_tokens`, default 2000).
- **Transient failures are retried automatically.** A `429` rate limit or
  a `5xx` is retried with capped exponential backoff, honoring a
  `Retry-After` header when the provider sends one (bounded, so a hostile
  value can't stall the request). Deterministic failures (a bad key, a
  malformed request, an oversized payload) are **not** retried — nor are
  timeouts, which would only multiply the wait. Tune or disable it via the
  `retry` block (`max_attempts`, `base_delay_ms`, `max_delay_ms`); set
  `max_attempts` to `0` to turn retrying off. Because the Filament modal
  calls the provider synchronously, the defaults stay modest.
- **Errors are typed and sanitized.** Each failure carries a stable code
  (`unauthorized`, `rate_limited`, `timeout`, `content_too_large`,
  `bad_request`, `truncated`, `content_filtered`, `provider_error`, …) and
  a `retryable` flag for the transient ones (rate limit, timeout, provider
  5xx). The message is a short, sanitized string — the raw provider
  response body is never surfaced or logged, and a timeout reports a clean,
  actionable message rather than the underlying transport error. In
  Filament a failure renders in the styled modal partial (not a bare red
  line), with a tailored next-step hint for the common modes: a bad key
  points at the API-key env var, a rate limit suggests a free (Google) or
  local provider, a timeout names `seo-pro.ai.timeout`, and an oversized
  page names `seo-pro.ai.max_input_chars`.

## What leaves your server

Exactly this, only to your configured provider, only on explicit action
(a clicked action or an invoked command):

- *Suggestions*: the model's class basename and key (e.g. "Post #3"),
  the currently resolved title and description, the canonical URL, and
  a plain-text content excerpt (HTML stripped) capped at
  `max_input_chars` (default 6000 characters).
- *Issue explanations*: the issue's type, severity, field, message, and
  target URL, plus the affected model's resolved title/description.
- *Description rewrite*: the same minimal page context as a suggestion,
  plus — when given — the scan issue's type and message.
- *Structured-data suggestion*: the same minimal page context as a
  suggestion. The model returns only a type and leaf field values; the
  JSON-LD is assembled locally.

Never: visitor data, IP addresses, request headers, credentials, or
full HTML. The Pro repository's SECURITY.md carries the authoritative
version of this list.
