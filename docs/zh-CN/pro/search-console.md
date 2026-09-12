---
description: "严格只读的 Google Search Console 面板，展示热门查询与页面的展示次数、点击次数、点击率和排名，并关联扫描器已知的页面。默认关闭。"
---

# Search Console（只读） {#search-console-read-only}

一个**只读**的 Google Search Console 面板，显示热门查询和页面的**展示次数、点击次数、点击率与平均排名**，并关联扫描器已知的页面，让你在同一处看到*“这个页面存在问题，**而且**展示次数正在下降”*。此功能**默认关闭**。

它的设计有三个特点：

- **严格只读。** 集成只请求一个 OAuth 权限范围，即包中硬编码的 `webmasters.readonly`。它只能读取 Search Analytics，不能执行其他操作：不会提交网站地图、请求编入索引，也不会更改 Search Console 中的任何内容。没有配置项可以扩大权限范围。
- **你的网站资源，你的凭据。** 请求从*你的服务器*直接发送到 Google，使用*你的*服务账号或 OAuth 凭据进行身份验证。不经过代理、不按用量计费，也不转售服务；包不会发送遥测数据。
- **错误在当前界面中显示。** 缺少凭据、403、配额错误或超时会显示为内联消息，不会中断面板渲染。历史同步命令则会报告失败，并停止获取后续日期的数据，详见下文。

## 提供哪些功能 {#what-you-get}

- **Pages needing attention（需要关注的页面）**：关联最有用的数据，找出**存在未解决扫描问题**、同时**仍获得搜索流量**的页面，按最值得优先处理的机会排序，即问题页面中展示次数最多的排在前面。优先修复这些页面。
- **Top pages（热门页面）**和 **Top queries（热门查询）**：常见的 Search Analytics 数据表。

在 Filament 仪表盘中，它是 *SEO* 导航组下的 **Search Console** 页面，仅在启用集成后出现。无界面使用时，可通过 `seo-pro:search-console` 命令和 `SeoPro::searchConsole()` 获取相同指标。

## 配置 {#setup}

你需要能够读取目标 Search Console 网站资源的 Google 凭据。支持两种方式，其中**服务账号**最适合服务器，配置也最简单。

### 服务账号（推荐） {#service-account-recommended}

1. 在 Google Cloud 中启用 **Search Console API**，创建**服务账号**，并下载其 JSON 密钥。
2. 在 Search Console → *Settings → Users and permissions*（设置 → 用户和权限）中，将服务账号邮箱（`…@….iam.gserviceaccount.com`）添加为用户。只读用途使用 Restricted（受限）权限即可。
3. 向包指定密钥和网站资源：

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

如果省略 `SEO_PRO_GSC_SITE_URL`，则会根据 `app.url` 推导 URL 前缀型网站资源。

### OAuth（离线刷新令牌） {#oauth-offline-refresh-token}

如果已有 OAuth 客户端和长期有效的**刷新令牌**，可按以下方式配置；建议该令牌只授权 `webmasters.readonly`。每次刷新都会请求这一权限范围。除非响应明确确认权限范围恰好为只读范围，否则包会拒绝返回的令牌；它不会假定 Google 一定会缩小较宽的既有授权。

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### 发布令牌迁移 {#publish-the-token-migration}

加密的访问令牌缓存存储在 `seo_gsc_tokens` 表中。发布并执行一次迁移：

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

然后使用 `php artisan seo:doctor` 确认配置。它会报告 Search Console 是否启用并配置好，不发送网络请求，也绝不输出机密信息。

## 无界面使用 {#headless-usage}

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

## 历史指标 {#historical-metrics}

