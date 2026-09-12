---
description: config/seo.php 中的所有选项，按解析器层分组，并列出每个值随包提供的默认设置。
---

# 配置 {#configuration}

发布配置文件：

```bash
php artisan vendor:publish --tag=seo-config
```

以下所有内容都位于 `config/seo.php`，所示值均为默认值。

## 全站默认值，第 1 层 {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` 会追加到解析后的标题，除非标题已经以它结尾。

`title_suffix_skip_when_contains` 是识别品牌的后缀省略列表。解析后的标题已经以**完整词语**包含任意一个标记时，会跳过后缀，避免重复品牌名称。匹配不区分大小写，并识别词语边界，因此 `Acmestic` 不匹配 `Acme`。默认 `[]` 保留历史行为。

## Robots 渲染策略 {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

解析后的指令等于上面的 `default_robots` 时，渲染的 `<head>` 会省略 `<meta name="robots">` 标签。多余的 `index,follow` 只会增加噪声，没有标签时爬虫本来就按 index,follow 处理。**不同于默认值**的指令，例如 `noindex`、`nofollow`、`max-snippet:-1`，始终原样输出。将 `emit_default` 设为 `true`，即可始终渲染标签，恢复 3.1 之前的行为。细粒度 `@seoRobots` 指令不受影响，因为它是显式选择，始终渲染。支持的指令和优先级见[渲染约定](/zh-CN/contributing/rendering-contract)。

## 索引保护：非生产环境的安全措施 {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

启用后，如果应用运行环境**不在** `allowed_environments` 中，保护会为每页强制使用 `noindex,nofollow`，优先级高于整个解析链，甚至覆盖逐页存储值；同时发送匹配的 `X-Robots-Tag` 响应头，输出禁止所有抓取的 `robots.txt`，并让 `seo:audit` 显示提示条。在允许的环境中，默认是 `production`，它不生效。

出厂时**关闭**，主动启用前输出逐字节不变。通过 `SEO_INDEXING_GUARD=true` 启用，通过 `SEO_INDEXING_GUARD=false` 关闭，两者都只需一行。通过 `SEO_INDEXING_GUARD_ALLOWED` 覆盖允许列表，使用逗号分隔；支持 `Str::is()` 通配符，例如 `prod*`；空列表表示所有环境都受保护。

`send_header` 在保护内部默认启用，会为所有经应用处理的响应发送 `X-Robots-Tag: noindex,nofollow`，因此没有 `<meta robots>` 的 PDF、订阅源和图片也能获得这条限制指令。中间件仅在保护启用时注册。强烈建议启用，完整说明见[索引保护指南](/zh-CN/guide/indexing-guard)。

## 规范网址 {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

解析器从请求网址或模型的 `getUrlForSEO()` **推导**规范网址时，默认移除查询字符串。跟踪、筛选和排序参数都会让同一页面产生重复内容的规范目标。`query_whitelist` 中列出的键会按列出顺序**保留**在推导规范网址中，其他参数仍被移除。典型情况是分页归档中的 `page`，因为 `/blog?page=2` 确实不是 `/blog`。

**显式设置**的规范网址，包括管理界面输入或来自更高优先级层的值，始终原样输出，保留整个查询字符串。允许列表只控制推导回退值。默认 `[]` 保留移除所有参数的行为。

## 功能开关 {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` 会在创建 `HasSEO` 模型时创建一个空的 `seo_meta` 行。注意，使用 `WithoutModelEvents` 的 seeder 会绕过这一行为。

## 焦点关键词 {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

这是焦点关键词的**工作流开关**。保持默认 `false` 时，没有焦点关键词的页面不会在任何地方被标记，[`seo:audit`](/zh-CN/guide/audit) 和 Pro 扫描都不会提醒，让从未使用这一功能的应用不必处理无关消息。当开始设置焦点关键词时，例如使用 [Filament 焦点关键词字段](/zh-CN/guide/filament)，再启用开关。免费审计、Pro 扫描和 Pro 编辑器都会开始对仍缺少关键词的页面报告 `missing_focus_keyword` 提示。它们读取同一标志，因此始终一致。

## 免费审计（`seo:audit`） {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

