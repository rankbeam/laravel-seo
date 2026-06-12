# AI assist (beta)

Pro v1.2.0 adds optional, **bring-your-own-key** AI assistance:
title and meta-description suggestions, and plain-language explanations
of scan issues. It is **off by default** — with the flag off, no AI code
path runs at all.

Three things define the design:

- **Your key, your provider.** Requests go from *your server* directly
  to the provider *you* configure (Anthropic or OpenAI), billed to your
  account. Nothing is proxied, metered, or resold, and the package sends
  no telemetry anywhere.
- **Suggestions, never silent changes.** The model proposes; you pick.
  A picked suggestion only fills the form field — saving stays explicit,
  and the regular validation (60/160 counters, evaluator warnings)
  applies to it like any hand-typed value.
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

:::

Any model your key can access works — set a smaller model to cut the
cost per suggestion. The config file (`config/seo-pro.php`, `ai` block)
exposes `timeout`, `max_input_chars` (caps what is sent),
`max_output_tokens` (caps what is generated), and `suggestion_count`.

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

## Headless

The same capability as JSON, for scripts and non-Filament apps:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17
```

Output includes the suggestions, the model used, and the token usage
per request. The command exits non-zero on any failure, with the error
in the JSON envelope.

## What leaves your server

Exactly this, only to your configured provider, only on explicit action
(a clicked action or an invoked command):

- *Suggestions*: the model's class basename and key (e.g. "Post #3"),
  the currently resolved title and description, the canonical URL, and
  a plain-text content excerpt (HTML stripped) capped at
  `max_input_chars` (default 6000 characters).
- *Issue explanations*: the issue's type, severity, field, message, and
  target URL, plus the affected model's resolved title/description.

Never: visitor data, IP addresses, request headers, credentials, or
full HTML. The Pro repository's SECURITY.md carries the authoritative
version of this list.
