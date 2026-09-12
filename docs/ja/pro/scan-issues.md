---
description: "Proスキャンが報告する各問題を定義する、固定の問題コードレジストリです。コードごとに重大度とフィールドを固定し、ダッシュボードやエクスポートはメッセージではなくコードを参照します。"
---

# スキャンの問題と問題コードレジストリ {#scan-issues-—-the-issue-code-registry}

Proスキャンが報告する各問題には、共通レジストリ`Rankbeam\Seo\Pro\Scanning\IssueRegistry`で定義した**固定の問題コード**があります。スキャナーがその場でコードを作ることはありません。`IssueRegistry::make()`を通じて問題を作成し、レジストリの重大度とフィールドを付与し、**未定義のコードを拒否**します。以下の一覧は、実装の基盤として使える仕様です。ダッシュボード、エクスポート、[Proスコア](/ja/pro/scoring)はメッセージを解析せずコードを参照します。無料の[`seo:audit`](/ja/guide/audit)はコア独自のメタデータレジストリを使い、対象範囲が狭く、一部のhreflangコードも異なります。

各コードには次の情報があります。

- **id**：`seo_scan_issues.issue_type`として保存される固定文字列。
- **severity**：`critical`、`warning`、`notice`のいずれか。**コードごとに固定**し、重大度を変える場合はコードを分けます。
- **field**：対象となる`seo_meta`のフィールド。ページ全体の検出結果は _page_。
- **execution class**：検出側に必要な実行条件（後述）。
- **evidence**：問題の`context`配列に入るキー。

## 実行クラス {#execution-classes}

チェックは実行に必要なものに応じて、3つのクラスのうち必ず1つに属します。

| クラス | 必要なもの | 実行できるもの |
|---|---|---|
| **metadata** | モデル + コアリゾルバー。ページ取得なし | モデルスキャン（`PageScanner`）。無料の[`seo:audit`](/ja/guide/audit)はメタデータチェックの一部に対応 |
| **rendered** | 配信されたページHTML（同一プロセス内のカーネルリクエストまたは外部取得） | URLスキャン（`UrlScanner`） |
| **network** | 別の宛先（他のURLを指す正規URL）を検証する**外向き**取得 | URLスキャン。**必ず`SsrfGuard`経由** |

無料の同一プロセス内監査がProスキャン全体と同等にならない理由はここにあります。ページを描画せず算出できるのは**metadata**のコードだけで、描画済みHTMLの取得やネットワーク経由の正規URL検証はProパイプラインだけが行います。レジストリは`IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`でクラス別に絞り込めます。

## メタデータのコード {#metadata-codes}

`PageScanner`がモデルとリゾルバーから検出します。`missing_title`、`missing_description`、文字数のコードは、URLスキャンでも配信された`<head>`を測定して出力します。コードも意味も同じです。

