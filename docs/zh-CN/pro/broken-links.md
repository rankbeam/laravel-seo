---
description: "有明确上限、可续跑的爬虫，记录无法访问的链接，包括可一键设为重定向的失效内部路由，以及按需启用的外部失效链接检测。默认关闭。"
---

# 失效链接爬虫 {#broken-link-crawler}

这是一个**有明确上限、可续跑的爬虫**：它遍历网站，跟踪各页面中的链接，并记录无法访问的链接，包括失效的**内部**链接（自己主机上的失效路由，可一键创建重定向来修复），以及按需检测的失效**外部**链接。此功能**默认关闭**。

它的设计有三个特点：

- **有上限，可续跑。** 一次抓取由多个小型队列任务完成，每个任务只处理有限数量的页面，并不断派发后续任务，直到抓取完成或达到上限。整次抓取也有上限（默认 2000 个页面；只有明确设为 `null` 才表示不限总页数，绝非默认行为），其余批次和时间限制仍然有效。请根据网站和服务器容量设置适当的上限与延迟。
- **默认采取安全限制。** 默认范围为 `internal_only`，仅检查自己主机上的链接，不向第三方发送请求。每次请求（无论内部还是外部）都经过共享的 **SsrfGuard**：检查协议允许列表、主机范围，并拒绝私有地址。外部链接检测需要主动启用，启用后仍受这些保护。
- **独立于 SEO 评分。** 检测结果存入专用表，绝不会写入 `seo_scan_issues` 或影响 0–100 分的评分。无论出站链接是否失效，页面评分都保持不变。失效链接属于运维问题，单独跟踪。

## 提供哪些功能 {#what-you-get}

在 Filament 仪表盘中（仅在启用后显示）：

- **Broken-link summary（失效链接摘要）**：显示尚未解决的内部和外部失效链接数量、上次抓取情况，并链接到检测结果表。
- **Broken-link crawl（失效链接抓取）**：显示当前抓取的实时进度，包括已抓取页面、已检查链接和发现的失效链接数量。
- **Broken links per scan（每次扫描的失效链接）**：显示最近多次抓取的变化趋势。
- **检测结果资源**：列出每条失效的 `source → target` 链接，支持筛选；内部链接可通过创建重定向修复。

无界面使用时，可通过 `seo-pro:broken-links-*` 命令获取相同数据。

## 为什么默认关闭 {#why-it-s-off-by-default}

与被动渲染和评分功能不同，爬虫会**发送网络请求**，也需要少量基础设施。因此，需要主动选择启用，而不应在安装后悄悄开始运行：

- 两张主要数据表采用**先发布迁移文件、再迁移**的方式（与所有 Pro 迁移一致）；UI 查询这些表之前，必须完成迁移。分类检查还使用 `seo_broken_link_inspections`。
- 抓取任务会进入**专用队列**，需要 **worker** 执行；没有 worker 的抓取永远不会推进。
- 链接失效需要**跨扫描确认**（见下文），因此设计用途是持续数周的**定期运行**，而不是一启用就立即给出已确认的结果。

## 配置 {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

然后运行迁移。`seo-pro:install` 会发布并执行所有 Pro 迁移（具有幂等性，可安全重跑）：

```bash
php artisan seo-pro:install
```

为抓取队列运行**专用 worker**。使用独立队列（`seo-broken-links`），正是为了避免长时间抓取排在面向用户的任务前面：

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

确认各项连接正确：`seo:doctor` 会检查启用标记、数据表，以及抓取队列是否使用真正的连接（非 `sync`），并为每个问题给出具体修复方法：

```bash
php artisan seo:doctor
```

完整的多队列拓扑（Redis、Supervisor、专用连接）与批次调优方法，请参阅[生产环境配置](/zh-CN/pro/production)。

## 运行抓取 {#running-a-crawl}

