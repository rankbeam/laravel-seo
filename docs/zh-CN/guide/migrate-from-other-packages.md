---
description: "将其他 Laravel SEO 包的 API 和存储映射到 HasSEO trait 与 saveSEO()，切换到 Rankbeam；逐模型 SEO 数据可通过一条命令导入。"
---

# 从其他 Laravel SEO 包迁移 {#migrating-from-other-laravel-seo-packages}

已经在使用其他 SEO 包？切换到 Rankbeam 的设计目标是一天内完成，而不是重写应用。本指南将各个常见包的 API 和存储，映射到 Rankbeam 的两个基础接口：[`HasSEO`](/zh-CN/guide/quickstart) trait 和 `saveSEO()`。对于按模型存储 SEO 数据的包，还提供一条命令导入器。

::: tip 从 WordPress 迁移？
如果准备将使用 Yoast 或 Rank Math 的内容网站迁出 WordPress，请参阅专门的[**从 WordPress 迁移**](/zh-CN/guide/migrate-from-wordpress)指南，其中介绍 CSV 导入器和实时数据库读取器。
:::

| 来源 | 数据存储位置 | 迁移路径 |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | `seo` 多态关系表 | **`php artisan seo:import-from ralphjsmit`**，再替换 trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | 不存储，使用运行时值和配置 | 替换代码，通过 `saveSEO()` 或计算 getter 设置值 |
| [`spatie/*`](#from-spatie-packages) | 不存储，使用 schema-org 或网站地图构建器 | 保留互补功能，其余移入 Rankbeam |

这里只有 **ralphjsmit** 将 SEO 数据持久化到数据库表，因此只有它有数据可供批量导入。其他工具是运行时标签构建器，没有可读取的数据表，需要将每次请求中的调用替换为存储的 `seo_meta`。

---

## 从 `ralphjsmit/laravel-seo` 迁移 {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo` 在 `seo` 表中为每个模型保存一个多态关系行，其结构接近 Rankbeam 的 `seo_meta`，因此可以进行清晰、幂等的批量导入。

### 1. 在原包旁安装 Rankbeam {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

迁移期间两个包可以共存，因为它们使用不同的数据表，`seo` 与 `seo_meta`，以及不同的 trait 命名空间。

::: warning 两个包共用一个配置文件位置
如果应用中仍有 `ralphjsmit/laravel-seo` 发布的 `config/seo.php`，它会遮蔽 Rankbeam 配置，因为两者共用 `seo` 配置键。请先备份，再删除，并重新发布 Rankbeam 配置：`php artisan vendor:publish
--tag=seo-config`。
:::

### 2. 运行导入器 {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

导入器读取 ralphjsmit 的 `seo` 表，将每行解析为真实的 Eloquent 模型，再把数据写入 `seo_meta`。

| 选项 | 效果 |
|---|---|
| `--dry-run` | 报告将要导入的内容，不写入。 |
| `--model="App\Models\Post"` | 限定到一个或多个模型类，可以重复指定。 |
| `--locale=fr` | 按此语言写入导入行，默认使用应用语言。 |
| `--table=legacy_seo` | 读取更名后的源表。 |
| `--connection=legacy` | 从另一个数据库连接读取源表。 |
| `--limit=100` | 最多导入 N 行，适合分阶段迁移。 |
| `--overwrite` | 替换已有非空值，默认只填充空字段。 |
| `--json` | 机器可读报告。 |
| `--force` | 跳过确认提示，供脚本或 CI 使用。 |

它是**幂等的**：重复运行会更新同一批行，不创建重复记录；默认只*填充*空字段，不覆盖已经在 Rankbeam 中设置的 SEO 数据。如果希望导入值替换现有值，传入 `--overwrite`。

### 3. 替换模型上的 trait {#_3-swap-the-trait-on-your-models}

将 ralphjsmit trait 换成 Rankbeam trait。方法名称略有不同，trait 现在读取的表是 `seo_meta`。

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

如果此前通过 ralphjsmit 的 `getDynamicSEOData()` 自定义 SEO 数据，请将逻辑移到 Rankbeam 的逐字段计算 getter：`getSEOTitle()`、`getSEODescription()`、`getSEOImage()`、`getUrlForSEO()`、`getSEOAlternates()`，详见[快速入门](/zh-CN/guide/quickstart)。需要持久化的覆盖值通过 `saveSEO()` 保存：

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### 字段映射 {#field-mapping}

导入器**显式**映射字段，不会盲目复制 Core 3 表结构不存在的列。

| ralphjsmit `seo` | Rankbeam `seo_meta` | 说明 |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | 从当前模型**重新解析**，见下文，不是原样复制。 |
| `title` | `title` | 截断到 70 个字符，即 `seo_meta` 列长度；报告超长值。 |
| `description` | `description` | 截断到 160 个字符；报告超长值。 |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | 截断到 50 个字符。 |
| `image` | `og_image` | `twitter:image` 通过解析器自动继承该值。 |
| `author` | *不导入* | Core 3 的 `seo_meta` 没有作者列。文章作者属于解析器层面的职责，不是存储的社交元数据。带有作者的行会**计数并报告**，便于决定将其放在哪里，例如 `getSEOData` 风格的计算值。 |
| `id`、`created_at`、`updated_at` | *不导入* | 结构字段。 |

**为什么重新解析多态类型。** 每个源行都会解析到真实模型，然后从模型自身的 `getMorphClass()` 获取 `seoable` 键。这样，即使 ralphjsmit 存储时使用不同约定，关系也能在应用*当前*的[多态映射](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)下保持正确。导入器也能跳过模型已删除的行，将其报告为已跳过，不会写入孤立记录。

### 报告提供的信息 {#what-the-report-tells-you}

不使用 `--json` 时，会输出结果表以及三个需要检查的部分：

- **已截断**：为适应 `seo_meta` 列而缩短的值，需要检查。
- **未导入**：有数据、但在 Core 3 中没有对应位置的源列，例如 `author`。
- **按原因列出的跳过行**：空源行、已删除模型、无法解析的模型类型。

### 验证 {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

确认结果满意后，移除 `ralphjsmit/laravel-seo` 并删除它的 `seo` 表。

---

## 从 `artesaos/seotools` 迁移 {#from-artesaos-seotools}

`artesaos/seotools` 是**运行时**标签构建器。每次请求通过 `SEOMeta`、`OpenGraph`、`TwitterCard` 和 `JsonLd` facade 设置值，通常在控制器中，并使用 `config/seotools.php` 默认值。它不按模型存储数据，因此没有表可导入，需要将逐请求调用移为存储值或计算值。

| artesaos/seotools 调用 | Rankbeam 对应方式 |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` 或 `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` 或 `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` 或 `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | 没有对应的 keywords 元标签；焦点关键词用于内部编辑检查。`saveSEO(['focus_keywords' => [...]])`，见[审计](/zh-CN/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [JSON-LD 结构化数据图](/zh-CN/guide/schema) |
| `config/seotools.php` 默认值 | `config/seo.php` 网站默认值和[解析器优先级](/zh-CN/concepts/resolver-precedence) |
| 布局中的 `{!! SEO::generate() !!}` | `@seo($model)`，见 [Blade](/zh-CN/guide/blade) |

这里改变的是思路：不再在每个控制器中逐项设置标签，而是按模型在 `seo_meta` 中保存一次 SEO 数据，再由 Rankbeam 解析器渲染。原本位于 `config/seotools.php` 的全站回退值，变为 Rankbeam 的[配置默认值](/zh-CN/reference/configuration)；逐路由静态页面使用 `@seoForRoute()`。

---

## 从 Spatie 包迁移 {#from-spatie-packages}

不存在名为 `spatie/laravel-seo` 的元数据存储包，因此没有数据需要导入。常与 SEO 配合使用的 Spatie 包属于**互补的构建器**，可以逐项保留或替换：

- **`spatie/schema-org`**：链式 JSON-LD 构建器。Rankbeam 有自己的[结构化数据图](/zh-CN/guide/schema)，提供类型化的 `Article`、`FAQPage`、`Product`、`BreadcrumbList`、`LocalBusiness` 和 `Organization` 构建器，存储到 `seo_meta.schema_jsonld` 并去重渲染。如果已有手动构建的 `spatie/schema-org` 对象，可以将其 `->toArray()` 输出传给 `saveSEO(['schema_jsonld' => $array])`，或使用 Rankbeam 构建器重新表达。
- **`spatie/laravel-sitemap`**：网站地图生成器。Rankbeam 的[网站地图注册表](/zh-CN/guide/sitemaps)基于它构建。可以注册模型作为数据源，让 Rankbeam 输出合并的网站地图；也可以保留现有 Spatie 网站地图，并关闭 Rankbeam 路由。

如果使用的是 [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO)，即另一种运行时、基于结构体的元数据构建器，可以遵循与 artesaos 相同的模式：将逐请求的 `setTitle`/`addMeta` 调用移到 `saveSEO()` 或计算 getter。

---

## 扩展导入器 {#extending-the-importer}

`seo:import-from` 命令背后是一个小型的 `Rankbeam\Seo\Importing\Contracts\Importer` 实现注册表，因此新增来源无需修改命令。当前内置来源包括 `ralphjsmit`，以及 WordPress 导入器 `wordpress-csv`、`yoast`、`rank-math`，详见[从 WordPress 迁移](/zh-CN/guide/migrate-from-wordpress)。可以在服务提供者中注册自己的来源：

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```

