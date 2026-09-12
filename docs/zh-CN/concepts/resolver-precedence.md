---
description: "了解 Rankbeam 如何解析每个 SEO 值：按优先级合并六层数据，较高层优先，null 不覆盖已有值，让每个页面都能输出合理结果。"
---

# 解析器优先级 {#resolver-precedence}

每个实际生效的 SEO 值，包括标题、描述、规范网址、robots 和图片，都由 `SEOResolver` 合并**六层数据**得到。较高层优先，`null` 不会覆盖较低层的值，因此每个页面都能渲染合理的内容。

## 六层数据 {#the-six-layers}

从最低层，始终存在，到最高层，始终优先：

| # | 层 | 来源 | 典型用途 |
|---|---|---|---|
| 1 | **网站配置** | `config/seo.php`（`site_name`、`title_suffix`、`default_og_image`、`default_robots` 等） | 整个品牌的默认值 |
| 2 | **数据库全局默认值** | 未指定模型类型的 `seo_defaults` 行 | 无需部署即可编辑的全站默认值 |
| 3 | **模型类型默认值** | 限定到某个模型类的 `seo_defaults` 行 | “所有产品都使用这张 OG 图片” |
| 4 | **路由默认值** | 限定到某个路由名称的 `seo_defaults` 行 | 没有模型的静态页面，例如 `home`、`contact` |
| 5 | **计算值** | 从模型自身属性推导 | 从 `title` 回退取得标题，从 `excerpt`/`body` 取得描述等 |
| 6 | **显式值** | 模型的 `seo_meta` 行，通过 `saveSEO()` 保存 | 编辑手动设置的内容 |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

结果是一个不可变的 `SEOData` 值对象，所有渲染器，包括 Blade、数组和 Inertia，均使用它。

## 计算得出的回退值，第 5 层 {#computed-fallbacks-layer-5}

没有显式值时，解析器从模型推导：

- **标题**：模型的 `title`/`name` 属性。
- **描述**：`seo.computed.description_fields` 中第一个包含有效文本的属性。默认顺序为 `excerpt`、`summary`、`description`、`intro`、`lead`、`teaser`、`content`、`body`、`text`、`article`。文本会移除 HTML、解码实体，并在词语边界截断。长度由 `seo.computed.description_max_length` 控制，默认 160，不添加省略号。
- **Robots**：来自模型的 `getSEORobots()` 钩子或 `is_indexable` 属性，见[控制 robots 和是否允许索引](#controlling-robots-and-indexability)。
- **从网址推导的值**：通过 `getUrlForSEO()` 获取规范网址和 `og:url`。

## 控制 robots 和是否允许索引 {#controlling-robots-and-indexability}

逐模型 `noindex` 已内置，无需额外包或复杂的字段设置。`HasSEO` trait 没有*声明* robots 方法，因为它是可选的，所以容易被忽略；但解析器已经支持以下三个来源，按优先级从高到低排列：

| 优先级 | 来源 | 示例 |
|---|---|---|
| 1 | **显式 `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | 模型上的 **`getSEORobots(): ?string` 钩子** | 返回 `'noindex, nofollow'`，或返回 `null` 继续回退 |
| 3 | **`is_indexable` 属性**，数据列或访问器 | 假值 ⇒ `noindex, nofollow`；真值 ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### 实际渲染的内容 {#what-actually-renders}

解析后的指令在进入 `<head>` 前，会经过**输出策略**过滤。**仅当指令与 `default_robots` 不同时**才输出 `<meta name="robots">` 标签，默认值为 `index,follow`。因此：

- **允许索引**的页面，解析为 `index, follow` 时，**不输出 robots 标签**；没有该标签，爬虫就按 index,follow 处理。
- **不允许索引**的页面输出 `<meta name="robots" content="noindex, nofollow">`。
- 任何不同于默认值的指令，例如 `noindex`、`max-snippet:-1`、`unavailable_after`，都会**原样输出**，保留输入时的空格。

设置 `seo.robots.emit_default = true` 可以始终渲染该标签。完整说明见 [robots 渲染策略](/zh-CN/reference/configuration#robots-rendering-policy)。

## 解析后应用的策略 {#policies-applied-after-resolution}

无论值来自哪一层，都会执行以下处理：

- **标题后缀**：追加 `title_suffix`，除非解析后的标题已经以它结尾。如果路由默认模板已经包含品牌名称，请让模板以该后缀结尾，避免出现“Brand — X | Brand”。
- **规范网址查询字符串处理**：*推导出的*规范网址，即模型网址或当前网址，会移除查询字符串，但保留 [`canonical.query_whitelist`](/zh-CN/reference/configuration#canonical-urls) 中列出的键，例如分页归档使用的 `page`；*显式设置的*规范网址则原样保留。
- **社交图片绝对网址**：`og:image` 和 `twitter:image` 始终输出为绝对网址，即使存储的是相对路径，因为 Open Graph 规范要求如此。

## 查看最终采用了哪一层 {#inspecting-which-layer-won}

[Filament 包](/zh-CN/guide/filament)会逐字段显示来源：手动设置、内容回退、模型类型默认值、全局默认值、站点配置或由 URL 推导。在代码中，`SEOWarningEvaluator` 提供同样的手动值与回退值区分，可用于构建自己的管理界面来源指示器。
