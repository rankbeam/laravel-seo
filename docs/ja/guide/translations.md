---
description: "Rankbeamの監査結果、編集時の警告、Filamentのラベルはアプリのロケールに従います。言語ファイルを公開して文言を上書きしたり、新しい言語を寄稿したりできます。"
---

# 翻訳 {#translations}

パッケージが出力する利用者向けの文言はすべて、Laravelの言語行です。監査結果、Filamentフィールド下の編集時の警告、ラベル、プレビュー、レポートが含まれます。パッケージは`app()->getLocale()`に従うため、イタリア語で動くパネルには追加設定なしでイタリア語を表示します。

問題や警告の**コード**（`missing_title`、`title_too_long`など）は変わらず、翻訳しません。翻訳するのは、コードに付随する人が読む文だけです。

同梱言語は英語、イタリア語（以前の文言はレビュー済みですが、変更した文言には再レビューが必要）、および初回翻訳のドイツ語、フランス語、スペイン語、ブラジルポルトガル語、オランダ語、トルコ語、ロシア語、ポーランド語（Tier 1）です。Core 3.16 / Filament 1.10 / Pro 2.35以降は、日本語、簡体字中国語（`zh_CN`）、繁体字中国語（`zh_TW`）、韓国語、ギリシャ語、ウクライナ語、チェコ語（Tier 2）も同梱しています。ロケールごとの正確な状態は[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)に記載しています。初回翻訳からサポート対象の言語に進むには、ネイティブによるレビューが必要です。

## 文言を上書きする {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

次に`lang/vendor/seo/{locale}/seo.php`を編集します。他のパッケージは同階層の対応するフォルダーで編集してください。残したキーが上書きされ、それ以外はパッケージのファイル、次いで英語にフォールバックします。

## 言語を寄稿する {#contribute-a-language}

`en`ファイルを対象ロケールにコピーし、値を翻訳して、すべての`:placeholder`を保持してください。テストスイートを実行し、プルリクエストを作成します。キーの欠落、不要なキー、空の値、プレースホルダーの欠落があると、同等性テストが失敗します。詳しいルールと用語集は[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)にあります。

## 意図的に翻訳しないもの {#what-is-not-translated-on-purpose}

CLIの表示言語はデフォルトで英語です。`seo.cli_locale` / `SEO_CLI_LOCALE`を設定するか、`--display-locale=it`を渡すと、対応するメッセージと監査の概要を翻訳できます。Proには専用の`seo-pro.cli_locale`設定があります。表示言語と、`--locale`で選択するコンテンツのロケールは別です。

- コマンドのヘルプ、保守用の診断、`seo:explain`の出力は英語のままです。PASS/WARN/FAILのラベルも変わりません。
- 出力されるHTML（`<meta>`、JSON-LD）は、パッケージの言語ではなくコンテンツの言語を使います。
- 問題コード、JSONのキー、ステータスコードは安定した識別子です。`--json`出力の人が読むラベルは翻訳される場合があるため、外部連携ではキーとコードを使ってください。

## もう一方の言語：コンテンツの言語 {#the-other-half-your-content-s-language}

このページで扱うのは、*パッケージ*が表示する言語です。*コンテンツ*の言語をどう扱うか、つまり文字体系別のタイトル長の目安、切り詰め、大文字・小文字の扱い、hreflangポリシー、`inLanguage`、地域別の検索エンジン、OG画像のフォントについては、[多言語コンテンツ](/ja/guide/multilingual)を参照してください。
