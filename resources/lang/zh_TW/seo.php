<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — 繁體中文
|--------------------------------------------------------------------------
|
| 首版：Claude（2026-09-07），機器翻譯。尚未經母語者審校 —
| 見 TRANSLATING.md。鍵是代碼，不翻譯；佔位符 (:length, :max, …) 保持原樣。
|
*/

return [
    'audit' => [
        'missing_title' => '頁面缺少 title 標籤。',
        'missing_description' => '頁面缺少 meta 描述。',
        'missing_og_image' => '頁面缺少 Open Graph 圖片。',
        'missing_focus_keyword' => '此頁面尚未設定焦點關鍵字。',
        'title_too_long' => '標題長度為 :length 個字元（建議上限 :max），在 Google 上可能會被截斷。',
        'title_too_short' => '標題只有 :length 個字元（建議下限 :min）。',
        'description_too_long' => '描述長度為 :length 個字元（建議上限 :max），可能會被截斷。',
        'description_too_short' => '描述只有 :length 個字元（建議下限 :min）。',
        'duplicate_title' => '標題「:title」也用在其他 :count 個頁面上。',
        'duplicate_description' => 'meta 描述與其他 :count 個頁面重複。',
        'robots_conflict_indexing' => 'robots meta 同時含有 index 與 noindex，指令互相衝突。',
        'robots_conflict_following' => 'robots meta 同時含有 follow 與 nofollow，指令互相衝突。',
        'noindex_warning' => '頁面設定了 noindex，但看起來是重要內容。',
        'invalid_canonical' => 'canonical URL 不是有效的 URL 格式。',
        'cross_domain_canonical' => 'canonical URL 指向不同的網域。',
        'insecure_canonical' => '在 https 網站上，canonical URL 卻使用 http://。',
        'shared_canonical' => '有 :count 個頁面共用同一個 canonical URL。',
        'aeo_missing_author' => '此頁面的文章在結構化資料中沒有作者。宣告作者可讓文章的著作歸屬與來源在 schema 中一目了然。',
        'aeo_article_missing_date' => '此頁面的文章在結構化資料中沒有發布日期。加上 datePublished 或 dateModified，可讓文章的時間軸在 schema 中明確呈現。',
        'hreflang_invalid_code' => 'hreflang 替代版本使用了搜尋引擎會忽略的語言代碼（:codes）。請使用 language[-Script][-REGION] 格式，例如 en、pt-BR、zh-Hant。',
        'hreflang_duplicate_code' => 'hreflang 替代版本中同一個語言代碼出現超過一次（:codes）。',
        'hreflang_missing_self' => 'hreflang 替代版本未包含此頁面本身。Google 要求每個語言版本都必須列出自己的 URL。',
    ],
    'warnings' => [
        'title_too_long' => '標題長度為 :length 個字元（建議上限：:max），在 Google 上可能會被截斷。',
        'title_is_fallback' => '尚未設定 SEO 標題，將改用內容標題代替。',
        'description_too_long' => '描述長度為 :length 個字元（建議上限：:max），可能會被截斷。',
        'description_is_fallback' => '尚未設定 SEO 描述，系統會自動從內容產生一段描述。',
        'no_image' => '沒有可用於社群預覽的圖片。請新增 SEO 圖片或內容圖片。',
        'image_is_fallback' => '尚未指定 SEO 圖片，將改用內容圖片代替。',
        'image_too_small' => '圖片太小（:widthx:height）。社群平台要求至少 :min_widthx:min_height px。',
        'image_not_ideal' => '圖片尺寸為 :widthx:height px。社群平台的理想尺寸是 :ideal_widthx:ideal_height px。',
    ],
    'status' => [
        'pass' => '通過',
        'warn' => '警告',
        'fail' => '失敗',
        'skipped' => '已略過',
    ],
    'severity' => [
        'critical' => '嚴重',
        'warning' => '警告',
        'notice' => '提示',
    ],
];
