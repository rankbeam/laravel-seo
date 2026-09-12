---
description: "生成 XML 网站地图，每个数据源一个文件并附带索引，通过 /sitemap.xml 提供访问。支持模型、闭包和网址列表，封装 spatie/laravel-sitemap。"
---

# 网站地图注册表 {#sitemap-registry}

这个包生成 XML 网站地图，每个数据源一个文件，另有一个索引，并通过 `/sitemap.xml` 和 `/sitemap-{name}.xml` 提供访问。生成功能封装了 [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)：

```bash
composer require spatie/laravel-sitemap
```

## 注册数据源 {#registering-sources}

在服务提供者的 `boot()` 中注册具名数据源：

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

每个数据源渲染到 `sitemap-{name}.xml`；`sitemap.xml` 则成为列出所有文件的索引。

注册表 API 还提供 `has($name)`、`names()`、`forget($name)` 和 `flush()`。

## 通过配置指定数据源 {#config-driven-sources}

如果更喜欢使用配置，`config/seo.php` 接受模型数据源和静态网址：

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info 自动发现以注册表为准
如果具名注册数据源已经覆盖某个模型，自动发现会跳过它。注册 `'posts'` 不会额外再生成一个 `sitemap-post.xml`。
:::

## 生成 {#generating}

```bash
php artisan seo:sitemap
```

文件写入 `seo.sitemap.disk` 配置的磁盘，默认是 `public`。安排命令定时运行，以保持网站地图更新：

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

超过 `seo.sitemap.max_urls_per_sitemap` 的网站地图会自动拆分。默认值为 50,000，也是 XML 规范的限制。

## 提供访问 {#serving}

包的路由提供命令已经生成的内容，并附带 XML 响应头、缓存响应头和 `X-Robots-Tag: noindex`：

- `/sitemap.xml`：索引或单个网站地图
- `/sitemap-posts.xml`：具名数据源

如果改为提供自行静态生成的网站地图，可以关闭这些路由：

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## 浏览器中的网站地图样式 {#styled-sitemap-in-the-browser}

Spatie 引擎输出的是原始 XML。用浏览器打开 Rankbeam 网站地图时，会看到易读且带有品牌样式的页面：每个网址以表格行显示，包含 `lastmod`、更新频率、优先级，以及图片和替代版本数量，还附有行内验证说明：

![Rankbeam 网站地图在浏览器中显示为易读、带有品牌样式的表格](/sitemap-styled.png)

实现方式是在每个生成的网站地图中引用 XSL 样式表：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

搜索引擎会**忽略**这条指令，因此网站地图仍然是普通、机器可读的 XML 文档，只改变*人*看到的样子。索引与所有子网站地图使用相同样式。

**默认启用。** 与图片和 hreflang 扩展不同，样式表不添加数据，也不执行逐记录工作，只是一行爬虫会跳过的指令，因此开箱即用。关闭后输出普通 XML：

::: warning 需要 spatie/laravel-sitemap ≥ 8.1
该指令通过 Spatie 的 `setStylesheet()` 写入，这项 API 在 `spatie/laravel-sitemap` **8.1** 中加入。如果应用解析到较旧版本，某些 PHP/Laravel 组合会出现这种情况，网站地图仍会生成，只是没有样式，不会损坏。运行 `composer update spatie/laravel-sitemap` 可获得带样式的视图。
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### 验证说明 {#validation-notes}

渲染后的页面会标记两类无需离开浏览器即可检查的问题：

- **网址缺少 `lastmod`**：只指出，不编造。Google 会降低对虚报更新时间的网站地图的信任，因此样式表指出空缺，而不是补造时间。
- **非绝对网址**：`<loc>` 不是绝对 `http(s)` 网址。

### 自行托管样式表 {#self-hosting-the-stylesheet}

默认情况下，包通过自己的 `/sitemap.xsl` 路由提供样式表，并让每个网站地图引用它。浏览器只会应用与网站地图**同源**的 XSLT，因此如果网站地图位于其他源，例如 CDN，需要发布文件，并将配置指向自己的副本：

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info 通过结构保证安全
样式表渲染的每个值，包括网址，都经过 XSLT 输出转义。`<loc>` 只有是 `http(s)` 网址时才会成为可点击链接，因此恶意网址内容无法向页面注入标记或 `javascript:` 链接。如果自定义发布后的 `.xsl`，请保留这个特性，不要添加 `disable-output-escaping`。
:::

