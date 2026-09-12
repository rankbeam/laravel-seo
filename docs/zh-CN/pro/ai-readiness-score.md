---
description: "SEO 评分之外的第二项确定性评分：按 Rankbeam 定义衡量技术层面的 AI 就绪度，即爬虫能否访问和读取页面，并与 SEO 分数分开记录。"
---

# AI 就绪度评分——第二个确定性维度 {#the-ai-readiness-score-—-a-second-deterministic-axis}

Pro 扫描会在 [SEO 评分](/zh-CN/pro/scoring)之外，为每个页面提供第二个数字：**0–100 的 AI 就绪度评分**。它回答的是另一个问题：*AI 爬虫和回答引擎能否访问、读取这些内容，并识别其归属？* 它**绝不会混入自然搜索 SEO 分数**。两者是独立的维度，各有自己的规则、版本和数据列。

::: warning 这个数字表示什么，又不表示什么
AI 就绪度评分是**由 Rankbeam 定义、具有确定性的技术兼容性指标**，检查页面上基于抓取的信号是否存在、格式是否正确。它**不预测**任何搜索或 AI 系统中的排名、索引、收录或引用，也没有任何分数能保证这些结果。`air_llms_txt` 检查会为**可选的** `llms.txt` 兼容性文件加分，供选择读取它的工具使用；Google Search 并不要求此文件，它也不是排名信号。
:::

和 SEO 评分一样，它**完全确定且可复现**：每一分都能追溯到一项有名称、基于抓取的检查，相同信号始终得到相同分数。**整个评分过程完全不调用 AI。** 这正是它的目的：提供透明、可审计的衡量方式，而不是像“AI 可见性”SaaS 产品那样让 LLM 进行采样。

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip 两个维度，始终分开
`AI-readiness: 74/100` 与 `SEO: 82/100` 并列存在，互不影响。AI 就绪度分数保存在 Pro 的 `seo_scan_results` 行中独立的 `ai_readiness_*` 列内。和 SEO 评分一样，**数值属于 Pro 功能**；免费核心包的 [`seo:audit`](/zh-CN/guide/audit) 不输出数值。
:::

## 累积加分，而不是扣分 {#additive-credit-not-penalty}

[SEO 评分](/zh-CN/pro/scoring)从 100 分开始*扣分*。AI 就绪度恰好相反：从 **0** 开始，按每项检查的权重全部或部分**加分**。“就绪度”是网站逐步积累的属性，因此没有 AI 信号的网站会如实得到接近 0 的分数，而不是“100 分减去几分”。权重合计恰好为 **100**。

## 评分规则 {#the-rubric}

分数依据**公开且有版本的评分规则** `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric` 计算，共有四类、十项检查：

### A · 机器人访问与控制——30 分 {#a-·-bot-access-control-—-30-points}

AI 搜索和助手爬虫能否实际访问你的网站？检查依据网站**实际提供的 `/robots.txt`**，针对**被扫描页面自身的路径**解析规则（即使根路径开放，`Disallow: /section` 下的页面仍会被禁止抓取；robots.txt 是供遵守规则的爬虫采用的指令，不是网络访问拦截），并使用 [AI 爬虫目录](/zh-CN/guide/ai-crawlers)中的用途分类：训练 / 搜索 / 助手。

| 检查 | 权重 | 得分方式 |
|---|---|---|
| `air_robots_reachable`——提供了可读取的 `robots.txt` | 6 | 存在 / 不存在 |
| `air_ai_search_access`——允许 AI **搜索**爬虫（引荐流量渠道）访问网站 | 10 | 按允许的比例 |
| `air_ai_assistant_access`——允许 AI **助手**爬虫访问网站 | 8 | 按允许的比例 |
| `air_explicit_ai_policy`——为已知 AI 机器人设置明确的 `robots.txt` 规则 | 6 | 存在 / 不存在 |

::: tip 禁止训练机器人并不意味着“就绪度更低”
禁止训练机器人（GPTBot、CCBot 等）是合理选择，因此**绝不会**因此扣分。训练方面只通过 `air_explicit_ai_policy` 加分，即是否有经过考虑的明确立场。禁止训练机器人、同时允许搜索和助手爬虫的网站，在这一类中仍可获得满分。
:::

### B · 可发现性——20 分 {#b-·-discoverability-—-20-points}

| 检查 | 权重 | 得分方式 |
|---|---|---|
| `air_sitemap_discoverable`——XML 网站地图可访问，**并且**由 `Sitemap:` 指令引用 | 12 | 两者都有 / 只有其一 / 两者都无 |
| `air_llms_txt`——提供有效的 `/llms.txt`（标题 + 链接） | 8 | 有效 / 存在 / 不存在 |

### C · 机器可读内容——22 分 {#c-·-machine-readable-content-—-22-points}

| 检查 | 权重 | 得分方式 |
|---|---|---|
| `air_server_rendered_content`——服务器渲染的 HTML 中有足够的文本（无需执行 JS 即有内容） | 14 | 按词数 |
| `air_markdown_twin`——通过内容协商提供页面对应的 Markdown 版本 | 8 | 存在 / 不存在 |

