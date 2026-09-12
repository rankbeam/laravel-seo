---
description: "稼働中のサイトでYoastやRank Mathを置き換えるための、リスクを抑えた手順です。デフォルトでは空欄だけを埋め、ドライランは何も書き込まず、移行元WordPressも変更しません。"
---

# WordPress → Rankbeam移行手順書 {#wordpress-→-rankbeam-migration-runbook}

WordPressのSEO構成（YoastまたはRank Math）をRankbeamへ置き換える手順です。デフォルトでは移行先の空のフィールドだけを埋めます。`--overwrite`を指定すると、明示的に置き換えを許可します。ドライランは何も書き込まず、移行元のWordPressデータベースは変更しません。インポート前に、移行元と移行先の両方をバックアップしてください。

これは、フィールド対応、テンプレートトークンの処理、移行元キーを詳しく説明する[WordPressからの移行](/ja/guide/migrate-from-wordpress)と対になる運用手順です。*何を移すか*は参照ガイド、*どの順番でどう移すか*はこちらで確認してください。

::: tip 必要なもの
- メタデータのインポートと`seo:audit`には、**Core**（`rankbeam/laravel-seo`）が必要です。
- **リダイレクト**も移す場合だけ、**Pro**（`rankbeam/laravel-seo-pro`）が必要です。`seo_redirects`テーブルはProの機能です。
- コンテンツをLaravelでモデル化済み（例：`App\Models\Post`）で、[`HasSEO`](/ja/guide/quickstart)トレイトを使用し、WordPressのスラッグと照合できることが必要です。照合にはモデルのルートキー、または`--match-by`で指定するカラムを使います。
:::

## 移行の仕組み {#the-shape-of-the-migration}

WordPressの行は**URL / 投稿**をキーとします。Rankbeamの`seo_meta`行は**ポリモーフィック**で、Eloquentモデルに紐づきます。インポートは各WordPress行をアプリのモデルと照合します。結果は3種類に分かれ、毎回その内訳を報告します。

| 結果 | 意味 | 対応 |
|---|---|---|
| **matched** | モデルに紐づき、`seo_meta`に書き込まれた行 | なし |
| **url-only** | モデルに一致しない行、または`--model`の指定なし | そのページにモデルが必要か、リダイレクトが必要かを判断 |
| **unmapped** | Core 3に保存先がないデータ。特に**著者** | `getSEOAuthor()`フックなどに移す |

---

## 手順0 — 併存させる（まだ切り替えない） {#step-0-—-coexist-no-cutover-yet}

稼働中のサイトと**並行して**Rankbeamを立ち上げます。モデルに`HasSEO`トレイトを追加し、ファサードまたはディレクティブでタグを出力します。ただし、WordPress本体やSEOプラグインはまだ削除**しないでください**。この段階ではインポートも破壊的な操作もせず、新しい構成が起動することだけを確認します。

切り替え中に新しいLaravelアプリと旧WordPressサイトを同じホストで配信する場合は、手順5まで別々のパスで運用してください。

## 手順1 — メタデータをインポート（まずドライラン） {#step-1-—-import-the-metadata-dry-run-first}

必ず`--dry-run`から始めてください。**何も書き込まず**、実行した場合の結果を完全な検証レポートで表示します。

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

主なオプションは次のとおりです。全一覧は`php artisan seo:import-from --help`で確認できます。

| オプション | 用途 |
|---|---|
| `--model=` | 対象モデルのFQCN。繰り返し指定できますが、WordPressインポーターは1回の実行で**1つ**のモデルに紐づけるため、コンテンツ種別ごとに実行します。 |
| `--match-by=` | スラッグを照合するモデルのカラム。デフォルトはルートキーです。 |
| `--post-type=` | DBリーダーを指定した投稿タイプに限定します。デフォルトは`post`と`page`です。 |
| `--connection=` | WordPressテーブルがあるデータベース接続です。 |
| `--table=` | WordPressテーブルの**接頭辞**。デフォルトは`wp_`です。 |
| `--locale=` | `seo_meta`行を書き込むロケールです。 |
| `--redirects-csv=` | 手順3用に、指定ファイルへリダイレクト候補も出力します。 |
| `--site-url=` | 絶対URLからパスを導出するための旧サイトURLです。 |
| `--overwrite` | 既存の空でない`seo_meta`を置き換えます。デフォルトは**空欄だけを埋める**動作です。 |
| `--limit=` | 移行元の行数を制限します。最初の試行に使えます。 |
| `--json` | 機械可読のレポートを出力します。 |

ドライランの結果が正しければ、`--dry-run`を外して適用します。

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

インポートは**冪等**で、デフォルトでは**空欄だけを埋めます**。このデフォルト動作なら、再実行してもRankbeamで編集済みのメタデータを上書きしません。

