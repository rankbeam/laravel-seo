---
description: config/seo.php içindeki tüm seçenekler, çözümleyici katmanına göre gruplandırılmış ve her değerin paketle gelen varsayılanıyla birlikte.
---

# Yapılandırma {#configuration}

Yapılandırma dosyasını yayımlayın:

```bash
php artisan vendor:publish --tag=seo-config
```

Aşağıdakilerin tamamı `config/seo.php` içinde yer alır. Gösterilen değerler varsayılanlardır.

## Site geneli varsayılanlar (katman 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

Çözümlenen başlık zaten `title_suffix` ile bitmiyorsa bu son ek başlığa eklenir.

`title_suffix_skip_when_contains`, markaya göre son eki bastıran bir listedir. Çözümlenen başlık bu belirteçlerden birini zaten **tam sözcük olarak** içeriyorsa (büyük/küçük harfe duyarsız ve sözcük sınırlarını dikkate alarak; dolayısıyla `Acmestic`, `Acme` ile eşleşmez), markanın başlıkta gereksiz yere iki kez geçmesini önlemek için son ek atlanır. Varsayılan `[]`, önceki davranışı korur.

## Robots çıktı politikası {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Çözümlenen yönerge yukarıdaki `default_robots` değerine eşitse üretilen `<head>`, `<meta name="robots">` etiketini içermez; gereksiz bir `index,follow` yalnızca gürültüdür ve arama motoru tarayıcısı bu etiketin yokluğunu tam olarak index,follow olarak yorumlar. **Farklı** bir yönerge (`noindex`, `nofollow`, `max-snippet:-1`, …) her zaman aynen üretilir. Etiketi her zaman üretmek için `emit_default` değerini `true` yapın (3.1 öncesi davranışı geri getirir). Ayrıntılı `@seoRobots` yönergesi etkilenmez; açıkça etkinleştirilir ve her zaman üretilir. Desteklenen yönergeler ve öncelikleri için [Çıktı sözleşmesine](/tr/contributing/rendering-contract) bakın.

## Dizine ekleme koruması (canlı ortam dışı güvenlik ağı) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Etkinleştirildiğinde ve uygulama `allowed_environments` içinde **bulunmayan** bir ortamda çalıştığında koruma, her sayfada `noindex,nofollow` değerini zorunlu kılar (tüm öncelik zincirinin üzerinde olduğundan, saklanmış sayfa değerini bile geçersiz kılar), bununla eşleşen `X-Robots-Tag` üst bilgisini gönderir, tüm taramayı reddeden `robots.txt` üretir ve `seo:audit` çıktısında bir uyarı bandı gösterir. İzin verilen ortamlarda (varsayılan olarak `production`) işlem yapmaz.

Paketle **kapalı** gelir (siz etkinleştirene kadar çıktı bayt düzeyinde aynıdır); `SEO_INDEXING_GUARD=true` ile etkinleştirin, `SEO_INDEXING_GUARD=false` ile kapatın; her ikisi de tek satırdır. İzin listesini `SEO_INDEXING_GUARD_ALLOWED` ile değiştirin (virgülle ayrılır; `prod*` gibi `Str::is()` joker karakterleri çalışır; boş liste her ortamı korumaya alır).

`send_header` (koruma içinde varsayılan olarak açık), uygulama üzerinden yönlendirilen her yanıtta ayrıca `X-Robots-Tag: noindex,nofollow` gönderir. Böylece `<meta robots>` içermeyen PDF'ler, akışlar ve görseller için de dizine eklememe yönergesi gönderilir; ara katman yalnızca koruma etkin olduğunda kaydedilir. Kesinlikle önerilir; tam [Dizine ekleme koruması kılavuzuna](/tr/guide/indexing-guard) bakın.

## Kanonik URL'ler {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Çözümleyicinin **türettiği** bir kanonik URL'nin (istek URL'sinden veya modelin `getUrlForSEO()` değerinden) sorgu dizesi varsayılan olarak çıkarılır; izleme, filtreleme ve sıralama parametreleri aynı sayfa için yinelenen içerikli kanonik hedefler oluşturur. Anahtarları `query_whitelist` içinde listelediğinizde, diğer tüm parametreler çıkarılmaya devam ederken bunlar türetilen kanonik URL'lerde bu sırayla **korunur**. Yaygın kullanım, sayfalandırılmış arşivler için `page` değeridir (`/blog?page=2`, gerçekten de `/blog` ile aynı değildir).

**Açıkça atanmış** bir kanonik URL (yönetim panelinden girilmiş veya daha yüksek öncelikli bir katmandan gelen) her zaman sorgu dizesi dâhil aynen üretilir; izin listesi yalnızca türetilen yedek değeri yönetir. Varsayılan `[]`, tüm parametreleri çıkarma davranışını korur.

## Özellik anahtarları {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

