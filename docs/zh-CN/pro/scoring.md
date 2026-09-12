---
description: "Pro 扫描的 0–100 SEO 评分：规则透明、结果确定，每个扣分都对应一项扫描问题，同一组问题始终得到相同分数。"
---

# SEO 评分——透明、有版本、归属 Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Pro 扫描为每个页面提供 **0–100 的 SEO 评分**，也就是从 RankMath 或 Yoast 迁移的用户熟悉的那个数字。它不是无法解释的黑箱等级，而是**完全可审计**的：每个扣分都恰好对应一项[扫描问题](/zh-CN/pro/scan-issues)，同一组问题始终得到相同分数。

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip 一个分数，一个归属
数值评分是 **Pro** 功能，保存在 Pro 的 `seo_scan_results` 记录中，不会写回核心包的 `seo_meta`（旧的 `seo_score` 列已在 Core 3 中移除）。免费核心包的 [`seo:audit`](/zh-CN/guide/audit) 为每个页面报告**通过 / 警告 / 失败**，**不提供数值**；评分属于付费增值功能。
:::

## 评分规则 {#the-rubric}

评分依据**公开且有版本的规则** `Rankbeam\Seo\Pro\Scanning\ScoreRubric` 计算。它由两部分定义：明确列出计分问题代码的**允许列表**，以及每种**严重程度对应的固定扣分**。

| 严重程度 | 扣分 | 含义 |
|---|---|---|
| `critical` | **−40** | 在本规则中影响较大的发现。 |
| `warning` | **−15** | 应尽快调查的发现。 |
| `notice` | **−5** | 可进一步完善的事项。 |

每个代码的严重程度直接取自[问题注册表](/zh-CN/pro/scan-issues)这一唯一事实来源，评分规则不会重新推导。每个代码只对应一种严重程度，正是为了确保分数具有确定性。

### 哪些问题计入评分 {#what-the-score-counts}

这些确定性检查由 Rankbeam 的产品评分规则选定，其中也包括需要编辑判断的启发式检查。critical 扣 40 分，warning 扣 15 分，notice 扣 5 分；这个分数不预测搜索表现。

| 代码 | 严重程度 | 扣分 |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

