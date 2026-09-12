---
description: "Pro 扫描问题背后的稳定代码注册表：每个代码有固定的严重程度和字段，仪表盘与导出读取代码，无需解析消息。"
---

# 扫描问题——问题代码注册表 {#scan-issues-—-the-issue-code-registry}

Pro 扫描报告的每个问题都有一个**稳定的问题代码**，取自统一的注册表 `Rankbeam\Seo\Pro\Scanning\IssueRegistry`。扫描器不会在实现中随意定义代码，而是通过 `IssueRegistry::make()` 构建每个问题，由它从注册表写入严重程度和字段，并**拒绝任何未定义的代码**。因此，下面的目录是一份可供集成依赖的契约：仪表盘、导出和 [Pro 评分](/zh-CN/pro/scoring)读取代码，而不解析消息。免费的 [`seo:audit`](/zh-CN/guide/audit) 使用核心包自己的元数据注册表，覆盖范围较窄，部分 hreflang 代码也有所不同。

每个代码包含：

- **id**——存储为 `seo_scan_issues.issue_type` 的稳定字符串。
- **severity**——`critical`、`warning` 或 `notice`，**每个代码固定一种严重程度**（需要区分时拆分代码，而不改变同一代码的严重程度）。
- **field**——相关的 `seo_meta` 字段；页面级发现则为 _page_。
- **execution class**——调用方检测该问题所需的条件，见下文。
- **evidence**——问题的 `context` 数组包含的键。

## 执行类别 {#execution-classes}

按运行所需条件，每项检查恰好属于以下三类之一：

| 类别 | 所需条件 | 谁能执行 |
|---|---|---|
| **metadata** | 模型 + 核心解析器，无需抓取页面 | 模型扫描（`PageScanner`）；免费的 [`seo:audit`](/zh-CN/guide/audit) 覆盖部分元数据检查 |
| **rendered** | 页面实际提供的 HTML（进程内内核请求或外部抓取） | URL 扫描（`UrlScanner`） |
| **network** | 通过**出站**抓取验证一个*独立*目标（指向其他位置的规范网址） | URL 扫描，**始终经过 `SsrfGuard`** |

这也是免费的进程内审计无法等同于完整 Pro 扫描的原因：只有 **metadata** 代码无需渲染页面即可计算，只有 Pro 流程会抓取渲染后的 HTML，并通过网络验证规范网址目标。可用 `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)` 按类别筛选注册表。

## 元数据代码 {#metadata-codes}

由 `PageScanner` 根据模型和解析器检测。（渲染后的 URL 扫描也会测量实际提供的 `<head>`，产生 `missing_title`、`missing_description` 和长度代码；代码及含义相同。）

