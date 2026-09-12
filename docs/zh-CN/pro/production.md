---
description: "规模化运行 Pro：专用队列、调度器、重试与恢复策略、数据保留和遥测。了解约 900 个页面的生产环境所采用、独立于 Filament 的部署拓扑。"
---

# 生产环境配置 {#production-setup}

Pro 的日常工作，包括网站扫描、失效链接抓取、可选的重定向命中计数刷入和 404 清理，都通过 Laravel 的队列与调度器运行。本指南是规模化运行这些功能的统一权威参考，涵盖专用队列、调度器、重试与恢复策略、数据保留，以及监控这些工作的遥测。这里的拓扑来自一个每天约 20k 次访问、约 900 个页面的生产环境，并提供可复现的配置方式。

这里的所有功能都**独立于 Filament**。无论有无面板，引擎、命令、队列和遥测都完全相同。使用 Filament 只是在上层增加视图，不会改变任务的调度或处理方式。

[[toc]]

## 安全部署顺序 {#safe-rollout-order}

按以下顺序操作；每一步都可以先验证，再进入下一步：

1. **安装**：发布配置和迁移文件，并执行迁移：

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` 会发布 `config/seo-pro.php` 和 Pro 迁移文件，然后执行 `migrate`。Pro 迁移采用**先发布、再执行**的方式（包不会自动加载迁移），因此这一步会把仅完成 `composer require` 的安装变成具备可用数据库结构的安装。操作具有幂等性，可随时重跑；添加 `--force` 可覆盖已发布文件，添加 `--no-migrate` 则只发布而不执行迁移。

2. 在服务提供者（`AppServiceProvider::boot()`）中**注册扫描目标**：

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. 启用后台工作之前，**验证**各项连接与配置：

   ```bash
   php artisan seo:doctor
   ```

   修复输出的每一条警告，每条都附有具体命令或配置行。在 CI 中添加 `--json`，并使用稳定的检查 ID 进行判断。

4. **配置队列与调度器**（见下文），部署队列 worker，并为 `schedule:run` 添加 cron 条目。

5. **最后启用可选功能**：失效链接爬虫、AI 辅助和 Search Console 默认均关闭。爬虫需要完成数据表迁移（第 1 步已发布这些迁移），并配置专用 worker（见下文）。

**升级到 Pro 2.41.0：**暂停扫描 worker，使用 `php artisan vendor:publish --tag=seo-pro-migrations --force` 发布迁移，执行 `php artisan migrate`，然后重启 worker 并运行 `php artisan seo:doctor`。新的 `seo_scan_target_completions` 表和 `seo_scan_runs.target_tracking` 列是必需项。每次运行、每个目标的处理凭据可防止重复的终结结果抬高计数，首个被接受的结果生效。尚未处理任何目标的旧排队运行可以继续。升级前已部分处理的运行会保留历史，但在下一次任务投递时结束，并提示重新扫描。重试次数已耗尽的目标应在新运行中重试。如需回滚，先停止 worker 并还原代码，再回滚迁移；如果还需要撤销后续扫描，请保留升级前的数据库备份。

## 为各类工作配置专用队列 {#dedicated-queues-per-workload}

长时间扫描或抓取绝不能排在邮件、通知等面向用户的任务前面。为每类 SEO 工作配置独立队列和独立 worker。

扫描流水线和失效链接爬虫分别读取可配置的队列：

| 工作类型 | 配置 | 环境变量 | 默认队列 |
|---|---|---|---|
| 页面扫描任务 | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | 默认队列 |
| 失效链接抓取任务 | `seo-pro.broken_links.queue.name`（另有 `.connection`） | `SEO_PRO_BROKEN_LINKS_QUEUE`（另有 `_CONNECTION`） | `seo-broken-links` |

### Redis 示例（生产环境拓扑） {#redis-example-the-production-topology}

`.env`：

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

为每个队列运行一个 worker，每个 worker 都是独立进程或 Supervisor program：

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

抓取 worker 的 `--timeout` 必须大于 `seo-pro.broken_links.batch.hard_time_budget_seconds`（默认 180）加上 HTTP 超时时间，以免批次在更新处理记录时被终止。任务会将自身的 `$timeout` 设为这一总和，因此 worker 参数应与之对齐。抓取请使用 `--tries=1`：已终止的任务会由下一次续跑任务（或 `seo-pro:broken-links-recover`）接管，不需要队列级重试。

`seo:doctor` 会报告各类工作的队列，并在队列解析为 `sync` 时发出警告，因为该连接会同步执行工作并阻塞当前流程。

## 调度器 {#scheduler}

Laravel 11、12 和 13 在 **`routes/console.php`** 中定义调度（`app/Console/Kernel.php` 的 `schedule()` 方法仅存在于从 Laravel 10 升级而来的应用；如果应用仍保留该方法，就将相同条目放在那里）。添加一条系统 cron 配置，让调度器每分钟运行一次：

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

然后按推荐频率注册所有周期性命令：

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

推荐频率一览：

| 命令 | 频率 | 原因 |
|---|---|---|
| `seo:sitemap` | 每天 | 根据当前内容刷新网站地图 |
| `seo-pro:scan` | 每周（内容变化较快时每天） | 重新审计所有目标 |
| `seo-pro:scan-recover` | 每小时 | 处理因 worker 终止而遗留的运行 |
| `seo-pro:scan-prune` | 每天 | 应用扫描运行记录的保留期限 |
| `seo-pro:redirects-flush-hits` | 每 5 分钟，仅当 `redirects.hits.flush_immediately=false` 时 | 将缓存在一起的命中计数刷入数据库 |
| `seo-pro:404-prune` | 每天 | 按保留期限和行数上限清理 404 日志 |
| `seo-pro:404-recheck` | 每天 | 重新请求尚未解决的 404 路径，将源页面已修复（现在返回 200）的路径标记为已恢复 |
| `seo-pro:broken-links-scan` | 每周 | 重新抓取失效链接（需要跨扫描确认） |
| `seo-pro:broken-links-recover` | 每小时 | 处理因 worker 终止而遗留的抓取 |
| `seo-pro:broken-links-prune` | 每天 | 应用爬虫数据保留期限 |

`seo-pro:scan` 和 `seo-pro:broken-links-scan` 只会将工作**加入队列**，实际由 worker 执行。恢复和清理命令同步运行，开销较小。

::: tip 跨扫描确认失效链接
只有在 `seo-pro.broken_links.mark_broken_after_failures` 次**连续扫描**都无法访问某个链接后，才会将其标记为失效；任意一次成功都会重置计数器。因此，抓取应定期调度，而不是只执行一次：一次暂时中断不会导致链接被标记。默认阈值为 3，每周扫描时，约在首次观察到失败后的两周确认，或在链接失效后的最多约三周确认。如果需要更快确认，可提高频率或降低阈值。
:::

## 批次调优（失效链接爬虫） {#batch-tuning-broken-link-crawler}

抓取由多个有明确上限、会自行派发后续任务的队列任务完成。默认配置对抓取设有有限的上限，应根据网站及被检查主机的容量调整。配置位于 `seo-pro.broken_links`：

| 键 | 默认值 | 限制的内容 |
|---|---|---|
| `max_pages_per_run` | `2000` | 整次运行抓取的页面数。`null` 表示明确选择不限总页数（绝非默认值） |
| `max_links_per_page` | `200` | 每页检查的链接数 |
| `max_total_links` | `null` | 可选的整次运行链接检查总数上限 |
| `batch.max_pages_per_job` | `50` | 每个队列任务处理的页面数 |
| `batch.max_links_per_job` | `1500` | 每个队列任务执行的链接检查数 |
| `batch.hard_time_budget_seconds` | `180` | 达到此时间后，任务**不再发起新的请求**，并派发续跑任务 |
| `batch.dispatch_delay_seconds` | `1` | 续跑任务之间的延迟 |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | 每次请求的限制 |
| `http.max_response_bytes` | 继承 `seo-pro.http.max_response_bytes` | 页面响应体与目标检查响应体的流式读取上限 |
| `seed.max_response_bytes` | 继承爬虫或共享 HTTP 上限 | 收集起始目标时获取的原始网站地图 XML / `.gz` 字节数 |
| `seed.max_inflated_bytes` | 继承起始目标、爬虫或共享上限 | 从 `.gz` 网站地图接受的解压后字节数 |
| `http.per_host_delay_ms` | `0` | 两次检查之间为目标主机预留的请求间隔（使用 `internal_and_external` 时应提高） |

让 `batch.hard_time_budget_seconds` 明显低于抓取 worker 的 `--timeout`，留出充足余量。正在进行的请求无法中途终止，它受 `http.timeout` 限制，因此 worker 超时时间应为时间预算 + HTTP 超时 + 额外余量。

对于 `internal_and_external` 抓取，需要扩大 `seo-pro.http.scope`（或 `seo-pro.http.allowed_hosts`），使 SsrfGuard 允许这些出站检查，并提高 `http.per_host_delay_ms`，避免过快请求第三方主机。如果抓取范围包含外部链接，但防护范围会阻止所有检查，`seo:doctor` 会发出警告。

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

每个队列配置一个 program。`/etc/supervisor/conf.d/app-workers.conf` 示例：

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` 必须大于 worker 的 `--timeout`，确保优雅重启不会在批次处理中途终止任务。

