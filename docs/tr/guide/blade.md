---
description: "Paketin Blade yönergeleriyle sunucuda oluşturulan Laravel sayfalarına SEO ekleyin. @seo modeli çözümler; meta, Open Graph, Twitter Cards ve JSON-LD çıktısını birlikte üretir."
---

# Blade kılavuzu {#blade-guide}

Klasik, sunucuda oluşturulan uygulamalar için paket yedi Blade yönergesi sunar. Genellikle bunlardan yalnızca `@seo` yeterlidir.

## Hepsi bir arada yönerge {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` modeli [öncelik zincirinden](/tr/concepts/resolver-precedence) geçirerek çözümler ve head bloğunun tamamını üretir: `<title>`, meta açıklaması, kanonik bağlantı, robots, Open Graph etiketleri, Twitter Card etiketleri ve eklenmiş JSON-LD. Robots etiketi **yalnızca site varsayılanından farklıysa** üretilir; gereksiz `index,follow` atlanır (yokluğu zaten index,follow anlamına gelir). Her zaman üretmek için `seo.robots.emit_default` ayarını kullanın. Tüm ayrıntılar için [Çıktı Sözleşmesine](/tr/contributing/rendering-contract) bakın.

İmzalar:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` bir `Model`, elle oluşturulmuş bir `SEOData` veya `null` kabul eder. Rota/dil kodu argümanları yalnızca `Model`/`null` yolu için geçerlidir; elle oluşturulmuş bir `SEOData` kendi değerlerini taşır.

## Rota sayfaları (modelsiz) {#route-pages-no-model}

Statik sayfalar, arşivler ve rotaya dayanan diğer sayfalar için:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Rota değerleri, rota adıyla sınırlandırılmış `seo_defaults` satırlarından gelir.

## Modelsiz sayfalar: elle oluşturulan `SEOData` {#model-less-pages-hand-built-seodata}

Listeler, arama sonuçları ve controller'da birleştirilen içerikler çoğu zaman tek bir modele dayanmaz. Bir `SEOData` oluşturup doğrudan `@seo` yönergesine (veya `SEO` facade'ına) iletin; `app(TagRenderer::class)->render(...)` kullanmanız gerekmez:

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

Elle oluşturulan bir `SEOData`, **açıkça belirtilmiş tercih** olarak ele alınır. Atadığınız her değer korunur; yalnızca çıktı oluşturulurken eksik kalan değerler tamamlanır:

- `canonical` / `og:url` yoksa geçerli URL'den türetilir (açıkça atanan `canonical`, sorgu dizesi dahil aynen korunur).
- `title_suffix` yalnızca başlıkta yoksa eklenir (başlık zaten bir marka belirteci içeriyorsa tamamen atlanır; bkz. [`title_suffix_skip_when_contains`](/tr/reference/configuration)).
- Göreli `og:image` / `twitter:image` yolları, `url()` ile mutlak URL'lere dönüştürülür (mevcut protokolü korur; HTTPS'i **zorlamaz**).
- `og:site_name` ve `locale` yapılandırmadan / uygulamanın dil kodundan doldurulur.

Veritabanı öncelik zinciri (genel / model türü / rota / `seo_meta` varsayılanları), elle oluşturulmuş bir `SEOData` ile **birleştirilmez**. Çıktıda ilettiğiniz değerler bulunur; yalnızca yukarıdaki eksikler tamamlanır.

Aynı değer facade üzerinden de çalışır:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Büyüyen uygulamalar için yerleşim kalıbı {#a-layout-pattern-that-scales}

Model sayfalarına, rota sayfalarına ve diğer tüm sayfalara hizmet veren tek bir yerleşim:

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

Controller'lar daha sonra `'seoModel' => $post` veya `'seoRoute' => 'blog.index'` iletir; HTML işaretlemesine dokunmaz.

## Ayrı etiketler için yönergeler {#granular-directives}

Etiketleri tek tek kontrol etmeniz gerektiğinde (örneğin başka bir paketin çıktısıyla birlikte kullanırken):

| Yönerge | Ürettiği çıktı |
|---|---|
| `@seoTitle($post)` | Yalnızca `<title>` |
| `@seoMeta($post)` | Yalnızca meta açıklaması |
| `@seoCanonical($post)` | Yalnızca kanonik bağlantı (yedek değer olarak geçerli URL'yi kullanır) |
| `@seoRobots($post)` | Yalnızca robots meta etiketi; her zaman üretilir (açıkça seçilmiş bir kullanımdır, bu nedenle `@seo` yönergesinin varsayılanla aynı değeri gizleme politikasını **uygulamaz**) |
| `@seoSchema($post)` | Yalnızca JSON-LD `<script>` öğesi; head veya body içinde geçerlidir |

Hepsi `@seo` ile aynı `($model, $route, $locale)` ifadesini kabul eder; geçerli sayfa için argümansız da kullanılabilir.

## Hreflang alternatifleri {#hreflang-alternates}

`HasSEO` kullanan modeller, hreflang bağlantılarını doğrudan çözümleyici üzerinden sağlayabilir:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Mutlak URL'ler kullanın. `@seo($post)` bu girdileri çözümler ve her birini `<link rel="alternate" hreflang="..." href="...">` olarak üretir. Kodlar önce BCP 47 biçimine dönüştürülür (`it_IT` → `it-IT`); `seo.hreflang` politikaları sayfanın kendisine başvuran bağlantıyı ve bir `x-default` girdisini ekleyebilir. Ücretsiz denetim geçersiz, yinelenen veya kendine başvurusu eksik girdileri işaretler. Bkz. [çok dilli içerik](/tr/guide/multilingual#hreflang).

## Kaçışlama ve güvenlik {#escaping-and-safety}

Metin değerleri `e()` ile kaçışlanır. JSON-LD, `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` ile kodlanır; böylece kullanıcı içeriğindeki bir `</script>` script öğesinin dışına çıkamaz.
