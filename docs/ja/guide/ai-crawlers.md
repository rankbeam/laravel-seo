---
description: "整備されたAIクローラーのカタログと許可・拒否ポリシーから、管理対象のrobots.txtと任意のai.txtを出力します。サイトを取得できるボットを選べる、無料のCore機能です。"
---

# AIクローラーの制御（robots.txt / ai.txt） {#ai-crawler-control-robots-txt-ai-txt}

主要なAI事業者は、名前の付いたボットでWebをクロールし、その多くが**robots.txt**を読んで取得可能な対象を判断します。Rankbeamは、整備されたボットカタログを備え、単純な許可・拒否ポリシーから管理対象の`robots.txt`と、任意で`ai.txt`を出力します。**AI検索やアシスタントのクローラーを許可しつつ、コンテンツを学習に使うクローラーを制限できます。**

無料のCore機能です。Proパッケージは、そのもう一方である[監視機能：AIボットのアクセスログ](/ja/pro/ai-bot-monitor)を追加し、実際に訪問したAIクローラーを確認できるようにします。

## デフォルトのポリシー {#the-default-policy}

カタログ内の各ボットには、主な用途が付いています。

| 用途 | 動作 | デフォルト |
| --- | --- | --- |
| `ai_search` | **AI検索**の回答用にページを取得し、インデックスに登録します（AI経由の流入チャネル） | **allow** |
| `ai_assistant` | チャット内で**ユーザー**に代わって、リアルタイムにページを取得します | **allow** |
| `ai_training` | モデルの**学習**用にコンテンツを収集します | **disallow** |

これは、AI時代における多くの発行者の方針に沿っています。ChatGPT searchやPerplexityなどを支えるAI検索・アシスタントのクローラーからはアクセスできるようにし、学習データとしての利用は拒否します。どの設定も変更できます。

::: warning アクセスの許可は引用を意味しません
クローラーを許可すると取得が*可能*になりますが、発見、インデックス登録、順位、回答への採用、文章の引用、出典としての引用を保証するものではありません。このポリシーが制御するのは、どのボットがページを取得してよいかという**アクセス**だけです。その後の結果までは制御しません。
:::

## クイックスタート {#quick-start}

AIクローラー用のブロックを表示し、公開する内容を確認します。

```bash
php artisan seo:robots-txt --print
```

使い方は2つあります。

### 方法A — 既存のrobots.txtにブロックを貼り付ける {#option-a-—-paste-the-block-into-your-existing-robots-txt}

すでに`public/robots.txt`を管理している場合は、管理対象のブロックだけを取得して貼り付けます。

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### 方法B — ファイル全体をRankbeamで管理する {#option-b-—-let-rankbeam-manage-the-whole-file}

完全な`robots.txt`を生成します。一般セクション、AI向けの指示、`Sitemap:`行、[llms.txt](/ja/guide/sitemaps)への参照を含みます。

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

ポリシーの変更をファイルに反映するため、定期実行を設定します。

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

動的に配信することもできます。`seo.ai_crawlers.route`を`true`にすると、パッケージが現在の設定から`/robots.txt`へのレスポンスを返します。生成処理は不要です。

::: warning 静的ファイルが優先されます
多くのアプリにはすでに`public/robots.txt`があり、Laravelがリクエストをルーティングする前にWebサーバーが配信します。存在を忘れたファイルと動的ルートのどちらかが、気付かないうちにもう一方を隠さないよう、動的ルートは**デフォルトで無効**です。静的な`robots.txt`がない場合にだけ、このルートを使ってください。
:::

## 制限の実効性について {#honesty-about-enforcement}

robots.txtはお願いであり、アクセスを遮断する壁ではありません。カタログ内のボットの多くはrobots.txtに従うと説明されていますが、ユーザー操作で動く一部のエージェント（`ChatGPT-User`、`Perplexity-User`）や、一部の学習用クローラー（`Bytespider`）は**従いません**。Rankbeamは、実際には効かない遮断を示唆しないよう、それらの行に`advisory`と記します。従わないボットを実際に止めるには、サーバーまたはエッジでの遮断（ファイアウォール、WAF、Cloudflareのボットルール）が必要です。[ProのAIボットアクセスログ](/ja/pro/ai-bot-monitor)で、対処すべきボットを確認できます。