| 代码 | 严重程度 | 字段 | 证据 | 含义 |
|---|---|---|---|---|
| `missing_title` | critical | title | — | 没有标题，也没有可计算的回退值。 |
| `missing_description` | warning | description | — | 没有元描述，也没有可计算的回退值。 |
| `missing_og_image` | notice | og_image | — | 没有 Open Graph 图片，也没有可计算的回退值。 |
| `missing_focus_keyword` | notice | focus_keywords | — | 未设置焦点关键词。 |
| `duplicate_title` | warning | title | `title`、`duplicate_urls` | 同一语言区域下的其他页面重复使用此标题。 |
| `duplicate_description` | warning | description | `description`、`duplicate_urls` | 同一语言区域下的其他页面重复使用此描述。 |
| `title_too_long` | warning | title | `length`、`max`、`script` | 解析后的标题超过该文字系统的建议长度（拉丁文字为 60，中日韩文字约为 30）。 |
| `title_too_short` | notice | title | `length`、`min`、`script` | 解析后的标题低于该文字系统的长度下限（拉丁文字为 30，中日韩文字约为 15）。 |
| `description_too_long` | warning | description | `length`、`max`、`script` | 解析后的描述超过该文字系统的建议长度（160 / 约 80）。 |
| `description_too_short` | notice | description | `length`、`min`、`script` | 解析后的描述低于该文字系统的长度下限（70 / 约 35）。 |
| `robots_conflict_indexing` | critical | robots | `robots` | Robots 指令同时包含 `index` 和 `noindex`。 |
| `robots_conflict_following` | warning | robots | `robots` | Robots 指令同时包含 `follow` 和 `nofollow`。 |
| `noindex_warning` | warning | robots | `robots`、`canonical`、`page_url`、`shipping_signal` | 具有自引用规范网址的页面为 `noindex`：这是需要复核的启发式信号，并不证明页面必须被索引。模型扫描和渲染后的 URL 扫描都会报告。 |
| `invalid_canonical` | critical | canonical | `canonical` | 规范网址值不是有效 URL。 |
| `cross_domain_canonical` | warning | canonical | `canonical`、`page_url` | 规范网址指向的主机与页面不同。 |
| `shared_canonical` | notice | canonical | `canonical` | 多个页面声明相同的规范网址。 |
| `insecure_canonical` | warning | canonical | `canonical` | `https` 网站上使用 `http://` 规范网址。 |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | 某个 hreflang 替代版本的值既不是 `x-default`，也不是有效的 BCP-47 语言代码。 |
| `hreflang_missing_self_reference` | warning | alternates | `locale`、`page_url` | 声明了替代版本，却没有任何一项引用页面自身的语言区域（自引用 hreflang）。 |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | 同一 hreflang 代码对应多个 URL，导致版本集存在歧义。 |
| `hreflang_missing_x_default` | notice | alternates | `languages` | 多语言 hreflang 版本集没有 `x-default` 回退项。 |
| `aeo_missing_author` | notice | schema | — | 页面结构化数据中的文章缺少作者实体，schema 未明确说明作者归属或来源。 |
| `aeo_article_missing_date` | notice | schema | — | 页面结构化数据中的文章缺少发布 / 修改日期，schema 未明确说明文章时间线。 |

长度阈值来自核心包按文字系统区分的[长度策略](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)（Pro 2.33）：拉丁文字为 60/160，中日韩文字约为 30/80，按字素计数，因此扫描不会与编辑器的字符计数器相矛盾。下限（拉丁文字标题 30、描述 70，中日韩文字约为其一半）用于扫描中优化不足的提示；上下文键 `script` 表明实际采用的分类。长度针对**解析后的**标题 / 描述测量，也就是实际渲染的值，包含回退值和标题后缀。

`hreflang_*` 代码验证页面声明的 hreflang 替代版本（读取解析器的 `alternates`）：无效或重复代码、缺少自引用，以及多语言版本集缺少 `x-default`。它们仅在页面声明替代版本时执行。这些元数据检查不会检查跨页面的**相互引用**（“返回标签”）；下面的可选网络检查会抓取另一个页面。

`aeo_*` 代码属于**回答就绪度（AEO）**信号，检查页面的文章内容能否从其结构化数据中明确识别。它们读取解析后的 JSON-LD 图，**仅在**图声明了文章类型的结构化数据（`Article`、`BlogPosting`、`NewsArticle` 等），但缺少 `author` 实体（明确的作者归属 / 来源）或 `datePublished` / `dateModified`（明确的时间线）时触发。不含文章的页面绝不会因此被标记。它们由 `seo-pro.scan.checks.aeo` 控制（默认开启），与免费的 [`seo:audit`](/zh-CN/guide/audit) 对应。

::: tip `missing_focus_keyword` 由开关控制
只有启用**核心包**的焦点关键词工作流（`seo.keywords.enabled`，默认为 `false`），才会触发焦点关键词提示。关闭时，扫描不会因为页面没有焦点关键词而标记它。免费的 [`seo:audit`](/zh-CN/guide/audit) 命令和 Filament 编辑器读取**同一个**核心开关，因此扫描、审计和编辑器提示始终一致；控制入口始终只有一个。
:::

## 渲染代码 {#rendered-codes}

