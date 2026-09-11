---
description: "Düğümleri sabit @id değerleriyle birbirine başvuran bir JSON-LD şema grafı üretin: Organization, WebSite, WebPage ve Article ile her sayfada tutarlı bir graf."
---

# Şema grafı (JSON-LD) {#schema-graph-json-ld}

Arama motorları, düğümler birbirine başvurduğunda JSON-LD'yi daha iyi okur: Organization, WebSite'ı yayımlar; WebSite, WebPage'i içerir; WebPage, Article hakkındadır. `SchemaGraph` tam olarak bunu üretir: **sabit `@id` değerleriyle** birbirine bağlanan düğümler. Böylece her sayfa tutarlı bir graf sunar.

## Sayfa grafı {#the-page-graph}

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

Blade'de oluşturun (head veya body içinde):

```blade
{!! $schemas->toScript() !!}
```

Organization ve WebSite verileri `config/seo.php` yapılandırmasından (`schema.organization`, `schema.website`) gelir; WebPage düğümü çözümlenen `SEOData` verilerinden doldurulur.

WebPage düğümü, sayfanın çözümlenen dil kodunu BCP 47 biçiminde (`it_IT` → `it-IT`) `inLanguage` alanında taşır. `ArticleSchema::fromModel()` bunu kayıtlı `seo_meta` dil kodundan alır; WebSite düğümü ise sitenin dillerini `schema.website.inLanguage` üzerinden listeler (tek kod veya liste). Hiç `inLanguage` üretmemek için `schema.in_language` değerini `false` olarak ayarlayın. Bkz. [çok dilli içerik](/tr/guide/multilingual#inlanguage-in-the-schema-graph).

## Türe özel oluşturucular {#typed-builders}

Yaygın zengin sonuç türleri için oluşturucular bulunur:

| Oluşturucu | Notlar |
|---|---|
| `ArticleSchema::fromModel($post)` | Tarihler, yazar ve yayımcı modelden ve yapılandırmadan alınır |
| `ProductSchema` | Teklifler, fiyat ve stok durumu |
| `BreadcrumbSchema::fromArray([...])` | Sıralı ad/URL çiftleri |
| `BreadcrumbSchema::fromModelAncestors($page)` | Bir `parent` zincirini izler (döngü korumasıyla) |
| `FAQSchema` | Soru/cevap çiftleri |
| `LocalBusinessSchema` | Adres, coğrafi konum ve çalışma saatleri |
| `OrganizationSchema` | Bağımsız kuruluş düğümü |

Tam bir makale sayfası:

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

## Eklenen şema ve `@seoSchema` {#attached-schema-and-seoschema}

Çözümlenen `SEOData` üzerinde saklanan şema (örneğin açıkça atanan meta verileriyle birlikte kaydedilen şema), `@seoSchema` yönergesi veya `SEO::toArray()` çıktısının `script` bölümü üzerinden oluşturulur:

```blade
@seoSchema($post)
```

Editörler, [Filament alanları](/tr/guide/filament#structured-data-schema-org) paketindeki isteğe bağlı **Yapılandırılmış veri** bölümüyle `seo_meta.schema_jsonld` alanını kod yazmadan doldurabilir. Otomatik breadcrumb anahtarı ve SSS / Ürün blokları, kaydetmeden önce `SchemaValidator` üzerinden doğrulanır.

## Kaçışlama {#escaping}

Tüm JSON-LD çıktıları — `SchemaCollection::toScript()`, `toJson()` ve renderer yolları — `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP` ile kodlanır. Başlıklarda veya içerikte bulunan bir `</script>` dizisi script öğesini sonlandıramaz. Şema dizilerine kendiniz `json_encode` uygulayarak bu korumayı atlamayın.
