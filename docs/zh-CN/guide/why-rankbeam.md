---
title: Rankbeam 是什么？了解 Laravel SEO 基础设施
description: "Rankbeam 是 Laravel 的开放核心 SEO 基础设施：免费 MIT 核心提供元数据、规范网址、JSON-LD、网站地图和爬虫控制，另有商业版 Pro 监测引擎和可选 Filament 界面。"
---

# Rankbeam 是什么？ {#what-is-rankbeam}

**Rankbeam 是 Laravel 的开放核心 SEO 基础设施：免费 MIT 核心提供元数据、规范网址、社交卡片、相互关联的 JSON-LD、网站地图和爬虫控制，另有可选的商业版 Pro，提供监测与工作流。** 它不是附加在应用旁边的运行时标签辅助工具，而是从你自己的模型和配置解析 SEO，将同一份类型化数据渲染为 Blade、Inertia head 或 JSON API；使用 Pro 时，还会在部署之后持续监测。

## 包系列 {#the-package-family}

Rankbeam 包含三个共享同一支持矩阵的包：

| 包 | 许可证 | 功能 |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT，免费** | 核心：元数据解析、相互关联的 JSON-LD 结构化数据图、XML 网站地图、爬虫控制、免费的 `seo:audit` 和导入器 |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT，免费** | Filament 4/5 表单字段和实时预览，写入核心的 `seo_meta` |
| `rankbeam/laravel-seo-pro` | **商业许可证** | 运维引擎：带有 0–100 分评分的队列扫描、重定向管理器、不记录 IP 的 404 监测器、失效链接爬虫、Search Console 洞察，以及使用自有密钥的 AI 辅助 |

边界是有意划定的。渲染页面输出的所有功能都采用 MIT 许可证并永久免费；付费购买的是生产环境的**审计和监测**层。商业版 Pro 是独立包，不会包含在免费核心中。

## 适用对象 {#who-it-s-for}

