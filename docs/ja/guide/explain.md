---
description: "seo:explainで、各SEOフィールドを設定した層と上書きされた値を確認します。読み取り専用で通信やライセンスは不要。想定外のtitleやrobotsタグの原因を調べられます。"
---

# 値の解決過程を調べる（`seo:explain`） {#explain-the-resolution-seo-explain}

Rankbeamは、[層ごとの優先順位](/ja/concepts/resolver-precedence)に従ってページのSEOを解決します。設定、データベースのデフォルト値（グローバル／モデルタイプ／ルート）、モデルから計算した値、明示的な`seo_meta`の順に処理し、その後に後処理（タイトルの接尾辞、正規URL、画像の絶対URL化）と[インデックス登録保護](/ja/guide/indexing-guard)を適用します。出力された`<title>`や`robots`タグが想定と異なる場合、**`seo:explain`で、各フィールドを設定した層と、その値が上書きした内容を正確に確認できます。**

この機能は読み取り専用で、ネットワーク通信やライセンスは不要です。マージ処理を別途実装しているわけでもありません。各値の取得元はリゾルバー自身の層別データから、最終値は実際のリゾルバーから取得するため、説明と実際の出力が食い違うことを防げます。

## 使い方 {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

モデルには[`HasSEO`](/ja/guide/quickstart)トレイトが必要です。

## 出力の読み方 {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by（設定元）** — 値が採用された層、つまりnullではない値を設定した層のうち、最も優先順位が高いものです。どの層もそのフィールドを設定せず、値が*導出された*場合は`post-processing`と表示されます。例えば、リクエストやモデルのURLから得た正規URL、正規URLから得たog:url、絶対URL化された画像です。
- **Overrode（上書きされた値）** — 値を提供したものの採用されなかった下位の層を、順にすべて表示します。どの値が上書きされたかを確認できます。
- **↳ notes（注記）** — 層のマージ後に値を変更した後処理です。タイトルの接尾辞、正規URLのクエリ文字列除去、og:urlの導出、画像の絶対URL化、すべての層に優先して`noindex`を強制するインデックス登録保護が含まれます。

::: tip og:typeとtwitter:card
この2つには、nullではないフレームワークのデフォルト値（`website`／`summary_large_image`）があります。そのため、値を設定した最上位の層（通常は`computed`）が`config`より優先されます。`seo_meta`のレコードが保存されていないページは、これらの値をその層から提供しません。したがって、`article`のように計算された`og:type`が、単なるデフォルトの`website`に上書きされることはありません。これは、実際のマージ処理と同じ動作です。
:::

## サイト全体の値の解決 {#site-level-resolution}

[サイト設定の取得元一覧に関する補足](/ja/concepts/resolver-precedence)に基づき、`seo:explain`は取得元を誤解しやすいサイト全体の値も表示します。**正規URLのホスト、サイト名、デフォルトのロケールを、どの取得元が設定したか**を確認できます。

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

特に確認したいのは、正規URLのホストです。`localhost`が公開URLに混入している、`https`のサイトなのに`http://`になっている、アプリのURLとモデルのURLが一致しない、といった誤りは、自己参照canonicalの不具合の典型的な原因です。

## JSON出力 {#json-output}

`--json`を指定すると、`target`、フィールドごとの`winner`、`losers`、`final`、`notes`、そして`site_level`の一覧を含む完全なトレースを出力します。ツールやCIで利用できます。

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## 関連ガイド {#see-also}

- [リゾルバーの優先順位](/ja/concepts/resolver-precedence) — `seo:explain`が追跡するすべての層。
- [無料のSEO監査](/ja/guide/audit) — `seo:audit`は*何が問題か*を検出し、`seo:explain`は*なぜその値になったか*を示します。
