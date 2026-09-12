---
description: "将手写的 Yoast 或 Rank Math SEO 标题、描述、规范网址、robots 和焦点关键词迁移到 Laravel 模型。本页提供导入器字段映射参考。"
---

# 从 WordPress 迁移 {#migrating-from-wordpress}

准备将内容网站迁出 WordPress？Rankbeam 可以将团队在 Yoast 或 Rank Math 中手工编写的 SEO 元数据，包括标题、描述、规范网址、robots 指令、焦点关键词和社交覆盖值，迁移到 Laravel 模型，避免切换时丢失多年优化成果。

::: tip 正在进行实际切换？
本页是导入器的*参考文档*，介绍字段映射、模板标记和源键。需要逐步执行、风险较低的**操作流程**，即共存、导入、验证，再停用旧系统时，请遵循 [WordPress 迁移操作手册](/zh-CN/guide/wordpress-migration-runbook)。
:::

有两条路径，都由同一个 `seo:import-from` 命令驱动：

| 路径 | 来源 | 适用场景 |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | 从 WordPress 导出的电子表格 | 大多数代理机构迁移，可精确控制网址 |
| [**数据库**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | 实时 WordPress 数据库 | 尽量完整保留数据，包括 OpenGraph/Twitter 覆盖值和 Rank Math 重定向 |

两者都具有**幂等性**，重复运行只更新相同行，不产生重复记录；都支持 **`--dry-run`**，默认只*填充*空字段，不覆盖已经在 Rankbeam 中设置的 SEO 数据。传入 **`--overwrite`** 才会用导入值替换现有值。

## WordPress 行如何变成 `seo_meta` 行 {#how-wordpress-rows-become-seo-meta-rows}

WordPress 数据不是 Laravel 多态关系数据。WordPress 行以**网址**或**文章 ID** 为键，而 Rankbeam 的 `seo_meta` 使用多态关系，每行都必须附着到真实的 Eloquent 模型。因此，导入器将每个 WordPress 行匹配到模型，并在报告中明确区分哪些已关联模型、哪些只有网址：

- **已关联模型。** 通过 `--model="App\Models\Post"` 指定目标模型。每行的 **slug**，即网址最后一个路径段，或 WordPress 的 `post_name`，会与模型匹配。默认使用路由键，也可以通过 `--match-by=` 选择列。匹配成功的行写入 `seo_meta`。
- **仅网址。** 没有匹配模型的行，或未指定 `--model` 的运行，无法生成 `seo_meta` 行，因为没有模型可关联。报告会将其列为跳过，原因为 `url-only`。其规范网址仍可成为[重定向候选](#redirects)。

WordPress 文章和页面通常对应*不同的* Laravel 模型，因此按内容类型分别运行导入器，并限定行范围：

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning 默认不扫描自定义文章类型
数据库读取器仅遍历 **`post`** 和 **`page`** 文章类型。使用自定义文章类型的网站，例如主题的 `product`、`event`、`pathology`，必须逐个显式指定，可重复使用 `--post-type=`：

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSV 导入 {#_1-csv-import}

CSV 路径适合大多数代理机构迁移。按以下表头导出，每个网址一行。列顺序不限，未识别列会被忽略并报告：

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

运行：

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| 列 | 映射到 `seo_meta` | 说明 |
|---|---|---|
| `url` | *匹配键* | 使用 slug，即最后一个路径段，匹配模型。必填。 |
| `title` | `title` | 截断到 70 个字符，报告超长值。 |
| `description` | `description` | 截断到 160 个字符。 |
| `canonical` | `canonical` | 同时用于[重定向候选](#redirects)。 |
| `robots` | `robots` | 原样存储，例如 `noindex, nofollow`，截断到 50 个字符。 |
| `focus_keyword` | `focus_keywords` | 逗号分隔，第一个为主关键词。 |

格式错误的行会被跳过并计数，包括缺少 `url` 的行，或列数与表头不一致的行。

---

## 2. 数据库导入：Yoast / Rank Math {#_2-database-import-yoast-rank-math}

如果仍保留 WordPress 数据库，导入器可以直接读取 SEO 元数据，包括 CSV 导出通常会丢失的 OpenGraph/Twitter 覆盖值，以及 Rank Math 的重定向。

### 配置指向 WordPress 的连接 {#point-a-connection-at-wordpress}

在 `config/database.php` 中添加 WordPress 数据库连接：

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

然后导入。表前缀默认是 `wp_`，可以通过 `--table=` 覆盖：

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

读取器遍历 `{prefix}posts` 中已发布的文章和页面，从 `{prefix}postmeta` 获取每篇文章的插件元数据，并将其 `post_name` slug 匹配到模型。

::: tip 非默认表前缀
托管 WordPress 服务经常使用随机化前缀，例如 `wppg_`，而不是 `wp_`。检查导出文件中的 `CREATE TABLE` 名称，传入真实前缀，例如 `--table=wppg_`，让读取器能够找到 `{prefix}posts` 和 `{prefix}postmeta`。
:::

::: tip 从 MySQL 8 中恢复的数据库导出读取
如果将 WordPress 数据库导出恢复到 MySQL 8+ 以便本地读取，请在导入 `.sql` 前放宽严格 SQL 模式。WordPress 的 `'0000-00-00'` 日期时间默认值会被 MySQL 8 默认的 `STRICT`/`NO_ZERO_DATE` 模式拒绝，因此数据库导入本身会先报 `Invalid default value for 'post_date'`，SEO 导入还没机会运行：

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### 字段映射 {#field-mapping}

两个导入器都**显式**映射字段。Core 3 中没有对应列的键会被报告为*未映射*，不会编造目标字段。

| Yoast 元数据键 | Rank Math 元数据键 | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots。** 仅存储与 WordPress 默认值不同的指令，因此普通的允许索引页面会让 `robots` 保持 null，并继承网站默认值。Yoast 分开的 `noindex` / `nofollow` 和高级标志 `noarchive`、`nosnippet`、`noimageindex` 会组合成一个字符串；Rank Math 序列化的 `robots` 数组也按相同方式读取，并移除 `index` / `follow` 默认值。

**未映射键**只报告，不复制，包括附件图片 ID（`*-image-id`）、关键词或 SEO 评分（`linkdex`、`content_score`、`rank_math_seo_score`）、主分类选择，以及 Rank Math 富媒体摘要的结构化数据标记。[结构化数据图](/zh-CN/guide/schema)提供更丰富、类型化的替代方式。

::: warning 规范网址原样导入
显式规范网址，即 `rank_math_canonical_url` / `_yoast_wpseo_canonical`，会**完全按存储值复制**。如果页面将规范网址固定为*旧*域名的绝对网址，这在托管或预发布主机中很常见，例如 `https://oldsite-staging.example.com/page/`，导入后仍指向旧域名，导入器不会重写主机名。`--site-url` 会从绝对网址提取请求*路径*，供[重定向候选](#redirects)和 CSV 行匹配使用，但**不会**重写存储的规范网址。跨域迁移后，请检查导入的规范网址并更新主机名，或清空它们，让解析器回退到指向自身的规范网址。大多数页面没有显式规范网址，因此不受影响；Yoast 和 Rank Math 在渲染时自动生成规范网址。
:::

### 模板标记 {#template-tokens}

Yoast 和 Rank Math 将标题和描述存储为带标记的**模板**，Yoast 使用 `%%title%%`，Rank Math 使用 `%title%`。导入器**解析能够推导的标记**，并**移除其余标记**，因此存储值不会是原始 `%%token%%` 字符串：

| 标记 | 解析为 |
|---|---|
| `%%title%%` / `%title%` | WordPress 文章标题 |
| `%%sitename%%` / `%sitename%` | 数据库导入时，从 `wp_options` 取得博客名称 |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`、`%%primary_category%%` 等 | *移除*，留空并整理周围分隔符 |

只要本次运行解析过任何模板标记，报告就会说明。请**审阅导入后的标题**，确认表达符合预期，并调整少数依赖了无法推导标记的标题。

---

## 重定向 {#redirects}

`seo_redirects` 是 [Rankbeam **Pro**](/zh-CN/pro/installation) 功能，因此核心导入器不会直接写入该表。传入 `--redirects-csv=` 后，导入器会**输出 CSV**，使用与 Pro 重定向表相同的列，即 `source_path,target_url,status_code,note`，再由你导入 Pro。

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

重定向候选来自：

- **CSV 导入**：如果一行的 `canonical` 指向与自身 `url` **不同的路径**，会生成从旧路径到规范网址的 `301`。指向自身、路径相同的规范网址*不会*输出，否则会形成循环。
- **Rank Math 数据库**：`{prefix}rank_math_redirections` 表中的启用规则。仅输出**精确匹配**规则；正则、包含、开头和结尾匹配规则无法映射为单一路径，因此报告为跳过。
- **Yoast 免费版**没有重定向表，只有 Yoast Premium 有，而其表结构不属于免费包范围。Yoast 重定向请使用 CSV 路径。

这些候选是**建议性的**。请先检查 CSV，再通过 [`seo-pro:redirects-import`](/zh-CN/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro) 导入 Pro。后者验证每一行，拒绝循环、不安全目标和重复项。CSV 结构是稳定约定，即**重定向 CSV 格式 v1**：`source_path,target_url,status_code,note`。

---

## 报告提供的信息 {#what-the-report-tells-you}

不使用 `--json` 时，命令输出结果表，包含已创建、已更新、未变更、已跳过和已扫描数量，另有**验证报告**及检查部分：

- **验证报告**：便于整体确认的分类，包括 **matched**，已关联模型的行，**url-only**，未匹配模型的行，以及截断和未映射数量。
- **已截断**：为适应 `seo_meta` 列而缩短的值。
- **未导入**：含有数据、但在 Core 3 中没有对应位置的源键，**包括每个不同的 `author` 值**。作者不是存储列，而是 [`getSEOAuthor()`](/zh-CN/concepts/resolver-precedence) 的职责，因此报告列出需要重新安排位置的数据，不让它们悄悄消失。
- **重定向候选**：写入了多少条，以及目标文件。
- **按原因列出的跳过行**：仅网址行、没有 SEO 元数据的文章、非精确匹配重定向规则。
- **警告**：例如已解析模板标记。

添加 `--json` 可获得上述全部内容的机器可读版本，其中 `verification` 块包含 matched/url-only 数量及所有作者值。

### 验证 {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

任意页面有问题时，`--strict` 以非零状态退出。参见[免费 SEO 审计](/zh-CN/guide/audit)。完整且有顺序的切换流程，即共存 → 导入 → 验证 → 停用旧系统，见 [WordPress 迁移操作手册](/zh-CN/guide/wordpress-migration-runbook)。

---

如果来源是 **Laravel** SEO 包，例如 ralphjsmit、artesaos 或 Spatie，请参阅[从其他 Laravel 包迁移](/zh-CN/guide/migrate-from-other-packages)。