当 SEO 需要**持久存储、关联模型、支持多语言、无头输出和审计**时，Rankbeam 的价值就会体现出来。这通常是带有动态内容或模型内容的 Laravel 生产应用。对于只需要一个标题和描述的少量静态页面，小型运行时元数据辅助包更合适，下文[何时组合方案仍然合适](#what-is-honestly-not-in-the-free-core)也会明确说明。

## 支持版本 {#supported-versions}

整个系列使用同一矩阵：

- **PHP** 8.2–8.4（Laravel 11）；8.2–8.5（Laravel 12）；8.3–8.5（Laravel 13）
- **Laravel** 11 / 12 / 13，Laravel 13 要求 PHP 8.3+
- **Filament** 4 / 5，可选

## Rankbeam 不替代什么 {#what-rankbeam-doesn-t-replace}

Rankbeam 协调 Laravel 应用自身的 SEO 输出。它不是托管的排名跟踪工具、关键词研究套件或分析产品，也不承诺排名、索引或 AI 引用结果。XML 网站地图生成封装了 [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap)，不会重新实现它；你的内容、路由和分析仍留在原有位置。

刚开始了解？可以[安装免费核心](/zh-CN/guide/installation)，或继续阅读一次真实生产替换的证据。Pro 和早期用户优惠见 [rankbeam.dev](https://rankbeam.dev/zh-CN/)。

## 为什么不组合三个包再写连接代码 {#why-not-three-packages-glue}

大多数 Laravel 应用拥有的并不是一个“SEO 包”，而是一套 **SEO 技术组合**：一个包按模型存储元数据，第二个包把字段加到 Filament，第三个包扫描页面，最后还需要应用专用的连接代码，让三者相互一致。每一部分单独看都可以，成本在于它们之间的衔接，而这层连接代码需要你长期维护。

本页记录一次真实生产替换：移除的正是这样一套组合，换成 Rankbeam 系列。下面的数字来自测量，而非宣传估计。

## 参考应用 {#the-reference-app}

这是一个真实运行于生产环境、在此匿名展示的 Laravel 内容网站：

- **医院或机构内容网站**，已在生产环境运行约 3 个月。
- **从 WordPress 迁移**，网站地图约有 900 页。
- 每天约 **20,000 次访问**。
- **Laravel 12**、**Filament 4** 管理后台、Blade 前端和 MySQL。

替换前的 SEO 组合：

| 层 | 包 |
|---|---|
| 元数据存储，每个模型对应 `seo` 表 | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Filament SEO 字段 | `ralphjsmit/laravel-filament-seo` |
| 页面扫描器 | `backstage/laravel-seo-scanner` |
| 各部分之间的连接 | **约 30 个应用定制类** |

我们移除了三个包，安装 Rankbeam **核心 + Pro + Filament**，运行 SEO 测试套件，应用启动后**没有 SEO 回归**。下面具体说明连接层的成本，以及哪些代码消失了。

## 替换后删除了什么 {#what-the-swap-deleted}

用 Rankbeam 替换扫描器组合后，直接删除了 **12 个定制类**。这些工作不再由应用承担，因为包系列提供了对应功能：

| 删除的应用类 | 原先用途 | 现在由谁提供 |
|---|---|---|
| `Services/SeoService.php` | 应用的 SEO 入口封装 | 核心解析器和 `SEO` facade |
| `Services/SeoWarningEvaluator.php` | 标题、描述长度和图片尺寸阈值 | 核心 `SEOWarningEvaluator`，由审计、预览和扫描共用 |
| `Services/Seo/SeoAssetInspector.php` | 检查本地图片尺寸 | 核心 `LocalImageInspector` |
| `Jobs/ScanAllPagesSeo.php` | 队列式全站扫描派发 | Pro 队列[扫描流水线](/zh-CN/pro/scan-issues) |
| `Jobs/ScanPageSeo.php` | 逐页扫描 | Pro `PageScanner` |
| `Jobs/ScanPublicPageSeo.php` | 逐公开页面扫描 | Pro 扫描流水线 |
| `Models/SeoScanBatch.php` | 扫描运行记录管理 | Pro `seo_scan_runs` |
| `Filament/Pages/SeoDashboard.php` | SEO 管理仪表板 | Pro `SeoDashboard` 插件 |
| `Filament/Widgets/SeoScanProgressWidget.php` | 扫描进度组件 | Pro 扫描组件 |
| `Filament/Widgets/SeoTrendChartWidget.php` | 扫描趋势组件 | Pro 扫描组件 |
| `Facades/Seo.php` | 存储包之上的应用 facade | 核心 `SEO` facade |
| `Console/Commands/RecoverLegacySeoMetadata.php` | 一次性元数据恢复 | 核心[导入器](/zh-CN/guide/migrate-from-wordpress)，即 `seo:import-from` |

::: info 其余代码的真实情况
这次替换有意**保留**了应用自写的失效链接爬虫，约 17 个类，包括扫描任务、检查器、种子构建器、来源解析器、两个模型、两个枚举、两个事件、Filament 资源及三个组件、两个命令；还保留了若干元数据和结构化数据辅助类：`CustomSEO`、`EntitySeoSection`、`DynamicSeoDataResolver`、`SitewideSchema`、`SeoKeywords`，合计约 **22 个额外类**。它们没有在第一天删除，因为 Rankbeam 的替代功能是后来才提供的：[Pro 失效链接爬虫](/zh-CN/pro/production)替代定制爬虫，Filament 的**关联模型目标**与**搜索结果及社交预览**替代 `CustomSEO`/`EntitySeoSection`，核心**结构化数据图**替代 `SitewideSchema`。采用整个系列后，这些定制功能，总计约**三十多个类**，都可以由包承担。
:::

问题不在于其中哪个包不好，而在于*集成*：十几个类把它们连接起来，让元数据变化同步反映在扫描器、仪表板和渲染的 head 中。这些都是没有上游维护、只有你自己测试、也无法受益于其他用户错误报告的定制代码。

## 逐项对照 {#side-by-side}

| 功能 | 组合方案，三个包加连接代码 | Rankbeam 系列 |
|---|---|---|
| 逐模型元数据存储 | 元数据包 | **核心**，`seo_meta`，MIT |
| **识别语言的**存储 | 通常由连接代码负责 | **核心**，`seo_meta` 通过列限定语言 |
| Filament SEO 字段 | Filament SEO 包 | **`laravel-seo-filament`**，MIT |
| 编辑**关联**模型的 SEO | 自行封装字段组件 | 正式支持的 `target:` 解析器 |
| 实时**搜索结果和社交**预览 | 自写 Blade/Alpine | 内置标签页式编辑预览 |
| 无头渲染，Inertia / Livewire / JSON | 参考应用使用 Blade；其他技术栈需要集成 | **一个解析器** → Blade、Inertia、Livewire、JSON，经过[约定测试](/zh-CN/contributing/rendering-contract) |
| 页面扫描器与按优先级排列的问题 | 扫描器包 | **Pro** [扫描流水线](/zh-CN/pro/scan-issues)和 `IssueRegistry` |
| 0–100 分评分 | 连接代码或没有 | **Pro** 透明的[带版本评分标准](/zh-CN/pro/scoring) |
| 重定向和 404 修复 | 另一个包或定制实现 | **Pro** 重定向管理器和不记录 IP 的 404 监测器 |
| 失效链接爬虫 | 定制，应用自行构建 | **Pro** 有边界、可恢复的爬虫 |
| JSON-LD 结构化数据**图** | 构建器加自行连接的 `@id` | **核心** 相互关联的 Organization/WebSite/WebPage 图 |
| XML 网站地图 | 网站地图包 | **核心** 网站地图注册表，封装 `spatie/laravel-sitemap` |
| WordPress / Yoast / Rank Math 导入 | 一次性脚本 | **核心** `seo:import-from` 和[操作手册](/zh-CN/guide/wordpress-migration-runbook) |
| **谁维护衔接部分** | **你** | 包系列，统一发布线 |

## 连接代码难以做好三件事 {#the-three-things-glue-can-t-do-well}

**1：统一的包系列和发布线。** 三个包可能有三个维护者、三份更新日志和三种升级节奏，连接代码就是为了吸收它们之间的变化。Rankbeam 核心、Pro 和 Filament 协同管理版本，使用同一个[支持矩阵](#tested-where-it-runs)和有文档说明的[升级边界](/zh-CN/reference/configuration)。行为变化在一个地方公布，无需等两个包发生分歧才发现。

**2：通过存储列支持语言。** `seo_meta` 在存储层既支持多态关系，**也**按语言限定。多语言 SEO 为每个 `(model, locale)` 保存一行，不是序列化数据块，也不是需要记得另加的连接表。[解析器优先级](/zh-CN/concepts/resolver-precedence)原生读取当前语言。

**3：从一个解析器提供无头渲染。** Rankbeam 解析类型化 `SEOData`，再将*同一份*数据渲染为 HTML、Inertia `Head` 载荷或 JSON 数组。[Blade](/zh-CN/guide/blade)、[Inertia](/zh-CN/guide/inertia-json)，包括 Vue/React/Svelte，以及 [Livewire](/zh-CN/guide/livewire)，都依据共同的[渲染约定](/zh-CN/contributing/rendering-contract)验证。不要求管理面板，每个 Pro 功能也能[通过 Artisan 以无头模式运行](/zh-CN/pro/headless)。

## 免费核心明确不包含什么 {#what-is-honestly-not-in-the-free-core}

Rankbeam 采用开放核心模式，边界是有意划定的，让你在 `composer require` 之前就知道会获得什么：

| 包 | 许可证 | 包含内容 |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT，免费** | 元数据解析、JSON-LD 结构化数据图、网站地图、免费的 `seo:audit` 和导入器 |
| `rankbeam/laravel-seo-filament` | **MIT，免费** | 写入 `seo_meta` 的 Filament 表单字段和区域 |
| `rankbeam/laravel-seo-pro` | **商业许可证** | 队列扫描、按优先级排列的问题、0–100 分评分、重定向、404 监测、失效链接爬虫、Search Console、AI 辅助和 Filament 仪表板 |

付费购买的是**技术 SEO 审计**和**网站监测**套件：扫描、评分、重定向、404 修复和爬虫。元数据引擎、结构化数据图、网站地图和免费的进程内审计采用 MIT 许可证，保持免费。

两项经得起核查的特性：

- **没有运行时许可证检查。** Pro 在安装时按项目授权，不会向我们回传运行状态，也没有能关闭应用的远程开关。Pro 会向你自己的日志输出*本地*运维遥测，可以关闭，绝不发送给我们。
- **使用自有密钥的 AI。** 可选的 [AI 辅助](/zh-CN/pro/ai-assist)使用*你的* Anthropic、OpenAI、Google 或本地模型密钥。不代理请求、不按用量计费、不转售，默认关闭。

::: tip 何时组合方案仍然合适
如果只有少量静态页面需要一个 `<title>` 和描述，运行时标签构建器已经足够。当 SEO 需要**存储**、**多语言**、**关联模型**、**无头输出**和**审计**，也就是包之间的连接开始成为需要你实际维护的代码时，Rankbeam 的价值才体现出来。
:::

## 较低风险的切换：离开 WordPress {#the-lowest-risk-switch-off-wordpress}

参考应用从约 900 页的 WordPress 网站迁移而来，这类用户最担心丢失多年的 Yoast/Rank Math 优化。Rankbeam 将其设计为可控路径：

1. **共存。** 在运行中的网站旁搭建 Rankbeam，暂不移除任何内容。
2. **导入，先试运行。** `seo:import-from yoast` / `rank-math` / `wordpress-csv` 读取标题、描述、规范网址、robots、焦点关键词和社交覆盖值。导入器**幂等**且**默认只填充空值**，未使用 `--overwrite` 时保留已设置元数据，`--dry-run` 不写入任何内容。
3. **移交重定向。** 核心输出带版本格式的重定向 CSV；Pro 的 `seo-pro:redirects-import` 在写入前验证每行，拒绝循环、不安全目标和重复项。
4. **删除任何内容前先验证。** `seo:audit --strict` 是 CI 或切换门槛，任何问题都会使其以非零状态退出。旧 WordPress 数据库保持不变，直到你决定删除。

完整流程见 [WordPress 迁移操作手册](/zh-CN/guide/wordpress-migration-runbook)，逐字段映射和模板标记处理见[从 WordPress 迁移](/zh-CN/guide/migrate-from-wordpress)。如果从 **Laravel** SEO 包迁移，例如 ralphjsmit、artesaos 或 Spatie，请看[包迁移指南](/zh-CN/guide/migrate-from-other-packages)。

## 大规模运行是否可靠 {#does-it-hold-up-at-scale}

参考应用最难的两个限制，是每天约 20k 请求都需要解析器，以及约 900 页的链接爬取。两者在测试套件中都有基准测试，断言的是**确定性**收益，即查询数量和任务边界，而不是手工调优的耗时数字：

**解析器缓存：热命中不访问数据库。** 主动启用解析缓存后，缓存解析跳过*整个*优先级链。基准测试对同一模型执行 25 次解析：

| | 数据库查询 |
|---|---|
| 未缓存，每次解析重新读取 `seo_meta` | **≥ 25** |
| 热缓存命中 | **0** |

缓存**默认关闭**，文档将其作为扩大规模时的选项。`seo_meta`、内容字段或默认值变化时，失效机制会清除正确条目。参见[配置 → 缓存](/zh-CN/reference/configuration)。

**失效链接爬虫：900 页规模下仍有明确边界。** 爬虫基准测试将生成的约 900 页内容集交给真实任务处理：

- 通过 **≥ 18 个有边界的任务**完成，每个任务最多 50 页。
- **任何单个任务**都不超过 50 页上限。
- 检查 **1,800 个链接**，每个失效目标都成为持久保存、已确认损坏的问题。

爬虫有有限的单次运行上限和每个任务的硬性时间预算，种子抓取和**每一跳重定向**都经过 SSRF 验证，还使用数据库租约，确保同一范围内只有一个运行处于活动状态。运维说明见[生产环境设置指南](/zh-CN/pro/production)。

## 在实际支持的环境中测试 {#tested-where-it-runs}

整个系列使用同一个支持矩阵，而不是三个：

- **PHP** 8.2–8.4（Laravel 11）；8.2–8.5（Laravel 12）；8.3–8.5（Laravel 13）
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## 还需要自行连接三个包吗 {#so-—-why-glue-three-packages-together}

如果组合方案需要十几个定制类来集成，需要跟随无法控制的发布节奏，为每种技术栈接入渲染，并手动补上语言处理，那么一个统一、支持无头模式和原生语言处理的包系列，可以替代这些连接代码。它已经在真实的 900 页、每天 20k 访问的生产应用中验证过，此时组合方案就不再理所当然是更稳妥的默认选择。

从[快速入门](/zh-CN/guide/quickstart)开始，五分钟内从 `composer require` 到完整渲染 `<head>`。
