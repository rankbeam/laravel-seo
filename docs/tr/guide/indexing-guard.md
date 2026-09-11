---
description: "Dizine eklenebilirliği Laravel ortamına bağlayın. İzin listeniz dışındaki hazırlık ve yerel kopyalarda noindex zorlanır ve tarayıcı botlar engellenir; bu kopyaların aramaya sızmasını önleyin."
---

# Dizine ekleme koruması (canlı ortam dışı güvenlik ağı) {#indexing-guard-non-production-safety-net}

Sitenizin bir hazırlık veya yerel kopyasının Google'a sızması, en yaygın ve en zararlı SEO hatalarından biridir: asıl sayfalarınızla rekabet eden yinelenmiş içerik, dizinde yer alan özel bir ortam ve URL kaldırma aracıyla haftalar süren temizlik. Yaygın neden, ayarlamayı unuttuğunuz bir `.env` dosyasında kalan `noindex` veya dağıtım sırasında üzerine yazılan bir robots kuralıdır.

**Dizine ekleme koruması** bunu yapısal olarak zorlaştırır. Dizine eklenebilirliği birinin hatırlaması gereken bir bayrak yerine Laravel *ortamına* bağlayın: uygulama izin listenizde olmayan herhangi bir ortamda çalıştığında her sayfada `noindex,nofollow` zorlanır, yönetilen `robots.txt` tüm tarayıcı botları engeller ve `seo:audit` bu durumu açıkça belirtir.

Bu, çekirdek paketin ücretsiz bir özelliğidir.

## Etkinken ne yapar? {#what-it-does-when-active}

`app()->environment()`, `seo.indexing_guard.allowed_environments` içinde **değilse** (ve koruma etkinse) dört işlem otomatik gerçekleşir:

