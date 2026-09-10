---
description: "Optional bring-your-own-key AI help: title and meta suggestions, plain-language scan-issue explanations, one-click rewrites and schema.org suggestions. Off by default."
---

# AI assist

Optional, **bring-your-own-key** AI assistance: title and meta-description
suggestions, plain-language explanations of scan issues, a one-click
**description rewrite**, and a **structured-data (schema.org) suggestion**.
It is **off by default** — with the flag off, no AI code path runs at all.

Three things define the design:

- **Your key, your provider.** Requests go from *your server* directly
  to the provider *you* configure — Anthropic, OpenAI, Google, or a
  local / OpenAI-compatible server — billed to your account where applicable.
  Nothing is proxied, metered, or resold, and the package sends no
  telemetry anywhere.
- **Interactive suggestions require explicit acceptance.** The model proposes; you pick.
  A picked suggestion or fix is only ever *applied by an explicit action*
  — it fills a form field, or writes one reviewed value when you click
  Apply — and the regular validation (the script-aware length counters,
  evaluator warnings, the schema validator) applies to it like any hand-typed
  value. The bulk-fill command below is an explicitly invoked write operation
  and does not require reviewing each generated field before saving.
- **Always non-fatal.** A missing key, an invalid key, an exhausted
  account, a rate limit, or a timeout produces an inline message. It can
  never block saving, rendering, or scanning.

## Providers at a glance

Choose by account access, data requirements and cost. All four integrations expose
the same tasks, but model support, output format, speed and quality can vary.

