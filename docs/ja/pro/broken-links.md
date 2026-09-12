---
description: "上限付き・再開可能なクローラーで、到達できないリンクを記録します。内部の無効ルートはワンクリックでリダイレクトにでき、外部リンクの確認も任意で有効にできます。デフォルトは無効です。"
---

# リンク切れクローラー {#broken-link-crawler}

サイトを巡回して各ページのリンクをたどり、到達できないものを記録する、**上限付き・再開可能なクローラー**です。対象は**内部**リンクの切れ（自サイトの無効ルートで、ワンクリックでリダイレクトにできます）と、任意で追加する**外部**リンクの切れです。**デフォルトでは無効**です。

設計の特徴は3つあります。

- **上限付きで再開可能。** クロールは小さな複数のキュージョブで実行し、それぞれ少数ページに制限します。完了または上限に達するまで継続ジョブを再投入します。実行全体にも上限があります。デフォルトは2000ページで、`null`は明示的に無制限を選ぶ値であり、デフォルトではありません。その場合もバッチごとの上限や時間上限は適用されます。サイトとサーバーの容量に合った上限・間隔を維持してください。
- **デフォルトで安全な範囲。** デフォルトのスコープは`internal_only`で、自サイトのホストのリンクだけを確認し、第三者へのリクエストは送りません。内部・外部を問わず、すべての取得は共通の**SsrfGuard**を経由し、スキーム許可リスト、ホスト範囲、プライベートアドレス拒否を適用します。外部リンク確認は明示的に有効化する必要があり、その場合もガードを使います。
- **SEOスコアと分離。** 検出結果は専用テーブルに保存し、`seo_scan_issues`や0–100のスコアには書き込みません。外向きリンクが切れていてもページのスコアは変わりません。リンク切れは運用上の問題として別に追跡します。

## 利用できる機能 {#what-you-get}

Filamentダッシュボードでは、有効化時に次を表示します。

- **リンク切れの概要**：未解決件数（内部・外部別）と最終クロールから、検出結果の一覧へ移動できます。
- **リンク切れクロール**：実行中の進捗（巡回ページ数、確認リンク数、検出したリンク切れ数）。
- **スキャンごとのリンク切れ**：最近のクロールの推移。
- **検出結果リソース**：切れた`source → target`リンクをすべて表示し、絞り込めます。内部リンクはリダイレクトとして修正できます。

ヘッドレスでも、`seo-pro:broken-links-*`コマンドから同じデータを取得できます。

## デフォルトで無効にしている理由 {#why-it-s-off-by-default}

受動的な描画・採点機能と異なり、クローラーは**ネットワークリクエストを送信**し、多少の実行基盤を必要とします。そのため、インストール時に黙って始めず、意図的な有効化を必要とします。

- 主な2つのテーブルは、Proのすべてのマイグレーションと同じく**公開して使う方式**です。UIが問い合わせる前にマイグレーションする必要があります。種別付き検査には`seo_broken_link_inspections`も使います。
- クロールは**専用キューに投入**され、実行には**ワーカー**が必要です。ワーカーがなければ進みません。
- リンク切れの確定は**複数回のスキャンにまたがる**ため（後述）、有効化直後に結果を確定するのではなく、数週間の**定期実行**を想定しています。

## 設定 {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

次にマイグレーションを実行します。`seo-pro:install`はProの全マイグレーションを公開・実行します。冪等で、安全に再実行できます。

```bash
php artisan seo-pro:install
```

クロールのキューには**専用ワーカー**を起動します。長いクロールでユーザー向けジョブを待たせないために、別キュー（`seo-broken-links`）を使います。

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

連携を確認します。`seo:doctor`は有効化フラグ、テーブル、クロールキューが実際の接続（`sync`以外）へ解決されるかを確認し、それぞれ具体的な修正方法を示します。

```bash
php artisan seo:doctor
```

Redis、Supervisor、専用接続を含む複数キュー構成とバッチ調整は、[本番環境の設定](/ja/pro/production)を参照してください。

## クロールの実行 {#running-a-crawl}

