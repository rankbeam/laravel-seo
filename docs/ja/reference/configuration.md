---
description: config/seo.phpの全オプションをリゾルバーの層ごとに整理し、各値の出荷時デフォルトとともに説明します。
---

# 設定 {#configuration}

設定ファイルを公開します。

```bash
php artisan vendor:publish --tag=seo-config
```

以下の設定はすべて`config/seo.php`にあります。示している値はデフォルトです。

## サイト全体のデフォルト（第1層） {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

解決済みタイトルの末尾がまだ`title_suffix`でない場合、その値を付加します。

`title_suffix_skip_when_contains`は、ブランド名を考慮して接尾辞を抑制する一覧です。解決済みタイトルに、この一覧のトークンのいずれかが**単語全体として**すでに含まれる場合、ブランド名の重複を防ぐため接尾辞を省略します。大文字・小文字を区別せず、単語境界を認識するため、`Acmestic`は`Acme`には一致しません。デフォルトの`[]`は従来の挙動を維持します。

## robotsの出力ポリシー {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

解決済みの指示が上記の`default_robots`と等しい場合、描画した`<head>`では`<meta name="robots">`タグを省略します。冗長な`index,follow`は不要で、タグがない状態をクローラーはindex,followとして扱うためです。**異なる**指示（`noindex`、`nofollow`、`max-snippet:-1`など）は常にそのまま出力します。`emit_default`を`true`にすると、常にタグを出力し、3.1より前の挙動に戻します。個別の`@seoRobots`指示には影響しません。これは明示的に選ぶ設定で、常に出力します。対応する指示と優先順位は[出力の共通仕様](/ja/contributing/rendering-contract)を参照してください。

## インデックス登録保護（本番以外の安全策） {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

有効にした状態で、アプリが`allowed_environments`に**含まれない**環境で動くと、全ページに`noindex,nofollow`を強制します。優先順位の連鎖全体より上に適用し、保存済みのページ別値も上書きします。対応する`X-Robots-Tag`ヘッダーを送り、すべてを拒否する`robots.txt`を出力し、`seo:audit`にバナーを表示します。許可された環境（デフォルトは`production`）では何もしません。

出荷時は**無効**で、有効化するまで出力バイト列は変わりません。`SEO_INDEXING_GUARD=true`で有効化し、`SEO_INDEXING_GUARD=false`で無効化できます。どちらも1行です。許可リストは`SEO_INDEXING_GUARD_ALLOWED`で上書きできます。カンマ区切りで、`prod*`のような`Str::is()`ワイルドカードも使えます。空の一覧ならすべての環境を保護します。

`send_header`は保護機能内でデフォルト有効です。アプリを経由するすべてのレスポンスに`X-Robots-Tag: noindex,nofollow`を送り、`<meta robots>`を持たないPDF、フィード、画像も登録対象から外すよう指示します。ミドルウェアを登録するのは保護機能が有効な場合だけです。強く推奨します。詳しくは[インデックス登録保護ガイド](/ja/guide/indexing-guard)を参照してください。

## 正規URL {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

リゾルバーがリクエストURLやモデルの`getUrlForSEO()`から**導出する**正規URLは、デフォルトでクエリ文字列を削除します。追跡・絞り込み・並べ替えパラメーターによって、同じページに重複コンテンツの正規URL候補が生じるためです。`query_whitelist`にキーを列挙すると、それらは指定順で導出した正規URLに**保持**され、ほかのパラメーターは引き続き削除します。よくある用途はページ分割した一覧の`page`です。`/blog?page=2`は実際に`/blog`とは異なるページです。

管理画面や優先順位の高い層で**明示的に設定した**正規URLは、クエリ文字列も含めて常にそのまま出力します。この許可リストは導出したフォールバックだけを制御します。デフォルトの`[]`は、すべてを削除する挙動を維持します。

## 機能の切り替え {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta`は、`HasSEO`モデルの作成時に空の`seo_meta`行を作ります。ただし`WithoutModelEvents`を使うシーダーでは、この処理を経由しません。

## フォーカスキーワード {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

フォーカスキーワードの**ワークフローの有効化条件**です。`false`（デフォルト）の間は、フォーカスキーワードがないページをどこでも指摘しません。[`seo:audit`](/ja/guide/audit)もProスキャンも警告しないため、この機能を使わないアプリが不要な注意を受けることはありません。[Filamentのフォーカスキーワードフィールド](/ja/guide/filament)などで設定を始めたら有効にしてください。無料監査、Proスキャン、Proエディターが、未設定のページに`missing_focus_keyword`の通知を出すようになります。すべて同じフラグを読むため、判断は常に一致します。

## 無料監査（`seo:audit`） {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