| Provider | Shipped default model | Structured output | Illustrative cost | Use |
|---|---|---|---|---|
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1` (set your own) | Best-effort (`response_format`) | **$0 API fee** for self-hosted inference; infrastructure costs remain | Control over the data destination |
| **OpenAI** | `gpt-5.5` | Structured Outputs where the selected model supports it | ~$0.005 / suggestion at the example assumptions below | An existing OpenAI account |
| **Anthropic** | `claude-opus-4-8` | `output_config.format` where supported | ~$0.015 / suggestion at those assumptions | An existing Anthropic account |
| **Google** | `gemini-2.5-flash` | `responseSchema` where supported | ~$0.0005 / suggestion at those assumptions | A Google account; check model quotas and prices |

These names describe shipped configuration, not guaranteed current availability
on your account. Costs use the package's example assumptions, not verified current
prices. Integration behavior and observations from the published trials:

- **Structured output.** Supported OpenAI, Google and Anthropic paths receive a
  JSON schema; invalid responses fail cleanly. Local servers receive
  `response_format` best-effort. If ignored, tolerant parsing returns either a
  valid list or a failure, without applying partial output.
- **Reasoning changes token consumption.** The described Gemini trial used about
  500 hidden reasoning tokens plus about 100 visible tokens for a description;
  the Anthropic calls tested reported no hidden reasoning tokens. This is not a
  universal property of those model families. Hidden tokens can be billed as
  output, which is why the reasoning floor below exists.
- **Models are configurable.** Set `SEO_PRO_AI_MODEL` to an available model
  compatible with the adapter's API and parameters. Examples include
  `claude-haiku-4-5`, `gpt-5.4-mini` and `gemma-3-12b-it`; verify support and
  output quality before using a model across a collection.

## Setup

Enable the feature and put your provider key in the environment. Switch
cloud providers by updating provider and key, checking any model override too.
The local adapter also needs its server URL.

::: code-group

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

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip A provider API key is separate from a Claude / ChatGPT subscription
A Claude Code, Claude.ai, or ChatGPT **subscription** does not fund the
**API**. `SEO_PRO_AI_API_KEY` must be a *pay-as-you-go API key* from the
provider's developer console (or a Google AI Studio key), with its own
credit balance. A subscription-only or unfunded account will authenticate but
return an **out of credit / quota** error — see [Troubleshooting](#troubleshooting).
:::

The config file (`config/seo-pro.php`, `ai` block) exposes `timeout`,
`max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models`
+ `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` (the cheap
tier for bulk-fill — see [Cost](#cheaper-bulk-generation)), `retry`,
the `pricing` table, and the `local` sub-block — all covered under
[Limits and tuning](#limits-and-tuning).

::: warning Key handling with a cached config
The config stores only the **name** of the environment variable
(`api_key_env`), never the key — so `php artisan config:cache` never writes
your key to `bootstrap/cache/config.php`. The flip side: with a cached
config, `.env` is not loaded, so set `SEO_PRO_AI_API_KEY` as a real
environment variable on the server.
:::

## Local inference and cloud options {#running-at-0-and-the-cheapest-paid-option}

- **Self-hosted inference avoids a provider's per-token API fee.** Hardware,
  electricity and operations still cost money. Content stays in your network
  only when the configured inference server and its dependencies stay there.
- **Google has model- and tier-specific quotas and pricing.** An AI Studio key
  (`aistudio.google.com/apikey`, format `AIza…`) may permit free-tier trials;
  check whether its limits fit your workload before enabling billing as needed.
  `gemini-2.5-flash` is the shipped default. Gemini and Gemma models do not all
  have the same thinking capabilities: `reasoning_models` applies configured
  name patterns, not a capability test.

To control where inference runs:

- **Local / OpenAI-compatible.** Use `provider=local` with an OpenAI Chat
  Completions-compatible server such as **Ollama**, **LM Studio**, **vLLM** or
  **LocalAI**, or a remote gateway such as **OpenRouter**. Set
  `SEO_PRO_AI_LOCAL_BASE_URL` to its API root (`/chat/completions` is appended)
  and choose an available `SEO_PRO_AI_MODEL`. A remote gateway receives the data
  outside your network and may charge you: the adapter name `local` does not
  imply local inference.

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

::: tip Control thinking on supported Ollama models
A local thinking model can exceed the default timeout. If the model and server
version support it, `['think' => false]` in `seo-pro.ai.local.extra_body` can
disable thinking for suggestions. Support varies; check the
[Ollama documentation](https://docs.ollama.com/capabilities/thinking). Raise
`seo-pro.ai.timeout` if needed. The package does not send `temperature`, since
some models reject it.
:::

## Cost

There is no package markup — you pay the provider directly, with no provider API fee for self-hosted inference (infrastructure costs remain). Two numbers matter: the **per-suggestion** cost for
interactive use, and the **bulk-fill** cost for a whole collection.

The `seo-pro.ai.pricing` table (USD per 1,000,000 tokens) turns a token
estimate into the dollar figure the bulk-fill confirm prompt shows. These are
**shipped estimation assumptions**, not verified current public prices — **override them with your
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

The published example uses a tested page's token consumption (one title and one
description) with those assumptions. The final column applies the example 50%
batch discount to supported adapters. These are not current-price quotations.

| Provider / model | ~ per suggestion pair | ~ per 1,000 records (bulk-fill) | ~ per 1,000 records (`--batch`) |
|---|---|---|---|
| Local `gemma`/`llama` (Ollama) | **$0 API fee** | **$0 API fee** | n/a (not implemented by adapter) |
| Google `gemini-2.5-flash` (paid) | ~$0.001 | ~$0.40 | n/a (not implemented by Rankbeam adapter) |
| OpenAI `gpt-5.5` | ~$0.008 | ~$5.25 | **~$2.63** (50% off) |
| Anthropic `claude-opus-4-8` | ~$0.03 | ~$20 | **~$10** (50% off) |

The command labels its estimate approximately ±50%, but **that is not a spending
cap or a guaranteed error range**. Actual inputs, outputs and prices change the
total. The estimate models visible output; billed hidden reasoning can increase
cost beyond it. Models with no pricing entry show only a token estimate.

### Cheaper bulk generation

Set `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`) to use a different model
**only for bulk-fill** (`seo-pro:ai-fill` / `SeoPro::aiFill()`). Filament and
`seo-pro:ai-suggest` keep `model`. When null, bulk-fill uses `model` too. The
estimate uses the selected model's price pattern. Evaluate representative output
before increasing volume; a cheaper model is not automatically suitable.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Package examples use **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`,
**google** `gemini-2.5-flash`, or a smaller **local** model. A pricing pattern can
match a name even if the provider does not offer it: confirm the actual model ID,
API compatibility and price before configuring it.