## Content signals（利用に関する意思表示） {#content-signals-usage-preferences}

`Allow` / `Disallow`は、ボットがページを取得してよいかという**アクセス**を制御します。[Content signals](https://contentsignals.org)（Cloudflareが推進する標準）は別の軸で、取得後のコンテンツをどのように**利用**してよいかを示します。`User-agent: *`グループの1行の`Content-Signal:`で、3つの意向を表します。

| シグナル | 元になるポリシーの用途 | 意味 |
| --- | --- | --- |
| `search` | `ai_search` | 検索インデックスの作成（リンクと短い抜粋） |
| `ai-input` | `ai_assistant` | リアルタイムでページをAIモデルに入力すること（RAG / グラウンディング） |
| `ai-train` | `ai_training` | AIモデルの学習またはファインチューニング |

**デフォルトでは無効**です。有効にするまではファイルがバイト単位で変わりません。有効にすると、Rankbeamは既存の`policy`から直接この行を生成します。`allow`は`yes`、`disallow`は`no`になります。

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

`policy`から用途を完全に削除すると、そのシグナルは**省略**されます。これは仕様上の「意向を表明しない」に当たり、明示的な`yes` / `no`とは異なります。

::: warning robots.txtと同じく、従うかどうかは相手次第です
Content signalsは意向を表明するもので、技術的に強制する仕組みでは**ありません**。クローラーは無視できます。上記のアクセスルールやエッジでの遮断と併用するものであり、それらを置き換えるものではありません。
:::

## 設定 {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

`overrides`を使うと、用途にかかわらず個別のボットを上書き設定できます。キーはカタログの**id**（例：`gptbot`、`claudebot`、`perplexitybot`、`google-extended`）です。

## カタログ {#the-catalog}

`SEO::aiCrawlers()`が正となる情報源です。Proのアクセスログも同じカタログで訪問者を識別するため、ボットを制御するファイルと観測するパネルで情報が食い違うことはありません。

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

OpenAI（GPTBot、OAI-SearchBot、ChatGPT-User）、Anthropic（ClaudeBot、Claude-SearchBot、Claude-User）、Google（Google-Extended）、Perplexity、Apple（Applebot-Extended）、Common Crawl（CCBot）、Meta、Amazon、ByteDanceなどの主要事業者を網羅し、それぞれについて公表された用途とrobots.txtのトークンを収録しています。

### 地域別の検索エンジン {#regional-search-engines}

3.15以降、カタログにはGoogleやBing以外の市場で重要な従来型Web検索クローラーも含まれます。用途は`search_engine`で、**デフォルトで許可**されています。

| id | トークン | 事業者 |
|---|---|---|
| `yandex` | `Yandex` | Yandex（ロシア）— この単独のトークンで同社の全ボットが対象になります |
| `baiduspider` | `Baiduspider` | Baidu（中国） |
| `yeti` | `Yeti` | Naver（韓国） |
| `seznambot` | `SeznamBot` | Seznam（チェコ） |
| `sogou` | `Sogou web spider` | Sogou（中国） |
| `360spider` | `360Spider` | Qihoo 360（中国） |
| `coccocbot` | `coccocbot-web` | Cốc Cốc（ベトナム） |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

他のボットと同じく`policy`と`overrides`の対象です。たとえば`'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']`で、配信先としない2つのクローラーに帯域を使わせない方針を設定でき、`'list' => 'all'`で各ボットの行を明示的に出力できます。一方、明示的に要求しない限り`all()`と`match()`には**含まれません**。含めるには`searchEngines()`、`all(true)`、`match($ua, true)`を使います。これにより、ProのAIボットログと「AIクローラー数」の意味が保たれます。

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

クローラーを識別できることは、その検索エンジンでの表示や順位を保証しません。対応するサイト所有権確認タグ（`yandex-verification`、`baidu-site-verification`、`naver-site-verification`、`seznam-wmt`）は`seo.verification`で設定します。[多言語コンテンツ](/ja/guide/multilingual#site-verification)を参照してください。
