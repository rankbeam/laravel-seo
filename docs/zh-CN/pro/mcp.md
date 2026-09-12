---
description: "无额外依赖的 stdio MCP 服务器，让 AI 助手通过 Model Context Protocol 读取并在获准时编辑 Laravel 网站的 SEO。Laravel 11：PHP 8.2–8.4；Laravel 12：PHP 8.2–8.5；Laravel 13：PHP 8.3–8.5。"
---

# MCP 服务器 {#mcp-server}

Rankbeam MCP 服务器让 AI 助手通过 [Model Context Protocol](https://modelcontextprotocol.io) **读取网站的 SEO，并可选择允许编辑**。将 MCP 客户端（Claude Code / Claude Desktop、Cursor、Codex 等）指向你的 Laravel 应用，它就能解析页面元数据、运行审计、读取 Pro 评分、查看 AI 爬虫策略，并在你允许时写回 SEO。

这是一个**无额外依赖**、自包含的 stdio 服务器，无需 SDK，也无需新包；支持 PHP 8.2–8.4（Laravel 11）、PHP 8.2–8.5（Laravel 12）、PHP 8.3–8.5（Laravel 13）。

::: tip Pro 功能
MCP 服务器随 `rankbeam/laravel-seo-pro` 提供。它**默认只读**；编辑需要主动启用，并受配置开关和模型允许列表控制。
:::

## 助手可以做什么 {#what-the-assistant-can-do}

### 分析工具（始终可用） {#analysis-tools-always-available}

| 工具 | 作用 |
| --- | --- |
| `seo_resolve` | 获取模型记录完全解析后的 SEO 元数据（标题、描述、规范网址、robots、Open Graph、JSON-LD），即页面实际会渲染的内容。 |
| `seo_audit` | 对模型记录（或前 N 条记录）执行进程内[元数据审计](/zh-CN/guide/audit)，实时运行相同的 `seo:audit` 检查，不经过队列。 |
| `seo_score` | 获取模型记录最新持久化的 [Pro SEO 评分](/zh-CN/pro/scoring)（0–100 + 等级）。 |
| `seo_robots_directives` | 获取托管的 [AI 爬虫 robots.txt 指令](/zh-CN/guide/ai-crawlers)及每个机器人的解析后允许 / 禁止策略。 |
| `validate_schema` | 使用核心包的结构化数据验证器验证 JSON-LD 对象，或允许列表内模型解析后的 schema 图（按 `@type` 检查 Google 富媒体搜索结果要求）。 |
| `analyze_robots` | 获取**每个已知 AI 爬虫**的权威允许 / 禁止判定，以及决定来源（单机器人覆盖、用途策略或默认值）。该策略对全站生效。 |
| `debug_social_share` | 获取社交爬虫实际会看到的模型记录 Open Graph + Twitter 卡片（应用回退之后），并附带卡片健康状况建议。 |
| `check_meta` | 聚焦单条模型记录的元数据健康状况：解析后的 title/description/canonical/robots/og:image、长度及是否存在，并包含审计问题。 |

### 网站内容工具（始终可用） {#site-content-tools-always-available}

这些“与网站对话”的工具让服务器成为能够理解内容的助手，可以列出和搜索页面。

| 工具 | 作用 |
| --- | --- |
| `list_pages` | 列出**允许列表内**模型中由 SEO 管理的页面（记录），每条包含 URL 和实际生效的标题。支持 `limit`/`offset` 分页。 |
| `search_pages` | 对**允许列表内**模型的页面进行全文搜索。模型支持搜索时使用 Laravel [Scout](https://laravel.com/docs/scout)，否则使用安全的 SQL `LIKE`（title/name/headline + 联接的 SEO 元数据）。每个命中项返回 URL、标题和片段。 |

### 运维工具（主动启用） {#ops-tools-opt-in}

这些运维工具读取扫描状态并更改网站配置。和编辑工具一样，它们**受 `allow_edits` 控制**；在默认的只读服务器上既不可见，也无法执行。

| 工具 | 作用 |
| --- | --- |
| `list_issues` | 获取当前未解决的 SEO 扫描问题（跨运行持久保存的未解决集合）及最新[扫描](/zh-CN/pro/scan-issues)运行的概要信息。可按 `severity` / `type` 筛选。 |
| `trigger_scan` | 启动扫描：针对允许列表内某条记录的定向扫描（返回运行），或覆盖所有目标的全量扫描。默认入队，也可通过 `sync: true` 同步执行。 |
| `create_redirect` | 创建重定向规则（来源路径或正则表达式 → 目标，状态为 `301`/`302`/`307`/`308`/`410`），复用重定向模型自身的验证器。 |

### 编辑工具（主动启用） {#edit-tool-opt-in}

| 工具 | 作用 |
| --- | --- |
| `seo_save_meta` | 通过 `saveSEO()` 向**允许列表内**的模型记录写入 SEO 元数据（标题、描述、规范网址、robots、OG、Twitter、JSON-LD）。 |

除非启用编辑，否则运维工具和 `seo_save_meta` **不会在 `tools/list` 中公布，也无法运行**，参见[安全性](#security)。只读服务器甚至不会告诉助手这些工具存在。

## 连接 AI 客户端 {#wiring-an-ai-client}

服务器通过 **stdio** 传输 JSON-RPC：客户端启动 Artisan 命令，再通过管道与其通信。将它注册到你使用的客户端即可，同一服务器适用于所有这些客户端。

::: tip 一条命令，适用于任何客户端
下面每个客户端都运行相同的启动命令 `php artisan seo-pro:mcp`，并**从应用根目录启动**，以便 Artisan 引导应用。如果机器上的 `php` 不在客户端的 `PATH` 中（Windows 上或未继承 shell 环境的图形应用中很常见），请同时提供 **`php` 和 `artisan` 的绝对路径**。Artisan 会从 `artisan` 脚本自身所在的目录启动，因此无需 `cwd`。
:::

### Claude Code（CLI） {#claude-code-cli}

一条命令即可注册，请**在应用根目录运行**：

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

确认已连接：

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

在 Windows / Laravel Herd 上，指定绝对路径，使其不受启动目录影响：

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

编辑配置文件（**Settings → Developer → Edit Config**），或直接打开：

- **Windows**：`%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**：`~/Library/Application Support/Claude/claude_desktop_config.json`

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

在 **Windows** 上，使用绝对路径 `php.exe`，并在 JSON 中将每个反斜杠写成双反斜杠进行转义：

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

完全退出并重新打开 Claude Desktop。工具会出现在消息栏的工具 / 插头图标中。

### Cursor {#cursor}

在项目中创建 `.cursor/mcp.json`（或使用 `~/.cursor/mcp.json` 对所有项目生效）。提供 `artisan` 的绝对路径，以便从任意位置启动：

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

在 Windows 上，和上面的 Claude Desktop 示例一样，使用 `php.exe` 的绝对路径及双反斜杠转义。在 **Settings → MCP** 中启用服务器。

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

与 Claude Code 一样，在 Windows/Herd 上传入 `php` + `artisan` 的绝对路径。

配置完成，助手现在可以调用这些工具。

## 一段实际对话 {#a-worked-conversation}

下面是针对演示应用的一段真实对话（一个有 900 个页面的医疗机构网站），工具实际输出在此做了缩写。允许列表中有两个可读模型 `App\Models\Page` 和 `App\Models\PublicPage`，且已开启 `allow_edits`。

> **你：**“Accoglienza”页面（Page id 1）的 SEO 怎么样？修复明显的问题。

助手首先**审计**页面，调用 `seo_audit { model: "App\\Models\\Page", id: 1 }`：

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

随后通过 `seo_resolve { model: "App\\Models\\Page", id: 1 }` **解析**当前元数据，查看实际渲染内容，发现标题为 17 个字符，描述为 11 个字符，均低于建议下限。于是通过 `seo_save_meta` **写入修复**：

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

工具确认写入，并返回重新解析的记录：

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

最后**再次审计**验证修复，`issue_count` 现在为 `0`：

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **助手：**已完成。标题（17 个字符）和描述（11 个字符）都低于建议下限。我设置了更完整、描述性更强的标题和描述，再次审计已无问题（0 项）。

写入经过与任何程序化保存相同的 `saveSEO()`，因此值的存储和解析方式与代码设置完全一致（通过模型的 fillable 字段和类型转换进行批量赋值）。它**不会**经过 Filament 表单的长度验证器，所以再次审计仅验证自动检查覆盖的内容。编辑准确性仍需复核；审计无问题不代表文本质量得到认证。

### 与网站内容对话 {#talking-to-your-site-s-content}

网站内容工具让助手能够回答**关于网站本身**的问题，而不局限于你指定的一条记录，这就是“与网站对话”的演示。

> **你：**哪些页面提到了“immunoterapia”？它们的标题怎么样？

助手通过 `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }` **搜索**：

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

第三个标题只有“Nuova”，因此通过 `check_meta { model: "App\\Models\\Page", id: 91 }` **检查该页面的元数据**：

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

> **助手：**有三个页面提到了“immunoterapia”。其中两个看起来不错，但 **/news/nuova-immunoterapia-2026** 的标题只有 5 个字符（“Nuova”），也没有元描述。要为它写更完整的标题和描述吗？

如果开启 `allow_edits`，助手可立即通过 `seo_save_meta` 修复；只读模式下则止于建议。这里 `search_pages` 使用了 SQL `LIKE` 回退（`"driver": "like"`），因为模型没有通过 Scout 建立索引。添加 [Laravel Scout](https://laravel.com/docs/scout) 后，同一工具会透明地改用你的搜索引擎。

## 安全性 {#security}

三层措施保障服务器的默认安全性。默认只读模式下三层均生效；放宽限制需要有意操作。

### 1. 编辑受开关控制（默认关闭） {#_1-edits-are-gated-off-by-default}

在打开开关之前，写入工具既不可见，也无法执行：

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

关闭 `allow_edits` 时（默认状态），`seo_save_meta` **不会由 `tools/list` 返回**，针对它的 `tools/call` 会以 JSON-RPC `-32602` 失败。助手无法写入，甚至无法发现可写入的工具。只对你信任的客户端和数据库开启。

### 2. 模型允许列表 {#_2-the-model-allowlist}

所有具有模型作用域的工具，无论读取*还是*写入，都只能访问允许列表内的 `HasSEO` 模型。AI 客户端无法把工具指向任意类，例如 `User`、计费模型或其他类：

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

请求访问不在允许列表内的类时，工具返回助手可读取的错误结果（`Model [App\Models\User] is not in the MCP allowlist`），绝不会触及该类。`models` 为空时，会回退到配置的 `seo.audit.models` / `seo.sitemap.models`，因此 MCP 的操作范围与包的其他部分完全一致，绝不会更广。

### 3. 仅使用 stdio——不向网络暴露接口 {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

服务器**仅通过 stdio** 通信：客户端启动进程，通过管道传入和传出 JSON-RPC。它**没有 HTTP 监听器、没有端口、没有套接字**，其他机器无从访问，也没有需要认证的远程接口。STDOUT 只承载协议流量，所有诊断信息都写入 STDERR（由客户端记录，例如 Claude Desktop 写入 `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`），因此意外的日志行不会破坏通信流。

::: warning 将启用编辑的服务器视为数据库写入权限
`allow_edits` 允许已连接的助手修改命令所连接数据库中的 SEO 记录。试用时请指向本地 / 预发布环境，严格限制允许列表，完成后再次关闭编辑。总开关 `'enabled' => false` 会直接拒绝启动命令。本地 stdio 传输并不能阻止 AI 客户端将工具结果发送给其提供商；也请考虑该客户端的数据配置。
:::

## 配置 {#configuration}

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

## Server Card（发现）——实验性功能，规范草案 {#server-card-discovery-—-experimental-draft-spec}

MCP **Server Card** 是放在约定 URL 上的小型 JSON 文档，让客户端在连接前发现服务器的名称、版本和所提供的功能。Rankbeam 可以为你的网站提供此卡片，向智能体工具声明*“这个网站有一个可对话的 MCP 服务器”*。它**默认关闭**，属于纯粹的附加功能，开启不会改变其他行为。

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

开启后，`GET /.well-known/mcp/server-card.json` 返回如下卡片：

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

卡片仅列出**当前已启用**的工具，因此只读服务器也不会通过卡片公布受开关控制的运维 / 编辑工具。

::: warning 跟随草案规范
此实现遵循 MCP Server Card **草案**提案（[SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127)，截至 2026 年 9 月 10 日仍为开放提案，尚未合并）。约定路径、`$schema` URL 及确切字段集合都**尚未定稿**，因此每项均可配置（`path`、`schema_url`、`name`、`website_url`）。此服务器通过 **stdio**（`php artisan seo-pro:mcp`）运行，所以卡片不包含 HTTP `remotes` 块；它是可发现性提示，不是可连接的 HTTP 端点。依赖它之前，请与你的客户端核对路径和结构；不需要时保持关闭。
:::

## 协议说明 {#protocol-notes}

仅提供工具的 MCP 服务器所需的 JSON-RPC 2.0 接口很小，本服务器直接实现了它：`initialize`（版本协商 + 能力握手）、`tools/list`、`tools/call` 和 `ping`。它公布 `2025-06-18` 协议版本（也理解 `2025-03-26` 和 `2024-11-05`），对未知方法返回 `-32601`，对格式错误的行返回 `-32700`。**工具**失败会作为助手可读取的 `isError` 结果返回，而不是传输错误。通知，即没有 `id` 的消息，例如 `notifications/initialized`，会按协议要求不予回复。

## 无界面使用 / 扩展 {#headless-extending}

`SeoPro::mcp()` 返回工具注册表，让你可以检查公开的工具或注册自己的工具：

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

自定义工具实现 `McpTool`（`name`、`description`、`inputSchema`、`isEnabled`、`handle`）。扩展 `AbstractTool` 可复用模型允许列表解析，让你的工具继承与内置工具相同的安全保障。
