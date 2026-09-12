---
description: "LaravelのサーバーサイドレンダリングでSEOを出力するBladeディレクティブを紹介します。@seoはモデルの値を解決し、meta、Open Graph、Twitter Card、JSON-LDをまとめて出力します。"
---

# Bladeのガイド {#blade-guide}

通常のサーバーサイドレンダリングを行うアプリ向けに、このパッケージは7種類のBladeディレクティブを提供しています。多くの場合、そのうちの`@seo`だけで対応できます。

## まとめて出力するディレクティブ {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo`は[優先順位](/ja/concepts/resolver-precedence)に従ってモデルの値を解決し、head内に必要な一式を出力します。対象は`<title>`、meta description、canonicalリンク、robots、Open Graphタグ、Twitter Cardタグ、付随するJSON-LDです。robotsタグは**サイトのデフォルト値と異なる場合にのみ**出力されます。省略時もindex,followとして扱われるため、冗長な`index,follow`は出力されません。常に出力するには`seo.robots.emit_default`を設定してください。詳しくは[出力の共通仕様](/ja/contributing/rendering-contract)を参照してください。

呼び出し形式：

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo`には、`Model`、手動で作成した`SEOData`、または`null`を渡せます。ルートとロケールの引数が適用されるのは、`Model`または`null`を渡した場合だけです。手動で作成した`SEOData`では、そのオブジェクト自身の値が使われます。

## ルートに対応するページ（モデルなし） {#route-pages-no-model}

静的ページ、アーカイブなど、モデルではなくルートに対応するページでは、次のように呼び出します。

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

ルート用の値は、ルート名を対象とする`seo_defaults`のレコードから取得されます。

## モデルのないページ：`SEOData`を手動で作成する {#model-less-pages-hand-built-seodata}

一覧、検索結果、コントローラーで組み立てるページには、対応する単一のモデルがないことがあります。`SEOData`を作成し、`@seo`または`SEO`ファサードにそのまま渡してください。`app(TagRenderer::class)->render(...)`を直接呼び出す必要はありません。

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

手動で作成した`SEOData`は、**明示的な指定**として扱われます。設定した値はすべて保持され、出力時に不足する次の値だけが補われます。

- `canonical`と`og:url`が未設定の場合は、現在のURLから取得されます。明示的に設定した`canonical`は、クエリ文字列も含めてそのまま保持されます。
- `title_suffix`は、タイトルにまだ含まれていない場合にのみ追加されます。タイトルにブランドを表すトークンがすでに含まれている場合は、追加自体が省略されます。[`title_suffix_skip_when_contains`](/ja/reference/configuration)を参照してください。
- 相対パスの`og:image`と`twitter:image`は、`url()`で絶対URLに変換されます。現在のスキームが使われ、HTTPSが**強制されるわけではありません**。
- `og:site_name`と`locale`は、設定値とアプリのロケールから補われます。

データベース上の優先順位に含まれるグローバル、モデルタイプ、ルート、`seo_meta`のデフォルト値は、手動で作成した`SEOData`には**マージされません**。渡した値に、上記の不足分だけを補って出力します。

同じオブジェクトをファサードにも渡せます。

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## 複数のページ種別に対応するレイアウト {#a-layout-pattern-that-scales}

モデルのページ、ルートのページ、それ以外のページを1つのレイアウトで扱えます。

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

コントローラーから`'seoModel' => $post`または`'seoRoute' => 'blog.index'`を渡すだけで、マークアップを変更する必要はありません。

## 個別に出力するディレクティブ {#granular-directives}

他のパッケージの出力と組み合わせる場合など、タグを個別に制御したいときは、次のディレクティブを使います。

| ディレクティブ | 出力内容 |
|---|---|
| `@seoTitle($post)` | `<title>`のみ |
| `@seoMeta($post)` | meta descriptionのみ |
| `@seoCanonical($post)` | canonicalリンクのみ。未設定時は現在のURLを使用 |
| `@seoRobots($post)` | robots metaのみ。明示的に出力を選ぶ呼び出しなので、常に出力されます。`@seo`とは異なり、デフォルト値との一致による出力抑制は**適用されません** |
| `@seoSchema($post)` | JSON-LDの`<script>`のみ。head内でもbody内でも有効 |

いずれも`@seo`と同じ`($model, $route, $locale)`形式の引数を受け取ります。引数を省略すると、現在のページが対象になります。

## hreflangによる代替ページ {#hreflang-alternates}

`HasSEO`を使うモデルは、リゾルバーを通じてhreflangリンクを直接提供できます。

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

絶対URLを使ってください。`@seo($post)`はこれらのエントリーを解決し、それぞれを`<link rel="alternate" hreflang="..." href="...">`として出力します。コードは最初にBCP 47形式へ変換されます（`it_IT` → `it-IT`）。`seo.hreflang`のポリシーによって、ページ自身への参照と`x-default`を追加することもできます。無料の監査機能は、無効なエントリー、重複、自己参照の欠落を検出します。[多言語コンテンツ](/ja/guide/multilingual#hreflang)を参照してください。

## エスケープと安全性 {#escaping-and-safety}

テキスト値は`e()`でエスケープされます。JSON-LDは`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`を指定してエンコードされるため、ユーザーのコンテンツに`</script>`が含まれていても、script要素を途中で閉じることはできません。
