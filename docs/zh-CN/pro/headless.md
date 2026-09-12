---
description: "Pro 的扫描、重定向和 404 日志功能均可在没有 Filament 时以无头模式运行。本页提供完全通过 Artisan 管理 Pro 的命令参考。"
---

# 无头模式使用 {#headless-usage}

Pro 的扫描、重定向和 404 日志等每项功能，都位于引擎中，可以无头运行，无需 Filament。面板只是管理界面，下面的命令提供对应的无头操作方式。

## 命令参考 {#command-reference}

### 设置与健康检查 {#setup-health-check}

| 命令 | 作用 |
|---|---|
| `seo-pro:install` | 发布 `config/seo-pro.php` 和 Pro 迁移，执行迁移，再显示后续步骤；支持 `--no-migrate`、`--force` |
| `seo:doctor` | 一次性健康检查：应用网址、核心与 Pro 表、扫描目标、网站地图、各工作负载的队列、可选功能和运行健康状况；每个警告都提供准确修复方法，`--json` 用于监测 |

`seo-pro:install` 是文档推荐的设置路径。Pro 迁移只通过发布提供，包不自动加载，因此安装器负责将单纯的 `composer require` 变成可用的数据库结构。它具有幂等性，随时可以重跑。

`seo:doctor` 不发起网络请求，也不输出密钥值，AI 检查只报告配置的密钥变量是否*已设置*。它验证配置和近期运行历史，无法证明外部 cron 或工作进程确实在运行。只有缺少必需表这样的关键失败，才会以非零状态退出，因此带有警告的 localhost 开发环境仍正常退出。`--json` 为每项检查提供稳定的 `id`，供集成识别。应在[安装](/zh-CN/pro/installation)后立即运行，也在 CI 中运行。

### 扫描 {#scanning}

| 命令 | 作用 |
|---|---|
| `seo-pro:scan` | 将所有注册目标的完整扫描加入队列，`--sync` 用于内联运行；**CI 门槛**包括 `--fail-on-error`、`--fail-on-warning`、`--report=`、`--format=json\|md\|html`，要求 `--sync` |
| `seo-pro:scan-status` | 最新运行摘要和未解决问题，最严重的在前；支持 `--limit=20`、`--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | 将因队列工作进程停止而被遗弃的运行标为失败 |
| `seo-pro:scan-prune` | 删除超过保留期限的已结束运行及其问题 |

### 失效链接爬虫 {#broken-link-crawler}

默认关闭。启用 `seo-pro.broken_links.enabled`，并迁移对应两张表，可通过 `seo-pro:install` 发布。爬取在多个有边界的队列任务中运行，应为对应队列配置专用工作进程。调优方式见[生产环境设置](/zh-CN/pro/production)。

| 命令 | 作用 |
|---|---|
| `seo-pro:broken-links-scan` | 将有边界、可恢复的爬取加入队列，`--scope=internal_only\|internal_and_external` 选择范围，`--url=*` 添加额外种子 |
| `seo-pro:broken-links-status` | 最新爬取摘要、未解决问题和本次运行的[分类检查](/zh-CN/pro/broken-links#typed-link-inspections)；**CI 门槛**包括 `--fail-on-error`、`--fail-on-warning`、`--report=`、`--format=` |
| `seo-pro:broken-links-cancel` | 取消运行中或排队中的爬取，`{run?}` 默认选择最新活动运行 |
| `seo-pro:broken-links-recover` | 将工作进程停止后遗弃、租约过期的爬取标为失败 |
| `seo-pro:broken-links-prune` | 应用爬虫保留策略，处理旧运行和已解决问题 |

### 重定向与 404 {#redirects-404s}

| 命令 | 作用 |
|---|---|
| `seo-pro:redirect-create {source} {target}` | 创建重定向规则，支持 `--code=301`、`--regex`、`--no-preserve-query`、`--note=` |
| `seo-pro:404-list` | 已记录的 404，按命中次数从高到低排列；支持 `--status=new\|ignored\|redirected\|all`、`--limit=20` |
| `seo-pro:redirects-flush-hits` | 在 `redirects.hits.flush_immediately=false` 时，将缓存中批量累积的重定向命中计数写入数据库 |
| `seo-pro:404-prune` | 删除过时的 404 条目并执行行数上限 |

### 页面检查清单 {#on-page-checklist}

| 命令 | 作用 |
|---|---|
| `seo-pro:checklist {model} {id}` | 对单个模型提供识别关键词的通过、警告或失败检查清单，支持 `--json`、`--strict`、`--locale=`；见[页面检查清单](/zh-CN/pro/on-page-checklist) |

同一检查清单也可通过 `SeoPro::checklistFor($model)` 使用。它服务于编辑反馈，包括关键词位置、长度、图片和内部链接，**不是** [SEO 评分](/zh-CN/pro/scoring)。

### Search Console，只读 {#search-console-read-only}

| 命令 | 作用 |
|---|---|
| `seo-pro:search-console` | 同时有未解决问题**和**搜索流量的页面，优先列出问题机会值最高的页面，默认使用 `--view=attention` |
| `seo-pro:search-console --view=pages` | 按展示次数、点击次数、CTR 或排名排列的热门页面 |
| `seo-pro:search-console --view=queries` | 热门查询，支持 `--days=`、`--limit=`、`--json` |

同样的指标可通过 `SeoPro::searchConsole()` 获取，见 [Search Console](/zh-CN/pro/search-console)。默认关闭，严格只读。

### AI 辅助 {#ai-assist}

| 命令 | 作用 |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | 以 JSON 输出标题和描述建议，支持 `--field=title\|description\|all`；见 [AI 辅助](/zh-CN/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | 以 JSON 输出扫描问题的通俗修复说明 |

### 一步解决 404 {#resolving-a-404-in-one-step}

`--from-404={path}` 是 404 监测器一键*创建重定向*操作的无头版本。它创建规则，**同时**将匹配日志条目标为已重定向，并关联到新规则：

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

命令使用与 Filament 表单相同的验证器。无效正则模式、超大值，以及不在允许列表中的外部目标，都会在写入任何内容之前被拒绝。

## 推荐调度 {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

上面每个周期性命令的推荐频率都在[生产环境设置](/zh-CN/pro/production)指南中，同时介绍队列拓扑、工作进程配置、重试和恢复策略、保留策略，以及每次运行结束时输出的结构化**遥测**，包括抓取页数、检查链接数、被阻止网址、耗时和队列延迟。

## 哪些需要 Filament 界面 {#what-needs-the-filament-ui}

没有任何引擎功能必须使用它。完整引擎，包括扫描流水线、问题跟踪、重定向匹配、404 日志、清理和恢复，在有无 Filament 时完全相同。面板添加的是*视图*：带实时扫描进度和严重程度统计的仪表板、可筛选的问题浏览和逐页弹窗、忽略或重新打开按钮、重定向增删改查表单，以及带一键操作的 404 表格。问题忽略和重新打开目前没有专用命令，可在面板中执行，或在 Tinker、自己的代码中通过 `SEOScanIssue` 模型的 `markIgnored()` / `reopen()` 操作。
