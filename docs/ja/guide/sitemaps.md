---
description: "ソースごとのXMLサイトマップとインデックスを生成し、/sitemap.xmlで配信します。モデル、クロージャ、URLリストからソースを登録でき、生成にはspatie/laravel-sitemapを使います。"
---

# サイトマップレジストリ {#sitemap-registry}

パッケージは、ソースごとのXMLサイトマップとインデックスを生成し、`/sitemap.xml`と`/sitemap-{name}.xml`で配信します。生成処理は[spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)を利用します。

```bash
composer require spatie/laravel-sitemap
```

## ソースを登録する {#registering-sources}

サービスプロバイダーの`boot()`で、名前付きのソースを登録します。

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

各ソースは`sitemap-{name}.xml`に出力され、`sitemap.xml`はそれらを列挙するインデックスになります。

レジストリAPIには、`has($name)`、`names()`、`forget($name)`、`flush()`もあります。

## 設定ファイルでソースを定義する {#config-driven-sources}

設定ファイルを使う場合は、`config/seo.php`にモデルソースと静的URLを指定できます。

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info 自動検出よりレジストリが優先されます
名前付きの登録済みソースがモデルを扱っている場合、自動検出はそのモデルをスキップします。`'posts'`を登録しても、別途`sitemap-post.xml`が生成されることはありません。
:::

## 生成する {#generating}

```bash
php artisan seo:sitemap
```

ファイルは`seo.sitemap.disk`で設定したディスク（デフォルトは`public`）に書き込まれます。サイトマップを最新に保つため、コマンドを定期実行してください。

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

`seo.sitemap.max_urls_per_sitemap`（デフォルトはXML仕様の上限である50,000件）を超えるサイトマップは、自動的に分割されます。

## 配信する {#serving}

パッケージのルートは、コマンドが生成した内容をXMLヘッダー、キャッシュヘッダー、`X-Robots-Tag: noindex`付きで配信します。

- `/sitemap.xml` — インデックス、または単一のサイトマップ
- `/sitemap-posts.xml` — 名前付きソース

独自に静的生成したサイトマップを配信する場合は、ルートを無効にします。

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## ブラウザーでのスタイル付きサイトマップ {#styled-sitemap-in-the-browser}

Spatieのエンジンは生のXMLを出力します。Rankbeamのサイトマップをブラウザーで開くと、ブランドを反映した読みやすいページになります。すべてのURLを表で表示し、`lastmod`、変更頻度、優先度、画像数・代替言語数と、その場で確認できる検証メモを添えます。

![ブラウザーで、Rankbeamのブランドを反映した読みやすい表として表示されたサイトマップ](/sitemap-styled.png)

生成した各サイトマップからXSLスタイルシートを参照する仕組みです。

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

検索エンジンはこの命令を**無視**するため、サイトマップは通常の機械可読XMLのままです。変わるのは*人*に見える表示だけです。インデックスとすべての子サイトマップに、同じスタイルを適用します。

**デフォルトで有効**です。画像・hreflang拡張と異なり、スタイルシートはデータを追加せず、レコードごとの処理もしません。クローラーが読み飛ばす命令を1行加えるだけなので、初期状態から有効です。無効にすると、通常のXMLを出力します。

::: warning spatie/laravel-sitemap ≥ 8.1が必要です
命令の出力には、`spatie/laravel-sitemap` **8.1**で追加されたSpatieの`setStylesheet()`を使います。アプリの依存関係が古いバージョンを選ぶ場合（一部のPHP/Laravelの組み合わせが該当）、サイトマップはスタイルなしの通常のXMLとして生成されます。動作が壊れることはありません。`composer update spatie/laravel-sitemap`で更新すると、スタイル付きの表示を使えます。
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### 検証メモ {#validation-notes}

表示ページは、ブラウザー内だけで確認できる2点を指摘します。

- **`lastmod`がないURL** — 欠落を表示し、値を作り上げることはありません。更新日時を偽るサイトマップはGoogleに評価されにくくなるため、スタイルシートは値を補わず、欠落を知らせます。
- **絶対URLでないURL** — `<loc>`が絶対`http(s)` URLでない場合です。

### スタイルシートを自分でホストする {#self-hosting-the-stylesheet}

デフォルトでは、パッケージ自身の`/sitemap.xsl`ルートからスタイルシートを配信し、各サイトマップから参照します。ブラウザーはサイトマップと**同一オリジン**のXSLTだけを適用します。サイトマップがCDNなど別のオリジンにある場合は、ファイルを公開して、そのコピーを設定で指定してください。

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info 安全性を組み込んだ構成
URLを含め、スタイルシートが出力するすべての値にXSLTの出力エスケープを適用します。また、`<loc>`をクリック可能なリンクにするのは`http(s)` URLの場合だけです。そのため、悪意のあるURLの内容でマークアップや`javascript:`リンクをページへ挿入することはできません。公開済みの`.xsl`をカスタマイズする場合も、この性質を保ってください。`disable-output-escaping`は追加しないでください。
:::

