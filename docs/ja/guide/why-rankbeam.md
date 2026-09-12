---
title: Rankbeamとは？Laravel向けSEO基盤の仕組み
description: "RankbeamはLaravel向けのオープンコアSEO基盤です。メタデータ、正規URL、JSON-LD、サイトマップ、クローラー制御を提供する無料のMITコアに、商用Pro監視エンジンと任意のFilament UIを組み合わせられます。"
---

# Rankbeamとは？ {#what-is-rankbeam}

**RankbeamはLaravel向けのオープンコアSEO基盤です。メタデータ、正規URL、ソーシャルカード、相互にリンクしたJSON-LD、サイトマップ、クローラー制御を提供する無料のMITコアと、任意で追加する商用Proの監視・ワークフロー機能で構成されます。** アプリに付け足す実行時のタグヘルパーではありません。自分のモデルと設定からSEOを解決し、同じ型付きデータをBlade、Inertiaのhead、JSON APIとして描画します。Proを使えば、デプロイ後も監視を続けます。

## パッケージ構成 {#the-package-family}

Rankbeamは、共通の対応バージョン表を持つ3つのパッケージで構成されます。

| パッケージ | ライセンス | 内容 |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT、無料** | コア：メタデータの解決、相互にリンクしたJSON-LDスキーマグラフ、XMLサイトマップ、クローラー制御、無料の`seo:audit`、インポーター |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT、無料** | コアの`seo_meta`に書き込むFilament 4/5のフォームフィールドとライブプレビュー |
| `rankbeam/laravel-seo-pro` | **商用** | 運用エンジン：0–100のスコア付きキュースキャン、リダイレクト管理、IPを保存しない404監視、リンク切れクローラー、Search Consoleインサイト、自分のキーを使うAI支援 |

この境界は意図的なものです。描画されたページが出力するものはすべてMITで、今後も無料です。料金の対象は本番環境の**監査と監視**の層です。商用Proは別パッケージであり、無料コアに含まれることはありません。

## 想定する利用者 {#who-it-s-for}

