---
description: "XML site haritaları üretin: kaynak başına bir dosya ve /sitemap.xml adresinde sunulan dizin. Model, closure veya URL listelerinden kaynak kaydedin; spatie/laravel-sitemap kullanır."
---

# Site haritası kayıt sistemi {#sitemap-registry}

Paket XML site haritaları oluşturur (kaynak başına bir dosya ve bir dizin) ve bunları `/sitemap.xml` ile `/sitemap-{name}.xml` adreslerinde sunar. Oluşturma işlemi [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap) paketini kullanır:

```bash
composer require spatie/laravel-sitemap
```

## Kaynak kaydetme {#registering-sources}

Adlandırılmış kaynakları bir servis sağlayıcısının `boot()` metodunda kaydedin:

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

Her kaynak `sitemap-{name}.xml` dosyasına yazılır; `sitemap.xml` tümünü listeleyen dizin olur.

Kayıt sistemi API'si ayrıca `has($name)`, `names()`, `forget($name)` ve `flush()` sunar.

## Yapılandırma üzerinden kaynaklar {#config-driven-sources}

Yapılandırmayı mı tercih ediyorsunuz? `config/seo.php` model kaynaklarını ve statik URL'leri kabul eder:

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

::: info Otomatik keşif, kayıt sistemine öncelik verir
Bir model adlandırılmış kayıtlı kaynak tarafından kapsanıyorsa otomatik keşif onu atlar; `'posts'` kaydetmek ayrıca bir `sitemap-post.xml` üretmez.
:::

## Oluşturma {#generating}

```bash
php artisan seo:sitemap
```

Dosyalar `seo.sitemap.disk` altında yapılandırılan diske yazılır (varsayılan `public`). Site haritalarını güncel tutmak için komutu zamanlayın:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

`seo.sitemap.max_urls_per_sitemap` değerini aşan site haritaları otomatik bölünür (varsayılan 50.000; XML belirtiminin sınırı).

## Sunma {#serving}

Paket rotaları, komutun ürettiği çıktıyı XML başlıkları, önbellek başlıkları ve `X-Robots-Tag: noindex` ile sunar:

- `/sitemap.xml` — dizin (veya tek site haritası)
- `/sitemap-posts.xml` — adlandırılmış kaynak

