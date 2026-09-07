<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — 日本語
|--------------------------------------------------------------------------
|
| 初版: Claude (2026-09-07)、機械翻訳。ネイティブによるレビューは
| 未実施 — TRANSLATING.md を参照。キーはコードなので翻訳しません。
| プレースホルダー (:length, :max, …) はそのまま残します。
|
*/

return [
    'audit' => [
        'missing_title' => 'ページに title タグがありません。',
        'missing_description' => 'ページに meta description がありません。',
        'missing_og_image' => 'ページに Open Graph 画像がありません。',
        'missing_focus_keyword' => 'このページにフォーカスキーワードが設定されていません。',
        'title_too_long' => 'タイトルが :length 文字です（推奨最大 :max 文字）。Google で省略される可能性があります。',
        'title_too_short' => 'タイトルが :length 文字しかありません（推奨最小 :min 文字）。',
        'description_too_long' => 'ディスクリプションが :length 文字です（推奨最大 :max 文字）。省略される可能性があります。',
        'description_too_short' => 'ディスクリプションが :length 文字しかありません（推奨最小 :min 文字）。',
        'duplicate_title' => 'タイトル「:title」は他の :count ページでも使われています。',
        'duplicate_description' => 'meta description が他の :count ページと重複しています。',
        'robots_conflict_indexing' => 'robots meta に index と noindex の矛盾するディレクティブがあります。',
        'robots_conflict_following' => 'robots meta に follow と nofollow の矛盾するディレクティブがあります。',
        'noindex_warning' => 'ページは noindex ですが、重要なコンテンツのようです。',
        'invalid_canonical' => 'canonical URL が有効な URL 形式ではありません。',
        'cross_domain_canonical' => 'canonical URL が別のドメインを指しています。',
        'insecure_canonical' => 'https サイトで canonical URL が http:// を使用しています。',
        'shared_canonical' => ':count ページが同じ canonical URL を共有しています。',
        'aeo_missing_author' => 'このページの記事に、構造化データ上の著者（author）がありません。著者を宣言すると、記事の著者性と出所がスキーマ上で明示されます。',
        'aeo_article_missing_date' => 'このページの記事に、構造化データ上の公開日がありません。datePublished または dateModified を指定すると、記事の時系列がスキーマ上で明示されます。',
        'hreflang_invalid_code' => 'hreflang の代替に、検索エンジンが無視するコードが含まれています（:codes）。language[-Script][-REGION] 形式（例: en、pt-BR、zh-Hant）を使用してください。',
        'hreflang_duplicate_code' => 'hreflang の代替に同じコードが複数回含まれています（:codes）。',
        'hreflang_missing_self' => 'hreflang の代替にこのページ自身が含まれていません。Google は各言語版が自身の URL を列挙することを要求しています。',
    ],
    'warnings' => [
        'title_too_long' => 'タイトルが :length 文字あります（推奨最大: :max 文字）。Google で省略される可能性があります。',
        'title_is_fallback' => 'SEO タイトルが未設定のため、コンテンツのタイトルがフォールバックとして使用されます。',
        'description_too_long' => 'ディスクリプションが :length 文字あります（推奨最大: :max 文字）。省略される可能性があります。',
        'description_is_fallback' => 'SEO ディスクリプションが未設定のため、コンテンツから自動生成されます。',
        'no_image' => 'ソーシャルプレビュー用の画像がありません。SEO 画像またはコンテンツ画像を追加してください。',
        'image_is_fallback' => 'SEO 専用の画像がないため、コンテンツ画像がフォールバックとして使用されます。',
        'image_too_small' => '画像が小さすぎます（:widthx:height）。ソーシャルプラットフォームでは最低 :min_widthx:min_height px が必要です。',
        'image_not_ideal' => '画像サイズは :widthx:height px です。ソーシャルプラットフォームの理想的なサイズは :ideal_widthx:ideal_height px です。',
    ],
    'status' => [
        'pass' => '合格',
        'warn' => '警告',
        'fail' => '不合格',
        'skipped' => 'スキップ',
    ],
    'severity' => [
        'critical' => '重大',
        'warning' => '警告',
        'notice' => '注意',
    ],
];