Rankbeamが役立つのは、SEOを**保存し、モデルに結び付け、複数ロケールで扱い、ヘッドレスで利用し、監査する**場合です。動的なコンテンツやモデルに基づくコンテンツを持つ本番Laravelアプリが該当します。少数の静的ページにタイトルとディスクリプションを1つずつ付けるだけなら、小さな実行時メタヘルパーの方が適しています。後述の[個別パッケージの組み合わせで十分な場合](#what-is-honestly-not-in-the-free-core)でも、その点を明記しています。

## 対応バージョン {#supported-versions}

全パッケージ共通の対応表です。

- **PHP** 8.2–8.4（Laravel 11）、8.2–8.5（Laravel 12）、8.3–8.5（Laravel 13）
- **Laravel** 11 / 12 / 13（Laravel 13にはPHP 8.3以降が必要）
- **Filament** 4 / 5（任意）

## Rankbeamが置き換えないもの {#what-rankbeam-doesn-t-replace}

RankbeamはLaravelアプリ自身のSEO出力を統合します。ホスト型の順位追跡サービス、キーワード調査ツール群、アクセス解析製品ではなく、順位、インデックス登録、AIによる引用を約束するものでもありません。XMLサイトマップの生成には独自実装ではなく[`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap)を利用します。コンテンツ、ルーティング、アクセス解析は既存の仕組みを維持できます。

初めて使う場合は[無料コアをインストール](/ja/guide/installation)するか、このまま本番環境での実際の置き換え事例をご覧ください。Proと初期購入者向けオファーは[rankbeam.dev](https://rankbeam.dev/ja/)で案内しています。

## 3つのパッケージと連携コードでは足りない理由 {#why-not-three-packages-glue}

多くのLaravelアプリが持つのは単一の「SEOパッケージ」ではなく、**SEOスタック**です。モデルごとにメタデータを保存するパッケージ、Filamentにフィールドを追加する別のパッケージ、ページをスキャンする3つ目のパッケージを組み合わせ、アプリ固有の連携層で整合させています。個々の部品には問題がありません。コストが生じるのはそれらの接続部分であり、その連携コードは自分で保守し続ける必要があります。

このページでは、そのような構成をRankbeamファミリーに置き換えた、実際の本番移行の結果を示します。以下の数値は宣伝用の推定ではなく実測です。

## 事例のアプリ {#the-reference-app}

実際に本番稼働しているLaravelのコンテンツサイトです（ここでは匿名化しています）。

- 本番稼働約3か月の**病院・組織向けコンテンツサイト**。
- **WordPressから移行**し、サイトマップ上で約900ページ。
- **1日約20,000訪問**。
- **Laravel 12**、**Filament 4**の管理画面、Bladeフロントエンド、MySQL。

置き換え前のSEOスタックは次のとおりでした。

| 層 | パッケージ |
|---|---|
| メタデータ保存（モデルごとの`seo`テーブル） | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| FilamentのSEOフィールド | `ralphjsmit/laravel-filament-seo` |
| ページスキャナー | `backstage/laravel-seo-scanner` |
| 各層をつなぐ処理全般 | **約30のアプリ独自クラス** |

3つのパッケージを削除し、Rankbeamの**コア + Pro + Filament**をインストールしてSEOテストスイートを実行したところ、**SEOのリグレッションはゼロ**でアプリが起動しました。以下では、連携層に実際に必要だったものと、不要になったものを示します。

## 置き換えで削除できたもの {#what-the-swap-deleted}

スキャナーの構成をRankbeamに置き換えることで、**12の独自クラスを完全に削除**できました。同等の処理をパッケージファミリーが担うため、アプリ側で維持する必要がなくなったものです。

| 削除したアプリのクラス | 役割 | 現在の提供元 |
|---|---|---|
| `Services/SeoService.php` | アプリのSEOエントリーポイントのラッパー | コアリゾルバー + `SEO`ファサード |
| `Services/SeoWarningEvaluator.php` | タイトル・ディスクリプションの長さと画像寸法のしきい値 | コアの`SEOWarningEvaluator`（監査・プレビュー・スキャンで共用） |
| `Services/Seo/SeoAssetInspector.php` | ローカル画像の寸法検査 | コアの`LocalImageInspector` |
| `Jobs/ScanAllPagesSeo.php` | サイト全体のスキャンをキューへ投入 | Proのキュー型[スキャンパイプライン](/ja/pro/scan-issues) |
| `Jobs/ScanPageSeo.php` | ページ単位のスキャン | Proの`PageScanner` |
| `Jobs/ScanPublicPageSeo.php` | 公開ページ単位のスキャン | Proのスキャンパイプライン |
| `Models/SeoScanBatch.php` | スキャン実行の記録管理 | Proの`seo_scan_runs` |
| `Filament/Pages/SeoDashboard.php` | SEO管理ダッシュボード | Proの`SeoDashboard`プラグイン |
| `Filament/Widgets/SeoScanProgressWidget.php` | スキャン進捗ウィジェット | Proのスキャンウィジェット |
| `Filament/Widgets/SeoTrendChartWidget.php` | スキャン推移ウィジェット | Proのスキャンウィジェット |
| `Facades/Seo.php` | 保存パッケージを包むアプリのファサード | コアの`SEO`ファサード |
| `Console/Commands/RecoverLegacySeoMetadata.php` | 一度限りのメタデータ復旧 | コアの[インポーター](/ja/guide/migrate-from-wordpress)（`seo:import-from`） |

::: info 残したものも明記
移行時には、アプリ独自のリンク切れクローラー（約17クラス：スキャンジョブ、チェッカー、シード生成、ソース解決、2つのモデル、2つの列挙型、2つのイベント、Filamentリソースと3つのウィジェット、2つのコマンド）と、メタデータ・スキーマ用のヘルパー（`CustomSEO`、`EntitySeoSection`、`DynamicSeoDataResolver`、`SitewideSchema`、`SeoKeywords`）を意図的に**残しました**。合わせて**さらに約22クラス**です。初日に削除しなかったのは、Rankbeam側の代替機能がその後に追加されたためです。独自クローラーには[Proのリンク切れクローラー](/ja/pro/production)、`CustomSEO`/`EntitySeoSection`にはFilamentの**関連モデルの対象指定**と**SERP・ソーシャルプレビュー**、`SitewideSchema`にはコアの**スキーマグラフ**が対応します。ファミリー全体を採用すれば、合計**およそ36クラス**に及ぶ独自処理をパッケージ側に移せます。
:::

個々のパッケージが悪いという話ではありません。問題は*統合部分*です。メタデータの変更をスキャナー、ダッシュボード、描画されたheadへ反映する十数以上の連携クラスは独自コードであり、上流の保守元も、自分たち以外のテストも、他の利用者からのバグ報告もありません。

## 機能の比較 {#side-by-side}

| 機能 | 個別構成（3パッケージ + 連携コード） | Rankbeamファミリー |
|---|---|---|
| モデルごとのメタデータ保存 | メタデータ用パッケージ | **コア**（`seo_meta`、MIT） |
| **ロケール対応**の保存 | 通常は連携層で対応 | **コア**：`seo_meta`のカラムでロケールを区分 |
| FilamentのSEOフィールド | Filament-SEOパッケージ | **`laravel-seo-filament`**（MIT） |
| **関連モデル**のSEO編集 | フィールドコンポーネントを自分でラップ | 標準の`target:`リゾルバー |
| **SERP + ソーシャル**のライブプレビュー | Blade/Alpineで独自実装 | 編集用のタブ付きプレビューを内蔵 |
| ヘッドレス描画（Inertia / Livewire / JSON） | 事例のアプリはBladeを使用。他の構成には連携が必要 | **1つのリゾルバー** → Blade、Inertia、Livewire、JSON（[共通仕様に対してテスト済み](/ja/contributing/rendering-contract)） |
| ページスキャナー + 優先順位付きの問題 | スキャナーパッケージ | **Pro**の[スキャンパイプライン](/ja/pro/scan-issues) + `IssueRegistry` |
| 0–100のスコア | 連携コードで実装、またはなし | **Pro**の明示的で[バージョン管理された基準](/ja/pro/scoring) |
| リダイレクト + 404からの復旧 | 別パッケージまたは独自実装 | **Pro**のリダイレクト管理 + IPを保存しない404監視 |
| リンク切れクローラー | 独自実装（事例のアプリでも自作） | **Pro**の上限付き・再開可能なクローラー |
| JSON-LDスキーマ**グラフ** | ビルダー + 独自の`@id`連携 | **コア**の相互リンクしたOrganization/WebSite/WebPageグラフ |
| XMLサイトマップ | サイトマップ用パッケージ | **コア**のサイトマップレジストリ（`spatie/laravel-sitemap`を利用） |
| WordPress / Yoast / Rank Mathのインポート | 一度限りのスクリプト | **コア**の`seo:import-from` + [実行手順書](/ja/guide/wordpress-migration-runbook) |
| **接続部分の保守担当** | **自分たち** | 同じリリース系列のパッケージファミリー |

## 連携コードでは対応しにくい3つの点 {#the-three-things-glue-can-t-do-well}

**1 — 一体として開発されるファミリーとリリース系列。** 個別の3パッケージには、3組の保守者、変更履歴、更新周期があります。そのずれを吸収するのが連携コードです。Rankbeamのコア、Pro、Filamentは、共通の[対応表](#tested-where-it-runs)と文書化された[更新時の境界](/ja/reference/configuration)に沿ってバージョン管理されます。挙動の変更は一か所で告知され、2つのパッケージの不一致によって初めて発見する必要はありません。

**2 — 慣習ではなくカラムとしてのロケール対応。** `seo_meta`は保存層でポリモーフィック**かつ**ロケール別に区分されています。複数ロケールのSEOは`(model, locale)`ごとの行であり、シリアライズした塊や後付けの連携テーブルではありません。[リゾルバーの優先順位](/ja/concepts/resolver-precedence)は現在のロケールを標準で読み取ります。

**3 — 1つのリゾルバーによるヘッドレス描画。** Rankbeamは型付きの`SEOData`を解決し、*同じ*データをHTML、Inertiaの`Head`ペイロード、JSON配列として描画します。[Blade](/ja/guide/blade)、[Inertia](/ja/guide/inertia-json)（Vue/React/Svelte）、[Livewire](/ja/guide/livewire)について共通の[出力仕様](/ja/contributing/rendering-contract)に照らして検証しています。管理画面は不要で、Proのすべての機能も[artisanからヘッドレスで実行](/ja/pro/headless)できます。

## 無料コアに含まれないもの {#what-is-honestly-not-in-the-free-core}

Rankbeamはオープンコアであり、その境界は意図的です。`composer require`を実行する前に、何が含まれるか把握できます。

| パッケージ | ライセンス | 含まれるもの |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT、無料** | メタデータの解決、JSON-LDスキーマグラフ、サイトマップ、無料の`seo:audit`、インポーター |
| `rankbeam/laravel-seo-filament` | **MIT、無料** | `seo_meta`に書き込むFilamentフォームのフィールドとセクション |
| `rankbeam/laravel-seo-pro` | **商用** | キュースキャン + 優先順位付きの問題 + 0–100のスコア、リダイレクト、404監視、リンク切れクローラー、Search Console、AI支援、Filamentダッシュボード |

有料となるのは、**テクニカルSEO監査**と**サイト監視**の機能群です。スキャン、スコア、リダイレクト、404からの復旧、クローラーが該当します。メタデータエンジン、スキーマグラフ、サイトマップ、同一プロセス内の無料監査はMITで、無料のままです。

確認できる特徴は2つあります。

- **実行時のライセンス確認なし。** Proはインストール時にプロジェクト単位でライセンスを付与します。ライセンス確認のための外部通信はなく、停止スイッチでアプリが落ちることもありません。Proは自分のログ向けに*ローカル*の運用テレメトリを出力しますが、オプトアウトでき、当方に送ることはありません。
- **自分のキーを使うAI。** 任意の[AI支援](/ja/pro/ai-assist)は、*利用者自身の*Anthropic、OpenAI、Google、またはローカルモデルのキーを使います。Rankbeamが中継、従量課金、再販売することはなく、デフォルトは無効です。

::: tip 個別パッケージの組み合わせで十分な場合
少数の静的ページに`<title>`とディスクリプションを1つずつ付けるだけなら、実行時のタグビルダーで十分です。Rankbeamが役立つのは、SEOを**保存**し、**複数ロケール**で扱い、**モデルに結び付け**、**ヘッドレス**で利用し、**監査**する場合です。パッケージ間の連携が実際に保守すべきコードになる段階が目安です。
:::

## リスクを抑えたWordPressからの移行 {#the-lowest-risk-switch-off-wordpress}

事例のアプリは約900ページのWordPress移行でした。長年積み重ねたYoast/Rank Mathの最適化を失うリスクが大きい利用者です。Rankbeamでは、その移行を安全な手順として扱います。

1. **併用する。** 稼働中のサイトと並行してRankbeamを用意します。この段階では何も削除しません。
2. **インポートする（先にdry-run）。** `seo:import-from yoast` / `rank-math` / `wordpress-csv`でタイトル、ディスクリプション、正規URL、robots、フォーカスキーワード、ソーシャル用の上書き値を読み取ります。インポーターは**冪等**で、**デフォルトでは空欄だけを補完**します。`--overwrite`を指定しなければ設定済みのメタデータを保持し、`--dry-run`では何も書き込みません。
3. **リダイレクトを引き継ぐ。** コアがバージョン付きのリダイレクトCSVを出力します。Proの`seo-pro:redirects-import`は書き込み前に全行を検証し、ループ、安全でない宛先、重複を拒否します。
4. **削除の前に検証する。** `seo:audit --strict`は、問題が1つでもあれば非ゼロで終了するCI・切り替え判定用のゲートです。従来のWordPressデータベースは、自分で削除を選ぶまで変更されません。

全手順は[WordPress移行の実行手順書](/ja/guide/wordpress-migration-runbook)、フィールドごとの対応とトークン処理は[WordPressからの移行](/ja/guide/migrate-from-wordpress)を参照してください。**Laravel**のSEOパッケージ（ralphjsmit、artesaos、Spatie）から切り替える場合は、[パッケージ移行ガイド](/ja/guide/migrate-from-other-packages)を参照してください。

## 規模が大きくても対応できるか {#does-it-hold-up-at-scale}

事例アプリの厳しい条件である、1日約20k件のリクエストすべてでのリゾルバー実行と、約900ページのリンククロールには、それぞれテストスイート内のベンチマークがあります。検証するのは手動調整した実時間ではなく、クエリ数とジョブの上限という**決定的な**改善です。

**リゾルバーのキャッシュ：保存済みキャッシュにヒットすればDBアクセスはゼロ。** 任意の解決結果キャッシュを有効にすると、キャッシュヒット時は優先順位の連鎖*全体*を省略します。ベンチマークでは同じモデルを25回解決します。

| | DBクエリ数 |
|---|---|
| キャッシュなし（毎回`seo_meta`を再読込） | **≥ 25** |
| 保存済みキャッシュにヒット | **0** |

キャッシュは**デフォルトで無効**で、大規模運用向けの選択肢として文書化しています。`seo_meta`、コンテンツフィールド、デフォルト値を変更すると、対応するエントリが無効になります。[設定 → キャッシュ](/ja/reference/configuration)を参照してください。

**リンク切れクローラー：900ページでもジョブごとに上限を維持。** クローラーのベンチマークでは、生成した約900ページのコーパスを実際のジョブで処理します。

- **18以上の上限付きジョブ**で完了（1ジョブ50ページまで）。
- **どのジョブも**50ページの上限を超えて巡回しません。
- **1,800リンク**を確認し、到達不能な宛先をすべて、リンク切れ確認済みの永続的な検出結果として記録します。

実行ごとの有限の上限と、ジョブごとの厳格な時間枠を設けています。シードの取得時と**すべてのリダイレクトの各段階**でSSRF検証を行い、DBリースによりスコープごとに有効な実行を1つに制限します。運用方法は[本番環境の設定ガイド](/ja/pro/production)を参照してください。

## 実行環境でのテスト {#tested-where-it-runs}

3つに分かれた対応表ではなく、全ファミリーで1つの対応表を使います。

- **PHP** 8.2–8.4（Laravel 11）、8.2–8.5（Laravel 12）、8.3–8.5（Laravel 13）
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## 3つのパッケージを連携させ続けるべきか {#so-—-why-glue-three-packages-together}

個別の構成を統合するために十数以上の独自クラス、自分では制御できないリリース周期、構成固有の描画連携、手作業で追加するロケール処理が必要になる一方、一体として開発されたヘッドレス・ロケール対応のファミリーで連携コードを削除でき、実際の900ページ・1日20k件の本番アプリで実績があるなら、個別構成を安全なデフォルトと考え続ける理由は薄れます。

[クイックスタート](/ja/guide/quickstart)から始めてください。`composer require`から完全な`<head>`の描画まで5分で進められます。
