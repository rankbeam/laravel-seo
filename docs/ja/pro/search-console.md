---
description: "読み取り専用のGoogle Search Consoleパネルです。上位クエリ・ページの表示回数、クリック数、CTR、掲載順位を、スキャナーが把握するページと関連付けます。デフォルトでは無効です。"
---

# Search Console（読み取り専用） {#search-console-read-only}

**読み取り専用**のGoogle Search Consoleパネルです。上位クエリとページの**表示回数、クリック数、CTR、平均掲載順位**を、スキャナーが把握しているページと関連付けます。「このページには問題が**あり**、表示回数も減っている」という状況を1か所で確認できます。**デフォルトでは無効**です。

設計には3つの柱があります。

- **読み取り専用に限定。** 連携が要求するOAuthスコープは、パッケージに固定された`webmasters.readonly`だけです。Search Analyticsの読み取り以外はできません。サイトマップの送信、インデックス登録の要求、Search Console上の変更は一切行いません。スコープを広げる設定もありません。
- **自分のプロパティと認証情報を使用。** リクエストは*自分のサーバー*からGoogleへ直接送信され、*自分の*サービスアカウントまたはOAuth認証情報で認証します。中継、従量課金、再販はなく、パッケージはテレメトリーを送信しません。
- **エラーは該当箇所に表示。** 認証情報の欠落、403、割り当て上限のエラー、タイムアウトは、パネルの描画を中断せず、その場にメッセージを表示します。履歴同期コマンドは、以下の説明のとおり失敗を報告し、後続の日付の取得を停止します。

## 利用できる情報 {#what-you-get}

- **対応が必要なページ** — **未解決のスキャン問題**があり、**検索流入もある**ページを結び付けた一覧です。問題のあるページの中で表示回数が多い順に、改善機会を示します。まずこれらを修正してください。
- **上位ページ**と**上位クエリ** — 一般的なSearch Analyticsの表です。

Filamentダッシュボードでは、*SEO*ナビゲーショングループの**Search Console**ページとして表示されます。連携を有効にした場合だけ現れます。ヘッドレスでは、`seo-pro:search-console`コマンドと`SeoPro::searchConsole()`から同じ指標を取得できます。

## セットアップ {#setup}

Search Consoleプロパティを読み取れるGoogleの認証情報が必要です。2つの方式に対応しており、サーバーでは**サービスアカウント**が最も簡単です。

### サービスアカウント（推奨） {#service-account-recommended}

1. Google Cloudで**Search Console API**を有効にし、**サービスアカウント**を作成して、JSONキーをダウンロードします。
2. Search Consoleの*設定 → ユーザーと権限*で、サービスアカウントのメールアドレス（`…@….iam.gserviceaccount.com`）をユーザーとして追加します。読み取り専用なら制限付き権限で十分です。
3. パッケージにキーとプロパティを指定します。

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

`SEO_PRO_GSC_SITE_URL`を省略すると、`app.url`からURLプレフィックスプロパティを導出します。

### OAuth（オフラインのリフレッシュトークン） {#oauth-offline-refresh-token}

OAuthクライアントと長期間有効な**リフレッシュトークン**がある場合は、以下のように設定してください。`webmasters.readonly`だけを承認したものを推奨します。更新のたびにこのスコープを要求します。レスポンスが読み取り専用スコープだけであることを明示的に確認できなければ、パッケージは返されたトークンを拒否します。Googleが広い権限を必ず狭めるとは想定しません。

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### トークン用マイグレーションを公開する {#publish-the-token-migration}

暗号化したアクセストークンのキャッシュは`seo_gsc_tokens`テーブルに保存します。一度だけ公開してマイグレーションを実行してください。

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

次に`php artisan seo:doctor`で設定を確認します。Search Consoleが有効か、設定済みかを報告します。ネットワーク通信は行わず、シークレットも表示しません。

## ヘッドレスでの利用 {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## 履歴指標 {#historical-metrics}