未传入 `--model` 选项时，免费的 [`seo:audit`](/zh-CN/guide/audit) 命令会审计这些模型。每个模型必须使用 `HasSEO` trait。列表为空时，命令回退到 `sitemap.models` 中注册的模型。

## 计算得出的回退值，第 5 层 {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

在主动启用的 `best` 策略下，构建器对一个有序候选列表评分：首先是 `getSEOImage()`，它仍是最高优先级候选；然后是模型的 `getSEOImages()` 钩子、常见图片字段、第一张内容图片，以及配置的默认图片。评分依据是图片像素尺寸与理想尺寸的接近程度，**低于最小尺寸的图片会被跳过**。仅测量**本地**图片，包括 `public/` 下的相对路径、公共磁盘，或自己主机上的绝对网址。远程网址不会被抓取，只作为回退。如果没有本地候选达到最小尺寸，选择会回退到第一个匹配项，因此 `best` 不会比 `first` 返回更少结果。可以从模型提供候选：

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## 网站地图 {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

通过代码注册数据源的方法，见[网站地图注册表指南](/zh-CN/guide/sitemaps)。

## 结构化数据（JSON-LD） {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

这些配置为[结构化数据图](/zh-CN/guide/schema)节点提供数据。

## 路由 {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

应用提供自己的静态 `/sitemap.xml` 时，设置 `enabled => false`。

## 缓存 {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### 解析器结果缓存 {#resolver-result-cache}

`SEOResolver` 在**每次**前端渲染时运行完整优先级链：配置 → 全局、模型类型和路由默认值 → 模型计算值 → 显式 `seo_meta` → 标题后缀、规范网址和结构化数据。在高流量网站上，参考应用约每天 20k 请求，这意味着每页会有多次数据库读取。

启用 `cache.resolver.enabled` 后，会缓存模型完整解析后的 SEO。**缓存命中完全跳过优先级链**；在包的基准测试中，热缓存命中产生**零次**数据库查询，而每次未缓存解析都会重新读取模型的 `seo_meta`。缓存载荷是普通数组，通过 `SEOData::fromArray()` 重建，绝不缓存对象，因为 Laravel 13 提供 `cache.serializable_classes = false`，缓存对象会以 `__PHP_Incomplete_Class` 形式返回。

它使用上面配置的 `store`，因此生产环境中应指向**共享、持久的缓存**，例如 `redis` / `memcached`。所有 Web 和队列工作进程都必须能看到缓存及其失效变化；具备这样的缓存之前，请保持关闭。

**失效处理自动且正确**，启用和关闭缓存的解析结果相同。条目以 `(model class, id, locale, route, request URL)` 为键，以下情况会清除：

- 页面的 `seo_meta` 行被**保存或删除**，无论通过 `saveSEO()`、Filament，还是直接写入 `SEOMeta`。
- 模型的**内容字段**变化，也就是 `getSEOContentFields()` 中的列。默认包括所有内置计算回退字段：标题字段、excerpt/summary/content/body/text/article 字段，以及 `featured_image`、`thumbnail`、`cover_image`、`og_image`、`photo`、`banner`、`hero_image` 等常见图片字段。如果模型从额外列计算 SEO，请覆盖该设置。
- **任意 `seo_defaults` 行**变化。默认值可能影响任何模型，因此会清空整个解析缓存。

支持**标签**的缓存存储，例如 `redis`、`memcached`、`array`，通过缓存**标签**清除模型条目；不支持标签的存储，例如 `file`、`database`，则回退到逐模型的**版本标记**。两种方式都不需要扫描键。

::: tip
只有基于模型的解析会被缓存。手动构建 `SEOData` 的 `SEO::render()`/`@seo()`，以及无模型路由的 `@seoForRoute()`，仍然实时解析。
:::

::: warning
缓存反映的是最近一次**内容字段**变化时，或 TTL 到期前，模型的 `updated_at` / 计算得出的 `modified_time`。仅调用 `touch()`，只改变 `updated_at` 而未修改 `getSEOContentFields()` 列时，不会强制重新解析，因此 `article:modified_time` 最多可能滞后一个 TTL。如果希望应用特有的计算字段变化后立即使缓存失效，请将其加入 `getSEOContentFields()`。
:::
