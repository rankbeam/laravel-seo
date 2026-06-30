# MCP server

The Rankbeam MCP server lets an AI assistant **read — and optionally edit — a
site's SEO** over the [Model Context Protocol](https://modelcontextprotocol.io).
Point an MCP client (Claude Code / Claude Desktop, Cursor, …) at your Laravel
app and it can resolve a page's metadata, run an audit, read the Pro score, see
your AI-crawler policy, and (when you allow it) write SEO back.

It's a **dependency-free**, self-contained stdio server — no SDK, no new
packages — and runs on PHP 8.2–8.4 × Laravel 11/12/13.

::: tip Pro feature
The MCP server ships with `rankbeam/laravel-seo-pro`. It is read-only by default;
edits are opt-in.
:::

## Wiring an AI client

The server speaks JSON-RPC over **stdio**: the client launches an Artisan command
and talks to it over the pipe. Register it with your client like any local MCP
server:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["artisan", "seo-pro:mcp"],
      "cwd": "/path/to/your/app"
    }
  }
}
```

(For Claude Code: `claude mcp add rankbeam-seo -- php artisan seo-pro:mcp`.)

That's it — the assistant can now call the tools below.

## Tools

### Read (always available)

| Tool | What it does |
| --- | --- |
| `seo_resolve` | The fully-resolved SEO metadata (title, description, canonical, robots, Open Graph, JSON-LD) for a model record. |
| `seo_audit` | The in-process [metadata audit](/guide/audit) for a model record (or the first N records). |
| `seo_score` | The latest persisted [Pro SEO score](/pro/scoring) (0–100 + grade) for a model record. |
| `seo_robots_directives` | The managed [AI-crawler robots.txt directives](/guide/ai-crawlers) and the resolved per-bot policy. |

### Edit (opt-in)

| Tool | What it does |
| --- | --- |
| `seo_save_meta` | Write SEO metadata (title, description, canonical, robots, OG, Twitter, JSON-LD) onto a model record via `saveSEO()`. |

`seo_save_meta` is **not advertised or runnable** unless you turn edits on:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,
],
```

## Safety: the model allowlist

Every model-scoped tool can only ever touch a `HasSEO` model on the allowlist —
an AI client can never point a tool at an arbitrary class:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

When `models` is empty it falls back to your configured `seo.audit.models` /
`seo.sitemap.models`, so the MCP shares the same surface the rest of the package
already operates on. A tool asked for a non-allowlisted model returns an error
result the assistant can read.

## Configuration

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // the seo-pro:mcp command refuses to run when false
    'allow_edits' => false,          // expose + permit seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP handshake
],
```

## Headless / extending

`SeoPro::mcp()` returns the tool registry, so you can introspect the exposed
tools or register your own:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

A custom tool implements `McpTool` (`name`, `description`, `inputSchema`,
`isEnabled`, `handle`) — extend `AbstractTool` to reuse the model-allowlist
resolution.