Bunun yerine kendi statik site haritanızı mı sunuyorsunuz? Rotaları kapatın:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Tarayıcıda biçimlendirilmiş site haritası {#styled-sitemap-in-the-browser}

Spatie'nin motoru ham XML çıktısı üretir. Rankbeam site haritasını tarayıcıda açtığınızda ise okunabilir, markalı bir sayfa görürsünüz: her URL bir tabloda `lastmod`, değişim sıklığı, öncelik, görsel/alternatif sayıları ve satır içi doğrulama notlarıyla gösterilir:

![Tarayıcıda okunabilir, markalı tablo olarak görüntülenen Rankbeam site haritası](/sitemap-styled.png)

Bu, oluşturulan her site haritasından bir XSL stil dosyasına başvurularak çalışır:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Arama motorları bu talimatı **yok sayar**; site haritası normal, makine tarafından okunabilir XML belgesi olarak kalır. Yalnızca *insanın* gördüğü görünüm değişir. Dizin ve tüm alt site haritaları aynı biçimde sunulur.

**Varsayılan olarak açıktır.** Görsel/hreflang uzantılarının aksine stil dosyası veri eklemez ve kayıt başına işlem yapmaz; arama motoru tarayıcılarının atladığı tek bir talimat satırıdır. Bu yüzden kurulumla birlikte açıktır. Düz XML üretmek için kapatın:

::: warning spatie/laravel-sitemap ≥ 8.1 gerekir
Talimat, `spatie/laravel-sitemap` **8.1** sürümünde eklenen Spatie `setStylesheet()` metodu üzerinden yazılır. Uygulamanız daha eski bir sürüm çözümlüyorsa (bazı PHP/Laravel birleşimlerinde olur) site haritaları düz, biçimlendirilmemiş XML olarak üretilir; hiçbir şey bozulmaz. Biçimlendirilmiş görünüm için `composer update spatie/laravel-sitemap` kullanın.
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Doğrulama notları {#validation-notes}

Oluşturulan sayfa, tarayıcıdan ayrılmadan kontrol edebildiği iki durumu işaretler:

- **`lastmod` değeri eksik URL'ler** — eksik bilgi gösterilir, asla uydurulmaz. Google, güncellik konusunda yanlış bilgi veren site haritasına daha az değer verir; bu nedenle stil dosyası boşluğu doldurmak yerine belirtir.
- **Mutlak olmayan URL'ler** — mutlak `http(s)` URL'si olmayan bir `<loc>`.

### Stil dosyasını kendiniz barındırma {#self-hosting-the-stylesheet}

Paket varsayılan olarak stil dosyasını kendi `/sitemap.xsl` rotasından sunar ve her site haritasını buna yönlendirir. Tarayıcılar yalnızca site haritasıyla **aynı kökenli** XSLT'yi uygular. Site haritalarınız başka bir kökende (örneğin CDN'de) bulunuyorsa dosyayı yayımlayın ve yapılandırmada kendi kopyanızı gösterin:

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

::: info Yapısı gereği güvenli
Stil dosyasının ürettiği her değer, URL'ler dahil, XSLT çıktı kaçışlamasından geçer. Bir `<loc>` yalnızca `http(s)` URL'siyse tıklanabilir bağlantıya dönüşür; böylece kötü amaçlı URL içeriği sayfaya HTML veya `javascript:` bağlantısı enjekte edemez. Yayımlanmış `.xsl` dosyasını özelleştirirseniz bu davranışı koruyun: `disable-output-escaping` eklemeyin.
:::

## Neler dahil edilir? {#what-gets-included}

Model kaynakları, dizine eklenebilir olarak çözümlenen kayıtları içerir; robots değeri `noindex` olarak çözümlenen model site haritasına girmez. URL'ler, kanonik bağlantıları da sağlayan `getUrlForSEO()` metodundan gelir; böylece site haritası ve kanonik bağlantı çelişmez.

## Görsel ve hreflang uzantıları {#image-hreflang-extensions}

İsteğe bağlı iki uzantı, her model URL'sini paketin o kayıt için zaten çözümlediği verilerle zenginleştirir. İkisi de **varsayılan olarak kapalıdır**; istediklerinizi `config/seo.php` içinde etkinleştirin:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

`HasSEO` trait'ini kullanan modellere uygulanırlar (değerler modelin tamamen çözümlenmiş `seoData()` verilerinden gelir):

- **`images`**, çözümlenen OG/içerik görselinden oluşturulan bir [Google görsel site haritası](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) girdisi ekler. Bu, `og:image` olarak üretilen değerle *aynıdır*; böylece site haritası sayfayla çelişmez. Kaydın kendi görseli yoksa bu değer site genelindeki `default_og_image` olur. Bu nedenle yalnızca URL başına görsel içeriğiniz için anlamlıysa etkinleştirin.
- **`alternates`**, modelin `getSEOAlternates()` metodundan `<xhtml:link rel="alternate" hreflang="…">` girdileri ekler; sayfanın `<head>` bölümünde üretilen hreflang bağlantılarıyla aynıdır. Mutlak URL'ler döndürün:

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

::: warning hreflang karşılıklı olmalı ve kendine başvurmalıdır
Google, bir bildirimi yalnızca her dil sürümü **kendisini ve diğer tüm sürümleri** listeliyorsa ve başvurular **karşılıklıysa** (her sayfa geri bağlantı veriyorsa) dikkate alır. Dolayısıyla `getSEOAlternates()` **tam** kümeyi döndürmeli ve her yerelleştirilmiş sürüm aynı tam kümeyi döndürmelidir. Geçerli `language[-Script][-REGION]` kodları veya `x-default` ve mutlak `http(s)` URL'leri kullanın. Boş olmayan `hreflang` veya `href` değeri eksik girdiler atlanır.

Liste yazılmadan önce [`seo.hreflang` politikalarından](/tr/guide/multilingual#hreflang) geçer: kodlar BCP 47 biçimine normalleştirilir (`it_IT` → `it-IT`); `include_self` / `x_default`, kendine başvuruyu ve `x-default` girdisini sizin için ekleyebilir. Site haritası her zaman sayfanın `<head>` bölümüyle aynı listeyi taşır. Ücretsiz denetim `hreflang_invalid_code`, `hreflang_duplicate_code` ve `hreflang_missing_self` bildirir; karşılıklılık için tarama gerekir (Pro).
:::

::: info Büyük ölçekte maliyet
**Çekirdek 3.20.1** itibarıyla modelin dahil edilmesi ve görsel/hreflang uzantıları, her model URL'si oluşturulurken aynı çözümlenen `seoData()` verisini yeniden kullanır. Bu yeniden kullanım, hata durumları dahil, URL tamamlanınca biter; sonraki oluşturma veya başka dil için yeni veri çözümlenir. 3.20.0'da çözümleyici önbelleği kapalıyken (varsayılan durum) dahil etme ve uzantılar öncelik zincirini iki kez dolaşabiliyordu. Her çözümleme hâlâ önbellek/veritabanı işlemleri yapabilir; özel `getSEO*()` getter'ları ek sorgular oluşturabilir. Web isteği yerine **zamanlanmış** `seo:sitemap` komutunu kullanın. 50.000 URL sınırına yakın ölçekte performans ölçün ve bu girdiler gerekmiyorsa uzantıları kapalı tutun.
:::

::: tip Yapılandırmayı daha önce yayımladınız mı?
`config/seo.php` **yüzeysel** birleştirilir; bu sürümden önce yapılandırma dosyasını yayımlamış uygulama `sitemap.images` / `sitemap.alternates` anahtarlarını otomatik almaz. `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` ortam değişkenleri tek başına bunları açmaz. İki anahtarı yayımlanmış `sitemap` dizisine ekleyin (yukarıdaki bloğa bakın) veya yapılandırmayı yeniden yayımlayın.
:::

## Tam kontrol: elle oluşturulan Spatie etiketleri {#full-control-hand-built-spatie-tags}

Çözümlenen verilerin kapsamadığı durumlar için — görsel açıklamaları, **video** veya **haber** girdileri ya da özel `hreflang` kümeleri — kayıtlı kaynaktan tamamen elle oluşturulmuş bir [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) döndürün. Oluşturucu `Url` etiketlerini aynen geçirir ve kendi uzantılarını asla eklemez; böylece tam kontrol sizde kalır:

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

Aynı özel çıkış yolu kayıt başına da vardır: `Sitemapable` uygulayan bir modelin `toSitemapTag()` metodu `Url` döndürüyorsa bu değer tam olarak döndürüldüğü haliyle üretilir.
