# MCP server

The Rankbeam MCP server lets an AI assistant **read — and optionally edit — a
site's SEO** over the [Model Context Protocol](https://modelcontextprotocol.io).
Point an MCP client (Claude Code / Claude Desktop, Cursor, Codex, …) at your
Laravel app and it can resolve a page's metadata, run an audit, read the Pro
score, see your AI-crawler policy, and — when you allow it — write SEO back.

It's a **dependency-free**, self-contained stdio server — no SDK, no new
packages — and runs on PHP 8.2–8.4 × Laravel 11/12/13.

::: tip Pro feature
The MCP server ships with `rankbeam/laravel-seo-pro`. It is **read-only by
default**; edits are opt-in behind a config flag and a model allowlist.
:::

## What the assistant can do

### Analysis tools (always available)

| Tool | What it does |
| --- | --- |
| `seo_resolve` | The fully-resolved SEO metadata (title, description, canonical, robots, Open Graph, JSON-LD) for a model record — what the page would actually render. |
| `seo_audit` | The in-process [metadata audit](/guide/audit) for a model record (or the first N records) — the same `seo:audit` checks, live, no queue. |
| `seo_score` | The latest persisted [Pro SEO score](/pro/scoring) (0–100 + grade) for a model record. |
| `seo_robots_directives` | The managed [AI-crawler robots.txt directives](/guide/ai-crawlers) and the resolved per-bot allow/disallow policy. |
| `validate_schema` | Validate a JSON-LD object — or an allowlisted model's resolved schema graph — against the core structured-data validator (Google rich-result requirements per `@type`). |
| `analyze_robots` | The authoritative allow/disallow verdict **per known AI crawler**, plus what decided it (a per-bot override, the purpose policy, or the default). The policy is site-global. |
| `debug_social_share` | The resolved Open Graph + Twitter card a social crawler would actually see for a model record (after fallbacks), with advisory card-health notes. |
| `check_meta` | A focused meta-health view — resolved title/description/canonical/robots/og:image with lengths and presence, plus the audit issues — for one model record. |

### Site-content tools (always available)

The "talk to your site" tools — they turn the server into a content-aware
assistant that can enumerate and search your pages.

| Tool | What it does |
| --- | --- |
| `list_pages` | List an **allowlisted** model's SEO-managed pages (records) — each with its URL and effective title. Supports `limit`/`offset` paging. |
| `search_pages` | Full-text search across an **allowlisted** model's pages — Laravel [Scout](https://laravel.com/docs/scout) when the model is searchable, a safe SQL `LIKE` (title/name/headline + joined SEO meta) otherwise. Returns URL, title, and a snippet per hit. |

### Ops tools (opt-in)

Operational tools that read scan state and change site configuration. Like the
edit tool, they are **gated behind `allow_edits`** — invisible and inert on a
read-only server (the default).

| Tool | What it does |
| --- | --- |
| `list_issues` | The current open SEO scan issues (the durable cross-run open set) and the latest [scan](/pro/scan-issues) run header. Filter by `severity` / `type`. |
| `trigger_scan` | Start a scan: a targeted scan of one allowlisted record (returns the run), or a full scan of every target — queued by default, or inline with `sync: true`. |
| `create_redirect` | Create a redirect rule (source path or regex → target, status `301`/`302`/`307`/`308`/`410`), reusing the redirect model's own validators. |

### Edit tool (opt-in)

| Tool | What it does |
| --- | --- |
| `seo_save_meta` | Write SEO metadata (title, description, canonical, robots, OG, Twitter, JSON-LD) onto an **allowlisted** model record via `saveSEO()`. |