元数据代码在模型扫描中检测，而渲染及网络代码仅在 URL 扫描中检测（参见[执行类别](/zh-CN/pro/scan-issues#execution-classes)）。因此，**模型**目标的分数反映元数据检查，**URL** 目标的分数反映渲染后的页面。模型扫描得到 100 分表示“未发现元数据缺陷”，并不表示“渲染后的页面完美无缺”；要检查后者，请扫描 URL。

### 哪些问题明确不计入评分 {#what-the-score-deliberately-does-not-count}

以下注册表代码被有意排除。排除项也是契约的一部分：测试会断言注册表中的每个代码要么计分，要么列在这里。

| 代码 | 排除原因 |
|---|---|
| `missing_focus_keyword` | **建议项。** 仅在主动启用的 `seo.keywords.enabled` 工作流中生效。页面不应因未使用焦点关键词而失分，评分也不应依赖配置开关。 |
| `noindex_page` | **信息项。** `noindex` 是一种有意设置的状态，不是元数据质量缺陷。“noindex 与自引用规范网址同时存在”这一启发式检查由 `noindex_warning` 计分。 |
| `multiple_h1` | **信息项。** Google 容许多个 H1，因此不对多个 H1 扣分。 |
| `blocked_url` | **缺乏证据。** SsrfGuard 拒绝了抓取，所以根本没有检查页面；这不是页面的缺陷。 |
| `canonical_target_blocked` | **缺乏证据。** 无法验证规范网址目标；这不是页面的缺陷。 |
| `hreflang_invalid_code`、`hreflang_missing_self_reference`、`hreflang_duplicate_code`、`hreflang_missing_x_default` | **目前属于建议项。** 这些 Pro hreflang 代码会出现在扫描中；免费审计有自己的 hreflang 代码，但目前不会改变分数。将其纳入评分需要提升 `VERSION`。 |
| `html_lang_missing`、`html_lang_invalid`、`html_lang_mismatch` | **建议项。** 语言检查不纳入本评分规则。 |
| `hreflang_not_reciprocal` | **建议项。** 可选的相互引用检查，不计分。 |
| `hreflang_target_unverified` | **缺乏证据。** 无法验证相互引用。 |
| `aeo_missing_author`、`aeo_article_missing_date` | **建议项。** 回答就绪度（AEO）信号，会在扫描和免费审计中标记缺少作者实体或发布日期的文章，但不改变分数；纳入评分需要提升 `VERSION`。 |

关键词密度、吸引力词汇以及[页面检查清单](/zh-CN/pro/on-page-checklist)中的其他项目完全不参与评分。它们是建议性检查，单独列为通过 / 警告 / 失败，而不是注册表代码。

## 版本管理——历史分数不会悄然改变 {#versioning-—-historical-scores-never-silently-change}

每个持久化的分数都记录生成它的 `ScoreRubric::VERSION`（`rubric_version`）。这带来两个结果：

- **新**问题代码在明确加入允许列表之前**不计分**，所以发布新检查绝不会追溯改变已存储的分数。（允许列表或权重变化本身就是评分规则变化，需要提升版本。）
- 分数会**存储下来，读取时不会重新计算**。上周看到的数字今天仍然相同，同时显示能够解释它的评分规则。

## 存储位置 {#where-it-s-stored}

每次扫描会为每个目标向 `seo_scan_results` 插入或更新一行：

| 列 | 保存的内容 |
|---|---|
| `scannable_type` / `scannable_id` | 被评分的模型（URL 目标为 null）。 |
| `url` | 被评分的 URL。 |
| `score` | 0–100 的分数。 |
| `rubric_version` | 生成分数的评分规则。 |
| `penalty_total` | 应用最低 0 分限制**之前**的原始扣分总和。 |
| `scored_issues` | 影响分数的问题数量。 |
| `breakdown` | `[{code, severity, penalty}, …]`，完整的计算记录。 |
| `keywords_enabled` | 扫描时的 `seo.keywords.enabled` 状态（为透明性而记录；评分并不依赖它）。 |
| `scan_run_id` | 生成分数的扫描运行（清理该运行时设为 null，而不删除评分记录；分数表示当前状态，不是运行历史）。 |
| `scored_at` | 评分时间。 |

## 读取评分 {#reading-the-score}

**无界面使用**——获取模型的最新评分：

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` 会在摘要中输出**网站平均分**。Filament 仪表盘将其作为醒目的“Avg. SEO score”统计项，并按等级着色。

### 等级区间 {#grade-bands}

字母等级根据分数生成，仅用于展示（数值才是契约）：

| 分数 | 等级 |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 上线检查信号（`noindex_warning`） {#the-shipping-signal-noindex-warning}

当页面同时包含 `noindex` 和**自引用规范网址**（指向自身 URL 的规范网址）时，会触发 `noindex_warning`。Rankbeam 将它视为需要复核的上线信号。自引用规范网址**不能**证明页面有被索引的意图；这种组合可能是有意设置的。跨域规范网址不会触发这一启发式检查。问题中包含 `context.shipping_signal`（例如 `self_canonical`），以及参与比较的 `canonical` 和 `page_url`。

两种扫描器都会执行此检查。模型扫描（`PageScanner`）将存储的规范网址与模型 URL 比较。渲染后的 URL 扫描（`UrlScanner`）则把具有自引用规范网址的 `noindex` 页面，从信息项 `noindex_page` 升级为计分项 `noindex_warning`。这也是排除 `noindex_page` 本身的原因：两条路径上的潜在冲突都由 `noindex_warning` 处理。更改索引指令之前，请先核实页面的实际意图。

## 配置 {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

允许列表和权重**不可配置**。对于给定的 `rubric_version`，所有安装中的评分都必须具有确定性，因此更改计算方式属于代码层面的评分规则变更，而不是设置项。