A **100-page fill** (each page missing both title and description = 200
provider calls), priced from the shipped `pricing` defaults and the
estimator's per-call token model (600 input + 150 output tokens/call), quality
model vs its `bulk_model` cheap tier:

| Provider | Quality model — 100 pages | `bulk_model` cheap tier — 100 pages |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **Local** (Ollama / vLLM) | any model — **$0 API fee** | any model — **$0 API fee** |

These are illustrative estimates, with no guaranteed ±50% range and no hidden
reasoning allowance. Update `seo-pro.ai.pricing` with the selected provider's
published prices before relying on the estimate.

## Output language

Every prompt names the page's language and its BCP-47 code — *"in Brazilian
Portuguese (pt-BR), the language of the page, regardless of any other language
in the excerpt"* — and the page context sent to the model carries a
`Language:` line (Pro 2.34). Before that the prompts said "in the same language
as the source content", which left the model to guess from a short or
code-mixed excerpt: a Turkish page with an English brand name in its excerpt
could come back in English. The locale is the one the page's metadata resolved
under (the app locale when the page has none), the same locale the
[length budget](/guide/multilingual#title-and-description-budgets-per-script)
is chosen for, so a Japanese page asks for ~30-character titles *in Japanese*.

Since Pro 2.36, an explicit content locale controls the metadata row, content hooks
and prompt language together. The operator's interface language stays unchanged.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Existing positional arguments are unchanged. Omit `locale:` to use the model's
`seoData()` default, which lets separate translation models declare their language.
The excerpt uses `getContentForSEO()` when it returns nonempty content, then falls
back to the configured content fields. Filament 1.11 passes the selected tab's
locale automatically, including its single-locale and page-switcher modes.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Bulk `plan()`, `fill()` and `submitBatchFill()` also accept a trailing `locale:`.
Use the same locale when creating `FillProgress(..., locale: 'it')` and submitting
the run. Each batch item records its content locale; collection uses that saved
locale and rechecks its metadata row before writing. Explicit locale runs have
separate checkpoint files and processed markers distinguish languages. Re-run the
same command to collect. Custom jobs should serialize and pass the content locale.

Checkpoints created before Pro 2.36 did not record a content locale. An outstanding
legacy batch is retained and rejected for automatic collection. Reconcile its
provider results and intended locale before discarding it with `--fresh`; otherwise
a replacement submission can bill the same work again. A legacy sequential
checkpoint with processed records likewise requires reconciliation before reset.

### Per-language evaluation

The Pro source repository contains 170 input pages across 17 locales, plus an
opt-in evaluation harness. The input pages have structural and base-language
heuristic checks; independent native approval remains pending.

The harness checks **both titles and descriptions**, records grapheme lengths and
language/script evidence, and preserves each provider response before assertions.
Short titles, mixed text and shared Chinese/Japanese characters can remain
uncertain. A Portuguese base-language guess does not establish Brazilian usage,
and the partial Chinese character checks do not certify regional writing quality.

Live runs require `SEO_PRO_AI_EVAL=1`, an explicit `SEO_PRO_AI_EVAL_LOCALES`
selection and a `SEO_PRO_AI_EVAL_RUN` ID. They can incur provider charges; none run
by default. Each run binds its evidence to the provider, requested/returned model,
fixture/request/code hashes and timestamps. Failed attempts remain available.
Resuming reuses saved responses; an interrupted request needs an explicit retry
because it may already have reached the provider.

Evidence is stored under `storage/app/seo-ai-evals/<run-id>/` in the source test
environment. The fixture `README.md` documents the versioned schema and commands.
Native reviewers score exact output hashes in separate review records. A passing
automatic check is not native approval or a guarantee of publishable copy.

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
  page's description budget (160 characters for Latin text, ~80 for CJK —
  the core [length policy](/guide/multilingual#title-and-description-budgets-per-script)).
  Review it in the modal; clicking **Apply rewrite**
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
  returns one meta description **always within the core length policy's
  budget for the page's script (160 for Latin, ~80 for CJK)** — the same
  budget the title and description suggestion prompts carry, chosen from the
  page's own resolved value. If the model overshoots, the text is
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
request** (input / output / reasoning) — to help calculate cost at your provider’s rates; token counts are not an invoice. The command exits non-zero on any failure, with the error in the
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
  review the output without database writes. **A dry run still calls the
  provider and can incur charges.**
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
- **Checkpointed resume.** Progress is stored after **every record**. Completed,
  recorded fields are skipped on resume; transient failures leave a record open
  for retry. **This cannot guarantee no duplicate charge:** a request may reach
  the provider before a timeout or interruption prevents its result being saved.
  A clean run clears its checkpoint. Reconcile uncertain work before using
  `--fresh` to ignore the previous checkpoint.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'quota_exceeded' => 'OpenAI: the provider account is out of
// credit or quota…'), and the seo-pro:ai-fill command prints those reasons —
// so a run never fails silently.
```

### Batch mode (50% cheaper)

For a large fill where you don't need the results in the next minute, `--batch`
routes the whole run through the provider's **asynchronous batch endpoint** —
[Anthropic Message Batches](https://docs.anthropic.com/en/docs/build-with-claude/batch-processing)
or the [OpenAI Batch API](https://platform.openai.com/docs/guides/batch) — which
bill at **half** the per-token price. The Rankbeam Google and local **adapters do not implement this batch path**,
so `--batch` prints a notice and runs sequentially. Google offers its own
[Batch API](https://ai.google.dev/gemini-api/docs/batch-api); this integration
does not use it. Check current model support and prices for each provider.

A batch is **submit-now, collect-later**, split across two runs of the same
command so the process can be closed in between:

```bash
# 1) Submit: builds one request per missing field, sends the whole batch in a
#    single call, prints the discounted estimate, and exits. Nothing is written yet.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

#    → Batch submitted: msgbatch_01Hkc… — 890 requests across 890 records via anthropic.
#      Most batches finish within an hour (max 24h, then they expire).
#      Re-run the SAME command to poll and apply the results:
#        php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

# 2) Collect: re-run the same command. While the batch is still processing it
#    just says so and exits; once results are ready it writes them and prints
#    the usual summary.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

- **Same requests, half the price.** Each batched item uses the same
  prompt, structured-output schema and model configuration as the synchronous call
  (the cheap [`bulk_model`](#cheaper-bulk-generation) when set). The batch envelope and execution timing differ.
- **The pre-run estimate is the discounted one.** Over the
  `seo-pro.ai.fill.confirm_over` threshold, submit prints the **50%-off** figure
  and asks before sending anything (`--force` skips it for automation).
- **Survives being closed.** The provider batch id and the request→record map
  are persisted with the same crash-resume bookkeeping as a sequential run, so
  the collect step works from a fresh process (a later cron tick, a redeploy).
  If the submitting run is killed in the split second between the provider
  accepting the batch and its id being written, the next run **fails closed** —
  it tells you a batch may be in flight and to check the provider dashboard,
  rather than silently re-submitting the whole (paid) batch.
- **Never overwrites.** The no-overwrite rule holds across the *entire* batch
  window: at collect time each record is re-checked, and only fields **still
  missing** are written — a title or description you added by hand while the
  batch was running is never clobbered.
- **Partial results are safe.** Results are matched back to records by id, in any
  order. A record whose field succeeded is written and marked done. A record that
  failed **transiently** (rate limit, timeout, provider error, or an expired
  item) is left open, so running `--batch` again submits a fresh, smaller batch
  for exactly those. A record the provider **rejected outright** (content filter,
  invalid request) is recorded and *not* retried — a doomed record can't loop and
  re-bill.
- **Switching provider mid-batch is refused, not silently mis-handled.** If you
  change `SEO_PRO_AI_PROVIDER` between submit and collect, the collect step stops
  with a clear message (switch back to collect, or `--fresh` to discard) instead
  of polling the wrong API. Run a single `--batch` submit at a time (don't fire
  two concurrent submits for the same models/field).
- **One knob:** `seo-pro.ai.fill.batch.request_timeout` (default `120`s,
  env `SEO_PRO_AI_FILL_BATCH_TIMEOUT`) — the HTTP timeout for the submit /
  poll / collect calls (its own, longer value than the short synchronous
  `timeout`, because a submit uploads every request and a collect streams the
  whole result file). Raise it for very large runs.

::: tip Schedule the collect
Because submit and collect are independent, a natural pattern is to submit from
a deploy hook or a one-off command and let a scheduled `seo-pro:ai-fill … --batch`
(every 15–30 min) poll and apply the moment the batch finishes — no long-running
process babysitting the run.
:::

## How replies are handled

Every call returns one provider-neutral envelope, so the behaviour is the
same across providers (and across the ones added later):

- **Structured output where the provider supports it.** OpenAI (native
  Structured Outputs), Google (Gemini `responseSchema`), and Anthropic
  (`output_config.format`) all have the JSON shape enforced by the API — a
  reply that is not valid JSON is a clean failure, never text-scraped. A
  local / OpenAI-compatible server is asked for it too (`response_format`),
  best-effort: a server that ignores the field still returns usable text,
  which is tolerantly parsed as the fallback. Either way you get a clean list
  or a clear failure — never a half-parsed reply.
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
  not surfaced or logged by the normal application path; the opt-in evaluation
  harness above does preserve responses as evidence**. In Filament a failure renders in the styled
  modal partial with a tailored next-step hint for the common modes (see
  [Troubleshooting](#troubleshooting)).

## Troubleshooting

Every failure is inline and non-fatal, with a typed code and a sanitized
message. The common ones and their one real fix:

| Symptom (error code) | What it means | Fix |
|---|---|---|
| **`quota_exceeded`** — *"the provider account is out of credit or quota"* | The key is valid but the **API account has no credit / quota**. Not a rate limit — retrying won't help. Distinct per provider: Anthropic returns *"credit balance is too low"*, OpenAI *"exceeded your current quota… check your plan and billing"* (`insufficient_quota`), Google *"prepayment credits are depleted"*. | Add credit / enable billing in the provider's console — or switch to a **local** model with no provider API fee. Remember a Claude/ChatGPT **subscription** does not fund the **API**. |
| **`unauthorized`** — *"authentication failed"* | The key is missing, wrong, or not valid for the configured provider. | Check the key in the env var named by `seo-pro.ai.api_key_env` (default `SEO_PRO_AI_API_KEY`) — set, current, and matching `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** — *"the provider rate limit was reached"* | A genuine, **transient** rate limit (retried automatically first). | Wait and retry, or move to a **local** model (subject to your server’s capacity) to avoid it. Raise `seo-pro.ai.fill.throttle_ms` for bulk runs on a low tier. |
| **`timeout`** — *"the request timed out"* | The provider didn't respond within `seo-pro.ai.timeout` (default 15s). Common with a **slow local reasoning model**. | Raise it with `SEO_PRO_AI_TIMEOUT`; for Ollama also set `['think' => false]` in `seo-pro.ai.local.extra_body`. |
| **`truncated`** — *"hit the max_output_tokens limit"* | The reply hit its output budget, possibly including hidden reasoning. | Raise `seo-pro.ai.max_output_tokens` (reasoning models can need 2000+), or confirm the model matches a `reasoning_models` pattern so the floor applies. |
| **`content_too_large`** (HTTP 413) | The page content sent exceeded the provider limit. | Lower `seo-pro.ai.max_input_chars` to send a shorter excerpt. |
| **`bad_request`** | A malformed request — usually a **model name** the account can't access, or an unsupported parameter. | Check `SEO_PRO_AI_MODEL` is a model your key/server can reach for the configured provider. |
| **`content_filtered`** | The provider's safety filter declined to answer. | Review the content and the provider’s guidance; do not automatically repeat the rejected request. |

::: tip Local inference still needs a working server
`SEO_PRO_AI_PROVIDER=local` against self-hosted inference avoids cloud-provider
credit problems. Hardware, model, API compatibility, timeout and capacity still
matter. A remote gateway configured with this adapter can require a key and payment.
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

The package does not deliberately collect visitor data, IP addresses, request
headers or credentials into prompts, and does not send full HTML. **Your content
fields and excerpts can themselves contain sensitive information**: review what
your application exposes. The provider credential is used to authenticate the
request. The Pro repository’s SECURITY.md is the data-handling reference.