### Horizon {#horizon}

如果使用 Horizon，请在 `config/horizon.php` 中为每类工作定义一个 supervisor，并由 Horizon 代替 Supervisor 管理进程：

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## 重试与失败处理 {#retry-failure-handling}

扫描目标任务会从配置读取自身的重试策略，**不依赖** worker 的 `--tries`：

| 键 | 默认值 | 含义 |
|---|---|---|
| `seo-pro.scan.tries` | `3` | 每个目标任务的尝试次数 |
| `seo-pro.scan.backoff` | `30` | 两次尝试之间的秒数 |
| `seo-pro.scan.timeout` | `300` | 每个目标任务的超时时间（防重叠锁在超时时间 + 60 后到期） |

目标任务耗尽重试次数后，会将目标记录为**失败**，但整次运行仍会结束（`partial` 或 `failed`）。已妥善处理的目标失败不会让运行停留在 `running` 状态。如果 worker 在更新处理记录前被终止，仍需使用下文的恢复清理。失败任务会进入标准的 `failed_jobs` 表，可按常规方式管理：

```bash
php artisan queue:failed
php artisan queue:retry all
```

将 `queue:prune-failed` 与 SEO 调度条目一起安排，以控制该表的规模：

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

失效链接抓取使用 `--tries=1`：已终止的任务会由它自己的下一次续跑任务（租约心跳已过期）或 `seo-pro:broken-links-recover` 接管，因此队列重试只会造成重复工作。

