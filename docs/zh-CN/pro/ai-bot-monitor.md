---
description: "记录观察到的 AI 爬虫访问：哪些机器人抓取过网站、频率如何，以及各自最近访问的网址和状态，补充 AI 爬虫控制的可观测性。"
---

# AI 机器人监测 {#ai-bot-monitor}

请求通过 **User-Agent 匹配**归属，不代表验证过机器人身份。监测器记录观察到的请求，User-Agent 可以伪造。

核心的 [AI 爬虫控制](/zh-CN/guide/ai-crawlers)决定 `robots.txt` *告诉* AI 爬虫什么。Pro 的 **AI 机器人监测器**补充另一部分：记录实际观察到的*行为*，包括哪些 AI 爬虫抓取过网站、频率，以及每个机器人最后访问的网址和 HTTP 状态。

它复用 404 监测器的基础机制，包括在响应结束后执行的全局中间件、更新或创建并累计命中的模型，以及相同的隐私策略。但它以**机器人**而不是路径为键，并记录**任何**响应状态，覆盖的正是 404 监测器有意排除的 AI 爬虫。机器人识别复用核心 `AiCrawlerRegistry`，使 robots.txt 策略与观察到的流量使用同一份机器人定义。

::: tip 需要 core ≥ 3.3
监测器通过核心 AI 爬虫目录 [`SEO::aiCrawlers()`](/zh-CN/guide/ai-crawlers)识别机器人。旧版核心中它不生效。
:::

## 启用 {#enabling-it}

默认关闭。启用后，全局中间件在每次响应之后记录匹配的爬虫，不延迟页面响应：

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

只需这些设置。中间件会自动注册，可以通过 `ai_bots.auto_register_middleware` 取消自动注册。每个已知机器人更新或创建一行，因此表大小受目录限制。

## 读取日志 {#reading-the-log}

### 无头模式 {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

每行提供 `bot`、`label`、`operator`、`purpose`、`hit_count`、`last_path`、`last_status`、`first_seen_at` 和 `last_seen_at`。

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

注册 Pro 插件后，SEO 导航组下会出现 **AI 机器人**表，包含机器人、运营商、用途、命中次数、最近状态、最近路径和最近访问时间。可以按用途筛选，界面只读。

## 隐私 {#privacy}

采用与 404 监测器相同的策略：**默认不存储 IP。** 主动启用 `ai_bots.hash_ip` 后，也只存储带密钥的 sha256，即 `ip_hash`，绝不写入原始 IP。

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## 时间段指标：按天分桶 {#period-metrics-daily-buckets}

累计表为每个机器人保留一行，适合排行榜，但无法回答**某个时间窗口内**有*多少次命中*或*多少个不同网址*。启用 `daily_enabled` 时，默认如此，每次命中还会按天、按路径记录到 `seo_ai_bot_daily` 中。因此，[白标报告](/zh-CN/pro/reports)显示的是真实的时间段数字，包括上次报告后的命中数和本期不同网址数，而不只是累计值差额。

数据量边界仍然存在，这也是累计日志原本按机器人只存一行的原因：

- 每个机器人每天有**不同路径上限** `daily_max_paths`。超过后，新的路径合并到一个溢出桶，因此每日命中总数仍精确，行数却不会失控。达到上限的不同网址数量显示为“N+”。
- **保留期限**由 `daily_retention_days` 设置，通过 `seo-pro:ai-bots-prune` 清理。

将 `daily_enabled` 设为 `false`，即可只保留累计排行榜。此时，报告的“自上次以来”回退为与上一份报告快照的差额，并忽略已有分桶，避免读取过时表。

时间段数字以**天为精度**。“自上次报告以来”从上一份报告所在日期开始计算整天，因此当天某次命中可能发生在精确生成时间之前，也可能之后。在通常的每日、每周或每月频率下，这种边界误差很小。

## 从观察转为控制 {#turning-observation-into-control}

监测器告诉你*谁*在抓取；核心的 [AI 爬虫控制](/zh-CN/guide/ai-crawlers)决定*允许抓取什么*。如果观察到一个你希望限制的训练爬虫，可以这样设置：

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

注意，有些机器人的文档明确说明不会遵守 `robots.txt`。监测器帮助发现它们，并决定是否在边缘层通过防火墙、WAF 或 Cloudflare 阻止。