| コード | 重大度 | フィールド | 証拠 | 意味 |
|---|---|---|---|---|
| `missing_title` | critical | title | — | タイトルがなく、計算可能なフォールバックもない。 |
| `missing_description` | warning | description | — | メタディスクリプションがなく、計算可能なフォールバックもない。 |
| `missing_og_image` | notice | og_image | — | Open Graph画像がなく、計算可能なフォールバックもない。 |
| `missing_focus_keyword` | notice | focus_keywords | — | フォーカスキーワードが未設定。 |
| `duplicate_title` | warning | title | `title`、`duplicate_urls` | 同じロケールの別ページでもタイトルが使われている。 |
| `duplicate_description` | warning | description | `description`、`duplicate_urls` | 同じロケールの別ページでもディスクリプションが使われている。 |
| `title_too_long` | warning | title | `length`、`max`、`script` | 解決済みタイトルが文字体系の推奨長を超える（ラテン文字60、CJK約30）。 |
| `title_too_short` | notice | title | `length`、`min`、`script` | 解決済みタイトルが文字体系の下限より短い（ラテン文字30、CJK約15）。 |
| `description_too_long` | warning | description | `length`、`max`、`script` | 解決済みディスクリプションが文字体系の推奨長を超える（160 / 約80）。 |
| `description_too_short` | notice | description | `length`、`min`、`script` | 解決済みディスクリプションが文字体系の下限より短い（70 / 約35）。 |
| `robots_conflict_indexing` | critical | robots | `robots` | robots指示に`index`と`noindex`が両方ある。 |
| `robots_conflict_following` | warning | robots | `robots` | robots指示に`follow`と`nofollow`が両方ある。 |
| `noindex_warning` | warning | robots | `robots`、`canonical`、`page_url`、`shipping_signal` | 自己参照の正規URLを持つページが`noindex`。確認用のヒューリスティックであり、そのページを必ずインデックス登録すべきという証拠ではない。モデル・描画済みURLの両スキャンで出力。 |
| `invalid_canonical` | critical | canonical | `canonical` | 正規URLの値が有効なURLではない。 |
| `cross_domain_canonical` | warning | canonical | `canonical`、`page_url` | 正規URLがページと異なるホストを指す。 |
| `shared_canonical` | notice | canonical | `canonical` | 複数ページが同じ正規URLを宣言している。 |
| `insecure_canonical` | warning | canonical | `canonical` | `https`サイトで`http://`の正規URLを使用。 |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | hreflangの代替URLに、`x-default`でも有効なBCP-47言語コードでもない値を使っている。 |
| `hreflang_missing_self_reference` | warning | alternates | `locale`、`page_url` | 代替URLが宣言されているが、ページ自身のロケールへの参照（自己参照hreflang）がない。 |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | 同じhreflangコードが複数URLに対応している（曖昧なクラスター）。 |
| `hreflang_missing_x_default` | notice | alternates | `languages` | 多言語hreflangクラスターに`x-default`のフォールバックがない。 |
| `aeo_missing_author` | notice | schema | — | ページの構造化データ内の記事に著者エンティティがない（スキーマに著者・出典を明示していない）。 |
| `aeo_article_missing_date` | notice | schema | — | ページの構造化データ内の記事に公開日・更新日がない（スキーマに記事の時間情報を明示していない）。 |