上述面板和命令读取的是**实时滚动窗口**，数据只存储在 Search Console 中。如果希望查询任意过去周期的**按日历史**，可运行同步命令，将每天每个查询和页面的指标保存到 `seo_gsc_metrics` 表中：

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **首次运行会回填** `sync.backfill_days`（默认 90；Search Console 约保留 16 个月，因此可以提高该值以获取更多历史）。后续运行会**从最后存储的日期继续**，并重新获取末尾的 `sync.overlap_days`，以补齐 Search Console 对近期数据的延迟最终确认。窗口始终截止于 3 天前，以考虑数据延迟。
- **具有幂等性。** 数据行按 `(date, dimension, key)` 执行 upsert，因此可以安全重跑。某一天获取失败（例如配额错误）时，运行会有序停止，并报告已保存的行数；下一次运行会从中断处继续。
- **这些历史用于什么。** 当数据表覆盖两个比较周期后，白标[报告](/zh-CN/pro/reports)中的 Search Console **变化项目**会改用真实的环比历史，即本周期与此前同等长度周期比较，而不是与上一份报告快照比较。这也是更丰富的关键词分析功能的基础数据。

只存储汇总指标：每天的查询文本、页面 URL，以及四项指标（点击次数、展示次数、点击率、排名）。绝不获取或写入逐用户或逐请求数据。

## 数据处理与安全 {#data-handling-security}

- **检查只读权限范围。** 服务账号 JWT 仅请求 `webmasters.readonly`，OAuth 刷新请求同样如此；如果响应没有权限范围，或范围更宽，包会拒绝响应。请使用仅授权读取权限的凭据。包不包含任何会修改 Search Console 的调用。

- **凭据保留在环境中。** 服务账号密钥、OAuth 客户端密钥和刷新令牌与 AI 密钥一样，在调用时从**指定名称**的环境变量读取，因此 `php artisan config:cache` 绝不会将它们写入 `bootstrap/cache/config.php`。缓存配置导致 `.env` 无法加载时，请确保这些凭据已存在于进程环境中。
- **令牌加密存储。** 根据凭据签发的短期访问令牌会以**加密形式**（使用应用密钥加密）保存在 `seo_gsc_tokens` 中，并重复使用到临近过期，因此不会每次打开视图都执行令牌交换。长期凭据绝不存储在数据库中，只保留在你的环境中。
- **每次请求都经过 SSRF 防护。** 令牌交换和 Search Analytics 调用都经过共享的 `SsrfGuard`，仅允许 HTTPS，主机必须解析到公共地址，并禁用重定向，因此请求无法被转向内部服务。
- **机密信息不会写入日志。** 访问令牌、密钥和认证请求头绝不会记录到日志。API 错误只显示 Google 自身经过清理且有长度上限的错误消息。
- **指标在本地缓存** `seo-pro.search_console.cache_ttl` 秒（默认 30 分钟），避免面板每次渲染都再次请求 API。实时面板和命令除了该缓存与加密访问令牌外，不持久化其他内容。只有主动运行的 `seo-pro:gsc-sync` 命令会永久写入指标，即 `seo_gsc_metrics` 中按日汇总的查询和页面数据，不包含逐用户数据。

## 配置参考 {#configuration-reference}

所有键都位于 `config/seo-pro.php` → `search_console` 下：

| 键 | 默认值 | 用途 |
| --- | --- | --- |
| `enabled` | `false` | 总开关（`SEO_PRO_GSC_ENABLED`）。 |
| `connection` | `service_account` | `service_account` 或 `oauth`。 |
| `site_url` | 根据 `app.url` 推导 | 网站资源（`https://example.com/` 或 `sc-domain:example.com`）。 |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | 存放密钥 JSON 或其路径的环境变量**名称**。 |
| `oauth.client_id` | — | OAuth 客户端 ID（非机密）。 |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | 存放客户端密钥的环境变量**名称**。 |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | 存放刷新令牌的环境变量**名称**。 |
| `default_days` | `28` | 报告窗口（截止于 3 天前，因为 GSC 数据存在延迟）。 |
| `row_limit` | `100` | 每份报告的前 N 行（API 上限为 25000）。 |
| `cache_ttl` | `1800` | 已获取报告的缓存秒数。 |
| `sync.backfill_days` | `90` | 首次运行 `gsc-sync` 时（空表）拉取的天数。 |
| `sync.overlap_days` | `2` | 每次运行重新获取的末尾天数（处理延迟最终确认）。 |
| `sync.row_limit` | `5000` | 同步时每天每个维度请求的最大行数。 |