通过仪表盘中的 **立即扫描（Scan now）**操作触发，也可在无界面环境中运行：

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

这两条命令都只会将抓取任务**加入队列**，实际工作由 worker 完成。

## 如何标记失效链接 {#how-a-link-gets-flagged}

只有在 `seo-pro.broken_links.mark_broken_after_failures` 次**连续抓取**都无法访问某个链接后，才会将它报告为失效（任意一次成功都会重置计数器；默认值为 **3**）。一次暂时中断不会导致链接被标记，因此应当**定期调度**抓取，而不是只运行一次。每周抓取一次、采用默认阈值时，需要三次失败才能确认：约为首次观察到失败后的两周，或链接失效后的最多约三周。若希望更快确认，可提高频率或降低阈值。

## 分类链接检查 {#typed-link-inspections}

除了检查“能否访问”，每个抓取到的链接还会接受一组**分类检查**，用于发现 URL 规范性问题，例如尾部斜杠不一致、编码混乱、重定向链、`javascript:` href、失效的页内锚点、缺乏描述性的锚文本等。每项检查都有固定的**严重程度**（`critical` · `warning` · `notice`，与[扫描问题](/zh-CN/pro/scan-issues)使用相同分类，便于用同一个 CI 门禁覆盖两者），并按每次抓取记录在 `seo_broken_link_inspections` 中。失效链接的*检测结果*需要连续多次抓取才能确认，而分类检查是单次运行的快照，**第一次抓取就会立即显示**，正适合 CI 门禁。

### 检查项参考 {#inspection-reference}

| 检查项 | 严重程度 | 标记条件 | 适用范围 |
| --- | --- | --- | --- |
| `broken_link` | critical | 目标返回 HTTP ≥ 400 | 任何链接 |
| `redirect_chain` | notice · warning | 目标必须经过重定向才能访问；超过 `redirect_chain_warning_hops` 时为 `warning` | 任何链接 |
| `link_unreachable` | notice | 本次抓取无法访问（网络错误、超时、被阻止），可能只是暂时情况 | 任何链接 |
| `insecure_link` | warning | `http://` 链接出现在 `https` 网站中（传输协议降级） | 任何链接 |
| `trailing_slash` | notice | 内部路径违反声明的尾部斜杠约定（**未设置 `trailing_slash` 时关闭**） | 内部链接 |
| `double_slash_url` | warning | 内部路径包含 `//`（空路径段） | 内部链接 |
| `duplicate_query_param` | notice | 查询参数名重复（`?a=1&a=2`）；`key[]` 数组语法除外 | 内部链接 |
| `non_ascii_url` | notice | 内部路径包含未编码的非 ASCII 字符 | 内部链接 |
| `uppercase_url` | notice | 内部路径包含大写字母（需检查大小写变体是否分别提供内容） | 内部链接 |
| `underscore_in_url` | notice | 内部路径使用下划线（SEO 通常更推荐用连字符分隔） | 内部链接 |
| `javascript_link` | warning | 锚链接使用 `javascript:` href，不是普通的可抓取目标 | 任何锚链接 |
| `missing_fragment` | warning | 同页 `#fragment` 在页面中没有匹配的 `id`/`name` | 同页链接 |
| `non_descriptive_anchor` | notice | 锚文本笼统（如“click here”“read more”）或只有裸 URL | 任何锚链接 |
| `absolute_internal_link` | notice | 内部链接使用绝对 URL，而不是相对于根目录的路径 | 内部链接 |

URL 规范性检查（尾部斜杠、大小写、编码、双斜杠等）仅适用于**内部**链接，因为外部网站的 URL 风格不由你管理。重定向、失效、无法访问和不安全链接检查适用于所有链接。指向自己框架路由和静态资源的链接会被跳过，以减少首次运行时的干扰（见下文 `exclude_paths` / `exclude_extensions`）。