`--model`オプションを指定しない場合に、無料の[`seo:audit`](/ja/guide/audit)コマンドが監査するモデルです。各モデルは`HasSEO`トレイトを使う必要があります。空なら、`sitemap.models`に登録したモデルにフォールバックします。

## 計算によるフォールバック（第5層） {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

任意で選ぶ`best`方式では、ビルダーが順序付き候補を画像のピクセル寸法と理想寸法の近さで評価し、**最小寸法に満たないものを除外**します。候補は最優先を維持する`getSEOImage()`、モデルの`getSEOImages()`フック、一般的な画像フィールド、本文の最初の画像、設定したデフォルトの順です。測定するのは**ローカル画像だけ**で、`public/`配下の相対パス、publicディスク、自サイトのホスト上の絶対URLが対象です。リモートURLは取得せず、フォールバックとしてのみ使います。最小寸法を満たすローカル候補がなければ先頭一致に戻るため、`first`で画像を選べる場合に`best`だけが画像を返さなくなることはありません。モデルから候補を公開します。

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## サイトマップ {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

プログラムからソースを追加する方法は[サイトマップレジストリのガイド](/ja/guide/sitemaps)を参照してください。

## スキーマ（JSON-LD） {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

これらは[スキーマグラフ](/ja/guide/schema)のノードに使います。

## ルート {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

アプリが独自の静的な`/sitemap.xml`を配信する場合は、`enabled => false`を設定します。

## キャッシュ {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### リゾルバーの結果キャッシュ {#resolver-result-cache}

`SEOResolver`はフロントエンド描画の**たびに**優先順位の連鎖全体を処理します。設定 → 全体・モデル種別・ルートのデフォルト → モデルの計算値 → 明示的な`seo_meta` → タイトル接尾辞・正規URL・スキーマの順です。トラフィックの多いサイト（事例アプリは約20kリクエスト/日）では、ページごとに複数回DBを読みます。

`cache.resolver.enabled`を有効にすると、モデルの完全に解決済みのSEOを保存し、**キャッシュヒット時は優先順位の連鎖全体を省略**します。パッケージのベンチマークでは、キャッシュなしの各解決がモデルの`seo_meta`を再読込する一方、保存済みキャッシュへのヒットはDBクエリ**ゼロ**です。ペイロードは単純な配列として保存し、`SEOData::fromArray()`で復元します。オブジェクトは保存しません。Laravel 13は`cache.serializable_classes = false`を使うため、キャッシュしたオブジェクトは`__PHP_Incomplete_Class`として返るからです。

上記の`store`を使うため、本番では**共有の永続キャッシュ**（`redis` / `memcached`）を指定してください。キャッシュと無効化の両方がすべてのWeb・キューワーカーから見える必要があります。それを用意するまでは無効のままにしてください。

**無効化は自動で行われ、有効・無効で解決結果は同じです。** エントリは`(model class, id, locale, route, request URL)`をキーとし、次の場合に削除します。

- ページの`seo_meta`行を**保存または削除**した場合。`saveSEO()`、Filament、直接の`SEOMeta`書き込みなど、どの経路も対象です。
- モデルの**コンテンツフィールド**が変わった場合。対象カラムは`getSEOContentFields()`です。デフォルトには標準の計算フォールバック用フィールド全体が含まれます。タイトル・見出し、excerpt/summary/content/body/text/article、および`featured_image`、`thumbnail`、`cover_image`、`og_image`、`photo`、`banner`、`hero_image`などの一般的な画像フィールドです。追加カラムからSEOを計算するモデルでは、この設定を上書きしてください。
- **いずれかの`seo_defaults`行**が変わった場合。デフォルトはどのモデルにも影響し得るため、解決結果キャッシュ全体を消去します。

**タグ対応**ストア（`redis`、`memcached`、`array`）では、キャッシュの**タグ**でモデルのエントリを削除します。**タグ非対応**ストア（`file`、`database`）では、モデルごとの**バージョンスタンプ**にフォールバックします。どちらもキーの走査は不要です。

::: tip
キャッシュするのはモデルに基づく解決だけです。手動で作った`SEOData`の`SEO::render()`/`@seo()`や、モデルを持たないルートの`@seoForRoute()`は、その都度解決します。
:::

::: warning
キャッシュは最後の**コンテンツフィールド**変更時点のモデルの`updated_at`・計算済み`modified_time`を反映します（またはTTLが切れるまで保持します）。`getSEOContentFields()`カラムを変えず、`updated_at`だけを進める単なる`touch()`では再解決を強制しません。`article:modified_time`は最大TTL分遅れる可能性があります。アプリ固有の計算カラムをすぐに無効化したい場合は、`getSEOContentFields()`へ追加してください。
:::
