---
description: "URLの公開・更新時に検索エンジンへ通知します。Proは共通のapi.indexnow.orgエンドポイントに送信し、参加エンジンへ伝達します。デフォルトでは無効です。"
---

# IndexNow — 公開時にインデックス登録を通知 {#indexnow-—-push-on-publish-indexing}

**IndexNow**を使うと、クローラーが変更ページを見つけるのを待たず、URLを公開・更新した時点で検索エンジンに*知らせる*ことができます。Proは共通の`api.indexnow.org`エンドポイントに送信し、1回の呼び出しで**すべての参加エンジンへ伝達**します。エンジンごとの個別送信はしません。[公式FAQ](https://www.indexnow.org/faq)にはAmazon、Bing、Naver、Seznam、Yandex、Yepが記載されています。通知はインデックス登録を保証しません。

**デフォルトでは無効**です。有効にしてURLを送信するまで、ネットワーク通信は発生しません。

## セットアップ {#setup}

### 1. キーを生成する {#_1-generate-a-key}

IndexNowは**キー**でホストの管理権限を確認します。Proが受け付けるのは`[a-f0-9-]`の8〜128文字です。32文字の16進文字列が適しています。一度生成したキーを維持し、環境変数で参照できるようにしてください。

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip キーは設定経由で読むため、`config:cache`後も使えます
Search Consoleの認証情報と異なり、IndexNowのキーは**シークレットではありません**。ホストの所有を証明するため、`/{key}.txt`で公開します。そのため、Proは設定層（`indexnow.key`。デフォルトは`env('SEO_PRO_INDEXNOW_KEY')`）から解決します。これは意図的です。**`.env`だけに定義した**値は、`config:cache`後にLaravelがそのファイルを読み込まなくなるため、`env()`から取得できません。実際のプロセス環境変数は引き続き利用できます。設定経由で読むと、キーは`config:cache`に取り込まれ、常に利用可能になります。その代わり、**キーを更新したら`php artisan config:cache`を再実行する必要があります**。キーをログに記録することはありません。本番でキーファイルが404になる場合は、[設定をキャッシュしたサーバー](#config-cached-servers)を参照してください。
:::

### 2. キーファイルを配信する {#_2-serve-the-key-file}

IndexNowは、キーだけを含む`https://{host}/{key}.txt`を取得して所有権を確認します。`route`が有効（デフォルト）なら、**Proが配信します**。

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

配信するのは、設定した1つのキーパスだけです。このルートが受けた他のパスは404を返し、IndexNowが無効な場合はこのルート全体が404になります。自分で、またはCDNでファイルをホストする場合は、`route`を無効にし、`key_location`にそのURLを指定してください。

## URLを送信する {#submitting-urls}

### 保存時に自動送信する（公開時の通知） {#automatically-on-save-the-push-on-publish-path}

モデルにトレイトを追加し、`auto_submit`を有効にします。保存のたびに、モデルの`getUrlForSEO()`を送信するジョブをキューに投入します。

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

トレイトは公開条件に従います。`shouldSubmitToIndexNow(): bool`を実装すると、完全に制御できます。なければ`is_published`属性にフォールバックし、それもなければ保存のたびに送信します。送信は常に**キュー経由**なので、モデルの保存がネットワークを待つことはありません。

### 手動で送信する {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()`はデフォルトでキューに投入します。`queue: false`を渡すと、その場で同期実行します。

### コマンドラインから送信する {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning 同じホストだけが対象です
各URLが`http(s)`であり、**かつ**設定した`host`に属することを検証します。それ以外は**除外**し、件数だけ数えて送信しません。送信できるのは自分が管理するURLだけであり、ホストの不一致はエンドポイント側でも拒否されます。`max_urls_per_request`（プロトコル上限の10000件）を超えるリストは、自動的に分割します。
:::

## 設定 {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## 再試行の仕組み {#how-retries-work}

キューの`SubmitToIndexNowJob`は、再試行*すべき*失敗を区別します。`429`（レート制限）、`5xx`、タイムアウトは、`backoff`の間隔で`tries`回を上限に再試行します。`400` / `403` / `422`（無効なキーやホスト不一致などの恒久的なクライアントエラー）は、ログに記録して**停止**し、無駄な再試行をしません。`200`と`202`（受信済み / キー確認待ち）は、どちらも成功として扱います。

本番では**専用キュー**を使い、エンドポイントの応答が遅くても利用者向けの処理を遅らせないようにしてください。

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## トラブルシューティング {#troubleshooting}

### 設定をキャッシュしたサーバー {#config-cached-servers}

`indexnow.enabled`が明らかに`true`なのに、本番で`/{key}.txt`が404になったり、送信しても何も起きなかったりする場合、原因の多くは、`php artisan config:cache`を実行したサーバーでキーが**`.env`にしかない**ことです。設定をキャッシュするとLaravelは`.env`を解析しないため、`env('SEO_PRO_INDEXNOW_KEY')`は`null`を返します。キーファイルのルートが登録されず、すべての送信が「未設定」として拒否されます。

デフォルト設定は`indexnow.key`を`env(...)`から解決するため、通常はキャッシュ構築時に取り込まれて動作します。問題になるのは、**設定を公開した後で`env(...)`のデフォルトを削除した**場合、または**`.env`だけに存在する独自の`key_env`名**を使った場合です。修正方法は2つあります。

1. **設定にキーを保持する**（推奨）— `indexnow.key`を`env('SEO_PRO_INDEXNOW_KEY')`のままにするか、値を直接設定して、`php artisan config:cache`を再実行します。後でキーを更新するときも、キャッシュを作り直します。
2. **実際の環境変数を渡す** — `SEO_PRO_INDEXNOW_KEY`を、**`.env`だけでなく**OS・プロセスの環境変数として設定します。PHP-FPMプールの`env[...]`、systemdの`Environment=`、プラットフォームの環境変数設定などを使えます。OSの環境変数は、設定をキャッシュした後も読み取れます。

`php artisan seo:doctor`で確認してください。この状態を検出すると、**「IndexNowは有効ですが、有効なキーを解決できません」**という内容と具体的な対処方法を表示します。また、設定をキャッシュした状態で起動し、キーを読めない場合は、Proがプロセスごとに1回だけ警告をログに記録します。

::: tip Google
GoogleはIndexNowに参加して**いません**。Google向けには、[Search Console](/ja/pro/search-console)連携と最新のサイトマップを使ってください。
:::