由 `UrlScanner` 根据实际提供的 HTML 检测：同主机目标使用进程内内核请求（无出站流量），外部目标使用受保护的抓取。

| 代码 | 严重程度 | 字段 | 证据 | 含义 |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL 返回 4xx/5xx 状态。 |
| `empty_response` | critical | page | — | URL 返回空响应体。 |
| `missing_canonical` | notice | canonical | — | 渲染后的 head 中没有 `<link rel="canonical">`。 |
| `noindex_page` | notice | robots | `robots` | 渲染后的页面为 `noindex`（信息项）。如果 `noindex` 页面还具有**自引用规范网址**，则升级为计分的 `noindex_warning`。 |
| `missing_h1` | notice | page | — | 没有 `<h1>` 标题。 |
| `multiple_h1` | notice | page | `count` | 存在多个 `<h1>`（信息项）。 |
| `missing_image_alt` | warning | page | `count`、`total`、`sample` | 内容图片缺少 `alt` 属性（显式的 `alt=""` 视为装饰图片，不会标记）。 |
| `thin_content` | notice | page | `word_count`、`threshold`、`segmenter` | 正文低于配置的词数。计词使用检查清单的分词器：有词间空格的文字按空白分隔，中文、日文和泰文使用 ICU 词典分词（`segmenter: intl`，需要 ext-intl），因此一篇 400 词的日文文章不会被当成一个“词”。 |
| `mixed_content` | warning | page | `count`、`sample` | `https` 页面包含 `http://` 子资源。 |
| `html_lang_missing` | notice | page | — | 没有 `<html lang>`，或其值为空。辅助技术可能选择不合适的语音。 |
| `html_lang_invalid` | notice | page | `declared` | `lang` 的值不是 BCP-47 标签（`english`、带下划线的 `en_US`、`jp`）。 |
| `html_lang_mismatch` | warning | page | `declared`、`declared_script`、`detected_script` | 可见正文使用的文字系统与声明语言不符，例如日文页面使用 `lang="en"`，或拉丁文字内容使用 `lang="ru"`。只判断文字系统层面（要判断拉丁文字页面是否误报另一种拉丁文字语言需要猜测，而扫描不会猜测）；需要正文中至少有 40 个字母。 |

## 网络代码 {#network-codes}

仅在启用对应的主动选择开关时，由 `UrlScanner` 检测：规范网址目标使用 `seo-pro.scan.url_checks.check_canonical_target`，hreflang 替代版本使用 `check_hreflang_reciprocity`。所有目标的抓取都**经过 `SsrfGuard`**（协议允许列表、主机范围、拒绝私有 IP、重定向 / 时间 / 大小预算），且**不**跟随重定向，以便发现会重定向的规范网址。自引用规范网址或替代版本会跳过，因为刚刚已抓取过页面自身。

