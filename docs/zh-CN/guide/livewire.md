---
description: "在 Livewire 应用中使用 Rankbeam 通用的 @seo 指令：向 head 输出普通 HTML，在整页组件和 Blade 布局中都像 Blade 一样工作。"
---

# Livewire {#livewire}

`@seo` Blade 指令不依赖特定前端框架，它们向 `<head>` 输出普通 HTML，因此在任何 Livewire 应用中，都与在 Blade 中一样工作。

## 初始整页渲染 {#initial-full-page-render}

在**整页 Livewire 组件**，即路由返回一个组件，或包裹 Livewire 组件的 Blade 布局中，`@seo` 的用法与 [Blade 指南](/zh-CN/guide/blade)完全相同：

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

第一次 HTTP 响应包含完整且爬虫可见的 head：标题、描述、规范网址、Open Graph、Twitter 和 JSON-LD。爬虫和社交抓取工具看到的就是这条路径，其输出完整正确。

## `wire:navigate` 的注意事项 {#the-wire-navigate-caveat}

Livewire 的 [`wire:navigate`](https://livewire.laravel.com/docs/navigate) 将链接点击变成 SPA 式访问。此时，Livewire 替换 `<body>` 并**合并 `<head>`**，但对 SEO 包来说，不同元素的处理有一项关键差异：

- **`<title>` 和 `<meta>`/`<link>`** 会从新页面的 head 合并，因此解析后的标题和元数据通常会更新。
- **`<script>` 被视为不可移除的资源。** Livewire 保留所有见过的 `<script>`，避免重新执行它们破坏 JavaScript。这意味着 **JSON-LD `<script>` 块会不断累积**：访问三篇文章后，三篇文章的结构化数据同时留在 head 中，读取结构化数据的工具就会看到错误实体或多个实体。

为便于清理，渲染器会**为输出的每个 JSON-LD 脚本添加标记**：

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## 接入 JSON-LD 清理 {#ship-the-json-ld-cleanup}

添加以下代码一次即可，例如放在根布局的 `@livewireScripts` 之后。每次 `wire:navigate` 时，它只保留**当前页面**的结构化数据，并移除陈旧内容：

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

这只依赖渲染器已经输出的 `data-seo-schema` 标记和每个网址对应的 ID，无需逐页接入。

::: warning 与当前网址比较，不要把最后追加的脚本直接视为当前页面
这段代码的早期版本会在结构化数据脚本少于两个时提前退出，并把*最后追加*的脚本视为当前页面。这样，从**有** JSON-LD 的页面导航到**没有** JSON-LD 的页面时，旧结构化数据仍会留在 head 中，因为此时只有旧脚本，提前退出就将其保留。它也无法删除重新访问页面时 Livewire 再次添加的**同网址重复脚本**。将每个 `data-seo-url` 与 `window.location` 比较，并只保留**最后一个**匹配项，才能在所有这些情况下同时移除陈旧结构化数据和重复内容。`rankbeam-examples` Livewire 应用及其浏览器测试验证的正是这点。
:::

::: tip SPA 导航中的单实例元标签
Livewire 的 head 合并通常可以避免单实例 `<meta>`/`<link>` 标签过时，但具体行为取决于 Livewire 版本和布局结构。对于爬虫元数据正确性尤为关键的页面，优先使用**整页重新加载**，也就是不带 `wire:navigate` 的普通链接，或者在**服务器端渲染**页面，让第一次 HTTP 响应成为权威结果。[`rankbeam-examples`](https://github.com/rankbeam) Livewire 应用在浏览器中执行真实的 `wire:navigate` 流程，验证这些行为。
:::

## Filament {#filament}

Filament 底层使用 Livewire，但它是一个**管理端内容编辑界面**。它编辑 `seo_meta`，不会渲染公开前端的 head。参见 [Filament 指南](/zh-CN/guide/filament)，本页内容不适用于管理面板。
