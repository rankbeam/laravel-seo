---
description: "Proスキャンの0〜100のSEOスコアです。各減点を1つのスキャン問題まで追跡でき、同じ問題の集合なら常に同じ数値になる、検証可能で決定的な指標です。"
---

# SEOスコア — 透明性、バージョン管理、Proでの提供 {#the-seo-score-—-transparent-versioned-pro-owned}

Proのスキャンは、各ページに**0〜100のSEOスコア**を付けます。RankMathやYoastから移行する人が求める、1つの数値です。計算の中身が見えない評価とは異なり、**すべて検証可能**です。各減点は必ず1つの[スキャン問題](/ja/pro/scan-issues)に対応し、同じ問題の集合からは常に同じ数値が得られます。

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip 数値スコアはProが担当します
数値スコアは**Pro**機能です。Proの`seo_scan_results`レコードに保存し、Coreの`seo_meta`には保存しません。旧`seo_score`カラムはCore 3で削除されました。無料のCoreの[`seo:audit`](/ja/guide/audit)は、ページ別に**pass / warn / fail**を報告し、**数値は出しません**。スコアは有料機能として提供します。
:::

## 評価基準 {#the-rubric}

スコアは、**公開され、バージョン管理された評価基準**`Rankbeam\Seo\Pro\Scanning\ScoreRubric`から計算します。定義するのは、対象となる問題コードの明示的な**許可リスト**と、固定された**重大度別の減点**の2つです。

| 重大度 | 減点 | 意味 |
|---|---|---|
| `critical` | **−40** | この評価基準で影響が大きい検出事項。 |
| `warning` | **−15** | 早めに調べるべき検出事項。 |
| `notice` | **−5** | 改善すると望ましい点。 |

各コードの重大度は、唯一の情報源である[問題レジストリ](/ja/pro/scan-issues)から直接読み取ります。評価基準が再判定することはありません。スコアを決定的に保つため、コードと重大度は1対1に対応します。

### スコアに含める検査 {#what-the-score-counts}

Rankbeamの製品評価基準が選んだ決定的な検査です。編集上の解釈が必要な経験則も含まれます。criticalは40点、warningは15点、noticeは5点の減点です。検索パフォーマンスを予測するスコアではありません。

| コード | 重大度 | 減点 |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

