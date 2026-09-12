---
description: "他のLaravel SEOパッケージからRankbeamへ移行するため、APIと保存方式をHasSEOトレイトとsaveSEO()に対応付けます。モデルごとのSEOデータには、1コマンドのインポーターを用意しています。"
---

# 他のLaravel SEOパッケージからの移行 {#migrating-from-other-laravel-seo-packages}

すでに別のSEOパッケージを使っている場合も、Rankbeamへの切り替えは全面的な書き直しではなく、1日で進められる作業を目指しています。このガイドでは、よく使われるパッケージのAPIと保存方式を、Rankbeamの2つの基本機能、[`HasSEO`](/ja/guide/quickstart)トレイトと`saveSEO()`に対応付けます。モデル単位でSEOデータを保存するパッケージ向けには、1コマンドで使えるインポーターも用意しています。

::: tip WordPressから移行する場合
コンテンツサイトをWordPress（YoastまたはRank Math）から移行する場合は、専用の[**WordPressからの移行**](/ja/guide/migrate-from-wordpress)ガイドを参照してください。CSVインポーターと稼働中のデータベースを読み取る方法を説明しています。
:::

| 移行元 | データの保存先 | 移行方法 |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | `seo`のポリモーフィックテーブル | **`php artisan seo:import-from ralphjsmit`**とトレイトの置き換え |
| [`artesaos/seotools`](#from-artesaos-seotools) | なし（実行時の値と設定） | コードを置き換え、`saveSEO()`または算出用ゲッターで値を設定 |
| [`spatie/*`](#from-spatie-packages) | なし（schema-org / サイトマップのビルダー） | 補完する機能は残し、それ以外をRankbeamへ移す |

この中でSEOデータをデータベースのテーブルに永続化するのは**ralphjsmit**だけなので、一括インポートするデータがあるのもこれだけです。他は実行時のタグビルダーであり、読み取るテーブルはありません。リクエストごとの呼び出しを、保存済みの`seo_meta`に置き換えます。

---

## `ralphjsmit/laravel-seo`からの移行 {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo`は、モデルごとにポリモーフィックな行を1つ、`seo`テーブルに保存します。その構造はRankbeamの`seo_meta`に近く、整合性を保った冪等な一括インポートが可能です。

### 1. Rankbeamを併せてインストール {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

移行中は両パッケージを併用できます。テーブル（`seo`と`seo_meta`）もトレイトの名前空間も異なります。

::: warning 設定ファイルは共用です
`ralphjsmit/laravel-seo`から公開した`config/seo.php`がアプリに残っていると、Rankbeamの設定より優先されます。両方が`seo`設定キーを使うためです。バックアップして削除し、Rankbeamの設定を再公開してください：`php artisan vendor:publish
--tag=seo-config`。
:::

### 2. インポーターを実行 {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

インポーターはralphjsmitの`seo`テーブルを読み取り、各行に対応する実際のEloquentモデルを解決して、`seo_meta`へ書き込みます。

| オプション | 動作 |
|---|---|
| `--dry-run` | インポート予定の内容を報告し、何も書き込みません。 |
| `--model="App\Models\Post"` | 1つ以上のモデルクラスに限定します。繰り返し指定できます。 |
| `--locale=fr` | 指定ロケールの行として書き込みます。デフォルトはアプリのロケールです。 |
| `--table=legacy_seo` | 名前を変更した移行元テーブルを読み取ります。 |
| `--connection=legacy` | 別のデータベース接続から移行元テーブルを読み取ります。 |
| `--limit=100` | 最大N行をインポートします。段階的な移行に使えます。 |
| `--overwrite` | 既存の空でない値を置き換えます。デフォルトでは空のフィールドだけを埋めます。 |
| `--json` | 機械可読のレポートを出力します。 |
| `--force` | 確認プロンプトを省きます。スクリプトやCI向けです。 |

処理は**冪等**です。再実行しても同じ行を更新し、重複を作りません。また、デフォルトでは空のフィールドを*埋めるだけ*で、Rankbeamに設定済みのSEOデータを上書きしません。既存値をインポート値で置き換える場合は、`--overwrite`を指定してください。

### 3. モデルのトレイトを置き換え {#_3-swap-the-trait-on-your-models}

ralphjsmitのトレイトをRankbeamのものに置き換えます。メソッド名は少し異なり、トレイトが読み取るテーブルは`seo_meta`になります。

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

ralphjsmitの`getDynamicSEOData()`でSEOデータをカスタマイズしていた場合は、そのロジックをRankbeamのフィールド別の算出用ゲッター（`getSEOTitle()`、`getSEODescription()`、`getSEOImage()`、`getUrlForSEO()`、`getSEOAlternates()`）へ移します。[クイックスタート](/ja/guide/quickstart)を参照してください。保存する上書き値には`saveSEO()`を使います。

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### フィールドの対応 {#field-mapping}

インポーターはフィールドを**明示的に**対応付けます。Core 3のスキーマに存在しないカラムを、無条件にコピーすることはありません。

| ralphjsmitの`seo` | Rankbeamの`seo_meta` | 補足 |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | そのままコピーせず、現在のモデルから**再解決**します。下記を参照してください。 |
| `title` | `title` | 70文字（`seo_meta`カラムの長さ）に切り詰めます。超過した値は報告します。 |
| `description` | `description` | 160文字に切り詰めます。超過した値は報告します。 |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | 50文字に切り詰めます。 |
| `image` | `og_image` | `twitter:image`はリゾルバーを通して自動で引き継ぎます。 |
| `author` | *インポート対象外* | Core 3の`seo_meta`には著者カラムがありません。記事の著者は、保存するソーシャルメタデータではなくリゾルバー層で扱います。著者を持つ行を**集計して報告**するため、移行先（たとえば`getSEOData`のような算出値）を判断できます。 |
| `id`、`created_at`、`updated_at` | *インポート対象外* | 構造上のフィールドです。 |

**morph型を再解決する理由。** 各移行元行から実際のモデルを解決し、モデル自身の`getMorphClass()`から`seoable`キーを取得します。ralphjsmitが別の規則で保存していても、アプリの*現在の*[morphマップ](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)に従ってリレーションを維持できます。また、すでにモデルが削除された行はスキップして報告し、参照先のない行として書き込むことはありません。

### レポートの内容 {#what-the-report-tells-you}

`--json`を使わない実行では、結果の表と、確認用の3つのセクションを表示します。

- **Truncated（切り詰め）** — `seo_meta`カラムに収めるため短くした値です。確認してください。
- **Not imported（未インポート）** — 値はあったものの、Core 3に保存先がない移行元カラムです。`author`などが該当します。
- **Skipped rows by reason（理由別のスキップ行）** — 空の移行元行、削除済みモデル、解決できないモデル型です。

### 検証 {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

結果に問題がないことを確認したら、`ralphjsmit/laravel-seo`と、その`seo`テーブルを削除します。

---

## `artesaos/seotools`からの移行 {#from-artesaos-seotools}

`artesaos/seotools`は**実行時**のタグビルダーです。`config/seotools.php`のデフォルトを基に、通常はコントローラー内で、`SEOMeta`、`OpenGraph`、`TwitterCard`、`JsonLd`ファサードを通してリクエストごとに値を設定します。モデルごとの保存はないため、インポートするテーブルもありません。リクエストごとの呼び出しを、保存値または算出値へ移します。

| artesaos/seotoolsの呼び出し | Rankbeamでの対応 |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])`または`getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])`または`getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])`または`getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | 対応するkeywordsメタタグはありません。フォーカスキーワードは内部の編集チェック用です。`saveSEO(['focus_keywords' => [...]])`（[監査](/ja/guide/audit)を参照） |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [JSON-LDスキーマグラフ](/ja/guide/schema) |
| `config/seotools.php`のデフォルト | `config/seo.php`のサイトデフォルトと[リゾルバーの優先順位](/ja/concepts/resolver-precedence) |
| レイアウトの`{!! SEO::generate() !!}` | `@seo($model)`（[Blade](/ja/guide/blade)を参照） |

