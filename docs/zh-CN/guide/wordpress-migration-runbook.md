---
description: "逐步将线上网站的 Yoast 或 Rank Math 替换为 Rankbeam：默认只填充空字段，试运行不写入，WordPress 保持不变，以较低风险完成迁移。"
---

# WordPress → Rankbeam 迁移操作手册 {#wordpress-→-rankbeam-migration-runbook}

本手册逐步介绍如何用 Rankbeam 替换 WordPress SEO 方案，即 Yoast 或 Rank Math。导入器默认填充目标空字段，只有 `--overwrite` 才显式允许替换。试运行不写入，源 WordPress 数据库保持不变。导入前请同时备份源和目标。

本手册是[从 WordPress 迁移](/zh-CN/guide/migrate-from-wordpress)的操作配套文档。后者详细说明字段映射、模板标记处理和源键，解释*迁移什么*；本页则解释*按什么顺序执行*。

::: tip 需要准备的内容
- **核心**（`rankbeam/laravel-seo`），用于元数据导入和 `seo:audit`。
- **Pro**（`rankbeam/laravel-seo-pro`），仅在同时迁移**重定向**时需要，因为 `seo_redirects` 表属于 Pro 功能。
- 已在 Laravel 中建模的内容，例如 `App\Models\Post`，使用 [`HasSEO`](/zh-CN/guide/quickstart) trait，并且能够将 WordPress slug 匹配到模型，使用模型路由键，或通过 `--match-by` 指定列。
:::

## 迁移的结构 {#the-shape-of-the-migration}

WordPress 行以**网址或文章**为键；Rankbeam 的 `seo_meta` 行是**多态关系**，关联到 Eloquent 模型。导入会将每个 WordPress 行匹配到模型，可能有三种结果，每次运行都会报告分类数量：

| 结果 | 含义 | 操作 |
|---|---|---|
| **matched** | 已关联模型，`seo_meta` 已写入 | 无需操作 |
| **url-only** | 未匹配模型，或没有指定 `--model` | 决定页面需要模型还是重定向 |
| **unmapped** | 行内包含 Core 3 无对应位置的数据，尤其是**作者** | 为其安排新位置，例如 `getSEOAuthor()` 钩子 |

---

## 第 0 步：共存，暂不切换 {#step-0-—-coexist-no-cutover-yet}

在运行中的网站**旁边**搭建 Rankbeam。为模型添加 `HasSEO` trait，通过 facade 或指令渲染标签，但**不要**移除 WordPress 安装或 SEO 插件。此时尚未导入任何内容，也没有破坏性操作，只是在验证新方案能够启动。

如果切换期间，新 Laravel 应用和旧 WordPress 网站由同一主机提供访问，请在第 5 步之前保持路径分开。

## 第 1 步：导入元数据，先试运行 {#step-1-—-import-the-metadata-dry-run-first}

始终从 `--dry-run` 开始。它**不写入任何内容**，只输出完整验证报告，说明*将会*发生什么。

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

常用选项如下，完整列表可运行 `php artisan seo:import-from --help` 查看：

| 选项 | 用途 |
|---|---|
| `--model=` | 目标模型的完整限定类名，可以重复指定；WordPress 导入器每次运行关联**一个**模型，因此需按内容类型分别运行 |
| `--match-by=` | 用于匹配 slug 的模型列，默认是路由键 |
| `--post-type=` | 将数据库读取器限定到这些文章类型，默认是 `post` + `page` |
| `--connection=` | WordPress 表所在的数据库连接 |
| `--table=` | WordPress 表**前缀**，默认是 `wp_` |
| `--locale=` | 写入 `seo_meta` 行时使用的语言 |
| `--redirects-csv=` | 同时将重定向候选输出到该文件，供第 3 步使用 |
| `--site-url=` | 旧网站网址，用于从绝对网址推导路径 |
| `--overwrite` | 替换已有非空 `seo_meta`，默认**只填充空值** |
| `--limit=` | 限制源行数量，适合初次尝试 |
| `--json` | 机器可读报告 |

试运行结果正确后，移除 `--dry-run` 以正式应用：

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

导入具有**幂等性**，默认**只填充空字段**，因此可以安全重跑，不会覆盖已经在 Rankbeam 中编辑的元数据。