ダッシュボードの**今すぐスキャン**アクション、またはヘッドレスで開始します。

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

どちらのコマンドもクロールを**キューに入れるだけ**で、実際に処理するのはワーカーです。

## リンク切れの判定方法 {#how-a-link-gets-flagged}

リンク切れとして報告するのは、`seo-pro.broken_links.mark_broken_after_failures`回の**連続したクロール**で到達できなかった場合だけです。成功が1回でもあればカウンターをリセットし、デフォルトは**3**回です。一時的な障害1回では指摘しないため、単発ではなく**定期実行**を想定しています。週次・デフォルトしきい値の場合、3回の失敗で確定します。最初の観測から約2週間、リンクが切れた時点から最大約3週間です。早く確定したい場合は頻度を上げるか、しきい値を下げてください。

## 種別付きのリンク検査 {#typed-link-inspections}

到達可能かだけでなく、巡回した各リンクに**種別付き検査**を行います。URLの整合性を分類し、末尾スラッシュの不統一、不適切なエンコード、リダイレクトチェーン、`javascript:`のhref、存在しないページ内アンカー、内容を説明しないアンカーテキストなどを指摘します。各検査には固定の**重大度**（`critical`・`warning`・`notice`）があり、[スキャンの問題](/ja/pro/scan-issues)と同じ語彙なので、1つのCIゲートで両方を扱えます。`seo_broken_link_inspections`にクロールごとに保存します。何回かの連続クロールで確定するリンク切れの*検出結果*とは異なり、検査は実行単位のスナップショットなので、**最初のクロールから直ちに**表示されます。これはCIゲートに必要な挙動です。

### 検査リファレンス {#inspection-reference}

| 検査 | 重大度 | 指摘する内容 | 適用対象 |
| --- | --- | --- | --- |
| `broken_link` | critical | 宛先がHTTP ≥ 400を返した | すべてのリンク |
| `redirect_chain` | notice · warning | リダイレクトでしか到達できない。`redirect_chain_warning_hops`を超えると`warning` | すべてのリンク |
| `link_unreachable` | notice | 今回到達不能（ネットワークエラー、タイムアウト、遮断）。一時的な可能性あり | すべてのリンク |
| `insecure_link` | warning | `https`サイト上の`http://`リンク（通信の安全性が低下） | すべてのリンク |
| `trailing_slash` | notice | 内部パスが宣言済みの末尾スラッシュ規約に反する（**`trailing_slash`を設定するまで無効**） | 内部 |
| `double_slash_url` | warning | 内部パスに`//`（空のセグメント）がある | 内部 |
| `duplicate_query_param` | notice | クエリキーが重複（`?a=1&a=2`）。`key[]`の配列構文は除外 | 内部 |
| `non_ascii_url` | notice | 内部パスに未エンコードの非ASCII文字がある | 内部 |
| `uppercase_url` | notice | 内部パスに大文字がある（大文字・小文字で別配信するものを確認） | 内部 |
| `underscore_in_url` | notice | 内部パスにアンダースコアを使用（SEO上はハイフンが推奨される区切り） | 内部 |
| `javascript_link` | warning | アンカーが`javascript:`のhrefを使う。通常の巡回可能な宛先ではない | すべてのアンカー |
| `missing_fragment` | warning | 同一ページの`#fragment`に対応する`id`/`name`がない | 同一ページ |
| `non_descriptive_anchor` | notice | アンカーテキストが一般的すぎる（「click here」「read more」）か、URLそのもの | すべてのアンカー |
| `absolute_internal_link` | notice | 内部リンクがルート相対パスでなく絶対URLで記述されている | 内部 |

末尾スラッシュ、大文字・小文字、エンコード、二重スラッシュなどの整合性検査は**内部**リンクだけに適用します。外部サイトのURL表記は管理対象ではありません。リダイレクト、リンク切れ、到達不能、安全でない通信の検査はすべてのリンクに適用します。自サイトのフレームワークルートと静的アセットへのリンクは省略し、初回から不要な通知を増やしません（後述の`exclude_paths` / `exclude_extensions`を参照）。

