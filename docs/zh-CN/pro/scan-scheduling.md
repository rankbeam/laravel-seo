---
description: "定期运行完整 SEO 扫描，在仪表盘及可选邮件中查看自上次扫描以来新增、再次出现或已修复的问题，并按影响大小排序。"
---

# 扫描调度与变化比较 {#scan-scheduling-delta}

**定期**运行完整 SEO 扫描，并在仪表盘及可选的摘要邮件中，按影响大小查看**自上次扫描以来的变化**：哪些问题新出现、再次出现或已修复。

这两个功能放在同一页介绍，是因为它们相互配合：变化比较让定期扫描通知提供值得关注的信息。

## 自上次扫描以来发生了什么变化 {#what-changed-since-the-last-scan}

每次扫描完成时，都会将其**未解决问题集合**冻结为轻量快照（`seo_scan_run_issues`）。比较两次运行的快照，即可得到准确的三类差异：

- **新增**：之前未处于未解决状态、现在处于未解决状态，且任何更早的扫描中也从未处于未解决状态的问题，即真正首次发现的问题。
- **复发**：曾经修复、现在**再次出现**的问题。这不是指“严重程度提高”：每种问题类型的严重程度固定，因此有意义的退化是问题再次出现，正对应问题[生命周期](/zh-CN/pro/scan-issues#issue-lifecycle)中的重新打开。
- **已修复**：较早扫描中尚未解决、现在已不在未解决集合中的问题。

每一类都[按影响大小排序](#impact-ordering)，优先显示最值得处理的问题。

### 为什么使用快照，而不是问题表 {#why-a-snapshot-not-the-issues-table}

问题具有已修复、重新打开的[生命周期](/zh-CN/pro/scan-issues#issue-lifecycle)：同一行会在多次扫描之间原地更新，只要问题仍未解决，其 `scan_run_id` 每次都会更新为最新运行。这适合保存持久的问题历史，但也意味着实时表无法告诉你*第 N 次运行结束时，哪些问题处于未解决状态*；持续存在的问题始终只指向最新运行。

因此，每次运行都按稳定的**问题指纹** `issue_type | target | field` 为未解决集合创建快照，这与[白标报告](/zh-CN/pro/reports)比较差异时使用的身份相同。变化比较就是对两个已冻结的指纹集合做集合运算，对**任意**两次运行都准确，不局限于相邻运行。

### 如实处理边界情况 {#edge-cases-handled-honestly}

- **某个页面不再属于扫描集合。** 其未解决问题不会再次接受扫描，因此仍保持未解决，并在每次运行中重新纳入快照。它们显示为**仍未解决**，不会误报为“已修复”。我们不会因为不再检查页面，就声称页面已修复。
- **两次扫描之间关闭了某项检查。** 该检查不再产生问题，生命周期会将原有问题标记为已修复，并将它们移出未解决集合，因此显示为**已修复**。这如实反映扫描器当前的判断；在问题级别，无法区分“你修复了问题”与“你关闭了检查”。
- **升级后的第一次扫描。** 此功能引入前的运行没有快照，因此不会被选作基线。第一次带快照的扫描成为**基线**，显示当前状态但不显示差异，不会将整个网站都报告为“新增”。从第二次带快照的扫描开始，变化比较正式生效。

### 在仪表盘中查看 {#on-the-dashboard}

[SEO 仪表盘](/zh-CN/pro/installation)中的 **“自上次扫描以来的变化”**小组件会比较最近两次已完成的扫描，显示新增、复发和已修复数量，以及各类别中按影响大小排序的主要问题。在尚未积累两次扫描快照时，它会显示一条简短的“基线”说明。

## 按影响大小排序 {#impact-ordering}

每个变化类别都按**影响**分值排序，让最大的问题排在前面：

```
impact = severity_weight × page_importance
```

- **severity_weight** 复用已公布的[评分规则](/zh-CN/pro/scoring)：critical 权重为 `40`，warning 为 `15`，notice 为 `5`。严重程度是产品对缺陷重要性的明确判断，因此排序沿用它，不再另造一套尺度。
- **page_importance** 由**真实搜索需求**驱动，也就是页面在 [Search Console](/zh-CN/pro/search-console) 中获得的展示次数，因为这一信号能实际区分不同页面：

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  展示次数经过对数缩放（流量为 10× 的页面，重要性并非也为 10×），并以最繁忙的页面进行归一化，因此同一公式可适用于小型博客和大型目录。网站地图 `<priority>` 只是**较弱的次要**信号：默认未设置，即使设置也通常是统一值，无法支撑整体排序；当你在 `seo.sitemap.models` 中按类型配置优先级时，它才会起到小幅调整作用。

**没有 Search Console 也可以使用。** 如果没有已同步的 GSC 历史，也没有配置优先级，那么每个页面的 `page_importance` 都为 `1`，影响排序就退化为纯粹的**严重程度排序**。这是合理的默认值，不是编造的权重。同步 [GSC 历史](/zh-CN/pro/search-console#historical-metrics)（`seo-pro:gsc-sync`）后，即可启用需求加权。

在 `seo-pro.scan.delta.impact` 下调整权重和时间窗口。

## 调度扫描 {#scheduling-a-scan}

包**默认不调度任何任务**。可按以下方式启用：

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

也可设置完整 cron 表达式，实现全面控制；它优先于 `frequency`：

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

配置到此即可。包会向 Laravel 调度器注册 `seo-pro:scan`，并使用 `withoutOverlapping` 防止已调度的**命令**重叠执行。这个调度器锁并不覆盖队列任务的整个生命周期。注册仅在调度器或控制台上下文中进行，因此**不会增加 Web 请求开销**。

::: warning 需要运行中的调度器
如果 Laravel 调度器未运行，包的调度配置不会生效。请使用标准的一行 cron（`* * * * * php artisan schedule:run`），或在开发环境运行 `php artisan
schedule:work`。参阅[生产环境配置](/zh-CN/pro/production#scheduler)。
:::

希望自行配置？保持 `schedule.enabled` 关闭，并从自己的控制台内核调度该命令；变化比较和摘要仍可正常使用：

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` 会同步执行扫描，而不是为每个目标派发一个队列任务。没有队列 worker 的小型网站可以使用，生产环境应保持关闭。

## 摘要邮件 {#summary-e-mail}

可主动启用邮件，在定期扫描结束时接收**“自上次扫描以来发生了什么变化”**摘要：带有自有品牌的 HTML 邮件，按影响大小列出新增、复发和已修复问题：

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

它复用[白标报告](/zh-CN/pro/reports)的品牌和邮件配置，因此会沿用代理机构名称、标志和强调色；未单独设置收件人时，则使用报告收件人。`only_on_change` 会在扫描没有变化时跳过邮件，首次基线扫描始终发送。

只有通过 `--notify` 启动的运行才会发送摘要；当 `notify.enabled` 开启时，调度器会自动添加该选项。临时执行 `seo-pro:scan` 且**不带 `--notify`** 时，不会给任何人发送邮件。

::: tip 希望使用其他渠道？
如果需要 Slack、webhook 或自定义摘要，而不是邮件，可以订阅 `Rankbeam\Seo\Pro\Events\SeoScanCompleted` 事件。它在每次运行结束时触发一次，并携带该运行；你可以使用 `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` 构建变化比较，再将结果发送到所需渠道。
:::

## 数据保留 {#retention}

快照会随所属运行级联删除，因此 [`seo-pro:scan-prune`](/zh-CN/pro/production#scheduler) 会自动清理到期快照，无需新增调度。只有不再关联任何未解决问题的运行才会被清理，因此近期运行的快照始终可用于比较。

使用 `seo-pro.scan.delta.snapshot => false` 可完全关闭快照，同时也会关闭变化比较和摘要。
