---
description: "Serve a clean Markdown representation of a page to AI crawlers via content negotiation, while normal visitors keep the untouched HTML. Free, core, off by default."
---

# Markdown for bots

A page of application HTML wraps its content in navigation, scripts and layout
markup. Some AI crawlers and answer engines accept a cleaner representation when
one is offered, so this feature can serve a **markdown representation** of a page
to clients that ask for it via content negotiation — while every normal visitor
keeps getting your HTML, untouched. It's an opt-in compatibility choice, not a
promise about how any given client parses or uses the result.

It pairs with [AI crawler control](/guide/ai-crawlers): that decides *whether* a
bot may fetch you; this decides *what* it gets when it does.

This is a free, core feature, and it's **off by default**.

## How it works

Turn it on, and a content-negotiation middleware is registered. After your
normal response is produced, it swaps in markdown **only when both** are true:

1. **The request asks for markdown** — an explicit `Accept: text/markdown`
   header, a `?format=md` query, or (opt-in) a known AI crawler by user-agent.
2. **A markdown source resolves for the route.**

Otherwise the response passes through unchanged — a browser is never affected,
and only a successful **HTML** response is ever replaced (never JSON, a
redirect, or a download).

```bash
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Where the markdown comes from

The middleware resolves a markdown source for the matched route, in this order:

### 1. A model's own markdown

A route-bound model that implements `toSeoMarkdown()` controls its output
exactly (implement the `ProvidesSeoMarkdown` contract, or just add the method):

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. A registered route source

For routes without a model (or to override), register a source by route name:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. The built fallback

When a route-bound `HasSEO` model has no `toSeoMarkdown()`, the middleware builds
a basic document from the resolved **title** (as an H1), the **description**, and
the model's **`getContentForSEO()`**:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Content is served as-is
The fallback emits `getContentForSEO()` verbatim. If your content is HTML rather
than markdown, implement `toSeoMarkdown()` to control the conversion. Disable the
fallback entirely with `seo.markdown_for_bots.build_from_content = false`.
:::

## Configuration

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Leave `serve_to_known_bots` off to negotiate purely on the explicit `Accept` /
`?format` signal; turn it on to also hand markdown to GPTBot, ClaudeBot,
PerplexityBot and the rest (identified via the
[AI-crawler catalog](/guide/ai-crawlers)) even when they don't ask.