## 包含哪些内容 {#what-gets-included}

模型数据源包含解析为允许索引的记录。robots 解析为 `noindex` 的模型不会进入网站地图。网址来自 `getUrlForSEO()`，与规范网址使用同一个方法，因此网站地图与规范网址不会不一致。

## 图片与 hreflang 扩展 {#image-hreflang-extensions}

两个可选扩展使用包已经为记录解析的数据，丰富每个模型网址。两者都**默认关闭**，可在 `config/seo.php` 中启用需要的扩展：

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

它们适用于使用 `HasSEO` trait 的模型，值来自模型完整解析后的 `seoData()`：

- **`images`** 根据解析后的 OG 或内容图片，添加 [Google 图片网站地图](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)条目。它与页面上渲染为 `og:image` 的值*相同*，因此不会与页面不一致。如果记录没有自己的图片，使用的就是全站 `default_og_image`，所以只有逐网址图片对内容有意义时才启用。
- **`alternates`** 从模型的 `getSEOAlternates()` 添加 `<xhtml:link rel="alternate" hreflang="…">` 条目，与页面 `<head>` 中渲染的 hreflang 链接相同。请返回绝对网址：

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflang 必须双向关联并包含自引用
只有每个语言版本都列出**自身和所有其他版本**，且引用**双向成立**，也就是每页都指回其他页面时，Google 才会采纳注释。因此，`getSEOAlternates()` 必须返回**完整**集合，每个本地化版本也必须返回同一完整集合。使用有效的 `language[-Script][-REGION]` 代码或 `x-default`，以及绝对 `http(s)` 网址。缺少非空 `hreflang` 或 `href` 的条目会被跳过。

列表写入前会经过 [`seo.hreflang` 策略](/zh-CN/guide/multilingual#hreflang)。代码被规范化为 BCP 47，例如 `it_IT` → `it-IT`，`include_self` / `x_default` 可以代为添加自引用和 `x-default`。网站地图始终包含与页面 `<head>` 相同的列表。免费审计报告 `hreflang_invalid_code`、`hreflang_duplicate_code` 和 `hreflang_missing_self`；双向关联需要爬取，由 Pro 处理。
:::

::: info 大规模运行的成本
从 **core 3.20.1** 开始，在构建每个模型网址时，是否包含模型的判断与图片、hreflang 扩展复用同一个解析后的 `seoData()`。本次网址处理结束后，包括失败情况，复用也随之结束；后续构建或其他语言会重新解析。在 3.20.0 中，如果解析器缓存关闭，也就是默认状态，包含判断加上扩展可能遍历两次优先级链。每次解析仍可能进行缓存或数据库操作，自定义 `getSEO*()` getter 也可能增加查询。请使用**定时运行的** `seo:sitemap` 命令，而不是 Web 请求。在接近 50,000 个网址的规模进行基准测试，不需要这些条目时保持扩展关闭。
:::

::: tip 已经发布过配置？
`config/seo.php` 使用**浅合并**，因此在这个版本之前发布过配置文件的应用，不会自动获得 `sitemap.images` / `sitemap.alternates` 键，仅设置 `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` 环境变量无法启用它们。请将两个键添加到已发布的 `sitemap` 数组中，参见上面的代码块，或重新发布配置。
:::

## 完全控制：手动构建 Spatie 标签 {#full-control-hand-built-spatie-tags}

对于解析数据未覆盖的内容，例如图片说明、**视频**、**新闻**条目或自定义 `hreflang` 集合，可以从已注册数据源返回完全手动构建的 [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images)。构建器原样传递 `Url` 标签，不会为其追加自己的扩展，因此你保有完整控制：

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

逐记录也可以使用同一机制：实现 `Sitemapable` 的模型，如果其 `toSitemapTag()` 返回一个 `Url`，就会按返回内容原样输出。