考え方が変わります。各コントローラーでタグを逐次設定する代わりに、SEOデータをモデルごとに`seo_meta`へ一度保存し、Rankbeamのリゾルバーが出力します。`config/seotools.php`にあったサイト全体のフォールバックは、Rankbeamの[設定デフォルト](/ja/reference/configuration)へ移します。ルートごとの静的ページには`@seoForRoute()`を使います。

---

## Spatieパッケージからの移行 {#from-spatie-packages}

メタデータを保存する`spatie/laravel-seo`パッケージはないため、インポートするものはありません。SEOと組み合わせて使われるSpatieパッケージは**補完的なビルダー**であり、部分的に残したり置き換えたりできます。

- **`spatie/schema-org`** — メソッドチェーンで使うJSON-LDビルダーです。Rankbeamにも、型付きの`Article`、`FAQPage`、`Product`、`BreadcrumbList`、`LocalBusiness`、`Organization`ビルダーを持つ[スキーマグラフ](/ja/guide/schema)があります。`seo_meta.schema_jsonld`に保存し、重複排除して出力します。手作業で組み立てた`spatie/schema-org`オブジェクトは、その`->toArray()`出力を`saveSEO(['schema_jsonld' => $array])`に渡すか、Rankbeamのビルダーで書き直せます。
- **`spatie/laravel-sitemap`** — サイトマップジェネレーターです。Rankbeamの[サイトマップレジストリ](/ja/guide/sitemaps)はこれを基にしています。モデルをソースとして登録し、Rankbeamに統合サイトマップを出力させることも、既存のSpatieサイトマップを残してRankbeamのルートを無効にすることもできます。

別の実行時・構造体ベースのメタデータビルダー、[`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO)を使っていた場合も、artesaosと同じ方法です。リクエストごとの`setTitle`/`addMeta`呼び出しを、`saveSEO()`または算出用ゲッターに移します。

---

## インポーターの拡張 {#extending-the-importer}

`seo:import-from`コマンドの基盤は、`Rankbeam\Seo\Importing\Contracts\Importer`実装の小さなレジストリです。コマンドを変更せず、新しいソースを追加できます。組み込みソースは現在、`ralphjsmit`とWordPressインポーター（`wordpress-csv`、`yoast`、`rank-math`。[WordPressからの移行](/ja/guide/migrate-from-wordpress)を参照）です。独自のソースはサービスプロバイダーに登録します。

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```