上記のパネルとコマンドは、**その時点を基準とする一定期間**のデータを直接読み取ります。保存先はSearch Console自身だけです。過去の任意の期間を照会できる**日単位の履歴**を持つには、同期コマンドを実行してください。日別・クエリ別およびページ別の指標を`seo_gsc_metrics`テーブルに永続保存します。

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **初回は過去のデータを取得**します。期間は`sync.backfill_days`（デフォルト90日）です。Search Consoleは約16か月を保持するため、値を増やせばさらに遡れます。次回以降は**最後に保存した日付から再開**し、末尾の`sync.overlap_days`日分を再取得して、最近のデータが遅れて確定することに対応します。対象期間は常に3日前で終わります。データに遅延があるためです。
- **冪等。** 行は`(date, dimension, key)`をキーに追加・更新するため、安全に再実行できます。ある日の取得が割り当て上限エラーなどで失敗すると、正常に処理を止め、保存した行数を報告します。次回は停止した場所から再開します。
- **利用先。** テーブルが両方の期間を網羅すると、ホワイトラベル[レポート](/ja/pro/reports)のSearch Consoleの**変動項目**は、前回レポートのスナップショットとの差分ではなく、実際の期間比較（今回の期間と、その直前の同じ長さの期間）に切り替わります。より詳しいキーワード分析の基盤にもなります。

保存するのは集計指標だけです。日ごとのクエリ文字列、ページURL、4つの数値（クリック数、表示回数、CTR、掲載順位）を保存します。ユーザー単位やリクエスト単位のデータを取得・保存することはありません。

## データの扱いとセキュリティ {#data-handling-security}

- **読み取り専用スコープを検証。** サービスアカウントのJWTは`webmasters.readonly`だけを要求します。OAuthの更新も同様で、スコープが欠落したレスポンスや、広いスコープを持つレスポンスは拒否します。読み取り権限だけを承認した認証情報を使ってください。パッケージにはSearch Consoleを変更する呼び出しはありません。

- **認証情報は環境に保持。** サービスアカウントのキー、OAuthシークレット、リフレッシュトークンは、AIキーと同じく、呼び出し時に**名前で指定した**環境変数から読み取ります。そのため、`php artisan config:cache`で`bootstrap/cache/config.php`に書き込まれることはありません。設定のキャッシュで`.env`が読み込まれない場合は、プロセスの環境から参照できるようにしてください。
- **保存時にトークンを暗号化。** 認証情報から発行した短期間有効なアクセストークンは、アプリキーで**暗号化**して`seo_gsc_tokens`に保存し、有効期限が近づくまで再利用します。そのため、画面表示のたびにトークン交換を行うことはありません。長期間有効な認証情報はデータベースに保存せず、環境だけに保持します。
- **すべてのリクエストをSSRFから保護。** トークン交換とSearch Analyticsの呼び出しは、共通の`SsrfGuard`を通ります。HTTPSのみを許可し、ホストが公開アドレスに名前解決される必要があります。リダイレクトも無効なため、内部サービスへ転送されることはありません。
- **シークレットをログに記録しません。** アクセストークン、キー、認証ヘッダーをログへ書き込みません。APIエラーには、サニタイズし長さを制限したGoogle自身のエラーメッセージだけを表示します。
- **指標をローカルキャッシュに保存**します。期間は`seo-pro.search_console.cache_ttl`秒（デフォルト30分）で、パネルの描画ごとにAPIを再呼び出ししないようにします。ライブのパネル・コマンドが保存するのは、このキャッシュと暗号化したアクセストークンだけです。指標を永続保存するのは、明示的に使う`seo-pro:gsc-sync`コマンドだけです。`seo_gsc_metrics`に日別・クエリ別・ページ別の集計を保存し、ユーザー単位のデータは保存しません。

## 設定リファレンス {#configuration-reference}

すべてのキーは`config/seo-pro.php` → `search_console`にあります。

| キー | デフォルト | 用途 |
| --- | --- | --- |
| `enabled` | `false` | 全体の有効・無効（`SEO_PRO_GSC_ENABLED`）。 |
| `connection` | `service_account` | `service_account`または`oauth`。 |
| `site_url` | `app.url`から導出 | プロパティ（`https://example.com/`または`sc-domain:example.com`）。 |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | キーのJSONまたはそのパスを持つ環境変数の**名前**。 |
| `oauth.client_id` | — | OAuthクライアントID（シークレットではありません）。 |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | クライアントシークレットを持つ環境変数の**名前**。 |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | リフレッシュトークンを持つ環境変数の**名前**。 |
| `default_days` | `28` | レポート期間。GSCのデータ遅延により3日前で終わります。 |
| `row_limit` | `100` | レポートごとの上位N行（APIの上限は25000）。 |
| `cache_ttl` | `1800` | 取得したレポートをキャッシュする秒数。 |
| `sync.backfill_days` | `90` | テーブルが空の状態で初めて`gsc-sync`を実行するときに取得する日数。 |
| `sync.overlap_days` | `2` | 遅れて確定するデータに対応し、毎回再取得する末尾の日数。 |
| `sync.row_limit` | `5000` | 同期が要求する、日別・ディメンション別の最大行数。 |