## 恢复 {#recovery}

worker 在任务处理中途终止，是进度计数无法自行恢复的一种情况。使用以下两项清理来结束这些运行，两者都应**每小时**调度：

- `seo-pro:scan-recover`：将连续 `seo-pro.scan.recovery.stuck_scan_timeout_hours` 小时（默认 2 小时）没有进展的页面扫描运行标记为失败。
- `seo-pro:broken-links-recover`：处理租约心跳已过期的抓取运行（`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`，默认 2 小时），将其标记为失败，并释放“每个范围仅允许一个活动运行”的名额。

`seo:doctor` 将这些情况呈现为**近期心跳证据**：开始使用扫描后，它会报告停滞的运行，并指向恢复命令。它无法证明 cron 确实在运行，任何命令都无法做到这一点；它只报告运行历史显示的情况。

## 数据保留 {#retention}

控制数据表规模。默认值如下，全部位于 `seo-pro.*` 中，设为 `null` 可关闭对应清理：

| 数据 | 配置 | 默认值 | 命令 |
|---|---|---|---|
| 扫描运行记录（含问题） | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404 日志 | `monitor_404.retention_days`（另有 `max_rows` `10000`） | `90` | `seo-pro:404-prune` |
| 抓取运行记录 | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| 已解决的检测结果 | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## 运维遥测 {#operational-telemetry}

每次运行结束时，无论是页面扫描**还是**失效链接抓取，都会通过日志系统输出一条结构化完成记录。因此，即使没有面板，也能获得指标历史。载荷只包含计数和耗时，不包含 URL、正文、请求头或访客数据：

| 指标 | 扫描 | 抓取 |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls`（被 SSRF 防护拒绝的目标） | — | ✓ |
| `transient_failures`（网络失败，下次扫描重新检查） | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds`（入队到首个批次） | ✓ | ✓ |

在 `seo-pro.telemetry` 中配置：

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

将 `channel` 指向专用日志通道，可将这些记录发送到实际使用的日志平台（Loki / Datadog / CloudWatch），避免混入应用日志：

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

需要更丰富的处理方式时，可以直接订阅事件；每个事件都公开相同的 `metrics()` 载荷：

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

遥测按尽力而为的原则运行：日志通道配置错误绝不会导致扫描失败。

## 独立于 Filament 的部署 {#filament-independent-deployment}

本页所有功能都不需要面板。无界面运行时，引擎、所有命令、队列、调度器、恢复、数据保留和遥测都保持相同。Filament 面板（`SeoProPlugin`）仅增加**视图**，包括实时扫描进度、问题表、重定向增删改查、404 监控和失效链接仪表盘。可以先部署引擎，通过 CLI 和调度器运维，之后再添加面板（也可以一直不添加），无需重新迁移或返工。完整命令参考请参阅[无界面使用](/zh-CN/pro/headless)。