長さのしきい値は、コアの文字体系対応[文字数ポリシー](/ja/guide/multilingual#title-and-description-budgets-per-script)に基づきます（Pro 2.33）。ラテン文字は60/160、CJKは約30/80をグラフェム単位で数えるため、スキャンとエディターの文字数カウンターは矛盾しません。下限（ラテン文字はタイトル30・ディスクリプション70、CJKはその約半分）は、最適化が不足している可能性を示すスキャン上の基準です。適用した区分はコンテキストキー`script`に入ります。測定対象は**解決済み**のタイトル・ディスクリプション、つまりフォールバックとタイトル接尾辞を含む実際の出力値です。

`hreflang_*`コードは、リゾルバーの`alternates`から読み取った、ページが宣言するhreflang代替URLを検証します。不正・重複コード、自己参照の欠落、多言語クラスターでの`x-default`の欠落が対象です。ページが代替URLを宣言している場合にのみ実行します。ページ間の**相互参照**（戻りのタグ）は、このメタデータチェックでは確認しません。後述の任意のネットワークチェックが相手ページを取得します。

`aeo_*`コードは**回答への対応度（AEO）**の指標です。ページの記事内容を構造化データから読み取れるかを確認します。解決済みJSON-LDグラフを読み、記事型の構造化データ（`Article`、`BlogPosting`、`NewsArticle`など）が宣言されているのに、`author`エンティティ（著者・出典の明示）や`datePublished` / `dateModified`（時間情報の明示）がない場合**だけ**出力します。記事のないページは対象にしません。`seo-pro.scan.checks.aeo`（デフォルト有効）で制御し、無料の[`seo:audit`](/ja/guide/audit)と対応しています。

::: tip `missing_focus_keyword`には有効化条件がある
フォーカスキーワードの通知は、**コア**のフォーカスキーワード機能（`seo.keywords.enabled`、デフォルト`false`）が有効なときだけ出力します。無効なら、キーワードのないページを指摘しません。無料の[`seo:audit`](/ja/guide/audit)コマンドとFilamentエディターも**同じ**コアのフラグを読むため、スキャン・監査・エディターの注意表示は一致します。有効化条件は1つだけです。
:::

## 描画済みHTMLのコード {#rendered-codes}

`UrlScanner`が配信HTMLから検出します。同一ホストは同一プロセス内のカーネルリクエスト（外向き通信なし）、外部の対象はガード付き取得を使います。

| コード | 重大度 | フィールド | 証拠 | 意味 |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URLが4xx/5xxステータスを返した。 |
| `empty_response` | critical | page | — | URLのレスポンス本文が空だった。 |
| `missing_canonical` | notice | canonical | — | 描画されたheadに`<link rel="canonical">`がない。 |
| `noindex_page` | notice | robots | `robots` | 描画ページが`noindex`（情報提供）。`noindex`かつ**自己参照の正規URL**を持つ場合は、代わりに採点対象の`noindex_warning`へ引き上げる。 |
| `missing_h1` | notice | page | — | `<h1>`見出しがない。 |
| `multiple_h1` | notice | page | `count` | `<h1>`が複数ある（情報提供）。 |
| `missing_image_alt` | warning | page | `count`、`total`、`sample` | コンテンツ画像に`alt`属性がない（明示的な`alt=""`は装飾画像として扱い、指摘しない）。 |
| `thin_content` | notice | page | `word_count`、`threshold`、`segmenter` | 本文が設定した単語数より少ない。チェックリストのトークナイザーを使い、空白区切りの文字体系は空白、中国語・日本語・タイ語はICU辞書分割（`segmenter: intl`、ext-intlが必要）で数えるため、日本語400語の記事を1「語」とは数えない。 |
| `mixed_content` | warning | page | `count`、`sample` | `https`ページに`http://`のサブリソースがある。 |
| `html_lang_missing` | notice | page | — | `<html lang>`がないか空。支援技術が不適切な音声を選ぶ可能性がある。 |
| `html_lang_invalid` | notice | page | `declared` | `lang`の値がBCP-47タグではない（`english`、アンダースコアを含む`en_US`、`jp`）。 |
| `html_lang_mismatch` | warning | page | `declared`、`declared_script`、`detected_script` | 可視本文の文字体系が宣言言語に合わない。例：日本語ページの`lang="en"`、ラテン文字の本文の`lang="ru"`。文字体系単位のみを判定する。ラテン文字のページが別のラテン系言語を宣言しているかは推測になるため判断しない。本文に文字体系の判定対象となる文字が40以上必要。 |

## ネットワークのコード {#network-codes}

`UrlScanner`が、対応する任意のフラグが有効な場合だけ検出します。正規URLの宛先には`seo-pro.scan.url_checks.check_canonical_target`、hreflang代替URLには`check_hreflang_reciprocity`を使います。すべての宛先は**`SsrfGuard`経由**で取得し、スキーム許可リスト、ホスト範囲、プライベートIPの拒否、リダイレクト・時間・サイズの制限を適用します。リダイレクトは**追跡しない**ため、リダイレクトする正規URLを確認できます。自己参照の正規URL・代替URLは、ページ自体を直前に取得済みなので省略します。

| コード | 重大度 | フィールド | 証拠 | 意味 |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | HTTP通信の前に`SsrfGuard`が対象を拒否した。 |
| `canonical_target_broken` | critical | canonical | `canonical`、`status` | 正規URLがHTTPエラーを返すページを指す。 |
| `canonical_target_redirect` | warning | canonical | `canonical`、`status`、`location` | 正規URLがリダイレクトするページを指す。最終URLを指定する。 |
| `canonical_target_noindex` | warning | canonical | `canonical` | 正規URLの宛先自体が`noindex`。 |
| `canonical_target_blocked` | notice | canonical | `canonical`、`reason` | 正規URLの宛先を検証できなかった（ガード拒否・解決不能）。 |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`、`href`、`status` | 宣言した代替ページが元ページを宣言し返していない。hreflangの組が無視される可能性はあるが、これ自体で翻訳ページがインデックス登録不能になるわけではない。 |
| `hreflang_target_unverified` | notice | alternates | `hreflang`、`href`、`reason` | 代替ページを取得できず（ガード拒否、エラーステータス、リダイレクト、サイズ上限超過）、相互参照を確認していない。証拠がない状態であり、不具合ではない。 |

相互参照の取得は、1ページにつき最大`hreflang_max_alternates`件（デフォルト10）です。`x-default`を含み、重複とページ自身は省略します。上記のモデル単位の`hreflang_*`メタデータコードは*宣言された*一覧を検証し、このクロールだけが相手ページを必要とします。

ここでのすべてのネットワーク処理は共通の`SsrfGuard`を再利用します。脅威モデルと残存するTOCTOUの注意事項は[SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md)を参照してください。

## コードとスコアの関係 {#how-codes-feed-the-score}

[Pro SEOスコア](/ja/pro/scoring)は、`100 −`上記の重大度に応じた採点対象問題ごとの固定減点で求めます。多くのコードは採点しますが、一部は意図的に除外します。`missing_focus_keyword`は参考情報、`noindex_page`と`multiple_h1`は情報提供です。`blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified`は「確認できなかった」状態であり不具合ではありません。`hreflang_*`、`html_lang_*`、`aeo_*`も参考指標として、現時点では採点から外しています。[採点ページ](/ja/pro/scoring)に完全な許可リストとコードごとの減点があります。

## 問題のライフサイクル {#issue-lifecycle}

問題は不具合のある間だけ存在する行ではなく、ライフサイクルを持ちます。スキャンは対象の問題を消して作り直すのではなく、状態を**照合して更新**します。各問題の識別情報は固定で、対象（モデルなら`scannable_type` + `scannable_id`、ルート・サイトマップ対象なら`url`）と`issue_type`の組です。各コードはスキャンごと・対象ごとに最大1回出力します。複数の該当箇所を扱うコード（`missing_image_alt`、`mixed_content`、`hreflang_*`など）も、`count` / `sample`付きの1行にまとめるため、この識別情報は一意です。

各スキャンで対象ごとに次を行います。

- **既存行のない**検出結果は`open`として作成し、`detected_at`を記録します。
- **既存の未解決行に一致する**検出結果は証拠を更新し、元の`detected_at`を維持します。*初回検出時点*は固定で、毎回リセットしません。
- 完了したチェックで**検出されなくなった**未解決問題は**`fixed`**にし、`resolved_at`を記録します。行を**削除せず保持**することで、実際の修正を記録します。
- **`fixed`**の問題が**再発**すると、同じ行を**再オープン**し、`detected_at`を再記録します。
- ダッシュボードで利用者が**`ignored`**にした問題は変更しません。

| 状態 | 意味 | 設定元 |
|---|---|---|
| `open` | 現在存在する。 | スキャン（新規または継続検出） |
| `fixed` | 以前は存在したが、現在は検出されない。 | 次に再検出しなかったスキャンが自動設定 |
| `ignored` | 利用者が無視する状態に設定し、未解決件数とスコアから除外。 | ダッシュボードの無視操作 |

修正結果を破棄せず記録するため、ホワイトラベル[レポート](/ja/pro/reports)は、レポート間のスナップショット差分の代わりに、期間内の**実際の修正・新規件数**を表示できます。未解決件数を使うダッシュボード、[`seo-pro:scan-status`](/ja/pro/headless)コマンド、[スコア](/ja/pro/scoring)はすべて`open`に絞るため、保存済みの`fixed`行で件数が増えることはありません。修正済みの行は解消した実行に帰属し、通常のスキャン実行の[保持期間](/ja/pro/production)で削除対象になります。

## 設定 {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

ガード付き取得のレスポンスサイズ上限は`seo-pro.http.max_response_bytes`（デフォルト2 MB）です。同一ホストの同一プロセス内スキャンには、この上限はありません。

## 互換性の注意（問題コードの改名） {#compatibility-note-issue-code-rename}

以前の単一コード`robots_conflict`には2つの重大度がありました。コードごとに重大度が1つに決まるよう分割しています。

| 旧コード | 新コード | 重大度 |
|---|---|---|
| `robots_conflict`（index + noindex） | `robots_conflict_indexing` | critical |
| `robots_conflict`（follow + nofollow） | `robots_conflict_following` | warning |

`robots_conflict`を保存や絞り込みに使っていた場合は、新しい2つのコードに更新してください。
