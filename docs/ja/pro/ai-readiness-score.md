---
description: "SEOスコアと並ぶ、決定的な第2のスコアです。クローラーがページへ到達して読めるかという技術的なAI対応度をRankbeam独自の基準で測り、SEOの数値とは分けて扱います。"
---

# AI対応度スコア — 決定的な第2の評価軸 {#the-ai-readiness-score-—-a-second-deterministic-axis}

Proのスキャンは、各ページに[SEOスコア](/ja/pro/scoring)と並んで**0〜100のAI対応度スコア**を付けます。こちらが扱うのは、*AIクローラーや回答エンジンがこのコンテンツに到達し、読み取り、出所を把握できるか*という別の問いです。**自然検索のSEOスコアに混ぜることはありません**。独立した2つの評価軸で、それぞれ専用の評価基準、バージョン、カラムを持ちます。

::: warning この数値が示すもの、示さないもの
AI対応度スコアは、**Rankbeamが定義した、技術的な互換性の決定的な指標**です。クロールで確認するページ上の情報が存在し、正しい形式になっているかを測ります。検索システムやAIシステムでの順位、インデックス登録、採用、引用を予測するものでは**なく**、どのスコアもその結果を保証しません。`air_llms_txt`の検査は、利用することを選んだツール向けの**任意の**互換性ファイル`llms.txt`に得点を与えます。Google検索の必須要件でも、ランキングシグナルでもありません。
:::

SEOスコアと同様、**完全に決定的で再現可能**です。各点を名前付きのクロール検査まで追跡でき、同じ情報からは常に同じ数値になります。**採点処理ではAIを一切呼び出しません**。透明で検証可能な測定であることが目的です。「AI可視性」をうたうSaaS製品のように、LLMの応答をサンプリングするものではありません。

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip 2つの評価軸を混ぜません
`AI-readiness: 74/100`と`SEO: 82/100`を並べて表示し、一方が他方を変えることはありません。AI対応度の数値は、Proの`seo_scan_results`行にある専用の`ai_readiness_*`カラムに保存します。SEOスコアと同じく、**数値スコアはPro機能**です。無料Coreの[`seo:audit`](/ja/guide/audit)は数値を表示しません。
:::

## 減点ではなく加点 {#additive-credit-not-penalty}

[SEOスコア](/ja/pro/scoring)は100から始め、ペナルティを*差し引きます*。AI対応度は逆で、**0**から始め、各検査の重みの全額または一部を**加点**します。対応度はサイトが積み上げるものなので、AI向けの情報がないサイトは「100から少し引く」のではなく、0に近い点になります。重みの合計はちょうど**100**です。

## 評価基準 {#the-rubric}

スコアは、**公開され、バージョン管理された評価基準**`Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`から計算します。4つのカテゴリに10個の検査があります。

### A · ボットのアクセスと制御 — 30点 {#a-·-bot-access-control-—-30-points}

AI検索・アシスタントのクローラーが到達できるかを確認します。サイトが**配信する`/robots.txt`**を、**スキャンしたページ自身のパス**に対して評価します。ルートが開放されていても、`Disallow: /section`配下のページは拒否対象です。robots.txtは従うクローラーへの指示であり、ネットワークアクセスの遮断ではありません。[AIクローラーカタログ](/ja/guide/ai-crawlers)の用途分類（学習 / 検索 / アシスタント）を使います。

| 検査 | 重み | 得点の基準 |
|---|---|---|
| `air_robots_reachable` — `robots.txt`が配信され、読み取れる | 6 | あり / なし |
| `air_ai_search_access` — AI**検索**クローラー（流入チャネル）のアクセスが許可されている | 10 | 許可されている割合 |
| `air_ai_assistant_access` — AI**アシスタント**クローラーのアクセスが許可されている | 8 | 許可されている割合 |
| `air_explicit_ai_policy` — 既知のAIボットに対する明示的な`robots.txt`ルールがある | 6 | あり / なし |

::: tip 学習用ボットの拒否で対応度が下がることはありません
学習用ボット（GPTBot、CCBotなど）の拒否は正当な選択であり、減点になることは**ありません**。学習について加点するのは、`air_explicit_ai_policy`で意図的かつ明示的な方針を持つことだけです。学習用ボットを拒否しつつ検索・アシスタントのクローラーを許可するサイトは、このカテゴリで満点を取れます。
:::

### B · 発見しやすさ — 20点 {#b-·-discoverability-—-20-points}

| 検査 | 重み | 得点の基準 |
|---|---|---|
| `air_sitemap_discoverable` — XMLサイトマップに到達でき、**かつ**`Sitemap:`指示から参照されている | 12 | 両方 / 片方 / どちらもなし |
| `air_llms_txt` — 有効な`/llms.txt`（見出しとリンク）が配信されている | 8 | 有効 / 存在する / なし |

### C · 機械可読のコンテンツ — 22点 {#c-·-machine-readable-content-—-22-points}

| 検査 | 重み | 得点の基準 |
|---|---|---|
| `air_server_rendered_content` — サーバーで生成したHTMLに十分なテキストがある（JSを実行しなくてもコンテンツが存在する） | 14 | 単語数による |
| `air_markdown_twin` — コンテントネゴシエーションでページのMarkdown版を配信する | 8 | あり / なし |

### D · 構造化データと回答への対応度 — 28点 {#d-·-structured-data-answer-readiness-—-28-points}