每个链接都会按**原始写法的精确 URL** 请求，只去除 `#fragment`，不会预先规范化。因此，服务器端的规范化重定向（例如 `/about/ → /about`）能够被实际观察到，并显示为 `redirect_chain`，不会因预先规范化而被掩盖。同一页面上每一种不同的链接写法都会接受检查，因此 `/page#ok` 与 `/page#missing`（或 `/a//b` 与 `/a/b`）都会分别检查，而不是只检查第一个。底层失效链接*检测结果*仍会将同一目标的各个别名合并为一个身份；分类检查按 `(page, target, inspection)` 记录。因此，同一目标即使有多个失效的页内锚点，也只生成一条 `missing_fragment` 记录（附一个示例），而不是每个锚点一条。

### 调整检查分类 {#tuning-the-taxonomy}

所有配置都位于 `seo-pro.broken_links.inspections` 下：

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

从 `rules` 中移除某个检查类，即可**关闭该规则**；通过 `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false` 可以**关闭整组分类检查**。以下两条规则值得提前了解：

- `trailing_slash` 在你**声明约定之前处于关闭状态**（`'always'` / `'never'`）。如果网站对 `/x` 和 `/x/` 都返回 `200`，就没有“错误”风格可标记；如果服务器通过重定向进行规范化，该情况已由 `redirect_chain` 报告。
- `absolute_internal_link` 会针对**每条**使用绝对 URL 的内部链接触发。如果你的网站约定使用绝对内部 URL，就会产生大量无害的 `notice` 级别记录；从 `rules` 中移除该规则即可停止报告。

## 持续集成 {#continuous-integration}

链接扫描和 [SEO 审计](/zh-CN/pro/scan-issues)都可以**使构建失败**并**写出报告产物**，让 Rankbeam 从仪表盘扩展为质量门禁。`--fail-on-error` 对应 `critical` 级别（失效链接或严重问题）；`--fail-on-warning` 会在存在 `critical` **或** `warning` 时失败（没有单独的“error”级别）。

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` 用于写出产物（若传入目录，则自动确定文件名）；`--format` 可设为 `json`（默认）、`md` 或 `html`。流水线解析时使用 JSON；HTML 则是可附加到运行记录的独立页面。

### GitHub Actions {#github-actions}

爬虫通过 HTTP 请求页面，因此 CI 必须指向可访问的内容：可以是本地启动的应用（如下所示），也可以通过 `SEO_PRO_BROKEN_LINKS_BASE_URL` 指定预发布 URL。还需注册模型或网站地图，以便抓取有可用的起始目标。

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## 调度 {#scheduling}

在 `routes/console.php` 中注册抓取及其维护任务：

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## 命令参考 {#command-reference}

| 命令 | 用途 |
| --- | --- |
| `seo-pro:broken-links-scan` | 将有上限、可续跑的抓取加入队列（`--scope=internal_only\|internal_and_external` 选择范围，`--url=*` 用于补充起始目标） |
| `seo-pro:broken-links-status` | 显示最近抓取摘要、尚未解决的失效链接及本次检查计数；支持 **CI 门禁**（`--fail-on-error`、`--fail-on-warning`、`--report=<file\|dir>`、`--format=json\|md\|html`） |
| `seo-pro:broken-links-cancel` | 取消正在运行或排队的抓取（`{run?}`，默认选择最近的活动抓取） |
| `seo-pro:broken-links-recover` | 将因 worker 终止而遗留的抓取标记为失败（租约过期） |
| `seo-pro:broken-links-prune` | 应用爬虫保留策略（清理旧运行记录和已解决的检测结果） |

## 调优 {#tuning}

抓取的各项限制，包括每次运行的页面数、每页链接数、每个任务的上限、硬性时间预算以及按主机设置的请求间隔，都在 `seo-pro.broken_links` 中。默认值保守且有限；提高上限之前，请参阅[生产环境配置中的批次调优表](/zh-CN/pro/production)。
