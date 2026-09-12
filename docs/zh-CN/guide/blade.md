---
description: "使用包提供的 Blade 指令，在服务器端渲染的 Laravel 中输出 SEO；一体化 @seo 解析模型并输出 meta、Open Graph、Twitter Cards 和 JSON-LD。"
---

# Blade 指南 {#blade-guide}

针对传统服务器端渲染应用，这个包提供七条 Blade 指令。通常只需要其中的 `@seo`。

## 一体化指令 {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` 按[优先级链](/zh-CN/concepts/resolver-precedence)解析模型，并渲染完整的 head 块：`<title>`、元描述、规范链接、robots、Open Graph 标签、Twitter Card 标签和附加的 JSON-LD。robots 标签**仅在值不同于网站默认值时输出**，多余的 `index,follow` 会省略，因为没有标签本来就表示 index,follow。设置 `seo.robots.emit_default` 可以始终渲染它。完整规则见[渲染约定](/zh-CN/contributing/rendering-contract)。

签名：

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` 接受 `Model`、手动构建的 `SEOData` 或 `null`。路由和语言参数仅适用于 `Model`/`null` 路径，手动构建的 `SEOData` 则携带自己的值。

## 没有模型的路由页面 {#route-pages-no-model}

用于静态页面、归档页和其他由路由承载的页面：

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

路由值来自按路由名称限定范围的 `seo_defaults` 行。

## 没有模型的页面：手动构建 `SEOData` {#model-less-pages-hand-built-seodata}

列表、搜索结果和控制器中组合的内容，往往没有单一对应模型。构建一个 `SEOData`，直接传给 `@seo` 或 `SEO` facade 即可，无需使用 `app(TagRenderer::class)->render(...)`：

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

手动构建的 `SEOData` 被视为**显式意图**。你设置的每个值都会保留，只在渲染时补齐以下空缺：

- 缺少 `canonical` / `og:url` 时，从当前网址推导；显式 `canonical` 原样保留，包括查询字符串。
- 仅当标题不含 `title_suffix` 时才追加它。如果标题已经带有品牌标记，则完全跳过，见 [`title_suffix_skip_when_contains`](/zh-CN/reference/configuration)。
- 相对的 `og:image` / `twitter:image` 路径通过 `url()` 转换为绝对网址；它遵循当前协议，**不会**强制 HTTPS。
- 从配置和应用语言填充 `og:site_name` 与 `locale`。

数据库优先级链，包括全局、模型类型、路由和 `seo_meta` 默认值，**不会**合并到手动构建的 `SEOData` 中。传入什么就渲染什么，仅补齐上述空缺。

同一个值也可以通过 facade 使用：

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## 可扩展的布局模式 {#a-layout-pattern-that-scales}

用一个布局服务模型页面、路由页面和其他页面：

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

之后，控制器只需传入 `'seoModel' => $post` 或 `'seoRoute' => 'blog.index'`，无需处理标记。

## 细粒度指令 {#granular-directives}

需要控制单个标签时，例如与其他包的输出组合，可以使用：

| 指令 | 输出内容 |
|---|---|
| `@seoTitle($post)` | 仅 `<title>` |
| `@seoMeta($post)` | 仅元描述 |
| `@seoCanonical($post)` | 仅规范链接，回退到当前网址 |
| `@seoRobots($post)` | 仅 robots 元标签，始终渲染；这是显式选择，因此**不会**使用 `@seo` 的默认值省略策略 |
| `@seoSchema($post)` | 仅 JSON-LD `<script>`，可放在 head 或 body 中 |

这些指令都接受与 `@seo` 相同的 `($model, $route, $locale)` 表达式；不传参数时用于当前页面。

## Hreflang 替代版本 {#hreflang-alternates}

使用 `HasSEO` 的模型，可以直接通过解析器提供 hreflang 链接：

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

请使用绝对网址。`@seo($post)` 解析这些条目，并将每个条目渲染为 `<link rel="alternate" hreflang="..." href="...">`。代码会先转换为 BCP 47 格式，例如 `it_IT` → `it-IT`；`seo.hreflang` 策略可以添加页面自身的引用和 `x-default`。免费审计会标记无效、重复或缺少自引用的条目。详见[多语言内容](/zh-CN/guide/multilingual#hreflang)。

## 转义与安全 {#escaping-and-safety}

文本值通过 `e()` 转义。JSON-LD 使用 `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` 编码，因此用户内容中的 `</script>` 无法逃出 script 元素。
