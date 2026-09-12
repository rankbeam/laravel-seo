---
description: "依据整理过的 AI 爬虫目录和允许或禁止策略，生成托管的 robots.txt 及可选 ai.txt，自行选择哪些机器人可以抓取网站。免费核心功能。"
---

# AI 爬虫控制（robots.txt / ai.txt） {#ai-crawler-control-robots-txt-ai-txt}

主要 AI 运营商使用具名机器人抓取网页，其中大多数会读取 **robots.txt**，决定可以抓取哪些内容。Rankbeam 提供整理过的机器人目录，并根据简单的允许或禁止策略，生成托管的 `robots.txt` 和可选的 `ai.txt`。这样，你可以**允许 AI 搜索和助手爬虫，同时限制将内容用于训练的爬虫。**

这是免费核心功能。Pro 包补充另一部分：[可观测性，即 AI 机器人访问日志](/zh-CN/pro/ai-bot-monitor)，显示哪些 AI 爬虫实际访问过网站。

## 默认策略 {#the-default-policy}

目录中的每个机器人都标注了主要用途：

| 用途 | 行为 | 默认值 |
| --- | --- | --- |
| `ai_search` | 抓取页面并为 **AI 搜索**回答建立索引，即 AI 引荐渠道 | **允许** |
| `ai_assistant` | 在对话中代表**用户**实时获取页面 | **允许** |
| `ai_training` | 收集内容以**训练**模型 | **禁止** |

这符合许多发布者面对 AI 时代的做法：允许 ChatGPT 搜索、Perplexity 等服务背后的 AI 搜索和助手爬虫访问，同时选择不让内容成为训练数据。所有设置都可以在配置中修改。

::: warning 可以访问不等于会被引用
允许爬虫访问，只是让检索*成为可能*，并不保证发现、索引、排名、收录到回答中、引用原文或标注来源。这项策略只控制**访问**，也就是哪些机器人可以抓取页面，不控制任何后续结果。
:::

## 快速开始 {#quick-start}

先输出 AI 爬虫规则块，查看将要发布的内容：

```bash
php artisan seo:robots-txt --print
```

有两种使用方式。

### 选项 A：将规则块粘贴到现有 robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

如果已经自行维护 `public/robots.txt`，只获取托管规则块并粘贴进去：

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### 选项 B：让 Rankbeam 管理整个文件 {#option-b-—-let-rankbeam-manage-the-whole-file}

生成完整的 `robots.txt`，包含常规部分、AI 指令、`Sitemap:` 行，以及指向 [llms.txt](/zh-CN/guide/sitemaps) 的链接：

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

安排定时运行，让文件持续反映当前策略：

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

也可以动态提供文件：将 `seo.ai_crawlers.route` 设为 `true`，包就会根据实时配置响应 `/robots.txt`，无需生成步骤：

::: warning 静态文件优先
大多数应用已经包含 `public/robots.txt`，Web 服务器会在 Laravel 路由请求之前直接提供它。动态路由**默认关闭**，避免在你忘记某个文件存在时，悄悄遮蔽该文件或被该文件遮蔽。只有不存在静态 `robots.txt` 时，才使用动态路由。
:::

## 执行效果的真实边界 {#honesty-about-enforcement}

robots.txt 是请求，不是围墙。目录中的大多数机器人有文档说明会遵守它，但少数用户触发的代理，例如 `ChatGPT-User`、`Perplexity-User`，以及一些训练爬虫，例如 `Bytespider`，**没有文档说明会遵守**。Rankbeam 将这些行标记为 `advisory`，避免暗示规则可以真正阻止它们。要实际阻止不遵守规则的机器人，需要服务器或边缘层拦截，例如防火墙、WAF 或 Cloudflare 机器人规则。[Pro AI 机器人访问日志](/zh-CN/pro/ai-bot-monitor)可以帮助你判断哪些需要关注。

## 内容信号：使用偏好 {#content-signals-usage-preferences}

`Allow` / `Disallow` 控制**访问**，即机器人是否可以抓取页面。[内容信号](https://contentsignals.org)是 Cloudflare 倡导的标准，表达另一个维度：内容抓取后可以如何**使用**。`User-agent: *` 组中的一行 `Content-Signal:` 携带三项偏好：

| 信号 | 来自的策略用途 | 含义 |
| --- | --- | --- |
| `search` | `ai_search` | 构建搜索索引，包括链接和简短摘录 |
| `ai-input` | `ai_assistant` | 将页面实时提供给 AI 模型，用于 RAG 或依据来源生成回答 |
| `ai-train` | `ai_training` | 训练或微调 AI 模型 |

该功能**默认关闭**，主动启用之前文件保持逐字节一致。启用后，Rankbeam 直接从现有 `policy` 推导该行：`allow` 转为 `yes`，`disallow` 转为 `no`：

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

如果从 `policy` 中完全移除某个用途，对应信号就会**省略**。这表示规范中的“未表达偏好”，不同于显式 `yes`/`no`。

::: warning 与 robots.txt 一样，属于建议性规则
内容信号表达偏好，**不是**技术控制措施，爬虫可以忽略。它们与上述访问规则及边缘层拦截并行使用，不能替代它们。
:::

## 配置 {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

通过 `overrides` 可以不考虑用途，单独覆盖某个机器人。键使用目录中的 **id**，例如 `gptbot`、`claudebot`、`perplexitybot`、`google-extended`。

## 目录 {#the-catalog}

`SEO::aiCrawlers()` 是可信来源。Pro 访问日志也使用同一份目录识别访客，因此控制机器人访问的文件与观察它的面板不会相互矛盾。

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

目录覆盖主要运营商：OpenAI 的 GPTBot、OAI-SearchBot、ChatGPT-User；Anthropic 的 ClaudeBot、Claude-SearchBot、Claude-User；Google 的 Google-Extended；以及 Perplexity、Apple 的 Applebot-Extended、Common Crawl 的 CCBot、Meta、Amazon、ByteDance 等。每个条目都带有文档说明的用途和 robots.txt 标识。

### 区域搜索引擎 {#regional-search-engines}

从 3.15 开始，目录也包含 Google/Bing 之外的重要传统网页搜索爬虫，标注为 `search_engine` 用途，且**默认允许**：

| id | 标识 | 运营商 |
|---|---|---|
| `yandex` | `Yandex` | Yandex，俄罗斯；不带后缀的标识涵盖其所有机器人 |
| `baiduspider` | `Baiduspider` | Baidu，中国 |
| `yeti` | `Yeti` | Naver，韩国 |
| `seznambot` | `SeznamBot` | Seznam，捷克 |
| `sogou` | `Sogou web spider` | Sogou，中国 |
| `360spider` | `360Spider` | Qihoo 360，中国 |
| `coccocbot` | `coccocbot-web` | Cốc Cốc，越南 |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

它们和其他机器人一样参与 `policy` 与 `overrides`，因此 `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` 可避免两个你不服务的爬虫占用带宽，`'list' => 'all'` 则为每个机器人生成显式规则行。除非明确要求，例如使用 `searchEngines()`、`all(true)`、`match($ua, true)`，否则它们**不会**包含在 `all()` 和 `match()` 中，以保持 Pro AI 机器人日志及所有“N 个 AI 爬虫”计数的含义不变：

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

识别爬虫并不保证在对应搜索引擎中的可见性或排名。配套的网站验证标签，包括 `yandex-verification`、`baidu-site-verification`、`naver-site-verification`、`seznam-wmt`，位于 `seo.verification` 下。参见[多语言内容](/zh-CN/guide/multilingual#site-verification)。
