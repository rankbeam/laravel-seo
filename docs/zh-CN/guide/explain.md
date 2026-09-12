---
description: "seo:explain 显示每个 SEO 字段由哪一层设置、覆盖了哪些值；只读，无需网络或许可证，用来调试不符合预期的标题或 robots 标签。"
---

# 解释解析结果（`seo:explain`） {#explain-the-resolution-seo-explain}

Rankbeam 通过[分层优先级链](/zh-CN/concepts/resolver-precedence)解析页面 SEO：配置、数据库默认值，包括全局、模型类型和路由默认值、模型计算值，再到显式 `seo_meta`。之后执行标题后缀、规范网址和图片绝对网址转换等后处理，以及[索引保护](/zh-CN/guide/indexing-guard)。当渲染的 `<title>` 或 `robots` 标签不符合预期时，**`seo:explain` 会准确显示每个字段由哪一层设置，以及覆盖了哪些值。**

它是只读的，无需网络或许可证，也不会重新实现合并逻辑。来源归属直接取自解析器各层的贡献，最终值也来自实际解析器，因此解释不会与真正渲染的结果逐渐偏离。

## 用法 {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

模型必须使用 [`HasSEO`](/zh-CN/guide/quickstart) trait。

## 读取输出 {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by**：最终生效的层，也就是设置了非 null 值、优先级最高的层。如果没有任何一层设置字段，但值由系统*推导*得到，则显示 `post-processing`，例如从请求或模型网址取得规范网址、从规范网址取得 og:url，或将图片转为绝对网址。
- **Overrode**：按顺序列出所有曾提供值、但因优先级较低而被覆盖的层，让你看到哪些值被遮蔽。
- **↳ notes**：合并各层之后改变值的后处理，包括标题后缀、规范网址查询字符串移除、og:url 推导、图片绝对网址转换，以及优先于所有层、强制使用 `noindex` 的索引保护。

::: tip og:type 和 twitter:card
这两个字段带有非 null 的框架默认值，分别为 `website` / `summary_large_image`，因此设置它们的最高层，通常是 `computed`，会优先于 `config`。没有保存 `seo_meta` 行的页面不会为它们提供任何值，因此像 `article` 这样的计算值 `og:type` 不会被单独的 `website` 覆盖。这忠实反映了实际的合并解析行为。
:::

## 网站级解析 {#site-level-resolution}

按照[网站配置来源记录的补充说明](/zh-CN/concepts/resolver-precedence)，`seo:explain` 也会报告那些来源经常让人困惑的全站值：**规范网址主机名、网站名称和默认语言分别由哪个来源设置**。

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

规范网址主机名最值得检查。主机名错误，例如泄漏的 `localhost`、在 `https` 网站上使用 `http://`，或应用网址与模型网址不一致，都是指向自身的规范网址出错的常见原因。

## JSON 输出 {#json-output}

`--json` 输出完整跟踪信息，供工具或 CI 使用，包括 `target`、每个字段的 `winner` / `losers` / `final` / `notes`，以及 `site_level` 来源记录：

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## 另请参阅 {#see-also}

- [解析器优先级](/zh-CN/concepts/resolver-precedence)：`seo:explain` 跟踪的完整链条。
- [免费 SEO 审计](/zh-CN/guide/audit)：`seo:audit` 找出*哪里有问题*；`seo:explain` 说明*某个值为什么是当前结果*。
