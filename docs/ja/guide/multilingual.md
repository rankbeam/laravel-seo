---
description: "Rankbeamが英語以外のコンテンツを扱う仕組みを説明します。文字体系別の文字数目安、書記素を壊さない切り詰め、ロケールに応じた大小文字処理、hreflangの正規化と方針、inLanguage、地域別検索エンジン、サイト所有権確認、OG画像のフォント、Unicode URLを扱います。"
---

# 多言語コンテンツ {#multilingual-content}

[翻訳](/ja/guide/translations)は、*パッケージ*を自分の言語で使うためのものです。このページでは、もう一方の側面、パッケージが**コンテンツの言語を理解する**仕組みを扱います。日本語にタイトル60文字の上限を当てはめるのは適切ではなく、単語境界での切り詰めはタイ語を壊します。トルコ語では`İstanbul`と`istanbul`は同じ単語で、hreflangの`it_IT`は無効です。韓国語のサイトではGoogleだけでなくNaverのクローラーも重要です。これらは翻訳ではなく、正しく処理できるかという問題です。すべての機能で一貫するよう、Coreで判断します。

デフォルトとポリシーの上書き設定は`config/seo.php`にあります。ICUによる単語分割やインストール済みフォントなど、一部の機能は実行環境の依存関係を必要とします。翻訳済みコンテンツはアプリから提供してください。

## コンテンツのロケールとUIのロケール {#content-locale-and-interface-locale}

Core 3.17、Filament 1.11、Pro 2.36は、選択したコンテンツのロケールを、メタデータ、算出用フック、プレビューURL、チェックリストのキーワード、AIリクエストに渡します。英語のパネルで、ラベルを変えずにイタリア語や日本語を編集できます。

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

これらの読み取りは、そのロケールのメタデータ行を選び、一時的なロケールの範囲内で`getSEOTitle()`、`getSEODescription()`、`getUrlForSEO()`、`getSEOSchema()`などのモデルフックを実行します。フックが例外を投げても、呼び出し元のモデルとアプリのロケールを保持します。Spatieの`setLocale()`と`getTranslatableAttributes()`を実装するモデルには、インスタンスのロケールも分離して渡します。フック自身が翻訳済みコンテンツを返す必要があります。Rankbeamは通常のデータベース属性を自動翻訳しません。

ProのモデルベースのAIメソッドと一括入力は、明示的な`locale:`を受け付けます。指定しない場合は、翻訳モデルが上書きした`seoData()`のデフォルトからコンテンツのロケールを決め、なければアプリのロケールにフォールバックします。Filamentのアクションには、そのフィールド自身のロケールを渡します。単一言語のエディターや追従モードも同じです。独自のキュージョブでは、選択したロケールをシリアライズし、実行時に明示的に渡してください。ワーカーの現在のロケールに依存しないでください。

独自の同期コンテンツ読み取りでは、`ModelLocale::run($model, $locale, $callback)`が分離したモデルをコールバックに渡し、`finally`でアプリのロケールを復元します。ロケール依存の読み取りは、すべてコールバック内で終えてください。遅延イテレーターやクロージャーを返しても、その範囲は延長されません。

## 文字体系別のタイトル・ディスクリプションの目安 {#title-and-description-budgets-per-script}