### D · 结构化数据与回答就绪度——28 分 {#d-·-structured-data-answer-readiness-—-28-points}

| 检查 | 权重 | 得分方式 |
|---|---|---|
| `air_schema_completeness`——存在 JSON-LD，主要实体有类型，包含作者归属与日期（文章需要作者和日期） | 18 | 完整 / 部分 / 无 |
| `air_answer_structure`——便于提取回答的结构：FAQ/QA/HowTo schema、标题层级、列表、简洁的引言 | 10 | 按结构要素数量 |

每项检查返回**满分**、**部分得分**或**不得分**；如果无法收集其所需信号，则返回**跳过**（例如目标扫描未抓取页面，却需要执行页面级检查）。跳过的检查得 0 分，但会明确标记，因此*无法检查*的信号不会被当作已确认不存在。

### 免费审计的覆盖范围 {#free-audit-reach}

schema 完整性（`air_schema_completeness`）可直接从模型的结构化数据解析，无需抓取；免费审计已有的回答就绪度发现也使用这条路径。其他九项检查需要抓取，因此完整数值属于 **Pro 扫描**的范围。

## 明确范围——这个维度不包含什么 {#honest-scope-—-what-this-axis-excludes}

此维度对**确定性的内容信号**评分，不包含涉及运行中应用或 DNS 的智能体基础设施检查：

| 排除项 | 原因 |
|---|---|
| **DNS-AID**（DNS 智能体发现记录） | 属于 DNS / DNSSEC 基础设施，不是页面响应的属性。 |
| **Web Bot Auth**（逐请求签名） | 属于交互式密码学握手，不是静态内容。 |
| **Protocol Discovery**（API Catalog、OAuth/OIDC、MCP Server Card、Agent Skills、WebMCP 等） | 需要运行中的应用 / API / MCP 服务器。 |
| **Commerce**（x402、MPP、UCP、ACP） | 属于智能体支付基础设施；内容网站没有需要收费的对象。 |

它包含 schema 实体完整性和回答块结构：这些内容信号描述组织方式和归属，并不保证搜索或回答引擎会采用它们。

## 版本管理——历史分数不会悄然改变 {#versioning-—-historical-scores-never-silently-change}

每个持久化的 AI 就绪度分数都会记录生成它的 `AiReadinessRubric::VERSION`（`ai_readiness_version`）。检查集合、权重或得分模型的任何变化都属于评分规则变更，必须**提升版本**，让存储的分数始终记录对应的解释规则，并使历史数值保持可比较。分数会**存储下来，读取时不会重新计算**。影响评分的阈值（词数、结构要素数量）是与版本关联的代码常量，绝不是配置项，因此设置不会悄然改变已发布的分数。

::: warning 版本不会固定的一项输入
机器人访问检查读取核心包**当前的** [AI 爬虫目录](/zh-CN/guide/ai-crawlers)。目录更新，例如新增机器人或重新分类用途，实际上会改变输入，即使不提升 `AiReadinessRubric::VERSION`，也可能改变两项机器人访问子分数（版本追踪的是*评分规则*，而不是目录）。这是有意为之：使用当下实际的机器人列表比冻结列表更有帮助。若需要精确比较历史结果，请在固定评分规则版本的同时固定核心包版本。
:::

## 存储位置 {#where-it-s-stored}

每次扫描会将 AI 就绪度列插入或更新到与 SEO 评分**同一条** `seo_scan_results` 记录中：

| 列 | 保存的内容 |
|---|---|
| `ai_readiness_score` | 0–100 的分数（直到启用此维度并扫描目标之前均为 null）。 |
| `ai_readiness_version` | 生成分数的评分规则。 |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]`，完整的计算记录。 |

一次运行完成后，会把该运行的平均分写入 `seo_scan_runs.avg_ai_readiness`，对应于 `avg_score` 的做法，用于展示 AI 就绪度趋势。

## 读取评分 {#reading-the-score}

**无界面使用**——模型的最新结果同时包含两个维度：

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament**——在任意资源表格中，将配套列放到 SEO 评分列旁边：

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

它也会在内联页面评分卡（SEO 标题字段上方）显示为配套徽标，并在[白标报告](/zh-CN/pro/reports)（PDF 和电子邮件）中有独立区域，包含数值、等级、相对上次报告的变化及每次扫描的趋势。它始终与自然搜索评分并列展示，绝不混合计算。

### 等级区间 {#grade-bands}

字母等级根据分数生成，仅用于展示（数值才是契约）；为保持一致，使用与 SEO 评分相同的区间：

| 分数 | 等级 |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 配置 {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

检查项和权重**不可配置**。对于给定的 `ai_readiness_version`，所有安装中的评分都必须具有确定性，因此更改计算方式属于代码层面的评分规则变更，而不是设置项。

::: warning 网站信号检测使用进程内请求路径
对于同主机目标，扫描通过 Laravel 的进程内 HTTP 内核解析 `/robots.txt`、`/llms.txt` 和页面，这与扫描的其他部分使用相同的路径。如果 `robots.txt` 或 `llms.txt` 以**静态文件**形式提供（绕过 Laravel 路由），扫描就看不到它们。请通过包提供的路由来提供这些文件（推荐设置），才能纳入评分。
:::