Bir `HasSEO` modeli oluşturulduğunda `auto_create_meta`, boş bir `seo_meta` satırı oluşturur (not: `WithoutModelEvents` kullanan seeder'lar bunu atlar).

## Odak anahtar kelimeler {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Odak anahtar kelime **iş akışını etkinleştiren anahtar**. `false` olduğu sürece (varsayılan), odak anahtar kelimesi olmayan sayfa hiçbir yerde işaretlenmez; ne [`seo:audit`](/tr/guide/audit) ne de Pro taraması bunu sorun sayar. Böylece odak anahtar kelimeleri hiç benimsemeyen uygulama, kullanmadığı bir özellik hakkında sürekli uyarılmaz. Odak anahtar kelimeleri atamaya başladığınızda (örneğin [Filament odak anahtar kelime alanıyla](/tr/guide/filament)) bunu açın; ücretsiz denetim, Pro taraması ve Pro editörü hâlâ anahtar kelimesi olmayan sayfalarda `missing_focus_keyword` bildirimini raporlamaya başlar. Aynı anahtarı okuduklarından her zaman tutarlı sonuç verirler.

## Ücretsiz denetim (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

`--model` seçeneği verilmediğinde ücretsiz [`seo:audit`](/tr/guide/audit) komutunun denetlediği modeller. Her biri `HasSEO` trait'ini kullanmalıdır. Liste boşsa komut, `sitemap.models` altında kayıtlı modellere döner.

## Hesaplanan yedek değerler (katman 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

İsteğe bağlı `best` stratejisinde oluşturucu, sıralı aday listesini her görselin piksel boyutlarının ideale yakınlığına göre puanlar ve **minimumun altında kalanları atlar**. Önce `getSEOImage()` (en yüksek öncelikli aday olmaya devam eder), ardından modelin `getSEOImages()` kancası, yaygın görsel alanları, içeriğin ilk görseli ve yapılandırılmış varsayılan gelir. Yalnızca **yerel** görseller ölçülür (`public/` altında göreli yol, public diski veya kendi sunucunuzdaki mutlak URL); uzak URL hiçbir zaman alınmaz ve yalnızca yedek olarak kullanılır. Hiçbir yerel aday minimumu karşılamazsa seçim ilk eşleşmeye döner; böylece `best`, hiçbir zaman `first` stratejisinin döndüreceğinden daha azını döndürmez. Adayları modelinizden sunun:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Site haritaları {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Programatik kaynaklar için [site haritası kayıt sistemi kılavuzuna](/tr/guide/sitemaps) bakın.

## Şema (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Bu değerler [şema grafının](/tr/guide/schema) düğümlerini besler.

## Rotalar {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Uygulamanız kendi statik `/sitemap.xml` dosyasını sunuyorsa `enabled => false` ayarını yapın.

## Önbellek {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Çözümleyici sonuç önbelleği {#resolver-result-cache}

`SEOResolver`, **her** ön yüz üretiminde tüm öncelik zincirini çalıştırır: yapılandırma → genel / model türü / rota varsayılanları → hesaplanan model değerleri → açıkça atanmış `seo_meta` → başlık son eki / kanonik URL / şema. Yoğun trafikli bir sitede (referans uygulama günde yaklaşık 20 bin istek alır) bu, sayfa başına birkaç veritabanı okumasıdır.

`cache.resolver.enabled` ayarını etkinleştirdiğinizde modelin tamamen çözümlenmiş SEO verileri önbelleğe alınır ve **önbellekte eşleşme bulunması öncelik zincirini tamamen atlar**. Paket performans ölçümünde, hazır önbellekten okuma **sıfır** veritabanı sorgusu çalıştırırken önbelleksiz her çözümleme modelin `seo_meta` verisini yeniden okur. Önbelleğe alınan veri düz bir dizidir ve `SEOData::fromArray()` ile yeniden oluşturulur (asla nesne değildir; Laravel 13, `cache.serializable_classes = false` ile geldiğinden önbelleğe alınmış nesne `__PHP_Incomplete_Class` olarak döner).

Yukarıda yapılandırılmış `store` kullanılır. Bu nedenle canlı ortamda **paylaşılan, kalıcı bir önbelleğe** (`redis` / `memcached`) yönlendirin; önbellek ve geçersizleştirmeleri her web/kuyruk işçisi tarafından görülebilmelidir. Böyle bir önbelleğiniz olana kadar kapalı bırakın.

**Geçersizleştirme otomatik ve doğrudur**; önbellek AÇIKKEN ve KAPALIYKEN çözümleme aynı sonucu verir. Girdiler `(model class, id, locale, route, request URL)` ile anahtarlanır ve şu durumlarda temizlenir:

- Sayfanın `seo_meta` satırı **kaydedildiğinde veya silindiğinde** (her yoldan: `saveSEO()`, Filament, doğrudan `SEOMeta` yazımı).
- Modelde bir **içerik alanı** değiştiğinde; `getSEOContentFields()` içindeki sütunlar (varsayılan liste tüm yerleşik hesaplanan yedek değer alanlarını içerir: başlık alanları, excerpt/summary/content/body/text/article alanları ve `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` ve `hero_image` gibi yaygın görsel alanları; modeliniz SEO'yu ek sütunlardan hesaplıyorsa listeyi özelleştirin).
- **Herhangi bir `seo_defaults` satırı** değiştiğinde (bir varsayılan herhangi bir modeli besleyebileceğinden tüm çözümleme önbelleği temizlenir).

**Etiket destekleyen** bir depoda (`redis`, `memcached`, `array`) modelin girdileri önbellek **etiketleriyle** temizlenir. **Etiket desteklemeyen** depoda (`file`, `database`) paket, model başına **sürüm damgasına** başvurur. Her ikisi de anahtarları taramadan çalışır.

::: tip
Yalnızca modele dayalı çözümlemeler önbelleğe alınır. Elle oluşturulmuş `SEOData` için `SEO::render()`/`@seo()` ve modelsiz rota için `@seoForRoute()` her çağrıda çözümlemeye devam eder.
:::

::: warning
Önbellek, modelin `updated_at` / hesaplanan `modified_time` değerini son **içerik alanı** değişikliği itibarıyla (veya TTL dolana kadar) yansıtır. Bir `getSEOContentFields()` sütununu değiştirmeden yalnızca `updated_at` değerini ilerleten yalın `touch()`, yeniden çözümlemeyi zorunlu kılmaz; `article:modified_time` en fazla TTL süresi kadar geriden gelebilir. Önbelleğin hemen geçersiz kılınmasını istiyorsanız uygulamanıza özgü hesaplanan sütunları `getSEOContentFields()` içine ekleyin.
:::
