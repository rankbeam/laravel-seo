---
description: "自分のSearch Consoleデータから得るキーワード分析です。順位の範囲、CTRの見直し候補、複数ページに共通するクエリなど、5つのレポートを提供します。"
---

# Search Consoleインサイト {#search-console-insights}

自分のSearch Consoleデータから5つのレポートを算出します。指定した順位範囲のクエリ、CTRの見直し候補、クエリとページの重複、クエリのクラスター、期間ごとの変化です。3つは同期済みの履歴を使い、2つはキャッシュされたライブリクエストを共有します。対象はこれらの分析に限られ、外部キーワード分析サービスの全データや全機能を提供するものではありません。

[読み取り専用のSearch Console連携](/ja/pro/search-console)と履歴同期を基盤にしています。同ページの`seo-pro:gsc-sync`を実行していれば、5つのうち3つは**追加のAPI呼び出しコストなし**で利用できます。

::: tip 前提条件
3つの*スナップショット*分析は、永続保存された`seo_gsc_metrics`の履歴を読み取ります。先に`seo-pro:gsc-sync`を定期実行してください。[Search Consoleの履歴](/ja/pro/search-console)を参照してください。同期した日数が多いほど、長い期間の推移を比較できます。
:::

## 5つの分析 {#the-five-surfaces}

### 1. 上位表示に近いキーワード {#_1-striking-distance-keywords}

**表示回数で加重した平均掲載順位が5〜20位**にあるクエリを、表示回数順に並べます。関連性や内部リンクの見直しに使ってください。この範囲にあることは、少し変更すれば1ページ目に上がるという根拠にはなりません。

### 2. CTRの改善候補 {#_2-ctr-opportunities}

**順位は良いものの、その順位から期待されるクリック率を下回る**クエリです。実際のCTRを、業界データを組み合わせた順位別CTR曲線と比較します。表示回数があり、期待値を大きく下回るクエリを**タイトル・ディスクリプションの書き換え候補**として、推定される*取り逃したクリック数*の順に並べます。[AIメタデータ提案](/ja/pro/ai-assist)の入力にも適した一覧で、どのクエリに向けて書き換えを検討すべきかが分かります。

### 3. カニバリゼーション {#_3-cannibalization}

同じ検索語に対して、**自分のURLが2つ以上表示される**クエリです。重複が必ず有害とは限りません。統合や差別化を行う前に、それぞれのページが異なる検索意図を満たしているかを確認してください。

### 4. クエリのクラスター {#_4-query-clusters}

**各ページが実際に検索結果に表示されるクエリ**をページ別にまとめます。Googleでそのページがどのトピックに関連付けられているかを示します。意図したトピックから外れつつあるページや、狙っていなかった有用な検索語で表示されているページを見つけるのに役立ちます。

### 5. 前の期間との推移比較 {#_5-trend-vs-previous-period}

今回の期間と、その直前の同じ長さの期間を比べ、クリック数、表示回数、掲載順位、CTRの**変化が大きいクエリ**を示します。掲載順位を比べるのは、両方の期間で流入があったクエリだけです。新規のクエリや完全に流入がなくなったクエリには、比較できる値がないためです。

## 数値の取得元：ライブとスナップショット {#where-the-numbers-come-from-live-vs-snapshot}

各分析は、必要な答えを正しく得られる最も低コストな情報源を使います。保存済みの履歴はディメンションごとに分かれているため、どの**クエリ**がどの**ページ**に対応したかを復元できません。その組み合わせが必要な2つの分析だけがライブ取得を行い、**1つのキャッシュ付きリクエストを共有**します。

| 分析 | 情報源 | 理由 |
|---|---|---|
| 上位表示に近いキーワード | **ローカルのスナップショット** | 必要なクエリ別の順位と表示回数は同期済みの履歴にあります。API呼び出しコストはありません。 |
| CTRの改善候補 | **ローカルのスナップショット** | 同じ自社データを使います。期待CTR曲線は静的な基準値で、外部照会ではありません。 |
| 推移の差分 | **ローカルのスナップショット** | 実際の日別履歴が必要で、同期が保存するデータがそのまま使えます。 |
| カニバリゼーション | **ライブ**（クエリ × ページ） | クエリとページの組は保存していません。すべての組を永続保存すると、保存量が大きく増えるためです。 |
| クエリのクラスター | **ライブ** — *分析3の取得を共有* | 同じ組のデータを、クエリ別ではなくページ別にまとめます。 |

そのため、インサイトページの表示で発生するSearch Analyticsリクエストは**最大1回**で、`search_console.cache_ttl`秒間キャッシュします。組み合わせを使う分析がライブなのは意図的です。カニバリゼーションとクラスターは*現時点*の状況を知るための分析で、共有キャッシュが繰り返しのリクエストを抑えます。トークン更新には追加の認証リクエストが必要になる場合があり、Googleの割り当て上限も適用されます。スナップショット分析はネットワークにアクセスしません。

## ダッシュボードでの表示 {#in-the-dashboard}

Filamentプラグインをインストールすると、*SEO*ナビゲーショングループに**インサイト**が表示され、ページタイトルは**Search Console インサイト**です。連携が有効な場合だけ表示され、読み取り専用です。各分析が1つのセクションになり、スナップショット分析にデータがなければ履歴同期を促します。組み合わせデータのライブ取得が失敗した場合も、サニタイズした通知をその場に表示し、ページ全体を表示不能にしません。

## 設定 {#configuration}

設定はすべて`config/seo-pro.php`の`search_console.insights`にあります。デフォルト値を出発点に、サイトの規模に合わせてしきい値を調整してください。

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info 期待CTR曲線
CTR改善候補の曲線は、公表された自然検索の順位別平均CTRを組み合わせた**経験則**です。比較の目安であり、特定のサイトについての断定ではありません。ここで指摘されたクエリは*見直し候補*であり、問題が証明されたわけではありません。自分で測定した曲線があれば、`position => percent`のマップとして`insights.ctr_curve`に設定してください。
:::

## 関連ページ {#see-also}

- [Search Console](/ja/pro/search-console) — この分析が読み取る、読み取り専用連携と履歴同期
- [ホワイトラベルレポート](/ja/pro/reports) — ブランドを反映したPDFでの期間比較
- [AIアシスト](/ja/pro/ai-assist) — CTR分析が指摘したタイトル・ディスクリプションの書き換え
