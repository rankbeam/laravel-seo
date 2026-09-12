---
description: "通过内容协商向 AI 爬虫提供页面的干净 Markdown 表示，同时保持普通访客的 HTML 完全不变。免费核心功能，默认关闭。"
---

# 面向机器人的 Markdown {#markdown-for-bots}

应用页面的 HTML 在内容周围包含导航、脚本和布局标记。一些 AI 爬虫和回答引擎在有更干净的表示形式时，会选择接受它。因此，这项功能可以通过内容协商，为提出请求的客户端提供页面的 **Markdown 表示**，同时让所有普通访客继续获得原封不动的 HTML。这是一项需要主动启用的兼容选择，并不承诺任何特定客户端会如何解析或使用结果。

它与 [AI 爬虫控制](/zh-CN/guide/ai-crawlers)配合使用：后者设置访问策略，本功能决定请求期间提供*什么内容*。

这是免费核心功能，**默认关闭**。

## 工作方式 {#how-it-works}

启用后会注册一个内容协商中间件。普通响应生成后，只有**同时满足以下两个条件**时，才替换为 Markdown：

1. **请求要求 Markdown**：显式 `Accept: text/markdown` 内容协商请求头、`?format=md` 查询参数，或者主动启用后，根据 User-Agent 识别到已知 AI 爬虫。
2. **能够为路由解析出 Markdown 来源。**

否则，响应原样通过，浏览器不受影响。只有成功的 **HTML** 响应会被替换，JSON、重定向或下载响应都不会。

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Markdown 来自哪里 {#where-the-markdown-comes-from}

以下来源可以为匹配的路由提供 Markdown。中间件**先尝试已注册的路由来源**，再尝试路由绑定模型。对每个模型，显式 `toSeoMarkdown()` 方法优先于内置回退逻辑；该方法返回 null 或空白时，会禁用这个模型的回退逻辑。

### 1. 模型自身的 Markdown {#_1-a-model-s-own-markdown}

如果没有已注册的路由来源返回内容，路由绑定模型可以通过实现 `toSeoMarkdown()` 控制自身输出。你可以实现 `ProvidesSeoMarkdown` 接口，也可以直接添加该方法：

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. 已注册的路由来源 {#_2-a-registered-route-source}

对于没有模型的路由，或需要覆盖模型输出的情况，可以按路由名称注册来源：

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. 内置回退逻辑 {#_3-the-built-fallback}

当路由绑定的 `HasSEO` 模型没有 `toSeoMarkdown()` 时，中间件会使用解析后的**标题**，作为 H1、**描述**以及模型的 **`getContentForSEO()`**，构建一个基本文档：

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning 内容原样提供
回退逻辑原样输出 `getContentForSEO()`。如果内容是 HTML 而不是 Markdown，请实现 `toSeoMarkdown()` 来控制转换。通过 `seo.markdown_for_bots.build_from_content = false` 可以完全关闭回退逻辑。
:::

## 配置 {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

保持 `serve_to_known_bots` 关闭时，只根据显式 `Accept` / `?format` 信号协商。启用后，即使 GPTBot、ClaudeBot、PerplexityBot 等机器人没有明确请求，也会向它们提供 Markdown；识别依据是 [AI 爬虫目录](/zh-CN/guide/ai-crawlers)。