メタデータのコードはモデルスキャンで検出され、出力HTMLやネットワークのコードはURLスキャンでのみ検出されます（[実行区分](/ja/pro/scan-issues#execution-classes)を参照）。そのため、**モデル**対象のスコアはメタデータの検査を、**URL**対象のスコアは出力されたページを反映します。モデルスキャンの100点は「メタデータの問題がない」という意味で、「出力ページが完璧」という意味ではありません。そちらを確認するにはURLをスキャンしてください。

### 意図的にスコアへ含めない検査 {#what-the-score-deliberately-does-not-count}

以下のレジストリコードは意図的に除外します。除外も仕様の一部であり、すべてのレジストリコードが採点対象か以下の一覧のどちらかに含まれることを、テストで確認しています。

| コード | 除外する理由 |
|---|---|
| `missing_focus_keyword` | **助言的。** 明示的に有効化する`seo.keywords.enabled`のワークフローが前提です。フォーカスキーワードを採用しないことで減点されるべきではなく、スコアが設定フラグに依存してもいけません。 |
| `noindex_page` | **情報提供。** `noindex`は意図的な状態であり、メタデータの品質不良ではありません。「自己参照の正規URLとnoindexの併用」という経験則は、代わりに`noindex_warning`で採点します。 |
| `multiple_h1` | **情報提供。** Googleは複数のH1を許容するため、H1が複数あること自体では減点しません。 |
| `blocked_url` | **証拠がない。** SsrfGuardが取得を拒否し、ページを検査できていません。ページの欠陥ではありません。 |
| `canonical_target_blocked` | **証拠がない。** 正規URLの参照先を検証できていません。ページの欠陥ではありません。 |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **現時点では助言的。** これらのProのhreflangコードはスキャンに表示されます。無料監査にも独自のhreflangコードがありますが、まだスコアには影響しません。採点に加えるには`VERSION`を更新する必要があります。 |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **助言的。** 言語の検査は、この評価基準から除外しています。 |
| `hreflang_not_reciprocal` | **助言的。** 任意の相互参照検査で、採点しません。 |
| `hreflang_target_unverified` | **証拠がない。** 相互参照を検証できていません。 |
| `aeo_missing_author`, `aeo_article_missing_date` | **助言的。** 回答への対応度（AEO）の指標です。スキャンと無料監査で著者エンティティや公開日がない記事を指摘しますが、スコアには影響しません。採点には`VERSION`の更新が必要です。 |

キーワード密度、訴求力のある語句、その他の[ページ内チェックリスト](/ja/pro/on-page-checklist)の項目は、スコアには一切入りません。独立したpass/warn/fail一覧の助言的な検査であり、レジストリコードではありません。

## バージョン管理 — 過去のスコアは知らないうちに変わりません {#versioning-—-historical-scores-never-silently-change}

保存する各スコアには、その計算に使った`ScoreRubric::VERSION`を`rubric_version`として記録します。これには2つの意味があります。

- **新しい**問題コードは、意図的に許可リストへ追加するまで**採点しません**。新しい検査をリリースしても、保存済みのスコアが遡って変わることはありません。許可リストや重みの変更自体が評価基準の変更であり、バージョンを更新します。
- スコアは**保存され、読み取り時に再計算されません**。先週見た数値は、その根拠となる評価基準とともに、今日も同じ数値で表示されます。

## 保存先 {#where-it-s-stored}

各スキャンは、`seo_scan_results`に対象ごとに1行を追加・更新します。

| カラム | 内容 |
|---|---|
| `scannable_type` / `scannable_id` | 採点したモデル。URL対象ではnullです。 |
| `url` | 採点したURL。 |
| `score` | 0〜100の数値。 |
| `rubric_version` | 計算に使った評価基準。 |
| `penalty_total` | 下限0を適用する**前**の減点合計。 |
| `scored_issues` | 数値に影響した問題の数。 |
| `breakdown` | `[{code, severity, penalty}, …]` — 完全な計算の内訳。 |
| `keywords_enabled` | スキャン時の`seo.keywords.enabled`の状態。透明性のために記録しますが、スコアはこの値に依存しません。 |
| `scan_run_id` | 採点した実行。実行履歴の削除時には、スコアを削除せずこの値をnullにします。スコアは実行履歴ではなく現在の状態です。 |
| `scored_at` | 採点日時。 |

## スコアを読む {#reading-the-score}

**ヘッドレス**でモデルの最新スコアを取得します。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status`は、概要に**サイトの平均スコア**を表示します。Filamentダッシュボードでは「Avg. SEO score」に相当する主要指標として表示され、評価ランクに応じて色が変わります。

### 評価ランクの範囲 {#grade-bands}

数値から表示用の文字ランクを導出します。仕様の基準となるのは数値です。

| スコア | ランク |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 公開時に確認すべき兆候（`noindex_warning`） {#the-shipping-signal-noindex-warning}

`noindex_warning`は、ページが`noindex`と**自己参照の正規URL**（自分自身のURLを指す正規URL）を併用している場合に発生します。Rankbeamはこれを公開時に確認すべき兆候として扱います。自己参照の正規URLは、インデックス登録を意図している証拠では**ありません**。この組み合わせが意図的な場合もあります。別ドメインの正規URLでは、この経験則は発動しません。問題には`context.shipping_signal`（例：`self_canonical`）と、比較した`canonical`、`page_url`を含みます。

両方のスキャナーがこの検査を適用します。モデルスキャン（`PageScanner`）は、保存された正規URLとモデルURLを比較します。出力URLのスキャン（`UrlScanner`）は、自己参照の正規URLを持つ`noindex`ページを、情報提供の`noindex_page`から採点対象の`noindex_warning`へ引き上げます。`noindex_page`自体を除外するのはそのためです。矛盾の可能性は、どちらの経路でも`noindex_warning`で扱います。インデックス登録の指示を変える前に、ページの実際の意図を確認してください。

## 設定 {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

許可リストと重みは設定で変更**できません**。同じ`rubric_version`なら、どのインストール環境でも決定的なスコアになる必要があります。計算方法を変えるのは設定変更ではなく、コードレベルでの評価基準の変更です。
