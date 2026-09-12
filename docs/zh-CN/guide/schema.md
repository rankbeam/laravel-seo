---
description: "输出通过稳定 @id 相互引用的 JSON-LD 结构化数据图，关联 Organization、WebSite、WebPage 和 Article，让每个页面保持一致。"
---

# 结构化数据图（JSON-LD） {#schema-graph-json-ld}

当节点相互引用时，搜索引擎更容易理解 JSON-LD：Organization 发布 WebSite，WebSite 包含 WebPage，而 WebPage 的内容是 Article。`SchemaGraph` 正是这样生成数据：一组通过**稳定 `@id` 值**相互关联的节点，让每个页面输出一致的数据图。

## 页面数据图 {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

在 Blade 中渲染，可放在 head 或 body：

```blade
{!! $schemas->toScript() !!}
```

Organization 和 WebSite 数据来自 `config/seo.php` 中的 `schema.organization`、`schema.website`；WebPage 节点则根据解析后的 `SEOData` 填充。

WebPage 节点的 `inLanguage` 来自页面解析后的语言，并转换为 BCP 47 格式，例如 `it_IT` → `it-IT`。`ArticleSchema::fromModel()` 从存储的 `seo_meta` 语言获取该值，WebSite 节点则从 `schema.website.inLanguage` 列出网站语言，可以是单个代码或列表。将 `schema.in_language` 设为 `false`，即可完全不输出 `inLanguage`。参见[多语言内容](/zh-CN/guide/multilingual#inlanguage-in-the-schema-graph)。

## 类型化构建器 {#typed-builders}

常见富媒体搜索结果类型均有对应构建器：

| 构建器 | 说明 |
|---|---|
| `ArticleSchema::fromModel($post)` | 从模型和配置取得日期、作者及发布者 |
| `ProductSchema` | 报价、价格、供应情况 |
| `BreadcrumbSchema::fromArray([...])` | 有序的名称与网址配对 |
| `BreadcrumbSchema::fromModelAncestors($page)` | 遍历 `parent` 链，带有循环保护 |
| `FAQSchema` | 问题与答案配对 |
| `LocalBusinessSchema` | 地址、地理位置、营业时间 |
| `OrganizationSchema` | 独立的组织节点 |

完整文章页示例：

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## 附加的结构化数据与 `@seoSchema` {#attached-schema-and-seoschema}

保存在解析后 `SEOData` 上的结构化数据，例如与显式元数据一起保存的内容，会通过 `@seoSchema` 指令，或 `SEO::toArray()` 的 `script` 部分渲染：

```blade
@seoSchema($post)
```

编辑可以通过 [Filament 字段](/zh-CN/guide/filament#structured-data-schema-org)包中可选的**结构化数据**区域，无需代码即可填写 `seo_meta.schema_jsonld`。该区域提供自动面包屑开关和 FAQ / Product 块，保存前通过 `SchemaValidator` 验证。

## 转义 {#escaping}

所有 JSON-LD 输出，包括 `SchemaCollection::toScript()`、`toJson()` 和各渲染器路径，都使用 `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP` 编码。标题或内容中的 `</script>` 序列无法终止 script 元素。不要自行对结构化数据数组调用 `json_encode`，绕过这层保护。
