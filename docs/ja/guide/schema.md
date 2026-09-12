---
description: "Organization、WebSite、WebPage、Articleのノードを安定した@idで相互参照するJSON-LDスキーマグラフを出力し、各ページで一貫したグラフを生成します。"
---

# スキーマグラフ（JSON-LD） {#schema-graph-json-ld}

JSON-LDは、ノード同士が参照し合う形にすると検索エンジンに伝わりやすくなります。OrganizationがWebSiteを発行し、WebSiteがWebPageを含み、WebPageがArticleについて述べるという関係です。`SchemaGraph`は、**安定した`@id`値**で相互に結び付けたノード群を生成し、すべてのページで一貫したグラフを出力します。

## ページのグラフ {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Bladeのheadまたはbody内で出力します。

```blade
{!! $schemas->toScript() !!}
```

OrganizationとWebSiteのデータは`config/seo.php`（`schema.organization`、`schema.website`）から取得し、WebPageノードには解決済みの`SEOData`を反映します。

WebPageノードの`inLanguage`には、ページの解決済みロケールをBCP 47形式（`it_IT` → `it-IT`）で設定します。`ArticleSchema::fromModel()`では保存済みの`seo_meta`のロケールを使い、WebSiteノードでは`schema.website.inLanguage`からサイトの言語を列挙します（単一のコードまたはリスト）。`schema.in_language`を`false`にすると、`inLanguage`は一切出力されません。[多言語コンテンツ](/ja/guide/multilingual#inlanguage-in-the-schema-graph)を参照してください。

## 型別のビルダー {#typed-builders}

一般的なリッチリザルトの型には、専用のビルダーがあります。

| ビルダー | 補足 |
|---|---|
| `ArticleSchema::fromModel($post)` | モデルと設定から日付、著者、発行者を取得 |
| `ProductSchema` | オファー、価格、在庫状況 |
| `BreadcrumbSchema::fromArray([...])` | 順序付きの名前とURLの組 |
| `BreadcrumbSchema::fromModelAncestors($page)` | `parent`の連鎖をたどる（循環を防ぐ保護付き） |
| `FAQSchema` | 質問と回答の組 |
| `LocalBusinessSchema` | 住所、位置情報、営業時間 |
| `OrganizationSchema` | 独立したOrganizationノード |

記事ページ全体の例です。

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## 付加したスキーマと`@seoSchema` {#attached-schema-and-seoschema}

解決済みの`SEOData`に保存されたスキーマ（たとえば、明示的なメタデータと一緒に保存したもの）は、`@seoSchema`ディレクティブまたは`SEO::toArray()`の`script`セクションから出力されます。

```blade
@seoSchema($post)
```

編集者は、[Filamentフィールド](/ja/guide/filament#structured-data-schema-org)パッケージの任意の**構造化データ**セクションを使い、コードを書かずに`seo_meta.schema_jsonld`を設定できます。自動パンくずリストの切り替えとFAQ / Productブロックがあり、保存前に`SchemaValidator`で検証されます。

## エスケープ {#escaping}

`SchemaCollection::toScript()`、`toJson()`、各レンダラー経由の出力を含め、すべてのJSON-LD出力は`JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`でエンコードされます。タイトルやコンテンツ内の`</script>`でscript要素が終了することはありません。スキーマ配列を自分で`json_encode`に渡して、この処理を迂回しないでください。
