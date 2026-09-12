---
description: "fibonoir/laravel-seo v1からrankbeam/laravel-seo v2へ移行します。パッケージ名が変わり、Coreはメタデータの解決・出力、JSON-LD、サイトマップに機能を絞っています。"
---

# fibonoir/laravel-seo v1からのアップグレード {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0ではパッケージ名を`rankbeam/laravel-seo`に変更し、Coreの機能をメタデータの解決・出力、JSON-LD、サイトマップに絞りました。アナライザー、スキャナー、リダイレクト、404監視、管理UIは別パッケージに移動しました。

## 1. パッケージを入れ替える {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. 名前空間を更新する {#_2-update-namespaces}

クラス名は変わりません。ルート名前空間だけが`Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`に変わりました。プロジェクト全体で検索・置換すれば対応できます。ファサードの別名`SEO`とBladeディレクティブ`@seo`は変わりません。

## 3. 古い公開済みファイルを削除する {#_3-delete-stale-published-files}

ファイルやテーブルを削除する前に、公開済みの設定をバックアップし、影響するデータをエクスポートしてください。復元できることも確認します。このガイドは、v1のリダイレクト、404、スキャン履歴を、スキーマが異なるProに移行するものではありません。以下で説明するCoreテーブルの互換性は、`seo_meta`と`seo_defaults`だけに適用されます。

::: warning エラーが出ないまま競合します
v1の`seo:install`がアプリに公開したファイルは、エラーメッセージを一切出さずにv2パッケージと競合することがあります。
:::

- **`config/seo.php`** — v1、またはv1のインストーラーが残していた可能性のある`ralphjsmit/laravel-seo`で公開したファイルは、パッケージの設定より優先され、`site_name`やすべての`{site_name}`テンプレートをnullにしてしまう場合があります。削除後、`php artisan vendor:publish --tag=seo-config`で再公開してください。
- **v1のマイグレーション**のうち、Coreが管理しなくなったテーブルは、`seo_redirects`、`seo_404_logs`、`seo_scan_runs`、`seo_scan_issues`、`seo_analytics_cache`、`seo_internal_links_index`です。対応するマイグレーションファイルを削除してください。本番にテーブルが存在する場合は、`rankbeam/laravel-seo-pro`をインストールする**前に**削除します。Proが異なるスキーマで作り直すためです。
- **公開済みのスタブ** — v1のFilament 3 / Livewire / Vue / React向けフローで`app/`と`resources/js`に公開したものは、存在しなくなったクラスを参照しています。

2つのCoreテーブル（`seo_meta`、`seo_defaults`）にはスキーマ互換性があり、アップグレード後もデータは保持されます。

## 4. 削除された機能と移動先 {#_4-removed-features-and-where-they-went}

| v1の機能 | 現在の場所 |
|---|---|
| FilamentのSEOフォームセクション | [`rankbeam/laravel-seo-filament`](/ja/guide/filament)（無料、MIT） |
| コンテンツアナライザー（32ルール） | この移行では旧アナライザーを引き継ぎません。テクニカルSEOの問題検出は`rankbeam/laravel-seo-pro`のサイトスキャナーにあります。数値のSEOスコアは、検出した問題から算出するPro機能です。 |
| サイト全体のスキャナー | `rankbeam/laravel-seo-pro` — キューによる処理パイプラインとダッシュボード |
| リダイレクト管理 | `rankbeam/laravel-seo-pro` — 正規表現の検証とオープンリダイレクト防止を強化 |
| 404監視 | `rankbeam/laravel-seo-pro` — プライバシーを重視（デフォルトではIPを保存しません） |
| GA4分析、内部リンク | `rankbeam/laravel-seo-pro`のバックログ |
| `seo:install`インストーラー | 廃止 — インストールはrequire、設定の公開、マイグレーションの順です |

## 5. 確認すべき動作変更 {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image`は常に絶対URLになります。** v1では手動で設定した相対パスをそのまま出力していました。
- **導出した正規URLからクエリ文字列を除去します。** 明示的な正規URLはそのまま保持します。
- **サイトマップの自動検出より登録済みソースが優先されます。** 登録済みの`sitemap-posts.xml`に加えて、重複する`sitemap-post.xml`が出力されることはなくなります。
- **JSON-LDは`JSON_HEX_*`でエスケープされます。** 生のscript出力を後処理している場合は、`<`形式のエスケープを考慮してください。

## 6. 既知の注意点 {#_6-known-gotchas}

- Laravel標準の`DatabaseSeeder`は`WithoutModelEvents`を使うため、シーダー内では`HasSEO`の自動作成フックが無効になります。
- ルートのデフォルトタイトルテンプレートにすでにブランド名が含まれる場合は、設定した`title_suffix`でテンプレートを終えてください。リゾルバーが二重の追加を省略します。