## 含まれるレコード {#what-gets-included}

モデルソースには、インデックス登録可能と解決されたレコードが含まれます。robotsが`noindex`に解決されるモデルは、サイトマップに含まれません。URLは正規URLと同じ`getUrlForSEO()`メソッドから取得するため、サイトマップと正規URLが食い違うことはありません。

## 画像とhreflangの拡張 {#image-hreflang-extensions}

2つの任意の拡張により、各モデルURLに、そのレコードについてパッケージがすでに解決するデータを追加できます。どちらも**デフォルトでは無効**です。必要なものを`config/seo.php`で有効にしてください。

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

`HasSEO`トレイトを使うモデルに適用され、値はモデルの完全に解決済みの`seoData()`から取得します。

- **`images`**は、解決済みのOG画像・コンテンツ画像から[Googleの画像サイトマップ](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)の項目を追加します。`og:image`として出力するものと*同じ*値なので、サイトマップとページは食い違いません。レコード固有の画像がない場合はサイト共通の`default_og_image`になるため、URLごとの画像に意味があるコンテンツの場合だけ有効にしてください。
- **`alternates`**は、モデルの`getSEOAlternates()`から`<xhtml:link rel="alternate" hreflang="…">`の項目を追加します。ページの`<head>`に出力するものと同じhreflangリンクです。絶対URLを返してください。

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflangには相互参照と自己参照が必要です
Googleが注釈を認識するには、すべての言語版が**自身と他のすべての言語版**を列挙し、参照が**相互**になっている必要があります。つまり、各ページが相手を参照し返す必要があります。そのため、`getSEOAlternates()`は**完全な**集合を返し、各言語版も同じ完全な集合を返さなければなりません。有効な`language[-Script][-REGION]`コードまたは`x-default`と、絶対`http(s)` URLを使ってください。空でない`hreflang`または`href`を持たない項目はスキップされます。

リストは出力前に[`seo.hreflang`ポリシー](/ja/guide/multilingual#hreflang)を通ります。コードはBCP 47に正規化され（`it_IT` → `it-IT`）、`include_self` / `x_default`で自己参照と`x-default`を自動追加できます。サイトマップは常に、ページの`<head>`と同じリストを持ちます。無料の監査は`hreflang_invalid_code`、`hreflang_duplicate_code`、`hreflang_missing_self`を報告します。相互参照の確認にはクロール（Pro）が必要です。
:::

::: info 大規模サイトでのコスト
**Core 3.20.1**以降、各モデルURLの生成中は、モデルを含めるかの判定と画像・hreflang拡張で、同じ解決済みの`seoData()`を再利用します。再利用は、失敗した場合も含め、そのURLの処理が終わると終了します。後の生成処理や別のロケールでは、データを新たに解決します。3.20.0では、リゾルバーのキャッシュが無効（デフォルト）の場合、対象判定と拡張によって優先順位の連鎖を2回たどることがありました。各解決処理では引き続きキャッシュやデータベースへの操作が発生する可能性があり、独自の`getSEO*()`ゲッターがクエリを追加する場合もあります。Webリクエストではなく、**定期実行する**`seo:sitemap`コマンドを使ってください。50,000 URLの上限に近い規模でベンチマークを行い、追加項目が不要なら拡張を無効のままにしてください。
:::

::: tip すでに設定ファイルを公開している場合
`config/seo.php`は**浅いマージ**で結合されます。このリリースより前に設定ファイルを公開したアプリには、`sitemap.images` / `sitemap.alternates`キーが自動では追加されません。`SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES`環境変数だけでは有効になりません。公開済みの`sitemap`配列に2つのキーを追加するか（上の例を参照）、設定を再公開してください。
:::

## 完全に制御する：手作業で組み立てるSpatieタグ {#full-control-hand-built-spatie-tags}

画像のキャプション、**動画**、**ニュース**の項目、独自の`hreflang`集合など、解決済みデータで扱えないものには、登録済みソースから完全に手作業で組み立てた[`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images)を返します。ビルダーは`Url`タグをそのまま通し、独自の拡張を追加しないため、出力をすべて制御できます。

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

レコード単位でも同じ方法を使えます。`Sitemapable`を実装し、`toSitemapTag()`が`Url`を返すモデルは、返された内容のまま出力されます。
