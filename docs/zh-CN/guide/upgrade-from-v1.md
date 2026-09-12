---
description: "从 fibonoir/laravel-seo v1 升级到 rankbeam/laravel-seo v2：包已更名，核心聚焦元数据解析、渲染、JSON-LD 和网站地图。"
---

# 从 fibonoir/laravel-seo v1 升级 {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0 将包更名为 `rankbeam/laravel-seo`，并将核心聚焦到元数据解析、渲染、JSON-LD 和网站地图。分析器、扫描器、重定向、404 监测器和管理界面移到了独立包中。

## 1. 替换包 {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. 更新命名空间 {#_2-update-namespaces}

类名保持不变，只有根命名空间从 `Fibonoir\LaravelSEO\*` 改为 `Rankbeam\Seo\*`。在整个项目中查找替换即可。`SEO` facade 别名和 `@seo` Blade 指令不变。

## 3. 删除过时的已发布文件 {#_3-delete-stale-published-files}

删除文件或数据表之前，备份已发布的配置并导出受影响数据，验证能够恢复。本指南不会将 v1 重定向、404 或扫描历史迁移到 Pro 的不同表结构中；下面所述的核心表兼容性只适用于 `seo_meta` 和 `seo_defaults`。

::: warning 问题可能没有错误提示
v1 的 `seo:install` 曾向应用发布一些文件，它们可能与 v2 包冲突，却不产生任何错误消息。
:::

- **`config/seo.php`**：如果它由 v1 发布，或由 v1 安装器可能遗留的 `ralphjsmit/laravel-seo` 发布，就会遮蔽包配置，可能使 `site_name` 和所有 `{site_name}` 模板变为 null。请删除它，然后重新发布：`php artisan vendor:publish --tag=seo-config`。
- **v1 迁移文件**：针对核心不再拥有的表，包括 `seo_redirects`、`seo_404_logs`、`seo_scan_runs`、`seo_scan_issues`、`seo_analytics_cache`、`seo_internal_links_index`，请移除对应迁移文件。如果生产环境中已存在这些表，需要在安装 `rankbeam/laravel-seo-pro` **之前**删除表，Pro 会使用不同结构重新创建。
- **已发布的代码模板**：v1 的 Filament 3 / Livewire / Vue / React 流程在 `app/` 和 `resources/js` 下发布的模板，引用了已经不存在的类。

两个核心表 `seo_meta`、`seo_defaults` 的结构兼容，数据会在升级后保留。

## 4. 已移除的功能及其新位置 {#_4-removed-features-and-where-they-went}

| v1 功能 | 当前所在位置 |
|---|---|
| Filament SEO 表单区域 | [`rankbeam/laravel-seo-filament`](/zh-CN/guide/filament)，免费，MIT |
| 内容分析器，32 条规则 | 此迁移不延续旧分析器。技术 SEO 问题检测由 `rankbeam/laravel-seo-pro` 的网站扫描器提供；数值 SEO 评分是 Pro 功能，根据问题计算。 |
| 全站扫描器 | `rankbeam/laravel-seo-pro`：队列流水线和仪表板 |
| 重定向管理器 | `rankbeam/laravel-seo-pro`：经过加固，包含正则验证和开放重定向防护 |
| 404 监测器 | `rankbeam/laravel-seo-pro`：优先保护隐私，默认不存储 IP |
| GA4 分析、内部链接 | 位于 `rankbeam/laravel-seo-pro` 待办计划中 |
| `seo:install` 安装器 | 已移除；现在通过 require、发布配置和运行迁移来安装 |

## 5. 需要检查的行为变化 {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` 始终为绝对网址。** v1 会原样输出手动设置的相对路径。
- **推导出的规范网址会移除查询字符串。** 显式规范网址原样保留。
- **网站地图自动发现以注册数据源为准。** 不会再在已注册的 `sitemap-posts.xml` 旁边生成重复的 `sitemap-post.xml`。
- **JSON-LD 使用 `JSON_HEX_*` 转义。** 如果你对原始脚本输出进行后处理，需要预期会出现 `<` 形式的转义。

## 6. 已知注意事项 {#_6-known-gotchas}

- Laravel 默认的 `DatabaseSeeder` 使用 `WithoutModelEvents`，会在 seeder 中禁用 `HasSEO` 的自动创建钩子。
- 如果路由默认标题模板已经包含品牌名称，请让模板以配置的 `title_suffix` 结尾，解析器就不会再次追加。