The ops tools and `seo_save_meta` are **not advertised in `tools/list` and not
runnable** unless you turn edits on — see [Security](#security). A read-only
server never even tells the assistant those tools exist.

## Wiring an AI client

The server speaks JSON-RPC over **stdio**: the client launches an Artisan
command and talks to it over the pipe. Register it with whichever client(s) you
use — the same server works for all of them.

::: tip One command, any client
Every client below runs the identical launch command:
`php artisan seo-pro:mcp`, started **from your app's root** (so Artisan can
bootstrap the app). On a machine where `php` isn't on the client's `PATH`
(common on Windows, or for GUI apps that don't inherit your shell environment),
give the **absolute path to both `php` and `artisan`** — Artisan boots from the
`artisan` script's own directory, so this needs no `cwd`.
:::

### Claude Code (CLI)

One command registers it. Run it **from your app's root**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Confirm it connected:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

On Windows / Laravel Herd, pin the absolute paths so it works regardless of the
launch directory:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop

Edit the config file (**Settings → Developer → Edit Config**), or open it
directly:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

On **Windows**, use the absolute `php.exe` and double-escape every backslash in
JSON:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Fully quit and reopen Claude Desktop. The tools appear behind the
tools/plug icon in the message bar.

### Cursor

Create `.cursor/mcp.json` in your project (or `~/.cursor/mcp.json` for all
projects). Give the absolute `artisan` path so it launches from anywhere:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

On Windows, use the absolute `php.exe` and double-escaped backslashes, exactly
as in the Claude Desktop example above. Enable the server in
**Settings → MCP**.

### Codex CLI

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

As with Claude Code, pass absolute `php` + `artisan` paths on Windows/Herd.

That's it — the assistant can now call the tools.

## A worked conversation

Here is a real exchange against a demo app (a 900-page medical-institute site),
with the tools' actual output abbreviated. The two read models on the allowlist
are `App\Models\Page` and `App\Models\PublicPage`, and `allow_edits` is on.

> **You:** How's the SEO on the "Accoglienza" page (Page id 1)? Fix anything obvious.

The assistant first **audits** it — `seo_audit { model: "App\\Models\\Page", id: 1 }`:

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

It **resolves** the current meta to see what's actually rendering —
`seo_resolve { model: "App\\Models\\Page", id: 1 }` — and finds a 17-character
title and an 11-character description, both under the recommended minimums. So
it **writes a fix** — `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

The tool confirms the write and echoes the freshly-resolved record:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Finally it **re-audits** to prove the fix — `issue_count` is now `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistant:** Done. The title (17 chars) and description (11 chars) were both
> under the recommended minimums — I set a fuller, descriptive title and
> description, and a re-audit now comes back clean (0 issues).

The write went through the same `saveSEO()` any programmatic save uses, so the
value is stored and resolved exactly like one set in code (mass-assigned through
the model's fillable fields and casts). It is **not** run through the Filament
form's length validators — so the re-audit is what confirms the new value is
actually good. The assistant proposed; the audit verified.

### Talking to your site's content

The site-content tools let the assistant answer questions **about the site
itself**, not just one record you name — the "talk to your site" demo.

> **You:** Which pages mention "immunoterapia", and are their titles in good shape?

The assistant **searches** — `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

The third title is just "Nuova", so it **checks that page's meta** —
`check_meta { model: "App\\Models\\Page", id: 91 }`:

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Assistant:** Three pages mention "immunoterapia". Two look good, but
> **/news/nuova-immunoterapia-2026** has a 5-character title ("Nuova") and no
> meta description. Want me to write a fuller title and description for it?

With `allow_edits` on it could fix it on the spot with `seo_save_meta`; read-only,
it stops at the recommendation. `search_pages` used the SQL `LIKE` fallback here
(`"driver": "like"`) because the model isn't Scout-indexed — add
[Laravel Scout](https://laravel.com/docs/scout) and the same tool transparently
searches through your engine instead.

## Security

Three layers keep the server safe by default. All three are on in the
read-only default; you relax them deliberately.

### 1. Edits are gated (off by default)

The write tool is invisible and inert until you flip a flag:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

With `allow_edits` off (the default), `seo_save_meta` is **not returned by
`tools/list`** and a `tools/call` for it fails with a JSON-RPC
`-32602` — the assistant cannot write, and cannot even discover that it could.
Turn it on only for a client and a database you trust.

### 2. The model allowlist

Every model-scoped tool — read *or* write — can only ever touch a `HasSEO`
model on the allowlist. An AI client can never point a tool at an arbitrary
class (a `User`, a billing model, anything):

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

A tool asked for a non-allowlisted class returns an error result the assistant
reads (`Model [App\Models\User] is not in the MCP allowlist`) — it never
touches the class. When `models` is empty it falls back to your configured
`seo.audit.models` / `seo.sitemap.models`, so the MCP shares the exact surface
the rest of the package already operates on — never a wider one.

### 3. stdio-only — nothing is exposed to the network

The server speaks **only over stdio**: the client spawns the process and pipes
JSON-RPC in and out. There is **no HTTP listener, no port, no socket** — nothing
to reach from another machine, and nothing to authenticate because there is no
remote surface to authenticate. STDOUT carries only protocol traffic; all
diagnostics go to STDERR (your client logs them, e.g. Claude Desktop writes
them to `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`) so a stray log line
can never corrupt the stream.

::: warning Treat an edit-enabled server like write access to your DB
`allow_edits` lets a connected assistant change SEO rows in the database the
command runs against. Point it at local/staging while you experiment, keep the
allowlist tight, and turn edits off again when you're done. The master switch
`'enabled' => false` refuses to start the command at all.
:::

## Configuration

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card (discovery) — experimental, draft spec

An MCP **Server Card** is a small JSON document at a well-known URL that lets a
client discover a server — its name, version, and what it offers — before
connecting. Rankbeam can serve one for your site, advertising *"this site has an
MCP server you can talk to"* to agent tooling. It is **off by default** and
purely additive — turning it on changes nothing else.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

With it on, `GET /.well-known/mcp/server-card.json` returns a card like:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

The card lists only the **currently enabled** tools, so a read-only server never
advertises the gated ops/edit tools through it.

::: warning Tracks a draft spec
This follows the **draft** MCP Server Card proposal
([SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127),
status *Draft*). The well-known path, the `$schema` URL, and the exact field set
are **not finalised** — so each is configurable (`path`, `schema_url`, `name`,
`website_url`). This server runs over **stdio** (`php artisan seo-pro:mcp`), so
the card carries no HTTP `remotes` block — it is a discoverability hint, not an
HTTP endpoint to connect to. Confirm the path and shape against your client
before relying on it, and leave it off if you don't need it.
:::

## Protocol notes

A tools-only MCP server is a small JSON-RPC 2.0 surface, and this one implements
it directly: `initialize` (version negotiation + capability handshake),
`tools/list`, `tools/call`, and `ping`. It advertises the
`2025-06-18` protocol version (and understands `2025-03-26` and `2024-11-05`),
returns `-32601` for unknown methods and `-32700` for malformed lines, and
returns a **tool** failure as an `isError` result the assistant can read — not a
transport error. Notifications (a message with no `id`, such as
`notifications/initialized`) correctly get no reply.

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
resolution, so your tool inherits the same safety guarantees as the built-ins.
