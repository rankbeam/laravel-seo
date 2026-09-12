---
description: "専用キュー、スケジューラー、再試行と復旧の方針、保持期間、テレメトリでProを大規模運用します。約900ページの本番環境を支える、Filamentに依存しない構成です。"
---

# 本番環境の設定 {#production-setup}

Proの日常的な処理であるサイトスキャン、リンク切れクロール、任意のリダイレクトヒット数の書き出し、404の古い記録の削除は、Laravelのキューとスケジューラーで実行します。このページは大規模運用のための共通ガイドです。専用キュー、スケジューラー、再試行と復旧の方針、保持期間、全体を監視するテレメトリを説明します。1日約20k訪問・約900ページの本番環境で使う構成を、再現できる形で示しています。

ここで扱うものはすべて**Filamentに依存しません**。パネルの有無にかかわらず、エンジン、コマンド、キュー、テレメトリは同じです。Filamentを使うと表示画面が追加されますが、処理のスケジュールや実行方法は変わりません。

[[toc]]

## 安全な導入順序 {#safe-rollout-order}

次の順序で進めてください。各段階を検証してから次に進めます。

1. **インストール**：設定とマイグレーションを公開し、実行します。

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install`は`config/seo-pro.php`とProのマイグレーションを公開してから、`migrate`を実行します。Proのマイグレーションは**公開して使う方式**で、パッケージが自動ロードすることはありません。そのため、この手順で`composer require`だけの状態から実際に使えるスキーマになります。処理は冪等で、いつでも再実行できます。公開済みファイルを上書きするには`--force`、マイグレーションせず公開だけするには`--no-migrate`を指定します。

2. サービスプロバイダー（`AppServiceProvider::boot()`）で**スキャン対象を登録**します。

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. バックグラウンド処理を有効にする前に、連携を**検証**します。

   ```bash
   php artisan seo:doctor
   ```

   出力された警告をすべて修正してください。各警告には具体的なコマンドや設定行が示されます。CIでは`--json`を加え、固定のチェックIDを使って判定します。

4. **キューとスケジューラーを設定**し（後述）、キューワーカーと`schedule:run`用のcronエントリを配置します。

5. **任意機能は最後に有効化**します。リンク切れクローラー、AI支援、Search Consoleはすべてデフォルトで無効です。クローラーにはマイグレーション済みテーブル（手順1で公開済み）と専用ワーカー（後述）が必要です。

**Pro 2.41.0への更新：** スキャンワーカーを一時停止し、`php artisan vendor:publish --tag=seo-pro-migrations --force`でマイグレーションを公開して`php artisan migrate`を実行します。その後ワーカーを再起動し、`php artisan seo:doctor`を実行します。新しい`seo_scan_target_completions`テーブルと`seo_scan_runs.target_tracking`カラムが必要です。実行・対象ごとの受理記録が、終端結果の重複でカウンターが増えるのを防ぎ、最初に受理した結果を採用します。まだ対象を処理していない古いキュー内の実行は継続します。更新前に一部を処理済みの実行は履歴を保持しますが、次にキュージョブを受け取ったとき、新規スキャンの案内とともに終了します。再試行を使い切った対象は新しい実行で再試行してください。ロールバックでは、ワーカーを止めてコードを戻した後にマイグレーションを戻します。その後のスキャンも取り消す必要がある場合に備え、更新前のデータベースバックアップを保持してください。

## 処理ごとの専用キュー {#dedicated-queues-per-workload}

長いスキャンやクロールが、メールや通知などユーザー向けジョブの前をふさいではいけません。各SEO処理に専用のキューとワーカーを割り当ててください。

スキャンパイプラインとリンク切れクローラーには、それぞれ設定可能なキューがあります。

| 処理 | 設定 | 環境変数 | デフォルトのキュー |
|---|---|---|---|
| ページ内スキャンのジョブ | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | デフォルトキュー |
| リンク切れクロールのジョブ | `seo-pro.broken_links.queue.name`（+ `.connection`） | `SEO_PRO_BROKEN_LINKS_QUEUE`（+ `_CONNECTION`） | `seo-broken-links` |

### Redisの例（本番構成） {#redis-example-the-production-topology}

`.env`：

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

キューごとにワーカーを起動します。それぞれ別プロセス・Supervisorプログラムです。

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

クロールワーカーの`--timeout`は、`seo-pro.broken_links.batch.hard_time_budget_seconds`（デフォルト180）とHTTPタイムアウトの合計より長くし、記録処理の途中でバッチが停止しないようにします。ジョブ自身の`$timeout`もその合計に設定されるため、ワーカーのフラグを合わせてください。クロールには`--tries=1`を使います。停止したジョブは次の継続処理か`seo-pro:broken-links-recover`が回収するので、キュー側の再試行は不要です。

`seo:doctor`は各処理のキューを報告し、`sync`に解決されると警告します。その場合、処理がその場で同期実行され、呼び出しをブロックするためです。

## スケジューラー {#scheduler}

Laravel 11、12、13では**`routes/console.php`**でスケジュールを定義します。`app/Console/Kernel.php`の`schedule()`メソッドがあるのはLaravel 10から更新したアプリだけです。まだ使っている場合は同じ項目をそこに置きます。スケジューラーを毎分動かすため、システムcronに1エントリ追加します。

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

次に、繰り返すコマンドを推奨頻度で登録します。

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

推奨頻度の一覧です。

| コマンド | 頻度 | 理由 |
|---|---|---|
| `seo:sitemap` | 毎日 | 現在のコンテンツからサイトマップを更新 |
| `seo-pro:scan` | 毎週（更新が多ければ毎日） | 全対象を再監査 |
| `seo-pro:scan-recover` | 毎時 | ワーカー停止で取り残された実行を回収 |
| `seo-pro:scan-prune` | 毎日 | スキャン実行の保持期間を適用 |
| `seo-pro:redirects-flush-hits` | `redirects.hits.flush_immediately=false`の場合のみ5分ごと | キャッシュにまとめたヒットカウンターをDBへ書き出す |
| `seo-pro:404-prune` | 毎日 | 404ログに保持期間と行数上限を適用 |
| `seo-pro:404-recheck` | 毎日 | 未解決404のパスを再取得し、元ページが直って200になったものを復旧済みにする |
| `seo-pro:broken-links-scan` | 毎週 | リンク切れを再クロール（複数回のスキャンで確定） |
| `seo-pro:broken-links-recover` | 毎時 | ワーカー停止で取り残されたクロールを回収 |
| `seo-pro:broken-links-prune` | 毎日 | クローラーの保持期間を適用 |

`seo-pro:scan`と`seo-pro:broken-links-scan`は処理を**キューに入れるだけ**で、実行するのはワーカーです。recover/pruneコマンドはその場で実行され、負荷は小さい処理です。

::: tip 複数回のスキャンによるリンク切れの確定
リンク切れと判定するのは、`seo-pro.broken_links.mark_broken_after_failures`回の**連続したスキャン**で到達できなかった場合だけです。1回でも成功すればカウンターをリセットします。単発でなく定期クロールにするのはこのためで、一時的な障害1回ではリンク切れにしません。デフォルトの3回なら、週次スキャンでは最初の失敗観測から約2週間、リンクが切れた時点から最大約3週間で確定します。早く確定したい場合は実行頻度を上げるか、しきい値を下げてください。
:::

## バッチの調整（リンク切れクローラー） {#batch-tuning-broken-link-crawler}

クロールは、上限を持ち、自分で継続ジョブを投入する複数のジョブで進みます。デフォルトには有限の上限があります。自サイトと確認先ホストの容量に合わせ、`seo-pro.broken_links`で調整してください。

| キー | デフォルト | 制限するもの |
|---|---|---|
| `max_pages_per_run` | `2000` | 1回の実行で取得するページ数。`null`は明示的に無制限を選ぶ値（デフォルトではない） |
| `max_links_per_page` | `200` | ページごとのリンク確認数 |
| `max_total_links` | `null` | 実行全体のリンク確認数に対する任意の上限 |
| `batch.max_pages_per_job` | `50` | キュージョブごとのページ数 |
| `batch.max_links_per_job` | `1500` | キュージョブごとのリンク確認数 |
| `batch.hard_time_budget_seconds` | `180` | この時間を過ぎると**新しい取得を開始せず**、継続ジョブを再投入 |
| `batch.dispatch_delay_seconds` | `1` | 継続ジョブ間の遅延 |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | リクエストごとの上限 |
| `http.max_response_bytes` | `seo-pro.http.max_response_bytes`を継承 | ページ取得・宛先確認のレスポンス本文に適用するストリーム読み込み上限 |
| `seed.max_response_bytes` | クローラー・共通HTTP上限を継承 | シード生成時に取得する生のサイトマップXML / `.gz`のバイト数 |
| `seed.max_inflated_bytes` | シード・クローラー・共通上限を継承 | `.gz`サイトマップの展開後に受け入れるバイト数 |
| `http.per_host_delay_ms` | `0` | 相手への負荷を抑える確認間隔（`internal_and_external`では長くする） |

`batch.hard_time_budget_seconds`はクロールワーカーの`--timeout`より十分短くしてください。進行中のリクエストは途中で中断できず、`http.timeout`で制限されます。そのため、ワーカーのタイムアウトは処理時間枠 + HTTPタイムアウト + 余裕時間とします。

`internal_and_external`のクロールでは、SsrfGuardが外向き確認を許可するよう`seo-pro.http.scope`または`seo-pro.http.allowed_hosts`を広げ、他社ホストへ短時間にリクエストを集中させないよう`http.per_host_delay_ms`を長くします。`seo:doctor`はクロール範囲が外部なのにガード範囲が全確認を遮断する場合に警告します。

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

キューごとに1プログラムを用意します。`/etc/supervisor/conf.d/app-workers.conf`の例：

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

正常な再起動でバッチ処理中のジョブが停止しないよう、`stopwaitsecs`はワーカーの`--timeout`より長くする必要があります。

### Horizon {#horizon}

Horizonを使う場合は、`config/horizon.php`で処理ごとのsupervisorを定義し、Supervisorの代わりにプロセス管理を任せます。

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## 再試行と失敗処理 {#retry-failure-handling}

スキャン対象ジョブは設定から自身の再試行方針を取得し、ワーカーの`--tries`には**依存しません**。

| キー | デフォルト | 意味 |
|---|---|---|
| `seo-pro.scan.tries` | `3` | 対象ジョブごとの試行回数 |
| `seo-pro.scan.backoff` | `30` | 試行間の秒数 |
| `seo-pro.scan.timeout` | `300` | 対象ジョブのタイムアウト（重複防止ロックはタイムアウト + 60で失効） |

対象ジョブが再試行を使い切ると対象を**失敗**として記録し、実行自体は終了します（`partial`または`failed`）。処理済みの対象失敗によって実行が`running`のまま残ることはありません。記録処理前にワーカーが強制終了した場合は、後述の復旧処理が必要です。失敗は標準の`failed_jobs`テーブルに入り、通常の方法で管理できます。

```bash
php artisan queue:failed
php artisan queue:retry all
```

テーブルを無制限に増やさないため、SEOのスケジュールとともに`queue:prune-failed`を登録します。

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

リンク切れクロールは`--tries=1`を使います。停止したジョブはリースのハートビートが古くなると次の継続処理、または`seo-pro:broken-links-recover`が回収するため、キューの再試行は処理を重複させるだけです。

## 復旧 {#recovery}

進捗記録が自動で修復できないのは、ジョブ途中でワーカーが停止した場合です。2つの整理処理で対応し、どちらも**毎時**実行します。

- `seo-pro:scan-recover`：`seo-pro.scan.recovery.stuck_scan_timeout_hours`（デフォルト2）の間進捗がないページ内スキャンの実行を失敗にします。
- `seo-pro:broken-links-recover`：リースのハートビートが古くなったクロール実行（`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`、デフォルト2）を回収し、失敗として、スコープごとに1実行という枠を解放します。

`seo:doctor`はこれを**最近のハートビートの証拠**として表示します。スキャンを使い始めると、停止した実行を報告してrecoverコマンドを案内します。cronが実際に動くことまでは証明できません。どのコマンドにもできないことであり、実行履歴に現れた事実を報告します。

## 保持期間 {#retention}

テーブルの増加を制限してください。デフォルトは次のとおりです。すべて`seo-pro.*`にあり、`null`で該当の削除処理を無効にします。

| データ | 設定 | デフォルト | コマンド |
|---|---|---|---|
| スキャン実行（+ 問題） | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404ログ | `monitor_404.retention_days`（+ `max_rows` `10000`） | `90` | `seo-pro:404-prune` |
| クロール実行 | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| 解決済み検出結果 | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## 運用テレメトリ {#operational-telemetry}

ページ内スキャンとリンク切れクロールは、完了するたびに構造化された完了ログを1行出力します。パネルなしでも指標の履歴を得られます。ペイロードは件数と時間だけで、URL、本文、ヘッダー、訪問者データは含みません。

| 指標 | スキャン | クロール |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls`（SSRF対策で拒否した対象） | — | ✓ |
| `transient_failures`（ネットワーク失敗。次回スキャンで再確認） | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds`（キュー投入 → 最初のバッチ） | ✓ | ✓ |

`seo-pro.telemetry`で設定します。

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

`channel`に専用ログチャンネルを指定すれば、アプリケーションログと混ぜずにLoki / Datadog / CloudWatchなどの出力先へ送れます。

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

より詳しく処理する場合はイベントを直接購読します。それぞれ同じ`metrics()`ペイロードを公開しています。

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

テレメトリはベストエフォートで、チャンネルの設定ミスでスキャンが失敗することはありません。

## Filamentに依存しないデプロイ {#filament-independent-deployment}

このページの内容にパネルは不要です。エンジン、全コマンド、キュー、スケジューラー、復旧、保持期間、テレメトリはヘッドレスでも同じです。Filamentパネル（`SeoProPlugin`）が追加するのは、リアルタイムのスキャン進捗、問題一覧、リダイレクトCRUD、404監視、リンク切れダッシュボードという**表示画面**だけです。エンジンを配置してCLIとスケジューラーで運用し、後からパネルを追加しても、あるいは追加しなくても、移行ややり直しは必要ありません。全コマンドのリファレンスは[ヘッドレスでの利用](/ja/pro/headless)を参照してください。
