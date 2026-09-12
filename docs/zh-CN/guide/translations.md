---
description: "Rankbeam 的审计问题、编辑器警告和 Filament 标签跟随应用语言。可以发布语言文件以覆盖文本，也可以贡献一种语言。"
---

# 翻译 {#translations}

包输出的所有面向用户的文本，包括审计问题、Filament 字段下的实时编辑器警告、标签、预览和报告，都是 Laravel 语言条目。包遵循 `app()->getLocale()`：面板使用意大利语时，就会显示意大利语，无需额外配置。

问题和警告的**代码**，例如 `missing_title`、`title_too_long`，永远不变，也永远不翻译。只翻译附在代码上的自然语言说明。

已提供的语言包括：英语、意大利语，较早的文本已审阅，变更文本仍需重新审阅；以及第一版翻译的德语、法语、西班牙语、巴西葡萄牙语、荷兰语、土耳其语、俄语和波兰语，属于 Tier 1。从 core 3.16 / Filament 1.10 / Pro 2.35 开始，还提供日语、简体中文（`zh_CN`）、繁体中文（`zh_TW`）、韩语、希腊语、乌克兰语和捷克语，属于 Tier 2。每种语言的准确状态见 [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)；按照该文件的标准，第一版翻译经过母语审阅后才成为正式支持的语言。

## 覆盖文本 {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

然后编辑 `lang/vendor/seo/{locale}/seo.php`，其他包使用相邻文件夹。保留的键会覆盖原值，其余键回退到包文件，再回退到英语。

## 贡献一种语言 {#contribute-a-language}

将 `en` 文件复制到你的语言目录，翻译值，保留每个 `:placeholder`，运行测试套件，然后提交拉取请求。完整性测试会在缺少键、存在多余键、空值或占位符丢失时失败。完整规则和术语表见 [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)。

## 有意不翻译的内容 {#what-is-not-translated-on-purpose}

CLI 默认以英语显示。设置 `seo.cli_locale` / `SEO_CLI_LOCALE`，或传入 `--display-locale=it`，即可翻译受支持的消息和审计摘要。Pro 有自己的 `seo-pro.cli_locale` 设置。显示语言与通过 `--locale` 选择的内容语言相互独立。

- 命令帮助、维护诊断和 `seo:explain` 输出保持英语；PASS/WARN/FAIL 标签保持稳定。
- 渲染的 HTML，包括 `<meta>`，以及 JSON-LD，使用内容的语言，不使用包的界面语言。
- 问题代码、JSON 键和状态代码保持为稳定标识符。`--json` 输出中的人类可读标签可以翻译；集成应读取键和代码。

## 另一部分：内容的语言 {#the-other-half-your-content-s-language}

本页介绍*包*所使用的语言。包如何理解*内容*的语言，包括按文字系统划分的标题预算、截断、大小写、hreflang 策略、`inLanguage`、区域搜索引擎和 OG 图片字体，见[多语言内容](/zh-CN/guide/multilingual)。