## 第 2 步：读取并归档验证报告 {#step-2-—-read-and-archive-the-verification-report}

每次运行都会输出**验证报告**，其中数字是删除任何内容之前需要确认的依据。将报告保存为长期可查的文件：

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

需要检查：

- **matched** 应等于预计应携带 SEO 元数据的页面数量。
- **url-only** 是未匹配模型的页面待办列表，需要逐项决定是否添加模型、设置重定向，见第 3 步，或无需处理。
- **truncated** 列出为适应 `seo_meta` 列而缩短的字段，请审阅这些标题和描述。
- **unmapped** 列出 Core 3 没有对应列的源数据，并明确列出**每个不同的 `author` 值**。作者不是存储列，而属于 `getSEOAuthor()` 的职责；报告的目的是让你主动安排这些数据的新位置，而不是几个月后才发现丢失。

## 第 3 步：将重定向导入 Pro {#step-3-—-import-the-redirects-into-pro}

核心导入器**绝不写入 `seo_redirects`**，因为它是 Pro 表。导入器提供固定、带版本格式的 CSV，即**重定向 CSV 格式 v1**：`source_path,target_url,status_code,note`。先试运行，再导入 Pro：

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

每行使用与 Filament 重定向表单完全相同的验证。格式错误行、无效状态代码、**不安全的外部目标**、**重复来源**和会形成**重定向循环**的规则，都会带着原因被跳过，绝不静默写入。试运行验证整个文件，包括循环和重复项，不写入任何内容。传入 `--overwrite` 才会替换现有规则的目标。

## 第 4 步：通过 `seo:audit --strict` 验证 {#step-4-—-verify-with-seo-audit-strict}

将免费的进程内审计作为迁移门槛。只要**任意**页面有问题，`--strict` 就会以非零状态退出，因此可用于 CI 或切换检查：

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

审计覆盖模型和解析器检查，包括标题及描述是否存在、长度、OG 图片、robots 冲突和规范网址格式。实际 HTML 与实时规范网址检查，以及 0–100 分评分，属于 [Pro 扫描](/zh-CN/pro/scan-issues)。如果有 Pro，也应运行扫描。另见[免费 SEO 审计](/zh-CN/guide/audit)。

然后在浏览器中抽查若干真实页面，查看源代码，确认 `<title>`、`<meta name="description">`、规范网址、robots 和 OpenGraph 标签确实渲染了导入值。

## 第 5 步：移除旧包或表之前完成验证 {#step-5-—-verify-before-removing-the-legacy-package-table}

以下条件**全部**满足之前，**不要**删除 WordPress 数据库、SEO 插件或旧包：

- [ ] 已为**每种**内容类型运行导入，每次使用一个 `--model`。
- [ ] 已归档的验证报告显示预期的 **matched** 数量，没有意外的 **url-only** 行。
- [ ] 所有需要保留的**未映射作者**值都已安排到新位置。
- [ ] 已将重定向导入 Pro，使用 `seo-pro:redirects-import`，并确认若干旧网址实际以 301 跳转到新网址。
- [ ] `php artisan seo:audit --strict` 退出码为 `0`。
- [ ] 使用 Pro 时，`php artisan seo:doctor` 报告没有遗留的旧 `seo` 表，也没有 `config/seo.php` 冲突。
- [ ] 已在浏览器中抽查渲染页面。

由于导入器默认只填充空值并且幂等，在通过这一门槛前，可以随时重跑第 1 步而不造成损害；旧数据仍在 WordPress 中。

## 第 6 步：停用旧系统 {#step-6-—-decommission}

只有第 5 步检查清单通过后，才将 WordPress 网站下线，再移除数据库、数据表和旧 SEO 包。保留数据库备份，直到确信新方案已在生产环境正确提供服务。

::: tip 回滚
在默认只填充空字段的模式下，第 1–4 步没有破坏性操作：`seo_meta` 是增量添加，重定向经过验证并可通过删除规则撤销，WordPress 数据保持不变。第 6 步之前，回滚就是*继续提供 WordPress 服务*；第 6 步之后，则需要*恢复 WordPress 备份*。
:::

---

如果来源是 **Laravel** SEO 包，例如 ralphjsmit、artesaos 或 Spatie，请参阅[从其他 Laravel 包迁移](/zh-CN/guide/migrate-from-other-packages)。