1. **Çözümleyici her sayfada `noindex,nofollow` zorlar.** Bu işlem tüm [öncelik zincirinin](/tr/concepts/resolver-precedence) *üzerinde* uygulanır; `seo_meta` içinde saklanan, sayfaya özel ve açıkça atanmış `robots` değerini bile geçersiz kılar.
2. Uygulama üzerinden yönlendirilen her yanıtta bir **`X-Robots-Tag: noindex,nofollow` HTTP başlığı** gönderilir; aşağıdaki [HTML dışı yanıtlar](#non-html-responses-pdfs-feeds-images) bölümüne bakın.
3. **`SEO::robotsTxt()->build()`, tümüne erişimi yasaklayan bir `robots.txt` üretir** (ayrıca `ai.txt`): düz bir `User-agent: *` / `Disallow: /`. Bu, hem `seo:robots-txt` komutunu hem de isteğe bağlı [dinamik rotayı](/tr/guide/ai-crawlers) kapsar.
4. **`seo:audit` belirgin bir uyarı bandı gösterir.** Böylece bir raporu okurken “her şey noindex” durumu sizi şaşırtmaz.

İzin verilen ortamlarda (varsayılan olarak `production`) koruma tamamen **etkisizdir**: çıktıyı hiç değiştirmez; üretilen çıktı bayt düzeyinde aynıdır.

## HTML dışı yanıtlar (PDF'ler, beslemeler, görseller) {#non-html-responses-pdfs-feeds-images}

Zorlanan `robots` **meta etiketi** yalnızca HTML ayrıştıran tarayıcı botlara ulaşır. PDF, RSS/Atom beslemesi, görsel veya başka bir HTML dışı yanıt `<head>` taşımaz. Bu nedenle koruma etkinken aynı yönerge, genel bir middleware aracılığıyla HTTP başlığı olarak da gönderilir:

```http
X-Robots-Tag: noindex,nofollow
```

Başlık ve meta etiketi aynı kaynaktan gelir; bu nedenle birbirleriyle çelişemezler. **Korumanın içinde varsayılan olarak açıktır** (korumanın kendisi isteğe bağlıdır ve izin verilen ortamlarda etkisizdir). Yalnızca meta etiketi davranışını korumak için kapatın:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Middleware **yalnızca koruma etkinleştirildiğinde** kaydedilir; koruma kapalıysa paket middleware yığınınıza hiçbir şey eklemez.

::: warning Statik dosyalar PHP'yi atlar
Web sunucunuzun doğrudan `public/` dizininden döndürdüğü bir dosya Laravel'e hiç girmez; dolayısıyla bu başlığı alamaz. Bu dosyaları ağın dış katmanında koruyun (web sunucusu yapılandırması / CDN). Koruma, uygulama üzerinden yönlendirilen her şeyi kapsar.
:::

## Neden açıkça atanmış robots değerini geçersiz kılar? {#why-it-overrides-an-explicit-robots-value}

Rankbeam'in diğer tüm noktalarında açıkça kaydedilen değer kazanır; öncelik zincirinin amacı budur. Koruma, bilerek tanımlanmış tek istisnadır ve açıkça atanan değer katmanının *üzerinde* yer alır. Çünkü burada risk tek yönlüdür:

- Hazırlık veritabanı genellikle canlı ortamın bir kopyasıdır. Bu nedenle `index,follow` kaydetmiş bir sayfa, yönergeyi hazırlık ortamına taşır ve dizine eklenmeyi ister.
- **Hazırlık ortamını yanlışlıkla dizine eklemek ciddi zarardır; ona yanlışlıkla `noindex` uygulamak ise bir şeyi değiştirmez.** Dolayısıyla koruma, zaten hiçbir zaman dizine eklenmesini istemediğiniz ortamlarda, kayıtlı değerin aşamayacağı bir alt sınırdır.

## Etkinleştirme {#enabling-it}

Koruma **kapalı** gelir. Böylece paketi kurmak veya yükseltmek, onayınız olmadan canlı ortam dışındaki çıktıyı asla değiştirmez (çözümleyicinin [`blank_is_unset`](/tr/concepts/resolver-precedence) özelliği ve oluşturulan OG görselleriyle aynı, etkinleştirilene kadar bayt düzeyinde aynı çıktı politikası). Tek satırla etkinleştirin:

```dotenv
SEO_INDEXING_GUARD=true
```

Varsayılan izin listesiyle `production` etkilenmez; bu nedenle korumayı ortak yapılandırmada açık tutabilirsiniz. İzin listesinin dizine eklenmesini istediğiniz tüm ortamları kapsadığını kontrol edin. Kullanımı **kuvvetle önerilir**; Core 4'te varsayılan olarak açılması değerlendirilmektedir.

Aynı tek satırla kapatın:

```dotenv
SEO_INDEXING_GUARD=false
```

## Hangi ortamların dizine eklenebileceğini seçme {#choosing-which-environments-may-index}

Varsayılan olarak yalnızca `production` ortamına izin verilir. Listeyi virgülle ayrılmış bir ortam değişkeniyle değiştirin:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Veya `config/seo.php` içinde:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Girdiler `Str::is()` ile eşleştirilir; dolayısıyla **joker karakterler** çalışır. `'prod*'`, `production` ve `prod-eu` ile eşleşir:

```php
'allowed_environments' => ['prod*'],
```

**Boş** liste, *hiçbir* ortamın dizine eklenemeyeceği anlamına gelir: koruma her yerde etkindir (güvenli yönde başarısız olur). Boş veya yalnızca boşluk içeren bir `SEO_INDEXING_GUARD_ALLOWED` ortam değeri `['production']` değerine döner; böylece bir yazım hatası canlı ortamı sessizce dizin dışı bırakamaz. Gerçekten “her yerde” istiyorsanız yapılandırmaya açıkça `[]` yazın.

## Doğrulama {#verifying-it}

`seo:audit` uyarı bandını gösterir; `--json` çıktısında makine tarafından okunabilir durumu taşır:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

Korunan bir ortamda sunulan/oluşturulan `robots.txt` ise şöyledir:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Kapsam {#scope}

Koruma **dizine ekleme yönergelerini** kontrol eder: `robots` meta etiketi, `X-Robots-Tag` başlığı ve `robots.txt`. Başlıklarınıza, açıklamalarınıza, kanonik URL'lerinize veya şemanıza dokunmaz; [robots çıktı politikasından](/tr/concepts/resolver-precedence) (`seo.robots.emit_default`) bağımsızdır. `noindex,nofollow` site varsayılanından farklı olduğu için her zaman etiket olarak üretilir.