## 手順2 — 検証レポートを読み、保存する {#step-2-—-read-and-archive-the-verification-report}

各実行は**Verification report（検証レポート）**を表示します。何かを削除する前に、その数値を確認してください。後から参照できる成果物として保存します。

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

確認する点は次のとおりです。

- **matched**は、SEOメタデータを持つ予定のページ数と一致する必要があります。
- **url-only**は、モデルに一致しなかったページの作業一覧です。それぞれにモデル、リダイレクト（手順3）、または何も必要ないかを判断します。
- **truncated**は、`seo_meta`カラムに収めるため短くしたフィールドです。該当するタイトルとディスクリプションを確認します。
- **unmapped**は、Core 3にカラムがない移行元データで、**異なる`author`の値をすべて**明示します。著者はカラムに保存せず、`getSEOAuthor()`で扱います。数か月後に欠落に気付くのではなく、移し先を意識して決めるためのレポートです。

## 手順3 — リダイレクトをProへインポート {#step-3-—-import-the-redirects-into-pro}

Coreのインポーターは、Proのテーブルである**`seo_redirects`には書き込みません**。固定されたバージョン付き形式のCSV（**リダイレクトCSV形式v1**：`source_path,target_url,status_code,note`）を渡します。Proへのインポートも、まずドライランで行います。

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

各行はFilamentのリダイレクトフォームと同じ方法で検証します。不正な行、無効なステータスコード、**安全でない外部の宛先**、**重複する移行元**、**リダイレクトループ**になるルールは、理由を付けてスキップし、黙って書き込みません。ドライランはループと重複を含むファイル全体を検証し、何も書き込みません。既存ルールの宛先を置き換える場合は、`--overwrite`を指定します。

## 手順4 — `seo:audit --strict`で検証 {#step-4-—-verify-with-seo-audit-strict}

無料のプロセス内監査を、移行の合否条件に使います。`--strict`は、**どれか1ページでも**課題があれば0以外で終了するため、CIや切り替えの判定に使えます。

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

監査はモデルとリゾルバーのチェックを行います。タイトル・ディスクリプションの有無と長さ、OG画像、robotsの矛盾、正規URLの形式が対象です。出力済みHTMLと実際の正規URLのチェック、0〜100のスコアは[Proスキャン](/ja/pro/scan-issues)の機能です。Proがあれば、そちらも実行してください。[無料SEO監査](/ja/guide/audit)も参照してください。

続いて、実際のページをいくつかブラウザーで確認します。ソースを表示し、`<title>`、`<meta name="description">`、canonical、robots、OpenGraphのタグにインポート値が出力されていることを確かめます。

## 手順5 — 旧パッケージ・テーブルを削除する前に検証 {#step-5-—-verify-before-removing-the-legacy-package-table}

次の条件を**すべて**満たすまで、WordPressデータベース、SEOプラグイン、旧パッケージを削除**しないでください**。

- [ ] **すべて**のコンテンツ種別でインポートを実行した。1回の実行につき`--model`を1つ指定する。
- [ ] 保存した検証レポートの**matched**が期待する件数で、想定外の**url-only**行がない。
- [ ] 必要な**未対応の著者**の値をすべて別の場所へ移した。
- [ ] Proへリダイレクトをインポートし（`seo-pro:redirects-import`）、いくつかの旧URLが実際に新URLへ301で転送される。
- [ ] `php artisan seo:audit --strict`が`0`で終了する。
- [ ] （Pro）`php artisan seo:doctor`が、旧`seo`テーブルの残存も`config/seo.php`の競合も報告しない。
- [ ] 出力ページをブラウザーで抜き取り確認した。

インポーターはデフォルトで空欄だけを埋め、冪等なので、このデフォルト設定では、この判定前に手順1を繰り返せます。元データはWordPressに残っています。

## 手順6 — 旧環境を廃止 {#step-6-—-decommission}

手順5のチェックリストを満たしてから、WordPressサイトを停止し、そのデータベース・テーブルと旧SEOパッケージを削除します。新しい構成が本番で正しく配信できていると確信できるまで、データベースのバックアップを保持してください。

::: tip ロールバック
デフォルトの空欄だけを埋めるモードでは、手順1〜4は破壊的な操作を行いません。`seo_meta`は追加的な処理で、リダイレクトは検証され、ルールを削除して戻せます。WordPressのデータは変更しません。手順6の前なら*「WordPressで配信を続ける」*、手順6の後なら*「WordPressのバックアップを復元する」*のがロールバックです。明示的に既存値を上書きした場合、移行先の値を戻すにはインポート前のバックアップも必要です。
:::

---

ralphjsmit、artesaos、Spatieなどの**Laravel** SEOパッケージから移行する場合は、[他のLaravelパッケージからの移行](/ja/guide/migrate-from-other-packages)を参照してください。
