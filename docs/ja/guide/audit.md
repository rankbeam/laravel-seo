---
description: "php artisan seo:auditで、現在のSEOの問題をページ別のpass/warn/fail表で確認します。キュー、ライセンス、通信を使わず、同一プロセス内で動く無料のCore機能です。"
---

# 無料のSEO監査（`seo:audit`） {#free-seo-audit-seo-audit}

`php artisan seo:audit`は、**今、SEOのどこに問題があるのか**という問いに、1つのコマンドで無料で答えます。`HasSEO`モデルを同一プロセス内で順に調べ、ページ別の**pass / warn / fail**表と概要を表示します。**キュー、ライセンス、ネットワーク通信は不要**です。

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## 検査する内容 {#what-it-checks}

監査が実行するのは**metadata**実行区分の検査だけです。ページを取得せず、モデルと[リゾルバー](/ja/concepts/resolver-precedence)だけで判定できる内容を扱います。

| 検査 | コード |
|---|---|
| タイトル・ディスクリプションの有無（フォールバックを考慮） | `missing_title`, `missing_description` |
| OG画像の有無（フォールバックを考慮） | `missing_og_image` |
| タイトル・ディスクリプションの長さ | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| サイト全体でのタイトル・ディスクリプションの重複 | `duplicate_title`, `duplicate_description` |
| robotsの矛盾と、不自然なnoindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| 正規URLの形式、別ドメイン、複数ページでの共有、安全でないURL | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| 回答への対応度（AEO）— 記事の構造化データ | `aeo_missing_author`, `aeo_article_missing_date` |
| フォーカスキーワードの設定（明示的に有効化） | `missing_focus_keyword` |
| hreflangの代替ページ（Coreのレジストリ。ページに設定がある場合） | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

多くのコードはProのスキャンにもありますが、レジストリは別です。特に、Coreでは`hreflang_missing_self`、Proでは`hreflang_missing_self_reference`を使います。`hreflang_duplicate_code`はCoreではnotice、Proではwarningです。同名でも検査範囲や重大度が同じだとは考えないでください。`blank_explicit_override`はCoreのレジストリに属します。長さはエディターの[文字体系別の目安](/ja/guide/multilingual#title-and-description-budgets-per-script)を使います。ラテン文字は60/160文字、CJKは約30/80文字で、書記素単位で数え、サフィックスを含む**解決済みの値**を測定します。そのため、監査結果と[Filamentエディター](/ja/guide/filament)の文字数カウンターは食い違いません。hreflangの検査は`seo.hreflang`ポリシー適用後のリストに対して実行されます。タグとサイトマップが出力するものと同じリストです。相互参照の確認にはクロールが必要なため、Proが担当します。

**回答への対応度（AEO）**の検査は、ページが記事型のJSON-LD（`Article`、`BlogPosting`、`NewsArticle`など）を宣言しており、構造化データとして記事を理解するための情報が欠けている場合だけ反応します。対象は`author`エンティティ（著者・出所の明示）や、`datePublished` / `dateModified`（時系列の明示）です。記事がないページには指摘しないため、AEOが該当しない場所では何も表示しません。これらは助言的なnoticeレベルの検査で、Proの0〜100のスコアには含まれません。

## 検査しない内容 — 機能の境界 {#what-it-does-not-check-—-the-capability-boundary}

無料のプロセス内監査は、Proの完全なスキャンと同等ではありません。コマンドも実行するたびにその点を明示します。次の検査は**実行しません**。

- **出力されたHTMLの検査** — `missing_h1`、`multiple_h1`、`missing_image_alt`、`thin_content`、`mixed_content`。配信されたページのHTMLが必要です。
- **実際の正規URLへのネットワーク検査** — `canonical_target_broken` / `_redirect` / `_noindex`。安全策を備えた外部への取得処理が必要です。
- **0〜100の数値スコア。** スコアはPro機能で、バージョン管理された評価基準とともにスキャン結果のレコードへ保存されます。[SEOスコア](/ja/pro/scoring)を参照してください。

これらは**Proのスキャン**に含まれます。完全な[問題レジストリ](/ja/pro/scan-issues)を参照してください。

## 監査対象を選ぶ {#choosing-what-to-audit}

デフォルトでは、`seo.audit.models`に列挙したモデルを監査し、未設定の場合は`seo.sitemap.models`にフォールバックします。

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

モデルを明示的に渡すこともできます。

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## オプション {#options}

| オプション | 効果 |
|---|---|
| `--model=` | 監査する`HasSEO`モデルのクラス。複数指定でき、設定ファイルより優先されます。 |
| `--locale=` | 指定ロケールでSEOデータを解決します。デフォルトはアプリのロケールです。 |
| `--limit=` | モデルごとの監査レコード上限。`0`は全件です。 |
| `--issues-only` | 問題が1つ以上あるページだけを表示します。 |
| `--strict` | 問題が1つでもあれば、0以外の終了ステータスを返します。CI向けです。 |
| `--json` | 表の代わりに機械可読のJSON（ページ、概要、検査範囲）を出力します。 |

### CIでのチェック {#ci-gate}

`--strict`を使うと、監査をビルド時のチェックにできます。

```bash
php artisan seo:audit --strict
```

警告または失敗のページが1つでもあれば`1`、監査したすべてのページが合格なら`0`で終了します。

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## フォーカスキーワード {#focus-keywords}

`missing_focus_keyword`のnoticeは**デフォルトでは無効**です。フォーカスキーワードのワークフローを明示的に有効にした場合だけ表示されます。

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Proのスキャンも**同じ**フラグを読むため、監査、スキャン、Proの編集画面の注意表示は常に一致します。ページのキーワードは、[Filamentのフォーカスキーワードフィールド](/ja/guide/filament)または`$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`で設定してください。

## 値が想定と異なる場合：`seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit`は*何が問題か*を示します。[`seo:explain`](/ja/guide/explain)は、*なぜその値に解決されたか*を示します。各値を設定した層（設定 / デフォルト / 算出 / 明示）、上書きされた値、その後の後処理（タイトルサフィックス、正規URLのクエリ除去、インデックス登録保護）による変更が分かります。監査の指摘や出力タグが想定と異なる場合に使ってください。

```bash
php artisan seo:explain "App\Models\Post" 42
```