| 検査 | 重み | 得点の基準 |
|---|---|---|
| `air_schema_completeness` — JSON-LDがあり、主要エンティティの型、著者情報、日付がある（記事では著者と日付） | 18 | 完全 / 一部 / なし |
| `air_answer_structure` — 回答を抽出しやすくする要素：FAQ/QA/HowToスキーマ、見出しの階層、リスト、簡潔な導入文 | 10 | 要素の数による |

各検査は**満額**、**一部**、**0点**のいずれかを返します。必要な情報を取得できなかった場合は**スキップ**になります。たとえば、ページを取得せずにスキャンした対象に対するページ単位の検査です。スキップした検査は0点ですが、その状態を明示するので、*検査できなかった*情報を「存在しないと確認済み」と表示することはありません。

### 無料監査で確認できる範囲 {#free-audit-reach}

スキーマの充足度（`air_schema_completeness`）は、ページを取得せずにモデルの構造化データから解決できます。無料監査が回答への対応度の指摘に使うものと同じ経路です。他の9つの検査にはクロールが必要なため、完全な数値は**Proのスキャン**が担当します。

## 対象範囲 — この評価軸に含めないもの {#honest-scope-—-what-this-axis-excludes}

この評価軸が採点するのは、**決定的に判定できるコンテンツ上の情報**です。稼働中のアプリやDNSに関する、エージェント向けインフラの検査は含めません。

| 対象外 | 理由 |
|---|---|
| **DNS-AID**（DNSのエージェント発見レコード） | DNS / DNSSECインフラであり、配信ページの性質ではありません。 |
| **Web Bot Auth**（リクエスト単位の署名） | 対話的な暗号ハンドシェイクであり、静的なコンテンツではありません。 |
| **Protocol Discovery**（API Catalog、OAuth/OIDC、MCP Server Card、Agent Skills、WebMCPなど） | 稼働するアプリ、API、MCPサーバーが必要です。 |
| **Commerce**（x402、MPP、UCP、ACP） | エージェント向けの決済基盤です。コンテンツサイトでは課金する対象がありません。 |

スキーマのエンティティ充足度と回答ブロックの構造は含みます。コンテンツの構成と出所を示す情報ですが、検索エンジンや回答エンジンが利用することを保証するものではありません。

## バージョン管理 — 過去のスコアは知らないうちに変わりません {#versioning-—-historical-scores-never-silently-change}

保存する各AI対応度スコアには、計算に使った`AiReadinessRubric::VERSION`を`ai_readiness_version`として記録します。検査の集合、重み、得点モデルの変更はすべて評価基準の変更であり、**バージョンを更新**します。そのため、保存済みの数値には必ず根拠となる評価基準が記録され、過去の数値を比較できます。スコアは**保存され、読み取り時に再計算されません**。単語数や要素数など、得点に影響するしきい値は、バージョンに対応するコードレベルの定数です。設定ではないため、設定変更で公開済みの数値が知らないうちに変わることはありません。

::: warning バージョンでは固定されない入力があります
ボットアクセスの検査は、Coreの**現在の**[AIクローラーカタログ](/ja/guide/ai-crawlers)を読み取ります。新しいボットや用途の再分類といったカタログ更新は、実質的に入力の変更です。`AiReadinessRubric::VERSION`を更新しなくても、2つのボットアクセスの部分スコアが変わる場合があります。バージョンが追跡するのはカタログではなく*評価基準*です。これは意図的で、固定した一覧より現在の実際のボット一覧を使う方が有用だからです。過去と厳密に比較するには、評価基準のバージョンとともにCoreパッケージのバージョンも固定してください。
:::

## 保存先 {#where-it-s-stored}

各スキャンは、SEOスコアと**同じ**`seo_scan_results`行に、AI対応度のカラムを追加・更新します。

| カラム | 内容 |
|---|---|
| `ai_readiness_score` | 0〜100の数値。この評価軸を有効にして対象をスキャンするまではnullです。 |
| `ai_readiness_version` | 計算に使った評価基準。 |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — 完全な計算の内訳。 |

実行完了時には、実行ごとの平均を`seo_scan_runs.avg_ai_readiness`に記録します。`avg_score`に対応する、AI対応度の推移です。

## スコアを読む {#reading-the-score}

**ヘッドレス**では、モデルの最新結果に両方の評価軸が含まれます。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament**では、任意のリソーステーブルでSEOスコアの列の隣に、対応する列を追加します。

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

ページ内スコアカード（SEOタイトルフィールドの上）にも補助バッジとして表示します。また、[ホワイトラベルレポート](/ja/pro/reports)のPDFとメールには、数値、ランク、前回レポートとの差、スキャンごとの推移を独立したセクションに表示します。常に自然検索のスコアと並べて表示し、混ぜることはありません。

### 評価ランクの範囲 {#grade-bands}

数値から表示用の文字ランクを導出します。仕様の基準となるのは数値です。一貫性のため、SEOスコアと同じ範囲を使います。

| スコア | ランク |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 設定 {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

検査と重みは設定で変更**できません**。同じ`ai_readiness_version`なら、どのインストール環境でも決定的なスコアになる必要があります。計算方法を変えるのは設定変更ではなく、コードレベルでの評価基準の変更です。

::: warning サイトの情報検出はプロセス内のリクエスト経路を使います
同一ホストの対象では、スキャンはLaravelのプロセス内HTTPカーネルを通じて`/robots.txt`、`/llms.txt`、ページを解決します。他のスキャンと同じ経路です。Laravelのルーティングを経由せず、**静的ファイル**として配信される`robots.txt`や`llms.txt`は検出できません。採点の対象にするには、推奨構成であるパッケージのルートから配信してください。
:::
