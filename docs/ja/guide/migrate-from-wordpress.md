---
description: "YoastやRank Mathで手作業で設定したタイトル、ディスクリプション、正規URL、robots、フォーカスキーワードをLaravelモデルへ移します。インポーターのフィールド対応リファレンスです。"
---

# WordPressからの移行 {#migrating-from-wordpress}

コンテンツサイトをWordPressから移行する場合、Rankbeamは、チームがYoastやRank Mathで手作業で設定したSEOメタデータをLaravelモデルへ引き継げます。タイトル、ディスクリプション、正規URL、robots指示、フォーカスキーワード、ソーシャル用の上書き値を移し、長年の最適化を切り替えで失わずに済みます。

::: tip 実際に稼働環境を切り替える場合
このページは、インポーターのフィールド対応、トークン、移行元キーを説明する*リファレンス*です。併存、インポート、検証、廃止の順で進める、リスクを抑えた具体的な**手順**には、[WordPress移行手順書](/ja/guide/wordpress-migration-runbook)を使ってください。
:::

方法は2つあり、どちらも同じ`seo:import-from`コマンドを使います。

| 方法 | ソース | 適した用途 |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | WordPressからエクスポートした表 | 多くの制作会社の移行案件。対象URLを正確に指定できます。 |
| [**データベース**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | 稼働中のWordPressデータベース | OpenGraph/Twitterの上書き値やRank Mathのリダイレクトも含む、詳細な移行。 |

どちらも**冪等**で、再実行しても同じ行を更新し、重複を作りません。**`--dry-run`**に対応し、デフォルトでは空のフィールドを*埋めるだけ*です。Rankbeamに設定済みのSEOデータは上書きしません。既存値をインポート値で置き換える場合は、**`--overwrite`**を指定します。

## WordPressの行を`seo_meta`行に変換する方法 {#how-wordpress-rows-become-seo-meta-rows}

WordPressのデータはLaravelのポリモーフィックデータではありません。WordPressの行は**URL**または**投稿ID**をキーとしますが、Rankbeamの`seo_meta`はポリモーフィックで、各行は実在するEloquentモデルに紐づきます。そのため、インポーターは各WordPress行をアプリのモデルと照合し、モデルに紐づいた行とURLだけの行をレポートで明確に分けます。

- **モデルに紐づく行。** `--model="App\Models\Post"`で対象モデルを指定します。各行の**スラッグ**（URLの最後のパス要素、またはWordPressの`post_name`）を、モデルのルートキーと照合します。`--match-by=`で別のカラムも指定できます。一致した行は`seo_meta`に書き込みます。
- **URLだけの行。** モデルに一致しない行、または`--model`を指定しない実行では、紐づけるモデルがないため`seo_meta`行を作れません。`url-only`の理由でスキップしたと報告します。その正規URLから[リダイレクト候補](#redirects)を作れる場合はあります。

WordPressの投稿と固定ページは、通常、*異なる*Laravelモデルに対応します。コンテンツ種別ごとに行を絞り、インポーターを1回ずつ実行してください。

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning カスタム投稿タイプはデフォルトでは対象外です
データベースリーダーが走査する投稿タイプは、**`post`**と**`page`**だけです。テーマの`product`、`event`、`pathology`など、カスタム投稿タイプを使うサイトでは、それぞれを明示してください。`--post-type=`を繰り返し指定できます。

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSVインポート {#_1-csv-import}

CSV方式は、多くの制作会社の移行案件に対応できます。次のヘッダーを使い、URLごとに1行をエクスポートします。列の順番は自由です。認識できない列は無視し、報告します。

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

実行します。

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| カラム | `seo_meta`の対応先 | 補足 |
|---|---|---|
| `url` | *照合キー* | スラッグ（最後のパス要素）をモデルと照合します。必須です。 |
| `title` | `title` | 70文字に切り詰め、超過した値を報告します。 |
| `description` | `description` | 160文字に切り詰めます。 |
| `canonical` | `canonical` | [リダイレクト候補](#redirects)にも使います。 |
| `robots` | `robots` | そのまま保存します（例：`noindex, nofollow`）。50文字に切り詰めます。 |
| `focus_keyword` | `focus_keywords` | カンマ区切りで、先頭が主キーワードです。 |

`url`がない行や、列数がヘッダーと合わない行は、不正な行としてスキップし、集計します。

---

## 2. データベースからのインポート（Yoast / Rank Math） {#_2-database-import-yoast-rank-math}

WordPressデータベースが残っていれば、SEOメタデータを直接読み取れます。通常のCSVエクスポートでは落ちるOpenGraph/Twitterの上書き値や、Rank Mathのリダイレクトも対象にできます。

### WordPressへの接続を設定 {#point-a-connection-at-wordpress}

`config/database.php`に、WordPressデータベースへの接続を追加します。

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

続いてインポートします。テーブル接頭辞のデフォルトは`wp_`で、`--table=`で変更できます。

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

リーダーは`{prefix}posts`の公開済み投稿・固定ページを走査し、各投稿のプラグインメタデータを`{prefix}postmeta`から読み取ります。投稿の`post_name`スラッグを、アプリのモデルと照合します。

::: tip デフォルト以外のテーブル接頭辞
マネージドWordPressホストでは、ランダム化した接頭辞（`wp_`ではなく`wppg_`など）を使うことがあります。ダンプ内の`CREATE TABLE`の名前を確認し、実際の接頭辞`--table=wppg_`を渡してください。これにより`{prefix}posts`と`{prefix}postmeta`を見つけられます。
:::

::: tip MySQL 8に復元したダンプから読み取る場合
ローカルで読み取るためWordPressダンプをMySQL 8以降へ取り込む場合は、`.sql`を読み込む前に厳格なSQLモードを緩めます。WordPressの日時デフォルト`'0000-00-00'`は、MySQL 8のデフォルトの`STRICT`/`NO_ZERO_DATE`モードでは拒否されます。そのためSEOインポートより前に、ダンプの取り込み自体が`Invalid default value for 'post_date'`で失敗します。

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### フィールドの対応 {#field-mapping}

両インポーターはフィールドを**明示的に**対応付けます。Core 3に対応カラムがないキーは、*未対応*として報告し、存在しない保存先を作ることはありません。

| Yoastのメタキー | Rank Mathのメタキー | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots。** WordPressのデフォルトと異なる指示だけを保存します。通常のインデックス登録可能なページでは`robots`をnullのままにし、サイトのデフォルトを引き継ぎます。Yoastの個別の`noindex` / `nofollow` / 詳細フラグ（`noarchive`、`nosnippet`、`noimageindex`）を1つの文字列にまとめます。Rank Mathのシリアライズされた`robots`配列も同様に読み取り、デフォルトの`index` / `follow`を除きます。

**未対応のキー**は報告し、コピーしません。添付画像のID（`*-image-id`）、キーワード・SEOスコア（`linkdex`、`content_score`、`rank_math_seo_score`）、主カテゴリーの選択、Rank Mathのリッチスニペット用スキーママーカーが対象です。スキーママーカーには、より表現力のある型付きの[スキーマグラフ](/ja/guide/schema)を代わりに使えます。

::: warning 正規URLは保存値のままインポートされます
明示的な正規URL（`rank_math_canonical_url` / `_yoast_wpseo_canonical`）は、**保存されているとおりに**コピーします。マネージド環境やステージング環境でよくあるように、ページが*旧ドメイン*の絶対URL（例：`https://oldsite-staging.example.com/page/`）を正規URLに固定していた場合、移行後もそこを指します。インポーターはホストを書き換えません。`--site-url`は、[リダイレクト候補](#redirects)とCSV行の照合用に絶対URLからリクエストの*パス*を導出しますが、保存する正規URLは書き換え**ません**。ドメインをまたぐ移行後は、インポートした正規URLを確認してホストを更新するか、値を消してリゾルバーの自己参照の正規URLにフォールバックさせてください。多くのページには明示的な正規URLがなく、影響しません。YoastとRank Mathは出力時に正規URLを自動生成します。
:::

### テンプレートトークン {#template-tokens}

YoastとRank Mathは、タイトルとディスクリプションをトークン付きの**テンプレート**として保存します。Yoastは`%%title%%`、Rank Mathは`%title%`を使います。インポーターは**導出できるトークンを解決**し、**それ以外を除去**するため、保存値に未処理の`%%token%%`文字列が残ることはありません。

| トークン | 解決後の値 |
|---|---|
| `%%title%%` / `%title%` | WordPressの投稿タイトル |
| `%%sitename%%` / `%sitename%` | `wp_options`のブログ名（データベースからのインポート） |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`、`%%primary_category%%`など | *除去*します。空にし、周囲の区切り文字を整理します。 |

トークンを解決した実行では、そのことをレポートで知らせます。**インポートしたタイトルを確認**し、意図した文章になっているか確かめてください。導出できなかったトークンに依存するものは調整します。

---

## リダイレクト {#redirects}

`seo_redirects`は[Rankbeam **Pro**](/ja/pro/installation)の機能なので、Coreのインポーターが直接そのテーブルに書き込むことはありません。代わりに`--redirects-csv=`を指定すると、Proのリダイレクトテーブルと同じカラム`source_path,target_url,status_code,note`を持つ**CSVを出力**します。これをProにインポートします。

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

リダイレクト候補は次のソースから作ります。

- **CSVインポート** — `canonical`が自身の`url`と**異なるパス**を指す行から、旧パスから正規URLへの`301`を作ります。パスが同じ自己参照の正規URLは、ループになるため出力*しません*。
- **Rank Mathのデータベース** — `{prefix}rank_math_redirections`テーブルの有効なルールです。**完全一致**のルールだけを出力します。正規表現・部分一致・前方一致・後方一致は、単一パスに対応しないためスキップして報告します。
- **無料版Yoast**にはリダイレクトテーブルがありません。Yoast Premiumにだけあり、そのスキーマは無料パッケージの対象外です。YoastのリダイレクトにはCSV方式を使ってください。

候補は**確認用の提案**です。CSVを確認した後、[`seo-pro:redirects-import`](/ja/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro)でProにインポートします。このコマンドはすべての行を検証し、ループ、安全でない宛先、重複を拒否します。CSVの形は固定の仕様で、**リダイレクトCSV形式v1**は`source_path,target_url,status_code,note`です。

---

## レポートの内容 {#what-the-report-tells-you}

`--json`を使わない実行では、結果の表（created / updated / unchanged / skipped / scanned）、**Verification report（検証レポート）**、確認用の各セクションを表示します。

- **Verification report（検証レポート）** — 判断に使う内訳を一目で確認できます。**matched**（モデルに紐づいた行）、**url-only**（モデルに一致しなかった行）、切り詰め・未対応の集計です。
- **Truncated（切り詰め）** — `seo_meta`カラムに収めるため短くした値です。
- **Not imported（未インポート）** — 値があったもののCore 3に保存先がないキーで、**異なる`author`の値をすべて**含みます。著者は保存カラムではなく[`getSEOAuthor()`](/ja/concepts/resolver-precedence)で扱うため、黙って失うのではなく、移し先を決める値として一覧にします。
- **Redirect candidates（リダイレクト候補）** — 出力した件数とファイルです。
- **Skipped rows by reason（理由別のスキップ行）** — URLだけの行、SEOメタデータのない投稿、完全一致でないリダイレクトルールです。
- **Warnings（警告）** — テンプレートトークンを解決した場合などに表示します。

`--json`を加えると、上記すべてを機械可読形式で取得できます。`verification`ブロックにはmatched/url-onlyの件数と、著者のすべての値を含みます。

### 検証 {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

いずれかのページに課題があると、`--strict`は0以外の終了コードを返します。[無料SEO監査](/ja/guide/audit)を参照してください。併存 → インポート → 検証 → 廃止という切り替え全体の順序は、[WordPress移行手順書](/ja/guide/wordpress-migration-runbook)に従ってください。

---

ralphjsmit、artesaos、Spatieなどの**Laravel** SEOパッケージから移行する場合は、[他のLaravelパッケージからの移行](/ja/guide/migrate-from-other-packages)を参照してください。