| 代码 | 严重程度 | 字段 | 证据 | 含义 |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | 在任何 HTTP 请求发生之前，目标已被 `SsrfGuard` 拒绝。 |
| `canonical_target_broken` | critical | canonical | `canonical`、`status` | 规范网址指向返回 HTTP 错误的页面。 |
| `canonical_target_redirect` | warning | canonical | `canonical`、`status`、`location` | 规范网址指向会重定向的页面；应指向最终 URL。 |
| `canonical_target_noindex` | warning | canonical | `canonical` | 规范网址指向的页面本身为 `noindex`。 |
| `canonical_target_blocked` | notice | canonical | `canonical`、`reason` | 无法验证规范网址目标（防护拒绝 / 无法解析）。 |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`、`href`、`status` | 页面声明的某个替代版本没有反向声明此页面。这对 hreflang 关联可能被忽略，但这本身不会使译文无法被索引。 |
| `hreflang_target_unverified` | notice | alternates | `hreflang`、`href`、`reason` | 无法抓取替代版本（防护拒绝、错误状态、重定向、超出大小限制），因此并未检查相互引用。这是缺乏证据，不是缺陷。 |

相互引用检查每页最多抓取 `hreflang_max_alternates` 个目标（默认 10），包含 `x-default`，跳过重复项和页面自身。上面的模型级 `hreflang_*` 元数据代码验证的是*声明的*列表；只有此项抓取检查需要读取另一个页面。

这里所有网络路径都复用共享的 `SsrfGuard`；威胁模型及残余 TOCTOU 风险说明见 [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md)。

## 代码如何参与评分 {#how-codes-feed-the-score}

[Pro SEO 评分](/zh-CN/pro/scoring)的计算方式为 `100 −` 每个计分问题的固定扣分，权重取决于上面的严重程度。多数代码计分，少数被有意排除：`missing_focus_keyword`（建议项），`noindex_page` 和 `multiple_h1`（信息项），`blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified`（“无法检查”不等于缺陷），以及 `hreflang_*`、`html_lang_*` 和 `aeo_*` 代码（目前不纳入评分的建议性信号）。[评分页面](/zh-CN/pro/scoring)列出了完整允许列表及每个代码的扣分。

## 问题生命周期 {#issue-lifecycle}

问题并不是只在缺陷存在时保留的一行数据；它有完整生命周期。扫描会**对齐更新**目标的问题，而不是清空后重新创建。每个问题都有稳定身份：目标（模型使用 `scannable_type` + `scannable_id`，路由 / 网站地图目标使用 `url`）加上其 `issue_type`。每次扫描中，每个目标的每个代码最多报告一次。“存在 N 个违规项”的代码（`missing_image_alt`、`mixed_content`、`hreflang_*` 等）将各实例合并为一行，包含 `count` / `sample`，从而保证该身份唯一。

每次扫描对每个目标执行以下处理：

- 发现**没有现有记录**的问题时，以 `open` 创建并写入 `detected_at`；
- 发现**匹配现有未解决记录**的问题时，刷新证据，保留原始 `detected_at`，使*首次发现时间*稳定，不再每次扫描重置；
- 已完成检查**不再发现**某个未解决问题时，将其标记为 **`fixed`**（写入 `resolved_at`）；**保留记录而不删除**，从而记录实际修复；
- **`fixed`** 问题**再次出现**时，在原记录上**重新打开**（回归），重新写入 `detected_at`；
- 用户在仪表盘中标记为 **`ignored`** 的问题保持不变。

| 状态 | 含义 | 设置方 |
|---|---|---|
| `open` | 当前存在。 | 扫描（新发现或仍然存在） |
| `fixed` | 曾经存在，目前不再发现。 | 扫描自动设置，在下一次未再次发现它的运行中更新 |
| `ignored` | 被用户静默处理，不计入未解决数量和评分。 | 仪表盘的 Ignore 操作 |

由于修复现在会被记录而不是丢弃，白标[报告](/zh-CN/pro/reports)可以展示一段时间内**实际修复 / 新增的数量**，无需仅比较两份报告的快照差异。所有读取未解决数量的地方，包括仪表盘、[`seo-pro:scan-status`](/zh-CN/pro/headless) 命令和[评分](/zh-CN/pro/scoring)，都会筛选 `open`，因此持久化的 `fixed` 记录不会虚增数量。已修复记录归属于解决它们的运行，并按正常的扫描运行[保留期](/zh-CN/pro/production)清理。

## 配置 {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

受保护抓取的响应大小预算由 `seo-pro.http.max_response_bytes` 控制（默认 2 MB）；进程内同主机扫描不受此上限限制。

## 兼容性说明（问题代码重命名） {#compatibility-note-issue-code-rename}

原先单一的 `robots_conflict` 代码（对应两种严重程度）已拆分，让每个代码恰好对应一种严重程度：

| 旧代码 | 新代码 | 严重程度 |
|---|---|---|
| `robots_conflict`（index + noindex） | `robots_conflict_indexing` | critical |
| `robots_conflict`（follow + nofollow） | `robots_conflict_following` | warning |

如果你曾存储 `robots_conflict` 或按它筛选，请更新为这两个新代码。
