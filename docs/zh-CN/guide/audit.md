---
description: "运行 php artisan seo:audit，以逐页通过、警告或失败表格查看当前 SEO 问题；进程内运行，无需队列、许可证或网络，属于免费核心。"
---

# 免费 SEO 审计（`seo:audit`） {#free-seo-audit-seo-audit}

`php artisan seo:audit` 用一条免费命令回答一个问题：**我的 SEO 现在有哪些问题？** 它在进程内遍历 `HasSEO` 模型，**无需队列、许可证或网络**，并输出逐页的**通过 / 警告 / 失败**表格和摘要。

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## 检查内容 {#what-it-checks}

审计只运行**元数据**执行类别，也就是仅靠模型和[解析器](/zh-CN/concepts/resolver-precedence)即可完成的检查，无需抓取页面：

| 检查项 | 代码 |
|---|---|
| 标题和描述是否存在，考虑回退值 | `missing_title`、`missing_description` |
| OG 图片是否存在，考虑回退值 | `missing_og_image` |
| 标题和描述长度 | `title_too_long`、`title_too_short`、`description_too_long`、`description_too_short` |
| 全站重复标题和描述 | `duplicate_title`、`duplicate_description` |
| Robots 冲突和可疑 noindex | `robots_conflict_indexing`、`robots_conflict_following`、`noindex_warning` |
| 规范网址格式、跨域、共享和不安全网址 | `invalid_canonical`、`cross_domain_canonical`、`shared_canonical`、`insecure_canonical` |
| 回答就绪度（AEO）：文章结构化数据 | `aeo_missing_author`、`aeo_article_missing_date` |
| 是否设置焦点关键词，需主动启用 | `missing_focus_keyword` |
| hreflang 替代版本，使用核心注册表，仅在页面存在替代版本时检查 | `hreflang_invalid_code`、`hreflang_duplicate_code`、`hreflang_missing_self` |

大多数代码也出现在 Pro 扫描中，但两者的注册表是独立的。例如，核心使用 `hreflang_missing_self`，Pro 使用 `hreflang_missing_self_reference`；`hreflang_duplicate_code` 在核心中是提示，在 Pro 中是警告。不要因为名称相同，就假定覆盖范围或严重程度一致。`blank_explicit_override` 属于核心注册表。长度使用编辑器的[按文字系统划分的预算](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)：拉丁文字为 60/160 个字符，CJK 约为 30/80，实际按字素计数，测量的是**解析后的值**，包括后缀。因此，审计与 [Filament 编辑器](/zh-CN/guide/filament)的计数器不会相互矛盾。hreflang 检查针对应用 `seo.hreflang` 策略后的列表，也就是标签和网站地图输出的同一份列表；双向关联检查需要爬取，因此属于 Pro。

**回答就绪度（AEO）**检查仅在页面声明文章类型 JSON-LD，例如 `Article`、`BlogPosting`、`NewsArticle`，并且缺少用于明确表达文章信息的信号时触发：`author` 实体用于明确作者身份和来源，`datePublished` / `dateModified` 用于明确时间线。没有文章的页面不会被标记，因此不适用的页面不会收到这类提醒。这些属于建议性的提示级问题，不计入 Pro 的 0–100 分评分。

## 不检查的内容：功能边界 {#what-it-does-not-check-—-the-capability-boundary}

免费的进程内审计无法等同于完整 Pro 扫描，命令每次运行都会说明这一点。它**不会**运行：

- **实际 HTML 检查**：`missing_h1`、`multiple_h1`、`missing_image_alt`、`thin_content`、`mixed_content`。这些需要页面实际提供的 HTML。
- **实时规范网址网络检查**：`canonical_target_broken` / `_redirect` / `_noindex`。这些需要经过防护的出站抓取。
- **0–100 数值评分**。评分是 Pro 功能，按带版本的评分标准计算，并持久化到扫描结果记录中，见 [SEO 评分](/zh-CN/pro/scoring)。

这些功能由 **Pro 扫描**提供，完整列表见[问题注册表](/zh-CN/pro/scan-issues)。

## 选择审计对象 {#choosing-what-to-audit}

默认情况下，命令审计 `seo.audit.models` 中列出的模型，并回退到 `seo.sitemap.models`：

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

也可以显式传入模型：

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## 选项 {#options}

| 选项 | 效果 |
|---|---|
| `--model=` | 要审计的 `HasSEO` 模型类，可以重复指定，覆盖配置。 |
| `--locale=` | 按此语言解析 SEO 数据，默认使用应用语言。 |
| `--limit=` | 每个模型最多审计的记录数，`0` 表示全部。 |
| `--issues-only` | 只列出至少有一个问题的页面。 |
| `--strict` | 发现任何问题时以非零状态退出，供 CI 使用。 |
| `--json` | 输出机器可读 JSON，包含页面、摘要和覆盖范围，代替表格。 |

### CI 门槛 {#ci-gate}

`--strict` 将审计变成构建检查：

```bash
php artisan seo:audit --strict
```

任意页面出现警告或失败时，退出码为 `1`；所有已审计页面通过时，退出码为 `0`。

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## 焦点关键词 {#focus-keywords}

`missing_focus_keyword` 提示**默认关闭**。只有主动启用焦点关键词工作流后才触发：

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Pro 扫描读取**同一个**标志，因此审计、扫描和 Pro 编辑器提醒始终一致。通过 [Filament 焦点关键词字段](/zh-CN/guide/filament)或 `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])` 设置页面关键词。

## 值不符合预期时：`seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` 告诉你*哪里有问题*，[`seo:explain`](/zh-CN/guide/explain)则解释*字段为何解析为当前值*：由配置、默认值、计算值还是显式值设置，覆盖了什么，以及后续的标题后缀、规范网址查询字符串移除和索引保护等后处理如何改变了它。审计结果或渲染标签令人意外时，可以使用：

```bash
php artisan seo:explain "App\Models\Post" 42
```