リンクの取得には、正規化した形でなく、**記述どおりのURL**を使い、`#fragment`だけを除去します。これにより、`/about/ → /about`のようなサーバー側の正規化リダイレクトを、事前のURL正規化で隠さず、実際に観測して`redirect_chain`として表示できます。同じページに記述された異なるリンク形式はすべて検査するため、`/page#ok`と`/page#missing`、または`/a//b`と`/a/b`も、最初の1つだけでなくそれぞれ評価します。基礎となるリンク切れの*検出結果*では引き続き、宛先の全別名を1つの識別情報にまとめます。検査行は`(page, target, inspection)`ごとに記録するため、複数のページ内アンカーが失敗する宛先も、アンカーごとでなく代表例付きの`missing_fragment`1行として表示します。

### 検査分類の調整 {#tuning-the-taxonomy}

すべて`seo-pro.broken_links.inspections`配下にあります。

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**1つのルールを無効化**するには`rules`からクラスを削除し、**分類全体を無効化**するには`SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`を設定します。特に次の2ルールを把握してください。

- `trailing_slash`は、**規約を宣言するまで無効**です（`'always'` / `'never'`）。`/x`と`/x/`の両方を`200`で配信するサイトには、指摘すべき「誤った」形式がないためです。サーバーがリダイレクトで正規化する場合は、すでに`redirect_chain`として表示されます。
- `absolute_internal_link`は、絶対URLで書かれた内部リンク**すべて**に反応します。サイトの規約で絶対URLを使う場合、無害な`notice`の行が大量にできます。`rules`から外せば通知を止められます。

## 継続的インテグレーション {#continuous-integration}

リンクスキャンと[SEO監査](/ja/pro/scan-issues)はどちらも、**ビルドを失敗させる**ことと**レポート成果物を書き出す**ことができ、Rankbeamをダッシュボードだけでなく品質ゲートとして使えます。`--fail-on-error`は`critical`の重大度（リンク切れ、重大な問題）に対応します。`--fail-on-warning`は`critical`**または**`warning`で失敗します。別の「error」という重大度はありません。

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>`は成果物を書き出します。ディレクトリを指定するとファイル名を導出します。`--format`は`json`（デフォルト）、`md`、`html`です。パイプラインで解析するにはJSON、実行に添付する自己完結したページにはHTMLを使います。

### GitHub Actions {#github-actions}

クローラーはHTTPでページを取得するため、CIでは到達可能なコンテンツを指定する必要があります。ローカル配信アプリ（下記）か、`SEO_PRO_BROKEN_LINKS_BASE_URL`で指定したステージングURLを使い、モデル・サイトマップを登録して、クロールのシードとなるソースを用意します。

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## 定期実行 {#scheduling}

`routes/console.php`にクロールと整理処理を登録します。

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## コマンドリファレンス {#command-reference}

| コマンド | 処理内容 |
| --- | --- |
| `seo-pro:broken-links-scan` | 上限付き・再開可能なクロールをキューに投入（`--scope=internal_only\|internal_and_external`、追加シード`--url=*`） |
| `seo-pro:broken-links-status` | 最新クロール概要、未解決リンク切れ、今回の検査件数。**CIゲート**（`--fail-on-error`、`--fail-on-warning`、`--report=<file\|dir>`、`--format=json\|md\|html`） |
| `seo-pro:broken-links-cancel` | 実行中・キュー待ちのクロールをキャンセル（`{run?}`。デフォルトは最新の有効な実行） |
| `seo-pro:broken-links-recover` | 停止したワーカーに取り残されたクロールを失敗にする（古いリース） |
| `seo-pro:broken-links-prune` | クローラーの保持方針を適用（古い実行 + 解決済みの検出結果） |

## 調整 {#tuning}

実行全体のページ数、ページごとのリンク数、ジョブごとの上限、厳格な時間枠、ホストごとの負荷を抑える間隔は、すべて`seo-pro.broken_links`にあります。デフォルトは控えめで有限です。値を上げる前に、[本番環境の設定にあるバッチ調整表](/ja/pro/production)を参照してください。
