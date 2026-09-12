---
description: "将是否允许索引与 Laravel 环境绑定，防止预发布或本地副本进入搜索；不在允许列表中的环境强制 noindex，并禁止爬虫抓取。"
---

# 索引保护：非生产环境的安全措施 {#indexing-guard-non-production-safety-net}

预发布或本地网站副本进入 Google，是最常见也最具破坏性的 SEO 错误之一：重复内容与真实页面竞争，私有环境出现在索引中，使用网址移除工具清理又可能耗时数周。典型原因是 `noindex` 只存在于某个忘记设置的 `.env` 中，或者 robots 规则被部署覆盖。

**索引保护**从结构上降低这类问题的发生概率。将是否允许索引与 Laravel *环境*绑定，而不是依赖某个需要人记住的标志：应用只要运行在允许列表之外的环境中，每页就会被强制设为 `noindex,nofollow`，托管的 `robots.txt` 会禁止所有爬虫，`seo:audit` 也会明确显示这一状态。

这是免费核心功能。

## 生效时执行的操作 {#what-it-does-when-active}

启用保护后，如果 `app()->environment()` **不在** `seo.indexing_guard.allowed_environments` 中，会自动执行四项操作：

1. **解析器为每个页面强制使用 `noindex,nofollow`。** 该规则的优先级*高于*整个[优先级链](/zh-CN/concepts/resolver-precedence)，甚至覆盖存储在 `seo_meta` 中、逐页显式设置的 `robots` 值。
2. **发送 `X-Robots-Tag: noindex,nofollow` HTTP 响应头**，覆盖所有经应用处理的响应，见下文[非 HTML 响应](#non-html-responses-pdfs-feeds-images)。
3. **`SEO::robotsTxt()->build()` 输出禁止所有抓取的 `robots.txt`**，以及 `ai.txt`，内容就是 `User-agent: *` / `Disallow: /`。这同时覆盖 `seo:robots-txt` 命令和可选的[动态路由](/zh-CN/guide/ai-crawlers)。
4. **`seo:audit` 输出醒目的提示条**，让你阅读报告时不会对“所有内容都是 noindex”感到意外。

在允许的环境中，默认是 `production`，保护完全**不生效**，不会改变任何输出，渲染结果逐字节一致。

## 非 HTML 响应：PDF、订阅源和图片 {#non-html-responses-pdfs-feeds-images}

强制输出的 `robots` **元标签**只能被解析 HTML 的爬虫读取。PDF、RSS/Atom 订阅源、图片或其他非 HTML 响应都没有 `<head>`。因此，保护生效时还会通过全局中间件，将同一条指令作为 HTTP 响应头发送：

```http
X-Robots-Tag: noindex,nofollow
```

响应头和元标签来自同一个来源，因此不会不一致。该功能**在保护内部默认启用**，但保护本身需要主动启用，并在允许的环境中保持无作用；关闭以下设置即可只使用元标签：

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

中间件**仅在保护启用时注册**，因此关闭保护时不会向应用栈添加任何中间件。

::: warning 静态文件绕过 PHP
Web 服务器直接从 `public/` 返回的文件不会进入 Laravel，因此无法获得这个响应头。请在边缘层保护这些文件，例如通过 Web 服务器或 CDN 配置。本功能覆盖的是所有经应用处理的响应。
:::

## 为什么覆盖显式 robots 值 {#why-it-overrides-an-explicit-robots-value}

在 Rankbeam 的其他地方，显式存储值始终优先，这正是优先级链的目的。索引保护是有意设置的唯一例外，位于显式层*之上*，因为这里的风险只朝一个方向发生：

- 预发布数据库通常是生产数据库的克隆，因此保存了 `index,follow` 的页面，会将该指令带到预发布环境并请求被索引。
- **错误地索引预发布环境会造成严重问题；错误地将它设为 `noindex` 则没有额外影响。** 因此，在本来就不希望索引的环境中，保护提供了一条存储值无法突破的底线。

## 启用 {#enabling-it}

保护出厂时**关闭**，所以安装或升级包不会在未经你选择的情况下，改变非生产环境的渲染。这与解析器的 [`blank_is_unset`](/zh-CN/concepts/resolver-precedence) 和生成的 OG 图片遵循同一策略：主动启用之前，保持逐字节一致。只需一行即可启用：

```dotenv
SEO_INDEXING_GUARD=true
```

默认允许列表不会影响 `production`，因此可以在共享配置中保持保护启用。请确认允许列表包含所有你希望被索引的环境。我们**强烈建议启用**，并考虑在 Core 4 中默认开启。

同样用一行关闭：

```dotenv
SEO_INDEXING_GUARD=false
```

## 选择允许索引的环境 {#choosing-which-environments-may-index}

默认仅允许 `production`。可以使用逗号分隔的环境变量覆盖列表：

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

也可以在 `config/seo.php` 中设置：

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

条目通过 `Str::is()` 匹配，因此支持**通配符**。例如，`'prod*'` 匹配 `production` 和 `prod-eu`：

```php
'allowed_environments' => ['prod*'],
```

**空列表**意味着*没有任何*环境允许索引，保护将在所有环境中生效，这是偏向安全的处理方向。注意，空或仅含空白的 `SEO_INDEXING_GUARD_ALLOWED` 环境变量值会回退到 `['production']`，避免拼写或设置错误悄悄让生产环境退出索引。如果确实希望所有环境都受保护，请在配置中显式写入 `[]`。

## 验证 {#verifying-it}

`seo:audit` 会显示提示条，并在 `--json` 中提供机器可读的状态：

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

受保护环境中提供或生成的 `robots.txt` 如下：

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## 范围 {#scope}

保护控制的是**索引指令**，包括 `robots` 元标签、`X-Robots-Tag` 响应头和 `robots.txt`。它不修改标题、描述、规范网址或结构化数据，也独立于 [robots 渲染策略](/zh-CN/concepts/resolver-precedence)，即 `seo.robots.emit_default`。由于 `noindex,nofollow` 与网站默认值不同，它始终会作为标签输出。
