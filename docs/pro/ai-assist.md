# AI assist

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
- **Always non-fatal.** A missing key, an invalid key, an exhausted
  account, a rate limit, or a timeout produces an inline message. It can
  never block saving, rendering, or scanning.

## Providers at a glance

Pick a provider by what you already have a key for, and how much you want
to spend. All four run every surface identically — the choice only changes
cost, speed, and where the request goes.

| Provider | Default model | Structured output | Marginal cost | Best for |
|---|---|---|---|---|
| **Google** (recommended) | `gemini-2.5-flash` | Native (`responseSchema`) | **$0** on the free tier | Trying it, and most production use |
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1` (set your own) | Best-effort (`response_format`) | **$0** (self-hosted) | Privacy — nothing leaves your network |
| **OpenAI** | `gpt-5.5` | Native (Structured Outputs, strict) | ~$0.005 / suggestion | An existing OpenAI account |
| **Anthropic** | `claude-opus-4-8` | Prompt-only (text-parsed) | ~$0.015 / suggestion | Highest-quality copy, cost no object |

Notes confirmed against a live run of every provider:

- **Structured output** is enforced by the API on OpenAI and Google, asked
  for best-effort on a local server, and prompt-coaxed then tolerantly
  parsed on Anthropic. Either way a caller gets a clean list or a clear
  failure — never a half-parsed reply.
- **Google and Anthropic are the two extremes on token spend.** Gemini 2.5
  and Gemma are *thinking* models: they burn hidden reasoning tokens before
  any visible output (measured: a three-line description cost Gemini ~500
  hidden reasoning tokens on top of ~100 visible ones). Those tokens are
  billed as output — cheap on Gemini's rates, free on the free tier, but
  the reason the reasoning floor below exists. Anthropic used **no** hidden
  reasoning tokens in the same calls.
- The default model is a starting point, not a lock-in: any model your key
  or server can reach works (`SEO_PRO_AI_MODEL`), and a smaller model
  (`claude-haiku-4-5`, `gpt-5.4-mini`, `gemma-3-12b-it`) cuts cost.

## Setup

Enable the feature and put your provider key in the environment. Switch
providers by changing exactly two lines — the provider and the key.

::: code-group

```dotenv [Google (free tier)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # a free AI Studio key: aistudio.google.com/apikey
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

:::

::: tip A provider API key is separate from a Claude / ChatGPT subscription
A Claude Code, Claude.ai, or ChatGPT **subscription** does not fund the
**API**. `SEO_PRO_AI_API_KEY` must be a *pay-as-you-go API key* from the
provider's developer console (or a free Google AI Studio key), with its own
credit balance. A subscription-only account will authenticate but return an
**out of credit / quota** error — see [Troubleshooting](#troubleshooting).
:::

The config file (`config/seo-pro.php`, `ai` block) exposes `timeout`,
`max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models`
+ `reasoning_min_output_tokens`, `suggestion_count`, `retry`, the `pricing`
table, and the `local` sub-block — all covered under
[Limits and tuning](#limits-and-tuning).

::: warning Key handling with a cached config
The config stores only the **name** of the environment variable
(`api_key_env`), never the key — so `php artisan config:cache` never writes
your key to `bootstrap/cache/config.php`. The flip side: with a cached
config, `.env` is not loaded, so set `SEO_PRO_AI_API_KEY` as a real
environment variable on the server.
:::

## The two $0 providers

Two providers run every surface at **$0 marginal cost**, so you can adopt
the feature without a billed account:

- **Google (recommended).** A free **AI Studio** key
  (`aistudio.google.com/apikey`, format `AIza…`) bills nothing per
  suggestion on the free tier. The default model is `gemini-2.5-flash`; the
  open `gemma-3-*` models work the same way. Both are *thinking* models, so
  they are listed in `reasoning_models` and get the higher output-token
  floor automatically.
- **Local / OpenAI-compatible.** Point `provider=local` at any server
  speaking the OpenAI Chat Completions API: **Ollama**, **LM Studio**,
  **vLLM**, **LocalAI** (all local, all free), or a remote gateway like
  **OpenRouter**. Set `SEO_PRO_AI_LOCAL_BASE_URL` to the server's API root
  (`/chat/completions` is appended) and `SEO_PRO_AI_MODEL` to a model the
  server has loaded. Nothing leaves your network.

::: warning Local `base_url` is validated — opt in for localhost
The `base_url` is a privileged setting, validated through the same
`SsrfGuard` every other outbound fetch uses: http/https only, no userinfo,
and — by default — it must resolve to a **public** address, so a stray or
hostile `base_url` can't be turned into a probe of internal services. A
genuinely local server lives on `127.0.0.1` (a private address), so a local
provider needs the explicit opt-in
`seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`).
Leave it off for a public gateway (OpenRouter). The request path is fixed
and redirects are never followed, so the key can't be bounced to another host.
:::

::: tip Ollama thinking models are slow by default — turn reasoning off
A local reasoning model (e.g. Gemma via Ollama) spends seconds on hidden
reasoning before it answers, which can exceed the short default `timeout`.
Add `['think' => false]` to `seo-pro.ai.local.extra_body` to skip that
reasoning on the strict-JSON suggestion calls — it cuts latency sharply. If
calls still time out, raise `seo-pro.ai.timeout` (see below). No
`temperature` is ever sent: some reasoning models reject it, so there is
intentionally no knob for it.
:::

## Cost

There is no package markup — you pay the provider directly, or nothing on a
free/local provider. Two numbers matter: the **per-suggestion** cost for
interactive use, and the **bulk-fill** cost for a whole collection.

The `seo-pro.ai.pricing` table (USD per 1,000,000 tokens) turns a token
estimate into the dollar figure the bulk-fill confirm prompt shows. The
shipped defaults are approximate public prices — **override them with your
provider's current published prices** for an accurate estimate:

| Model pattern | Input $/1M | Output $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

Measured against a real page (one title + one description suggestion, the
default models), at those prices:

| Provider / model | ~ per suggestion pair | ~ per 1,000 records (bulk-fill) |
|---|---|---|
| Google `gemini-2.5-flash` (free tier) | **$0** | **$0** |
| Local `gemma`/`llama` (Ollama) | **$0** | **$0** |
| Google `gemini-2.5-flash` (paid) | ~$0.001 | ~$0.18 |
| OpenAI `gpt-5.5` | ~$0.008 | ~$5.25 |
| Anthropic `claude-opus-4-8` | ~$0.03 | ~$20 |

Bulk-fill figures match the pre-run estimate the command prints (see
[Bulk-fill](#bulk-fill-missing-metadata)); it is deliberately honest to
about ±50%, since real input size and output length vary per page. A model
with no pricing entry (any local model) shows a **token** estimate only,
with no invented dollar figure.

## Limits and tuning

Every knob lives in the `config/seo-pro.php` `ai` block:

- **`timeout`** (default `15` seconds, env `SEO_PRO_AI_TIMEOUT`) — also the
  cap on the synchronous call the Filament suggestion modal makes while it
  opens, so it is kept short for UX. A **slow reasoning or local model can
  exceed 15s** and time out; raise it via `SEO_PRO_AI_TIMEOUT` (and see the
  Ollama `think => false` tip above) if you run one. A timeout is always
  non-fatal: it produces an inline error, never a blocked save.
- **`max_input_chars`** (default `6000`) — the cost + privacy cap on how
  much page content (plain text, HTML stripped) is sent per request.
- **`max_output_tokens`** (default `1000`) — base cap on generated tokens.
  A reply that hits it returns a distinct `truncated` failure, never a
  silent half-answer.
- **`token_budgets`** — per-task output caps (`suggestions` 800,
  `explanation` 600, `rewrite` 300, `schema_suggestion` 700). None needs
  the full default, but the reasoning floor is applied on top.
- **`reasoning_models`** + **`reasoning_min_output_tokens`** (default
  `2000`) — any model whose name matches a pattern (`*gemma*`,
  `gemini-2.5-*`, `o1*`/`o3*`/`o4*`) has its output budget raised to the
  floor, because a thinking model spends hidden tokens before producing any
  visible output and a small budget would truncate it.
- **`suggestion_count`** (default `3`) — how many title/description
  alternatives to request.
- **`retry`** — automatic retry for *transient* failures only; see
  [How replies are handled](#how-replies-are-handled).

## In Filament

With the optional Filament packages installed
(`rankbeam/laravel-seo-filament` >= 1.1), enabling AI assist adds:

- **Suggest with AI** on the SEO title and description fields of every
  resource using the SEO section (on edit pages). The modal shows the
  generated alternatives with character counts; picking one fills the field
  for review.
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
  suggestion (e.g. an Article with no author or image) is shown with the
  missing fields and is **not** applied.

The last two are bounded fixes: see [Bounded fixes](#bounded-fixes-propose-never-auto-apply).

## Bounded fixes (propose, never auto-apply)

Two assist actions go one step beyond a suggestion — they produce a single,
*constrained* value you can apply in one click. Both still **propose only**:
nothing is persisted until you explicitly accept.

- **Rewrite description** (`SeoSuggestionService::rewriteDescription($model, $issue?)`)
  returns one meta description **always within the core
  `DESCRIPTION_MAX_LENGTH` (160)**. If the model overshoots, the text is
  trimmed deterministically at a sentence (then word) boundary, so an
  accepted rewrite can never itself trip the `description_too_long` warning.
  Passing the scan issue steers the rewrite (e.g. *too long* vs *missing*).
- **Suggest structured data** (`SeoSuggestionService::suggestSchemaType($model)`)
  asks the model only for a **type recommendation and leaf field values** —
  never raw JSON-LD. Deterministic code then assembles the document with the
  core schema builders (`ProductSchema` / `ArticleSchema` /
  `BreadcrumbSchema`) and validates it with the core `SchemaValidator`, so a
  hallucinated `@type`, `@context`, or structure can never reach the page.
  If the assembled document is missing a required field, it is surfaced as
  *incomplete* and withheld — in a live test, one provider proposed an
  `Article` for a thin page and it was correctly withheld for a missing
  author and image, while others declined to suggest a type at all.

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

Output includes the suggestions (or the recommended type, the built JSON-LD,
and whether it validates), the model used, and the **token usage per
request** (input / output / reasoning) — so you can see exactly what each
call cost. The command exits non-zero on any failure, with the error in the
JSON envelope. Like every assist surface, `seo-pro:suggest-schema` is
**propose-only** — it prints the document and writes nothing.

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

It iterates the records, finds the ones whose **title or description is
missing** — no explicit value *and* no computed fallback, the **same
definition the [audit](/guide/audit) uses** — generates one with the
suggester, and saves it.

It is deliberately conservative:

- **Only gaps are filled.** A record that already has (or can derive) the
  field is skipped; an existing value is **never overwritten**.
- **`--dry-run`** generates and prints the values without saving, so you can
  review the spend and the output first.
- **It writes**, so in production it asks for confirmation unless you pass
  `--force`. `--field` (title | description | all) and `--limit` scope the run.
- Each filled field is one suggester call billed to your key, and it runs
  only when AI assist is enabled.

### At scale: pacing, a cost estimate, and crash-resume

Filling hundreds or thousands of models is a long, **paid** operation, so
the command is built to be safe against a large collection:

- **Paced calls.** `seo-pro.ai.fill.throttle_ms` (default `200`) inserts a
  delay between provider calls so a big run doesn't burst into the
  provider's rate limit. Set it to `0` for a local or free provider that
  wants maximum speed, or raise it on a low tier.
- **A cost estimate you confirm first.** A run that will touch at least
  `seo-pro.ai.fill.confirm_over` records (default `100`) prints an estimate
  and asks before making a single call:

  ```text
  About to fill ~890 missing fields via anthropic (claude-opus-4-8) across 948 records.
  Estimated ~667,500 tokens ≈ $18.02 (rough, ±50%).
  Continue? (yes/no) [no]
  ```

  The dollar figure comes from the `seo-pro.ai.pricing` table (see
  [Cost](#cost)). A model with no pricing entry (e.g. a local model) shows
  the token estimate with no invented dollar figure. `--force` skips the
  prompt for automation; a dry run confirms too, because it makes the same
  paid calls.
- **Resumable — an interrupted run never re-pays.** Progress is checkpointed
  to storage after **every** record, so if the run is killed (a worker
  timeout, a deploy, Ctrl-C) the next run **resumes**: records already
  filled are skipped, not billed again. A transient failure (rate limit,
  timeout) leaves its record un-checkpointed so the resume retries exactly
  it. A clean run clears its checkpoint; pass `--fresh` to ignore a prior
  run's checkpoint and start over.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'quota_exceeded' => 'OpenAI: the provider account is out of
// credit or quota…'), and the seo-pro:ai-fill command prints those reasons —
// so a run never fails silently.
```

## How replies are handled

Every call returns one provider-neutral envelope, so the behaviour is the
same across providers (and across the ones added later):

- **Structured output where the provider supports it.** OpenAI (native
  Structured Outputs) and Google (Gemini `responseSchema`) have the JSON
  shape enforced by the API. A local / OpenAI-compatible server is asked for
  it too (`response_format`), best-effort: a server that ignores the field
  still returns usable text, which is tolerantly parsed. Prompt-only
  providers (Anthropic) always fall back to that tolerant parser. Either way
  you get a clean list or a clear failure — never a half-parsed reply.
- **Truncation is an explicit, actionable error.** If a reply is cut off at
  the output-token cap, you get a `truncated` error that says to raise
  `seo-pro.ai.max_output_tokens` — not a silently shortened title. This is
  most common with **reasoning / thinking models**; those models get the
  higher `reasoning_min_output_tokens` floor automatically.
- **Transient failures are retried automatically.** A `429` rate limit or a
  `5xx` is retried with capped exponential backoff, honoring a `Retry-After`
  header when present (bounded, so a hostile value can't stall the request).
  **Deterministic** failures are **not** retried — a bad key, a malformed
  request, an oversized payload, a **timeout**, or an **out-of-credit /
  quota** account (retrying an unfunded account only burns backoff). Tune or
  disable retrying via the `retry` block; set `max_attempts` to `0` to turn
  it off.
- **Errors are typed and sanitized.** Each failure carries a stable code
  (`unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`,
  `content_too_large`, `bad_request`, `truncated`, `content_filtered`,
  `provider_error`, …) and a `retryable` flag for the transient ones. The
  message is a short, sanitized string — **the raw provider response body is
  never surfaced or logged**. In Filament a failure renders in the styled
  modal partial with a tailored next-step hint for the common modes (see
  [Troubleshooting](#troubleshooting)).

## Troubleshooting

Every failure is inline and non-fatal, with a typed code and a sanitized
message. The common ones and their one real fix:

| Symptom (error code) | What it means | Fix |
|---|---|---|
| **`quota_exceeded`** — *"the provider account is out of credit or quota"* | The key is valid but the **API account has no credit / quota**. Not a rate limit — retrying won't help. Distinct per provider: Anthropic returns *"credit balance is too low"*, OpenAI *"exceeded your current quota… check your plan and billing"* (`insufficient_quota`), Google *"prepayment credits are depleted"*. | Add credit / enable billing in the provider's console — or switch to the **free** Google tier or a **local** model for $0. Remember a Claude/ChatGPT **subscription** does not fund the **API**. |
| **`unauthorized`** — *"authentication failed"* | The key is missing, wrong, or not valid for the configured provider. | Check the key in the env var named by `seo-pro.ai.api_key_env` (default `SEO_PRO_AI_API_KEY`) — set, current, and matching `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** — *"the provider rate limit was reached"* | A genuine, **transient** rate limit (retried automatically first). | Wait and retry, or move to a $0 provider (Google free tier / local) to avoid it. Raise `seo-pro.ai.fill.throttle_ms` for bulk runs on a low tier. |
| **`timeout`** — *"the request timed out"* | The provider didn't respond within `seo-pro.ai.timeout` (default 15s). Common with a **slow local reasoning model**. | Raise it with `SEO_PRO_AI_TIMEOUT`; for Ollama also set `['think' => false]` in `seo-pro.ai.local.extra_body`. |
| **`truncated`** — *"hit the max_output_tokens limit"* | A **thinking model** spent the budget on hidden reasoning before finishing. | Raise `seo-pro.ai.max_output_tokens` (reasoning models can need 2000+), or confirm the model matches a `reasoning_models` pattern so the floor applies. |
| **`content_too_large`** (HTTP 413) | The page content sent exceeded the provider limit. | Lower `seo-pro.ai.max_input_chars` to send a shorter excerpt. |
| **`bad_request`** | A malformed request — usually a **model name** the account can't access, or an unsupported parameter. | Check `SEO_PRO_AI_MODEL` is a model your key/server can reach for the configured provider. |
| **`content_filtered`** | The provider's safety filter declined to answer. | Rare for SEO copy; try a different provider or model. |

::: tip The fastest way out of a paid-provider problem is a free one
A missing key, an exhausted account, or a rate limit all disappear on
`SEO_PRO_AI_PROVIDER=google` with a free AI Studio key, or
`SEO_PRO_AI_PROVIDER=local` against Ollama — both $0, and the surfaces
behave identically.
:::

## What leaves your server

Exactly this, only to your configured provider, only on explicit action (a
clicked action or an invoked command):

- *Suggestions*: the model's class basename and key (e.g. "Post #3"), the
  currently resolved title and description, the canonical URL, and a
  plain-text content excerpt (HTML stripped) capped at `max_input_chars`
  (default 6000 characters).
- *Issue explanations*: the issue's type, severity, field, message, and
  target URL, plus the affected model's resolved title/description.
- *Description rewrite*: the same minimal page context as a suggestion, plus
  — when given — the scan issue's type and message.
- *Structured-data suggestion*: the same minimal page context as a
  suggestion. The model returns only a type and leaf field values; the
  JSON-LD is assembled locally.

Never: visitor data, IP addresses, request headers, credentials, or full
HTML. The Pro repository's SECURITY.md carries the authoritative version of
this list.
