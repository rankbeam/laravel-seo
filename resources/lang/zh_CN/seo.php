<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — 简体中文
|--------------------------------------------------------------------------
|
| 首版：Claude（2026-09-07），机器翻译。尚未经母语者审校 —
| 见 TRANSLATING.md。键是代码，不翻译；占位符 (:length, :max, …) 保持原样。
|
*/

return [
    'audit' => [
        'missing_title' => '页面缺少 title 标签。',
        'missing_description' => '页面缺少元描述。',
        'missing_og_image' => '页面缺少 Open Graph 图片。',
        'missing_focus_keyword' => '此页面未设置焦点关键词。',
        'title_too_long' => '标题长度为 :length 个字符（建议最多 :max 个）；在 Google 中可能会被截断。',
        'title_too_short' => '标题仅 :length 个字符（建议至少 :min 个）。',
        'description_too_long' => '描述长度为 :length 个字符（建议最多 :max 个）；可能会被截断。',
        'description_too_short' => '描述仅 :length 个字符（建议至少 :min 个）。',
        'duplicate_title' => '标题“:title”还被其他 :count 个页面使用。',
        'duplicate_description' => '元描述与其他 :count 个页面重复。',
        'robots_conflict_indexing' => 'Robots meta 中同时存在 index 和 noindex 指令，相互冲突。',
        'robots_conflict_following' => 'Robots meta 中同时存在 follow 和 nofollow 指令，相互冲突。',
        'noindex_warning' => '页面设置了 noindex，但看起来是重要内容。',
        'invalid_canonical' => 'canonical URL 不是有效的 URL 格式。',
        'cross_domain_canonical' => 'canonical URL 指向了另一个域名。',
        'insecure_canonical' => '站点使用 https，但 canonical URL 使用的是 http://。',
        'shared_canonical' => ':count 个页面共用同一个 canonical URL。',
        'aeo_missing_author' => '此页面上的一篇文章在结构化数据中没有作者。声明作者可以在 schema 中明确文章的作者身份和来源。',
        'aeo_article_missing_date' => '此页面上的一篇文章在结构化数据中没有发布日期。添加 datePublished 或 dateModified 可以在 schema 中明确文章的时间线。',
        'hreflang_invalid_code' => 'hreflang 备用链接中包含搜索引擎会忽略的语言代码（:codes）。请使用 language[-Script][-REGION] 格式，例如 en、pt-BR、zh-Hant。',
        'hreflang_duplicate_code' => 'hreflang 备用链接中同一个代码出现了多次（:codes）。',
        'hreflang_missing_self' => 'hreflang 备用链接中未包含此页面本身。Google 要求每个语言版本都列出自己的 URL。',
    ],
    'warnings' => [
        'title_too_long' => '标题长度为 :length 个字符（建议最多：:max 个）。在 Google 中可能会被截断。',
        'title_is_fallback' => '未设置 SEO 标题，将回退使用内容标题。',
        'description_too_long' => '描述长度为 :length 个字符（建议最多：:max 个）。可能会被截断。',
        'description_is_fallback' => '未设置 SEO 描述，将根据内容自动生成一条。',
        'no_image' => '没有可用于社交预览的图片。请添加 SEO 图片或内容图片。',
        'image_is_fallback' => '未设置专门的 SEO 图片，将回退使用内容图片。',
        'image_too_small' => '图片太小（:widthx:height）。社交平台要求至少 :min_widthx:min_height px。',
        'image_not_ideal' => '图片尺寸为 :widthx:height px。社交平台的理想尺寸是 :ideal_widthx:ideal_height px。',
    ],
    'status' => [
        'pass' => '通过',
        'warn' => '警告',
        'fail' => '失败',
        'skipped' => '已跳过',
    ],
    'severity' => [
        'critical' => '严重',
        'warning' => '警告',
        'notice' => '提示',
    ],
];