Rankbeamの編集上の文字数目安は、ラテン文字のタイトル・ディスクリプションで60/160書記素、CJKで30/80です。変更可能な近似値であり、ピクセル単位の測定でも、検索エンジンが値全体を表示する保証でもありません。Googleは[タイトルリンク](https://developers.google.com/search/docs/appearance/title-link)や[メタディスクリプション](https://developers.google.com/search/docs/appearance/snippet)に固定の文字数制限を定めていません。表示文は端末の幅に応じて切り詰められることがあります。

`Rankbeam\Seo\I18n\LengthPolicy`が、渡されたテキストの目安を決めます。

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

エディターの警告（`SEOWarningEvaluator`）、無料の`seo:audit`、算出ディスクリプションの切り詰め、Proスキャン、Filamentのカウンターは、すべてこの同じポリシーを読み取ります。警告はタイトルのサフィックスを含む解決済みの値を評価しますが、エディターは未保存のテキストも表示できます。長さはバイトやコードポイントではなく、**書記素クラスター**で数えます。クラスター境界は、インストール済みのUnicode実装に従います。音節の数でも、検索結果のピクセル幅でもありません。

設定行は`seo.length_policy`にあり、文字体系のグループ（`latin`、`cyrillic`、`greek`、`cjk`、`thai`、`arabic`、`hebrew`、`devanagari`）をキーにします。一覧にないグループには`default`を使います。各行は一部のキーだけを設定し、残りを継承できます。

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

デフォルトで異なるのは`cjk`だけです。公開済みの古い設定を使ってアップグレードした環境でも、何も変更せずに組み込みの`cjk`行が適用されます。

::: tip 文字体系が混在するタイトル
検出には重み付きの文字数を使い、CJKの字形を2倍に数えます。そのため「Laravel SEO の完全ガイド」はCJKになり、「Laravel SEO for the 東京 developer」はラテン文字のままです。年や価格など、文字体系を判定する対象の文字を含まない値は、ページのロケールの文字体系を使います。
:::

`SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH`定数は、それを読むコード向けに、ラテン文字のデフォルトとして引き続き存在します。

## 書記素を壊さず、文字体系に応じて切り詰める {#grapheme-safe-script-aware-truncation}

算出ディスクリプション（`seo.computed.description_max_length`はラテン文字向けの目安）はポリシーに応じて調整され、CJKでは半分になります。`Rankbeam\Seo\I18n\Truncator`が次の規則で切り詰めます。

- 単語間にスペースがあるテキストは、従来の規則を維持します。上限内の最後の単語境界が上限の60%以上の位置にあればそこで切り、省略記号は付けず、末尾の句読点を除きます。ラテン文字では従来とバイト単位で同じ結果です。
- 漢字・仮名・タイ文字には単語間のスペースがないため、上限内の最後の文・節の区切り（。！？、，など）を優先します。次に、韓国語のようにスペースがあればそこを使い、どちらもなければ上限で切ります。
- 分割は書記素クラスター単位なので、結合文字の並びの途中で切れません。タイ語の母音記号や絵文字の修飾子が、基になる文字から離れることはありません。

## ロケールに応じた大小文字処理 {#locale-aware-casing}

`mb_strtolower()`はロケールを考慮しません。`Rankbeam\Seo\I18n\CaseFolder`は考慮します。

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()`は表示用で、`fold()`、`equals()`、`contains()`、`containsWord()`は比較用です。Coreは、ブランド名を考慮したタイトルサフィックスの重複回避（`seo.title_suffix_skip_when_contains`）に使います。トルコ語のブランドは`i`のどちらの形でも一致し、アクセント付きのブランドにも適切な単語境界を使います。Proのキーワードチェックも同じヘルパーを基にします。

ケースフォールディングはアクセントを保持します。アクセントのある綴りとない綴りを、すべて同一視するわけではありません。言語別のステマーが独自の簡約を行う場合はありますが、`CaseFolder`や同一形の照合とは別の処理です。

## hreflang {#hreflang}

Googleが読むのは`language[-Script][-REGION]`、つまりISO 639-1の2文字言語コードに、任意でISO 15924の文字体系とISO 3166-1 alpha-2の地域を加えたもの、および`x-default`です。`es-419`のような数値の地域はBCP47では有効ですが、[Googleのhreflang仕様](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes)の対象外です。Laravelアプリは代わりに*ロケール*（`it_IT`、`pt_br`）を渡しがちですが、アンダースコアはここでは無効です。`seo.hreflang`の3つのポリシーを、モデルの`getSEOAlternates()`一覧に適用してから、`<link rel="alternate">`タグ、サイトマップの`<xhtml:link>`項目、`llms.txt`リンク、監査入力に変換します。すべて同じポリシーを使います。`llms.txt`は「他の言語版」のリンクから、ページ自身と`x-default`を除きます。

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**はデフォルトで有効です。区切り、大文字・小文字、登録済みの別名（`iw_IL` → `he-IL`）を調整します。繰り返した区切り文字（`en__US` → `en--US`）は保持し、監査で指摘できるようにします。渡されたバイト列を保持する場合は無効にします。
- **`include_self`**は、一覧にページ自身のURLも言語コードもない場合、そのロケールと正規URLを追加します。Googleは各言語版に自己参照を要求します。フックが*他の*言語だけを返す場合に有効にしてください。
- **`x_default`**は、一覧に`x-default`がないとき、その代替ページを複製して使う言語を指定します。

空の一覧は空のままです。翻訳のないページには、自己参照も`x-default`も追加しません。

無料の監査は、ポリシー適用後の一覧に対して3つのチェックを追加します。

| コード | 重大度 | 意味 |
|---|---|---|
| `hreflang_invalid_code` | warning | Googleの仕様外のコード（`en-UK`、`jp`、`english`、`es-419`、`fil`）。 |
| `hreflang_duplicate_code` | notice | 同じコードが2回記載されている。 |
| `hreflang_missing_self` | warning | ページ自身のURLが一覧にない。 |

相互参照（相手のページも戻りの参照を持つか）の確認にはクロールが必要で、Proスキャンが担当します。任意で有効にする`check_hreflang_reciprocity`はSsrfGuardを通して各代替ページを取得し、相手がこのページのURLを**その言語コードとともに**宣言していなければ、`hreflang_not_reciprocal`を出します。Pro 2.38以降が対象です。[スキャンの課題](/ja/pro/scan-issues#network-codes)を参照してください。必要に応じて公開ヘルパーも使えます。

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### 言語コードの3つの仕様 {#three-language-code-contracts}

Core **3.18以降**は、アプリの設定値とHTMLで配信する値を区別します。

| 入力 | アプリ側の正規化 | HTMLの言語 | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | そのまま配信すると無効 | そのまま配信すると無効 |
| `de-CH-1901` | 保持 | 有効な登録済みバリアント | 未対応のバリアント |
| `es-419` | 保持 | 有効な数値地域 | 未対応の数値地域 |
| `zh-Hant-TW` | 保持 | 有効 | 有効 |
| `fil` | 保持 | 有効な登録済み言語 | 2文字コードの仕様外 |
| `iw_IL` | `he-IL` | アンダースコアは無効。`iw-IL`は非推奨だが有効なタグ | 正規化した`he-IL`を使う |
| `en__US` | `en--US` | 無効 | 無効 |
| `x-default` | 保持 | Rankbeamのコンテンツ言語ポリシーでは拒否 | 有効なフォールバック用マーカー |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Core 3.17以前からの移行：**`Hreflang::isValid()`と`parse()`は、配信するコードを厳格に検証します。呼び出し側がLaravelのロケールを渡す場合は、先に`fromLocale()`を呼んでください。HTMLの`lang`属性を検査する場合は、トリミングや正規化をせず、`LanguageTag::isValidHtml()`を使います。非推奨でも登録済みのタグはHTMLでは有効です。正規化は、IANAで優先別名として明示されたものだけを適用し、`en-UK`が`en-GB`を意味すると推測しません。監査が報告できるよう、不正な項目を事前に除去することもありません。

バリデーターには、**2026年8月8日**付のIANAレジストリの情報を、ソースハッシュと再現可能なジェネレーターとともに同梱しています。RFC 5646の構造、登録済みサブタグ、extlangの接頭辞、バリアント・拡張の重複を検証します。旧来の登録タグ（grandfathered）と私用範囲にも対応します。バリアントの推奨接頭辞は、有効性を決める必須規則ではありません。拡張の名前空間と構造は検証しますが、CLDRオプションの意味や私用部分の意味はAPIの対象外です。ICUや実行時のダウンロードは不要です。[RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html)と[HTMLの言語定義](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes)を参照してください。

Pro **2.38以降**は、`lang`がない場合や空の場合を不明・欠落として報告し、不正な配信バイト列には`html_lang_invalid`を出します。文字体系の不一致チェックは、実際の文字体系サブタグ、またはIANAに登録されたデフォルトを使います。私用・拡張の内容や未知の言語を、ラテン文字だとはみなしません。未対応の文字体系グループは判定しません。これらは完全な言語検出ではありません。

相互参照の確認には、元ページの有効な自己参照コードを使います。それがなければ、有効でGoogleに対応するHTMLの言語を使います。別の言語コードに付いた戻りURLでは合格しません。元の言語コードを確定できなければ、結果は`hreflang_target_unverified`のままです。重複する宛先URLは、既存の代替ページ数・本文サイズの制限内で1回だけ取得します。SSRF保護、リダイレクトの拒否、検証できない失敗の扱いは維持します。

## スキーマグラフの`inLanguage` {#inlanguage-in-the-schema-graph}

`WebPage`ノードの`inLanguage`は、ページの解決済みロケール（`it_IT` → `it-IT`）から取ります。`ArticleSchema::fromModel()`は保存済みの`seo_meta`ロケールから取ります。`WebSite`ノードの言語は設定から取得します。

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## 地域別の検索エンジン {#regional-search-engines}

`seo:robots-txt`の基盤となるクローラーカタログには、Google/Bing以外の地域で重要な従来型のWeb検索クローラーも含まれます。Yandex、Baidu、Naver（`Yeti`）、Seznam、Sogou、360、Cốc Cốc、DuckDuckGoです。目的を`search_engine`として分類し、デフォルトでは許可します。ポリシーとボット別の上書きの対象になるため、たとえば中国向けに提供していないショップでは、2つのクローラーによる帯域使用を避ける設定ができます。

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()`と`match()`はAI専用のままです。ProのAIボットログと「AIクローラーN種」の件数は変わりません。検索エンジンを取得するには、`searchEngines()`、`all(true)`、`match($ua, true)`を使います。[AIクローラー制御](/ja/guide/ai-crawlers#regional-search-engines)を参照してください。

::: warning Baidu
クローラーと所有権確認タグへの対応は、Baiduでの発見、インデックス登録、検索順位を保証しません。
:::

## サイトの所有権確認 {#site-verification}

所有権確認トークンは、設定した検索エンジンごとに1つのmetaタグとして、すべてのページに出力します。Googleはどこにあるタグでも受け付け、Yandex、Baidu、Naverはルートページを見るため、そちらも対象になります。空にしたキーは何も出力しません。

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

値はトークンの一覧にもできます。Googleはプロパティの所有者ごとに1つ発行します。

## あらゆる文字体系のOG画像 {#og-images-in-every-script}

同梱のカード用フォントは、ラテン文字、キリル文字、ギリシャ文字に対応しています。それ以外の文字体系は、`seo:og-images`を実行するマシンにインストールしたフォントに依存します。CJKフォントは16MB以上あるため、他のフォントは同梱しません。テンプレートには文字体系別のフォールバックスタック（`seo.og_image.font_stack`）があります。漢字が適切な国・地域の字形になるよう、ページの言語のNoto CJKファミリーを先頭にします。描画予定のタイトルに対応するフォントがホストにない場合、コマンドは文字体系ごとに1回警告します。

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Debian/Ubuntuでは`apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`を使います。詳しくは[OG画像の生成](/ja/guide/og-image#fonts-and-non-latin-scripts)を参照してください。

## 複数言語の`llms.txt` {#llms-txt-in-several-languages}

`seo.llms_txt.alternates`を有効にすると、他の言語版があるページの箇条書き末尾に`Also in: [it](…), [de](…)`を付けます。ポリシー適用後の代替ページから、`x-default`とページ自身を除いたものです。デフォルトでは無効です。

## Unicode URL {#unicode-urls}

RankbeamはURLをスラッグ化したり書き換えたりしません。`/città/`や`/検索`のようなパスは、すべての出力でそのまま残ります。IDNホスト（`https://münchen.example/`）や、Unicodeまたはパーセントエンコードされたパスの正規URLも、監査で受け付けます。PHPのASCII専用`FILTER_VALIDATE_URL`の代わりに`Rankbeam\Seo\I18n\Url::isValid()`を使います。canonical、hreflang、サイトマップの項目をバイト単位で一致させるため、各URLの形式はUnicodeのままかパーセントエンコードか、どちらか一方に統一してください。

## 対応言語と「対応」の意味 {#which-languages-are-supported-and-what-that-means}

パッケージは、以下の17ロケールの文言と分析処理の振り分けを提供します。この表は技術上の対応範囲であり、ネイティブによる編集承認や、未設定のホストでの描画保証ではありません。日本語・中国語の単語分析には、利用可能なICUが必要です。使えなければ、影響する単語ベースのチェックはスキップします。ラテン文字以外の描画には適切なフォントが必要です。処理の振り分けは両リポジトリのテストで検証します。Coreの`tests/Feature/I18n/SupportedLanguagesTest.php`はロケール一覧、hreflangコード、文字数目安を固定し、Proの`tests/Feature/OnPage/LanguageSupportMatrixTest.php`は分析エンジンを固定します。表の行が正しくなくなればCIが失敗します。

| 言語 | ロケール | タイトル / ディスクリプション | 単語の数え方 | キーワード照合 | 読みやすさ |
|---|---|---|---|---|---|
| 英語 | `en` | 60 / 160 | スペース | Snowball | Flesch Reading Ease |
| イタリア語 | `it` | 60 / 160 | スペース | Snowball | Gulpease |
| ドイツ語 | `de` | 60 / 160 | スペース | Snowball | Wiener Sachtextformel |
| フランス語 | `fr` | 60 / 160 | スペース | Snowball | Kandel-Moles |
| スペイン語 | `es` | 60 / 160 | スペース | Snowball | Fernández-Huerta |
| ポルトガル語（ブラジル） | `pt_BR` | 60 / 160 | スペース | Snowball | Martins |
| オランダ語 | `nl` | 60 / 160 | スペース | Snowball | Flesch-Douma |
| トルコ語 | `tr` | 60 / 160 | スペース | Snowball | Ateşman |
| ロシア語 | `ru` | 60 / 160 | スペース | Snowball | Oborneva |
| ポーランド語 | `pl` | 60 / 160 | スペース | Snowball | Pisarek |
| 日本語 | `ja` | 30 / 80 | ICU辞書 | ケースフォールド後の完全一致 | ヒューリスティック、**スコアなし** |
| 中国語（簡体字） | `zh_CN` | 30 / 80 | ICU辞書 | ケースフォールド後の完全一致 | ヒューリスティック、**スコアなし** |
| 中国語（繁体字） | `zh_TW` | 30 / 80 | ICU辞書 | ケースフォールド後の完全一致 | ヒューリスティック、**スコアなし** |
| 韓国語 | `ko` | 30 / 80 | スペース | ケースフォールド後の完全一致 | ヒューリスティック、**スコアなし** |
| ギリシャ語 | `el` | 60 / 160 | スペース | Snowball | LIX |
| ウクライナ語 | `uk` | 60 / 160 | スペース | ケースフォールド後の完全一致 | LIX |
| チェコ語 | `cs` | 60 / 160 | スペース | Snowball | LIX |

この表では、次の3点も明確にしています。

- **Pro 2.37からSnowballを同梱しています。** 12言語は、任意パッケージとは独立して、固定した3.1.1のアルゴリズムを使います。ウクライナ語とCJKは同一形の照合を使い、パッケージが独自に語尾規則を作ることはありません。同一形の照合は活用形を見逃す場合があり、ステミングは異なる単語をまとめてしまう場合があります。[エンジン制御と移行時の注意](/ja/pro/on-page-checklist#upgrading-from-pro-2-36)を参照してください。
- **「ヒューリスティック、スコアなし」と「LIX」は別です。** このパッケージでは、日本語・中国語・韓国語にスコアを付けない方法を使います。チェックリストは文の長さと漢字の割合から*レベル*を報告し、スコアは`null`です。設定にかかわらず助言にとどまります。ギリシャ語・ウクライナ語・チェコ語には専用の式を実装していないためLIXを使います。LIXは音節を必要としませんが、その閾値はすべての言語に合わせて校正されているわけではありません。各式の入力には推定値が含まれます。[統計の仕様](/ja/pro/on-page-checklist#text-statistics-and-api-limits)を参照してください。
- **パッケージのUI翻訳は初稿です。** `TRANSLATING.md`にネイティブがレビューしたと記載されているものを除きます。イタリア語はレビュー済みですが、それ以外はレビュアーを求めています。翻訳をレビューすることは、パッケージの貢献者として自分の言語でクレジットされるための、最も手軽な方法です。

一覧にないロケールでは、英語の文言、文字体系別またはデフォルトの文字数目安、同一形のキーワード照合、LIXまたはヒューリスティックな読みやすさ評価にフォールバックする場合があります。そのフォールバックは、検証済みの言語対応ではありません。チェックリストの`analysis`ブロックで、文字体系、分割器、ステマー、読みやすさの方法を確認できます。ラベルだけでなく、利用可否やスキップした判定も確認してください。

### 地域で重要な検索エンジンに対応する {#reaching-the-search-engines-that-matter-locally}

言語の提供は、テキストだけの問題ではありません。クローラーカタログにはGoogleとBingに加え、Yandex、Baidu、NaverのYeti、Seznam、Sogou、360、Cốc Cốcが含まれ、`seo.verification`がそれぞれのサイト所有権確認タグを出力します。韓国語サイトならNaver、チェコ語ならSeznam、ウクライナ語やロシア語ならYandexなどです。[地域別の検索エンジン](#regional-search-engines)と[サイトの所有権確認](#site-verification)を参照してください。

## 他のパッケージが追加する機能 {#what-the-other-packages-add}

- **laravel-seo-filament**は、ライブカウンターとSERPプレビューに同じ文字数ポリシーを使います。また1.9から、[言語ごとの`seo_meta`行](/ja/guide/filament#several-languages)を編集できます。ロケールごとのタブに専用のカウンター、プレビュー、フォールバック表示を持つか、翻訳プラグインの言語切り替えに追従します。
- **laravel-seo-pro**は、スキャンの`title_length` / `description_length`チェックとAI支援のプロンプトに同じポリシーを使います。2.34から、ページ自身の言語で分析します。中国語・日本語・タイ語のICU単語分割、Snowballステミング、この`CaseFolder`によるロケールに応じたキーワード照合、10言語の公表済み読みやすさの式と推定入力、CJK向けと明示したヒューリスティック、ギリシャ語・ウクライナ語・チェコ語向けと明示したLIX、16言語のストップワード、`html lang`とhreflang相互参照のスキャンチェック、ページ言語を指定するAIプロンプト、dompdfで描画できない文字体系向けのChrome出力レポートを提供します。[ページ内チェックリスト](/ja/pro/on-page-checklist#keyword-matching)、[スキャンの課題](/ja/pro/scan-issues)、[AI支援](/ja/pro/ai-assist#output-language)、[レポート](/ja/pro/reports#reports-in-every-script-browsershot-renderer)を参照してください。
