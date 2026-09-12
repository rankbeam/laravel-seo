---
description: "サイトで観測したAIクローラーの動きを記録します。どのボットが何回取得し、最後にどのURLとステータスにアクセスしたかを確認する、AIクローラー制御の監視側の機能です。"
---

# AIボットの監視 {#ai-bot-monitor}

リクエストの分類には**User-Agentの一致**を使います。ボットの身元を検証しているわけではありません。この機能が記録するのは観測したリクエストであり、User-Agentは偽装できます。

Coreの[AIクローラー制御](/ja/guide/ai-crawlers)は、`robots.txt`でAIクローラーに何を*伝えるか*を決めます。Proの**AIボット監視**はそのもう一方で、実際に*何をしたか*を記録します。サイトを取得したAIクローラー、回数、各ボットが最後にアクセスしたURLとHTTPステータスを確認できます。

404監視の仕組み（終了処理で動くグローバルミドルウェア、アクセス数を加算するupsertモデル、同じプライバシー方針）を再利用します。ただし、パスの代わりに**ボット**をキーとし、**すべての**レスポンスステータスを記録します。対象は、404監視が意図的に除外しているAIクローラーです。ボットの識別にはCoreの`AiCrawlerRegistry`を再利用するため、robots.txtのポリシーと観測した通信は、共通の情報源に基づきます。

::: tip Core ≥ 3.3が必要です
CoreのAIクローラーカタログ（[`SEO::aiCrawlers()`](/ja/guide/ai-crawlers)）でボットを識別します。古いCoreでは何もしません。
:::

## 有効にする {#enabling-it}

デフォルトでは無効です。有効にすると、グローバルミドルウェアが各レスポンスの後で一致したクローラーを記録します。ページの返却を遅らせません。

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

これだけでミドルウェアが自動登録されます。`ai_bots.auto_register_middleware`で自動登録を無効にできます。既知のボットごとに1行を追加または更新するため、テーブルの行数はカタログの規模に収まります。

## ログを読む {#reading-the-log}

### ヘッドレス {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

各行には`bot`、`label`、`operator`、`purpose`、`hit_count`、`last_path`、`last_status`、`first_seen_at`、`last_seen_at`があります。

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Proプラグインを登録すると、SEOナビゲーショングループの下に**AI ボット**の表が表示されます。ボット、事業者、用途、アクセス数、最後のステータス、最後のパス、最終観測日時を確認でき、用途で絞り込めます。読み取り専用です。

## プライバシー {#privacy}

404監視と同じ方針で、**デフォルトではIPを保存しません**。`ai_bots.hash_ip`を明示的に有効にした場合も、キー付きSHA-256（`ip_hash`）だけを保存し、生のIPを書き込むことはありません。

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## 期間別の指標（日別バケット） {#period-metrics-daily-buckets}

累計テーブルはボットごとに1行を保持します。順位表には便利ですが、**特定の期間**にボットが*何回アクセスしたか*、*異なるURLをいくつ取得したか*は分かりません。`daily_enabled`が有効（デフォルト）の場合、各アクセスを日別・パス別のバケット（`seo_ai_bot_daily`）にも記録します。そのため、[ホワイトラベルレポート](/ja/pro/reports)は累計値の差分ではなく、**実際の**期間別の数値、つまり前回のレポート以降のアクセス数と、今回の期間に取得した一意のURL数を表示できます。

累計ログをボットごとに1行にした理由である、行数の上限も維持します。

- ボットごと・日ごとの**一意のパス数の上限**（`daily_max_paths`）があります。上限を超えた新しいパスは、単一の超過分バケットにまとめます。日ごとのアクセス総数を正確に保ちつつ、行数が増え続けることを防ぎます。上限に達した一意のURL数は「N+」と表示します。
- **保持期間**（`daily_retention_days`）を過ぎたデータは、`seo-pro:ai-bots-prune`で削除します。

`daily_enabled`を`false`にすると、累計の順位表だけを保持します。その場合、レポートの「前回以降」は前回レポートのスナップショットとの差分にフォールバックします。既存のバケットは無視され、古いテーブルを読むことはありません。

期間別の数値の粒度は**日単位**です。「前回レポート以降」は前回レポートの日から丸1日単位で数えます。その日のアクセスには、レポートの正確な生成時刻より前のものも後のものも含まれ得ます。通常の日次・週次・月次の頻度では、この境界のずれはわずかです。

## 観測を制御につなげる {#turning-observation-into-control}

監視機能は*誰が*クロールしているかを示し、Coreの[AIクローラー制御](/ja/guide/ai-crawlers)は*何を取得してよいか*を決めます。制限したい学習用クローラーが見つかった場合は、次のように設定できます。

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

`robots.txt`に従わないと公表されているボットもあります。監視機能でそれらを見つけ、エッジ（ファイアウォール / WAF / Cloudflare）で遮断するかを判断してください。
