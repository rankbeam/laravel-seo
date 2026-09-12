---
description: "各ページ専用の1200×630のOpen Graph画像を生成します。ヘッドレスブラウザーでBladeテンプレートを描画し、タイトルを適切に折り返し、長すぎる場合は省略します。無料のコア機能で、デフォルトは無効です。"
---

# OG画像の生成 {#generated-og-images}

コア3.20以降、Chromeによる描画ではJavaScriptを無効にし、HTTP(S)、FTP、WebSocketによるアセットへのリクエストを遮断します。独自テンプレートも、同梱テンプレートと同様に、静的なHTML/CSSと埋め込みアセットを使う必要があります。

ソーシャルカードがないページでは、共有の`default_og_image`にフォールバックし、どのページを共有しても同じ画像になります。この機能では、各ページに**専用の**1200×630のOpen Graph / Twitterカードを用意します。[spatie/browsershot](https://github.com/spatie/browsershot)を介して実際のヘッドレスブラウザーでBladeテンプレートを描画するため、タイトルの複数行への折り返し、アクセント付き文字の表示、CJK文字に適したフォントへのフォールバック、長すぎるタイトルの省略に対応できます。独自の画像ライブラリだけでは、これらすべてを適切に処理するのは困難です。

無料のコア機能で、**デフォルトは無効**です。無効な場合は`default_og_image`をそのまま使い、パッケージに追加の依存関係は生じません。

::: info 設計上、静的な事前生成を採用
カードはWebリクエスト中にその場で描画せず、artisanコマンドで事前生成します。ページがリンクするのはディスク上に存在するカードだけです。訪問者のリクエストでブラウザーを起動したり、存在しない画像（404）にリンクしたりすることはありません。**リアルタイム描画用のエンドポイントはありません**（[注意事項](#caveats)を参照）。
:::

## 動作要件 {#requirements}

ブラウザードライバーは任意の依存関係なので、無料コアはそれなしでインストールできます。この機能を有効にする場合、アプリケーションに次が必要です。

```bash
composer require spatie/browsershot
```

さらに、Browsershotが使う実行環境も必要です。

- ホスト上の**Node.js**。
- Nodeが解決できるように、**アプリケーションのルート**にインストールした**Puppeteer**。
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**。Puppeteerはデフォルトで専用のChromiumをダウンロードします。本番環境では通常、システムのChromeを指定します（[`chrome_path`](#configuration)を参照）。

::: warning Windowsではpuppeteerをアプリケーションのルートにインストール
Windowsでは`npm_module_path`に頼らず、`puppeteer`をアプリケーションのルートにインストールしてください。この設定キーはBrowsershotの`setNodeModulePath()`に対応し、POSIX形式の`NODE_PATH=…`プレフィックスを出力するため、**Windowsでは効果がありません**。WindowsのNodeはアプリケーションから上位ディレクトリをたどってモジュールを解決するため、ルートへのインストールが有効です。[注意事項](#caveats)も参照してください。
:::

## 有効化 {#enabling}

まだ設定を公開していなければ公開し（`php artisan vendor:publish --tag=seo-config`）、機能を有効にします。

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

次にカードを**事前生成**します。これを行うまで画像は描画されません。

```bash
php artisan seo:og-images
```

## 値の解決方法 {#how-resolution-works}

生成画像が明示的に設定した画像を上書きすることはありません。有効時、リゾルバーは**ページ固有の画像がない場合にのみ**`og:image`を補います。つまり、解決した`og:image`が空か、サイト共通の静的な`default_og_image`のままである場合です。モデルに明示した画像（`getSEOImage()`、`seo_meta`の行、コンテンツフィールドなど）は、生成カードより常に優先されます。

値を決定するため、リゾルバーは生成サービスの**存在確認を条件とする**検索を呼び出します。カードの保存パスを計算し、**設定したディスクにファイルがすでに存在する場合にのみ**公開URLを返します。描画は行いません。安全性は次の仕組みに基づきます。

- Webリクエストで**ブラウザーを起動することはありません**。最悪の場合でも、この機能を導入する前と同じ静的な`default_og_image`にリンクします。
- ページが**未生成の画像にリンクすることはありません**。共有先の画像が一時的に404になる期間は生じません。

「コンテンツが変わった」時点から「カードが存在する」時点までの差は、デプロイ時や定期実行で[`seo:og-images`](#the-seo-og-images-command)コマンドを実行して埋めます。

## `seo:og-images`コマンド {#the-seo-og-images-command}

リゾルバーが配信できるように、カードを事前生成します。

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`：事前生成するモデルクラスを1つ以上指定します。繰り返し指定できます。省略すると`seo.og_image.models`を使い、未設定なら[サイトマップのモデル](/ja/guide/sitemaps)（`seo.sitemap.models`）にフォールバックします。`seo:llms-txt`と同じく、サイトマップのソースを共用する方針です。
- `--force`：すでに存在するカードも再描画します。`cache_version`を上げずにテンプレートやブランドカラーを変更した場合に使います。
- `--prune`：事前生成後、設定パス内の保存済みカードのうち、現在のどのモデルのコンテンツにも一致しないものを削除します（後述）。安全のため、ファイル名が生成されたコンテンツハッシュであるファイルだけを削除し、同じディレクトリの他のアセットは削除しません。**`--model`で対象を絞った実行では無視されます**。残すべきファイルの集合に他のモデルが含まれないためです。`--model`なしで実行してください。

各モデルは`HasSEO`トレイトを使う必要があります。タイトルのないレコードはカードに表示する内容がないためスキップします。コマンドは`generated`、`skipped`、`failed`、および`--prune`指定時の`pruned`の件数を報告します。

### 定期実行 {#scheduling}

カードをコンテンツに追従させ、タイトル変更で不要になったカードを削除するため、定期的に事前生成します。

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### 無効化の仕組み {#the-invalidation-model}

カードのファイル名は、**描画結果のピクセルに影響するすべての値のハッシュ**です。タイトル、サイト名、テンプレート名、ドライバー、寸法、ブランドのグラデーション色、`cache_version`の値、**インストール済みパッケージのバージョン**が含まれます。

このハッシュがキャッシュキーとなるため、次の2つの挙動を理解しておく必要があります。

- **タイトルを変更 → 新しいハッシュ → 新しいファイル。** 古いカードはディスク上で*参照されないファイル*になり、再生成するまでページは静的なデフォルト画像にフォールバックします。コマンドを実行すると新しいカードを生成し、`--prune`で不要なカードを削除します。これが無効化の仕組みであり、別途「1ページ分のキャッシュを破棄する」手順はありません。
- **`cache_version`を上げる、またはパッケージを更新 → すべてのハッシュが変化。** テンプレートやブランドカラーを編集した後は`cache_version`を使ってすべてのカードを一度に無効化できます。パッケージの更新は自動的にハッシュへ反映されるため、同梱テンプレートを変更するリリースの後に古いカードが配信されることはありません。

## 同梱テンプレート {#bundled-templates}

同じブランドグラデーションを使う1200×630のテンプレートを3種類同梱しています。

| テンプレート | 適した用途 | 表示内容 |
| --- | --- | --- |
| `seo::og.default` | 全般 | タイトル + サイト名 |
| `seo::og.article` | ブログ記事、ニュース | セクション名の小見出し + タイトル + 著者・日付 |
| `seo::og.product` | 商品、掲載情報 | ブランド表記 + カテゴリチップ + タイトル + ディスクリプション |

`seo.og_image.template`で全体のテンプレートを選択するか、**モデル種別ごと**にテンプレートを対応付けることで、記事と商品に異なるカードを自動的に使えます。

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

モデルに`getOgImageTemplate(): ?string`を定義すれば、実行時に自身のテンプレートを上書きできます。ビュー名を返すか、対応表またはデフォルトにフォールバックする場合は`null`を返します。優先順位はモデルのフック、`templates`の対応表、全体の`template`です。

## テンプレートのカスタマイズ {#customizing-the-template}

カードはBladeビュー（デフォルトは`seo::og.default`）を自己完結したHTML文書に描画したものです。同梱フォントはdata URIとして埋め込まれるため、ブラウザーにネットワーク接続は不要です。変更方法は2つあります。

**同梱ビューを公開して編集する場合：**

```bash
php artisan vendor:publish --tag=seo-views
```

その後、`resources/views/vendor/seo/og/default.blade.php`を編集します。

**独自のビューを指定する場合：**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

テンプレートには次の変数が渡されます。

| 変数 | 型 | 補足 |
| --- | --- | --- |
| `$title` | `string` | 設定済みのOGタイトル。なければページタイトル。 |
| `$siteName` | `?string` | 解決済みの`og:site_name`。 |
| `$fontDataUri` | `string` | 同梱の太字フォントを`data:` URIにしたもの。利用できなければ空文字列となり、ブラウザー自身のsans-serifを使います。 |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`。 |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`。 |
| `$width` | `int` | 出力幅（デフォルト`1200`）。 |
| `$height` | `int` | 出力高（デフォルト`630`）。 |
| `$locale` | `?string` | `<html lang>`属性に使う、解決済みのページロケール。 |
| `$author` | `?string` | 記事の著者（`seo::og.article`で使用）。 |
| `$publishedDate` | `?string` | `seo::og.article`の公開日。利用できる場合はページロケールのICU medium形式を使い、それ以外はCarbonで月名を翻訳し、`M j, Y`の順序で表示します。日付がなければnullです。 |
| `$section` | `?string` | コンテンツのセクションまたはカテゴリ（記事の小見出し、商品のチップ）。 |
| `$description` | `?string` | OGディスクリプション。なければページのディスクリプション（`seo::og.product`で使用）。 |

::: info テンプレート名もキャッシュキーの一部
テンプレートの**名前**とグラデーション色はどちらもコンテンツハッシュに含まれるため、テンプレートの切り替えや色の変更で既存のカードは自動的に無効になります。同じテンプレートを*その場で編集*しても名前が変わらないため無効化されません。編集後は`cache_version`を上げるか、`--force`を実行してください。
:::

## 設定 {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

ほとんどのスカラー値には対応する環境変数（`SEO_OG_IMAGE_ENABLED`、`SEO_OG_IMAGE_DISK`、`SEO_OG_IMAGE_CHROME_PATH`、`SEO_OG_IMAGE_NO_SANDBOX`など）があります。全一覧は設定ファイルを参照してください。配列型のキー（`templates`、`models`、`browsershot_args`、`font_stack`）は設定ファイルで直接編集します。

リゾルバーがディスクの`url()`を`og:image`の値として使うため、ディスクは**公開配信されている必要があります**。`public`ディスクでは、`public/storage`から参照できるよう、`php artisan storage:link`を一度実行します。

## Linuxでの実行（サンドボックス） {#running-on-linux-the-sandbox}

Chromeのサンドボックス機構を制限するホストでは、`php artisan seo:og-images`が次のエラーで失敗することがあります。

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

原因の1つとして、Ubuntu 23.10以降のユーザー名前空間の制限が考えられます。[Puppeteerのトラブルシューティングガイド](https://pptr.dev/troubleshooting)と、実際のブラウザー起動エラーを確認してください。Chromeのサンドボックスを維持できるよう、ホストの設定を修正する方法を優先します。

**1. 明示的な代替策：`--no-sandbox`でChromeを実行。** ブラウザーの隔離が無効になります。デプロイ環境でこのトレードオフを意図的に受け入れる場合にのみ使ってください。

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeamは静的に生成したHTMLを描画し、リモートアセットへのリクエストを遮断しますが、これらの制御はChromeのサンドボックスの代わりにはなりません。描画プロセスは非特権で実行し、関係のないワークロードや秘密情報から隔離してください。

**2. サンドボックスを維持。** `no_sandbox`は無効のままにします。AppArmorが原因の場合は、実際のChrome実行ファイルに合わせたプロファイルを設定します。[Chromiumのガイド](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md)を参照してください。例：

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

その後、`sudo apparmor_parser -r /etc/apparmor.d/chrome-og`でプロファイルを読み込み、サンドボックスを有効にした状態でChromeが起動することを確認します。

::: tip その他のフラグ
共有メモリが少ないコンテナでは、Linuxでよくある別の問題として描画中にChromeがクラッシュします。その場合は`browsershot_args`でフラグを追加します。

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## 独自ドライバー {#custom-drivers}

同梱ドライバーは`browsershot`だけですが、レンダラーはインターフェース（`Rankbeam\Seo\Contracts\OgImageRenderer`）の背後にあります。たとえばcanvasやサービスを利用する独自レンダラーを登録し、`seo.og_image.driver`で選択できます。

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

ドライバーの役割は、自己完結したHTML文字列を指定寸法のPNGバイト列に変換することだけです。レイアウトやテンプレート処理は担当しません。

## フォントと非ラテン文字 {#fonts-and-non-latin-scripts}

同梱のカード用フォント（Noto Sans Bold、OFL）は、**ラテン文字、キリル文字、ギリシャ文字**をカバーします。それ以外の中国語、日本語、韓国語、タイ語、アラビア語、ヘブライ語、デーヴァナーガリー文字、絵文字は、**`seo:og-images`を実行するマシンにインストールしたフォント**を使います。他のフォントを同梱しないのは意図的です。CJKフォントは1つで16 MB以上になり、適切なフォントがホストにあればChromeの文字単位のフォールバックが機能します。

この動作を支える仕組みは3つあります（3.15）。

1. **すべての同梱テンプレートに文字体系別の`font-family`スタックを設定。** bodyでは最初に同梱フォントの`'OGBrand'`、次に`seo.og_image.font_stack`、最後に`sans-serif`を宣言します。デフォルトの中間スタックは`Noto Sans`、4つの`Noto Sans CJK`ファミリー、`Noto Sans Thai`、`Noto Sans Arabic`、`Noto Sans Hebrew`、`Noto Sans Devanagari`、`Noto Color Emoji`です。Chromeは文字ごとに、インストール済みの最初のファミリーへフォールバックし、ないものは飛ばします。同じ漢字コードポイントでも各地域のフォントで字形が異なるため（漢字統合）、**ページの言語に対応するCJKファミリーを先頭に移します**（`ja` → JP、`zh-Hans` → SC、`zh-Hant` / `zh-TW` / `zh-HK` → TC、`ko` → KR）。`<html lang>`属性にはBCP 47形式のページロケールを設定します。スタックもキャッシュキーに含まれるため、変更するとすべてのカードが再描画の対象になります。

2. **`seo:og-images`による事前確認。** 描画前にfontconfig（`fc-list :lang=ja`、`th`、`ar`など）を使い、タイトル、サイト名、ディスクリプションに含まれる文字体系をフォントがカバーしているか確認します。混在テキストに少数含まれる文字体系も対象です。足りない場合は**文字体系ごとに1回**、インストールするパッケージとともに警告します。

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   fontconfigがない環境（Windows、macOS、最小構成のコンテナ）では推測せず、警告を出しません。フォントがなくても描画自体は失敗せず、Chromeは.notdefの四角を表示します。そのため、この警告が必要です。

3. **実描画スモークテストの文字体系別グリフフィクスチャ。** `SEO_OG_IMAGE_LIVE_TEST=1`を指定すると、`tests/Feature/OgImage/BrowsershotSmokeTest.php`はja、zh-Hans、zh-Hant、ko、el、ru、tr、th、ar、he、hiのタイトルと、同じ長さの未割り当てコードポイント（必ず四角になる）からなる対照画像を描画します。2つのPNGがバイト単位で一致すると、文字体系とパッケージ名を示して失敗します。これはスモークチェックであり、全グリフの表示を証明するものではありません。一部のグリフが欠けていても、混在するラテン文字や折り返しの違いで画像が異なることがあります。デプロイ先ホストで実際の描画結果と使用フォントを確認してください。言語単位のFontProbe警告も事前確認であり、完全なフォント対応の証明ではありません。コアに`seo:doctor`コマンドはありません。この確認には`seo:og-images`を使います。

Debian/Ubuntuの場合：

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

3.15より前に`--tag=seo-views`で公開した独自テンプレートも動作し続けます。新しい変数`$fontFamily`と`$lang`を受け取りますが、使わなくても構いません。

## 注意事項 {#caveats}

本番環境で問題になり得るため、制約を明記します。

- **事前生成のみで、リアルタイム描画エンドポイントはありません（v1）。** リクエスト時にカードを描画するルートはありません。Webリクエストで描画しないため、**設定や防御が必要な署名付きURL / SSRF / DoSの公開面はありません**。その代わり、カードを用意するにはデプロイ時や定期実行で[`seo:og-images`](#the-seo-og-images-command)を実行する必要があります。
- **`npm_module_path`はWindowsでは効果がありません。** Browsershotの`setNodeModulePath()`に対応し、コマンドの前にPOSIX形式の`NODE_PATH=…`を付けますが、Windowsでは無視されます。Windowsでは`puppeteer`を**アプリケーションのルート**にインストールし、Nodeが上位ディレクトリをたどって解決できるようにします。Linux/macOSでは設定どおりに動作します。
- **非ラテン文字にはホスト上のフォントが必要です。** [フォントと非ラテン文字](#fonts-and-non-latin-scripts)を参照してください。同梱フォントはラテン文字、キリル文字、ギリシャ文字をカバーし、それ以外はデプロイイメージにインストールしたフォントを使います。不足するとコマンドが知らせます。
- **失敗してもページ配信は継続。** 描画に失敗した場合（パッケージ不足、ブラウザーのクラッシュ、タイムアウト）、コマンドが報告し、ページは静的な`default_og_image`を使い続けます。ブラウザーが壊れてもページが500になることはありません。
