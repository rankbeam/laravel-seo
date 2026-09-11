---
description: "Her sayfaya, Blade şablonundan arayüzsüz tarayıcıyla oluşturulan kendi 1200×630 Open Graph görselini verin. Başlıklar düzgün satır kırar ve kısaltılır. Ücretsiz çekirdek özelliği, varsayılan olarak kapalıdır."
---

# Oluşturulan OG görselleri {#generated-og-images}

Çekirdek 3.20 itibarıyla Chrome ile oluşturma, JavaScript'i devre dışı bırakır ve HTTP(S), FTP ve WebSocket varlık isteklerini engeller. Özel şablonlar, paketle gelenler gibi statik HTML/CSS ve gömülü varlıklar kullanmalıdır.

Sosyal kartı olmayan sayfa ortak bir `default_og_image` değerine döner; her paylaşımda aynı görsel görünür. Bu özellik her sayfaya **kendi** 1200×630 Open Graph / Twitter kartını verir. Kart, [spatie/browsershot](https://github.com/spatie/browsershot) aracılığıyla gerçek bir arayüzsüz tarayıcıda Blade şablonundan oluşturulur. Böylece başlık satırlara bölünür, aksanlar görünür, CJK doğru yedek yazı tipini kullanır ve aşırı uzun başlıklar düzgün kısaltılır. Elle yazılmış bir görsel kütüphanesi bunları tek başına doğru yapamaz.

Çekirdek paketin ücretsiz bir özelliğidir ve **varsayılan olarak kapalıdır**. Kapalıyken `default_og_image` değişmeden kullanılır ve paket ek bağımlılık gerektirmez.

::: info Tasarım gereği statik ön oluşturma
Kartlar web isteği sırasında anlık değil, önceden bir Artisan komutuyla oluşturulur. Sayfa yalnızca diskte zaten bulunan karta bağlantı verir; ziyaretçi isteği asla tarayıcı başlatmaz veya eksik (404) görsele bağlantı vermez. **Canlı oluşturma uç noktası yoktur** (bkz. [Önemli noktalar](#caveats)).
:::

## Gereksinimler {#requirements}

Tarayıcı sürücüsü isteğe bağlı bağımlılıktır; ücretsiz çekirdek onsuz kurulur. Özelliği açmak için uygulamanızda şunlar gerekir:

```bash
composer require spatie/browsershot
```

Ayrıca Browsershot'ın çalıştırdığı ortam:

- Makinede **Node.js**.
- Node'un bulabilmesi için **uygulama köküne** kurulmuş **Puppeteer**:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer varsayılan olarak kendi Chromium'unu indirir; canlı ortamda genellikle sistemdeki Chrome'u gösterirsiniz (bkz. [`chrome_path`](#configuration)).

::: warning Windows'ta puppeteer paketini uygulama köküne kurun
Windows'ta `npm_module_path` ayarına güvenmek yerine `puppeteer` paketini uygulama köküne kurun. Bu anahtar, POSIX `NODE_PATH=…` öneki üreten Browsershot `setNodeModulePath()` metoduna karşılık gelir ve **Windows'ta etkisizdir**. Node burada modülleri uygulamadan üst dizinlere doğru arayarak çözümler; bu nedenle köke kurulum çalışır. Bkz. [Önemli noktalar](#caveats).
:::

## Etkinleştirme {#enabling}

Henüz yapmadıysanız yapılandırmayı yayımlayın (`php artisan vendor:publish --tag=seo-config`) ve özelliği açın:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Ardından kartları **önceden oluşturun** (bu adımı yapana kadar hiçbir kart oluşturulmaz):

```bash
php artisan seo:og-images
```

## Çözümleme nasıl çalışır? {#how-resolution-works}

Oluşturma işlemi, atadığınız görselin yerini asla almaz. Özellik açıkken çözümleyici `og:image` değerini **yalnızca sayfanın kendi görseli yoksa** doldurur; yani çözümlenen `og:image` boşsa veya hâlâ site genelindeki statik `default_og_image` ise. Modele özel açıkça atanmış görsel (`getSEOImage()`, `seo_meta` satırı, içerik alanı vb. üzerinden) oluşturulan karttan her zaman önceliklidir.

Çözümleyici değeri belirlemek için oluşturucunun **dosya varlığına bağlı** sorgusunu çağırır: kartın depolama yolunu hesaplar ve herkese açık URL'yi **yalnızca dosya yapılandırılmış diskte zaten varsa** döndürür. Asla görsel oluşturmaz. Güvenlik davranışı şudur:

- Web isteği **asla tarayıcı başlatmaz**; en kötü durumda özellik öncesinde olduğu gibi statik `default_og_image` görseline bağlantı verir.
- Sayfa **henüz oluşturulmamış görsele asla bağlantı vermez**; paylaşımların 404'e yöneldiği bir ara durum oluşmaz.

“İçerik değişti” ile “kart var” arasındaki boşluğu [`seo:og-images`](#the-seo-og-images-command) komutunu dağıtımda ve/veya zamanlanmış olarak çalıştırarak kapatırsınız.

## `seo:og-images` komutu {#the-seo-og-images-command}

Çözümleyicinin sunabileceği dosyalar olması için kartları önceden oluşturur.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — kartları hazırlanacak bir veya daha fazla model sınıfı. Tekrarlanabilir. Verilmezse komut `seo.og_image.models` kullanır; yedek olarak [site haritası modellerinizi](/tr/guide/sitemaps) (`seo.sitemap.models`) alır. `seo:llms-txt` gibi site haritası kaynaklarını paylaşır.
- `--force` — mevcut kartları yeniden oluşturur (`cache_version` artırmadan şablonu veya marka renklerini değiştirdiğinizde kullanın).
- `--prune` — hazırlama sonrasında yapılandırılmış yol altındaki, artık mevcut hiçbir modelin içeriğiyle eşleşmeyen kayıtlı kartları siler (aşağıya bakın). Güvenlik için yalnızca adları oluşturulan içerik hash'leri olan dosyaları kaldırır; aynı dizindeki diğer varlıklarınızı asla silmez. **Kapsamı `--model` ile sınırlandırılmış çalıştırmada yok sayılır** (korunacak küme diğer modellerinizi kapsamaz); `--model` olmadan çalıştırın.

Her model `HasSEO` trait'ini kullanmalıdır. Başlıksız kayıt atlanır (karta konacak bir şey yoktur). Komut `generated`, `skipped`, `failed` ve (`--prune` ile) `pruned` sayılarını raporlar.

### Zamanlama {#scheduling}

Kartların içeriğinizi izlemesi için zamanlanmış olarak hazırlayın; başlıklar değişince geride kalan sahipsiz dosyaları temizleyin:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Geçersizleştirme modeli {#the-invalidation-model}

Kartın dosya adı, **piksellerini etkileyen her şeyin hash'idir**: başlık, site adı, şablon adı, sürücü, boyutlar, marka geçiş renkleri, `cache_version` numarası **ve kurulu paket sürümü**.

Bu hash önbellek anahtarıdır ve anlaşılması gereken iki sonucu vardır:

- **Başlığı değiştir → yeni hash → yeni dosya.** Eski kart diskte *sahipsiz* kalır; siz yeniden hazırlayana kadar sayfa statik varsayılana döner. Komut yeni kartı oluşturur; `--prune` sahipsiz dosyayı siler. Geçersizleştirme modeli budur; ayrı bir “tek sayfanın önbelleğini temizle” adımı yoktur.
- **`cache_version` değerini artır veya paketi yükselt → tüm hash'ler değişir.** Şablonu veya marka renklerini düzenledikten sonra tüm kartları bir defada geçersizleştirmek için `cache_version` kullanın. Paket yükseltmesi otomatik hesaba katılır; dolayısıyla paket şablonunu değiştiren yeni sürüm eski kartları sunamaz.

## Paketle gelen şablonlar {#bundled-templates}

Üç şablon gelir; hepsi aynı marka renk geçişi üzerinde 1200×630 boyutundadır:

| Şablon | Uygun kullanım | Gösterdikleri |
| --- | --- | --- |
| `seo::og.default` | Her türlü içerik | Başlık + site adı |
| `seo::og.article` | Blog yazıları, haberler | Başlık üstü bölüm etiketi + başlık + yazar · tarih satırı |
| `seo::og.product` | Ürünler, listeler | Marka bloğu + kategori etiketi + başlık + açıklama |

`seo.og_image.template` ile genel bir şablon seçin veya makale ile ürünün otomatik farklı kartlar alması için şablonları **model türüne göre** eşleyin:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Model, `getOgImageTemplate(): ?string` tanımlayarak çalışma sırasında kendi şablonunu da belirleyebilir (görünüm adı veya eşleme/varsayılana dönmek için `null` döndürün). Öncelik: model kancası, ardından `templates` eşlemesi, son olarak genel `template`.

## Şablonu özelleştirme {#customizing-the-template}

Kart, kendi kendine yeterli HTML belgesine dönüştürülen bir Blade görünümüdür (varsayılan `seo::og.default`). Paket yazı tipi data URI olarak gömülür; tarayıcı ağa ihtiyaç duymaz. İki şekilde değiştirebilirsiniz:

**Paket görünümünü yayımlayıp düzenleyin:**

```bash
php artisan vendor:publish --tag=seo-views
```

Ardından `resources/views/vendor/seo/og/default.blade.php` dosyasını düzenleyin.

**Ya da kendi görünümünüzü gösterin:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Şablon şu değişkenleri alır:

| Değişken | Tür | Notlar |
| --- | --- | --- |
| `$title` | `string` | Atanmışsa OG başlığı, değilse sayfa başlığı. |
| `$siteName` | `?string` | Çözümlenen `og:site_name`. |
| `$fontDataUri` | `string` | `data:` URI olarak paketle gelen kalın yazı tipi (yoksa boş dize; tarayıcı kendi sans-serif yazı tipini kullanır). |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Çıktı genişliği (varsayılan `1200`). |
| `$height` | `int` | Çıktı yüksekliği (varsayılan `630`). |
| `$locale` | `?string` | `<html lang>` özniteliği için çözümlenen sayfa dili. |
| `$author` | `?string` | Makale yazarı (`seo::og.article` kullanır). |
| `$publishedDate` | `?string` | `seo::og.article` için yayımlanma tarihi: varsa sayfa dilinde ICU orta biçimi; yoksa Carbon, ayı `M j, Y` sırasıyla çevirir. Tarih verilmezse null. |
| `$section` | `?string` | İçerik bölümü / kategori (makale üst etiketi, ürün etiketi). |
| `$description` | `?string` | OG açıklaması, yoksa sayfa açıklaması (`seo::og.product` kullanır). |

::: info Şablon adı önbellek anahtarının parçasıdır
Hem şablon **adı** hem renk geçişi değerleri içerik hash'ine katılır. Bu nedenle şablon değiştirmek veya renkleri değiştirmek mevcut kartları otomatik geçersizleştirir. Şablonu *aynı dosyada* düzenlemek bunu yapmaz (ad değişmez). Düzenleme sonrasında `cache_version` değerini artırın veya `--force` çalıştırın.
:::

## Yapılandırma {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Çoğu skaler değerin eşleşen ortam değişkeni vardır (`SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, …); tam liste için yapılandırma dosyasına bakın. Dizi biçimindeki anahtarlar (`templates`, `models`, `browsershot_args`, `font_stack`) doğrudan yapılandırma dosyasında düzenlenir.

Disk **herkese açık sunulmalıdır**; çözümleyici diskin `url()` değerini `og:image` olarak kullanır. `public` diskiyle, `public/storage` yolunun diski göstermesi için bir kez `php artisan storage:link` çalıştırın.

## Linux'ta çalıştırma (sandbox) {#running-on-linux-the-sandbox}

Chrome'un sandbox mekanizmalarını kısıtlayan makinelerde `php artisan seo:og-images` şu hatayla başarısız olabilir:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Olası nedenlerden biri Ubuntu 23.10+ üzerinde kısıtlanmış kullanıcı ad alanlarıdır. [Puppeteer sorun giderme kılavuzunu](https://pptr.dev/troubleshooting) ve tarayıcının gerçek başlatma hatasını kontrol edin. Chrome'un sandbox korumasını tutabilmesi için makine yapılandırmasını düzeltmeyi tercih edin.

**1. Açıkça seçilen yedek yol: Chrome'u `--no-sandbox` ile çalıştırın.** Bu, tarayıcı yalıtımını kapatır. Yalnızca dağıtımınız bu ödünleşimi bilerek kabul ediyorsa kullanın:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam statik olarak üretilmiş HTML'i işler ve uzak varlık isteklerini engeller; ancak bu kontroller Chrome sandbox'ının yerini almaz. Görsel oluşturma sürecini ayrıcalıksız tutun, ilgisiz iş yüklerinden ve sırlardan yalıtın.

**2. Sandbox'ı koruyun.** `no_sandbox` kapalı kalsın. Neden AppArmor ise tam olarak kullanılan Chrome çalıştırılabilir dosyasına uygun profil hazırlayın; [Chromium kılavuzuna](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md) bakın. Örneğin:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Ardından profili `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` ile yükleyin ve Chrome'un sandbox etkin olarak başladığını doğrulayın.

::: tip Diğer bayraklar
Paylaşılan belleği yetersiz bir container için (diğer yaygın Linux hatası: oluşturma sırasında Chrome'un çökmesi), `browsershot_args` üzerinden bayrak ekleyin:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Özel sürücüler {#custom-drivers}

Paketle gelen tek sürücü `browsershot` olsa da renderer bir sözleşmenin (`Rankbeam\Seo\Contracts\OgImageRenderer`) arkasındadır. Kendi sürücünüzü, örneğin canvas veya servis tabanlı renderer'ı kaydedin ve `seo.og_image.driver` ile seçin:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Sürücü yalnızca kendi kendine yeterli HTML dizesini belirtilen boyutta PNG baytlarına dönüştürür; yerleşim veya şablonlama sorumluluğu yoktur.

## Yazı tipleri ve Latin dışı yazı sistemleri {#fonts-and-non-latin-scripts}

Paketle gelen kart yazı tipi (Noto Sans Bold, OFL), **Latin, Kiril ve Yunancayı** kapsar. Diğer her yazı sistemi — Çince, Japonca, Korece, Tayca, Arapça, İbranice, Devanagari, emoji — **`seo:og-images` çalıştıran makinede kurulu** yazı tiplerinden gelir. Başka yazı tipi bilinçli olarak paketlenmez: bir CJK yazı tipi 16 MB'dan büyüktür ve uygun yazı tipi makinede bulunduğunda Chrome'un karakter başına yedek seçimi doğru çalışır.

Bunu güvenilir kılan üç unsur vardır (3.15):

1. **Her paket şablonunda yazı sistemine göre `font-family` listesi.** Body önce `'OGBrand'` (paket yazı tipi), ardından `seo.og_image.font_stack` bildirir. Varsayılan liste: `Noto Sans`, dört `Noto Sans CJK` ailesi, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari`, `Noto Color Emoji`; son olarak `sans-serif`. Chrome her karakter için kurulu ilk aileye döner; bulunmayan aile atlanır, dolayısıyla liste yalnızca yardımcı olur. **Sayfa dilinin CJK ailesi öne taşınır** (`ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR). Çünkü aynı Han kod noktası, her bölgesel yazı tipinde farklı çizilir (Han birleştirmesi). `<html lang>` özniteliği sayfa dilini BCP 47 biçiminde taşır. Liste önbellek anahtarının parçasıdır; değişmesi her kartı yeniden oluşturur.

2. **`seo:og-images` içinde ön kontrol.** Komut, oluşturmadan önce fontconfig'e (`fc-list :lang=ja`, `th`, `ar`, …) bir yazı tipinin başlık, site adı ve açıklamadaki yazı sistemlerini kapsayıp kapsamadığını sorar; karma metindeki azınlık yazı sistemi de dahildir. Kurulacak paketi belirterek **yazı sistemi başına bir kez** uyarır:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   fontconfig olmayan ortamlarda (Windows, macOS, asgari container) tahmin yürütmek yerine sessiz kalır. Yazı tipi eksikliği görsel oluşturmayı başarısız kılmaz; Chrome .notdef kutuları çizer. Uyarının var olma nedeni budur.

3. **Canlı smoke testinde yazı sistemi başına glif örneği.** `SEO_OG_IMAGE_LIVE_TEST=1` ile `tests/Feature/OgImage/BrowsershotSmokeTest.php`, ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he ve hi dillerindeki bir başlığı, atanmamış kod noktasından oluşan aynı uzunlukta kontrol metninin (kesinlikle kutular) yanında oluşturur. İki PNG bayt düzeyinde aynıysa yazı sistemini ve paketi belirterek başarısız olur. Bu bir temel çalışma kontrolüdür; her glifin kanıtı değildir. Karma Latin metin veya farklı satır kırılmaları, bazı glifler eksikken bile görselleri farklı kılabilir. Gerçek çıktıyı ve dağıtım makinesinde kullanılan yazı tiplerini inceleyin. Dil düzeyindeki FontProbe uyarısı da ön kontroldür; eksiksiz yazı tipi kapsamı sertifikası değildir. Çekirdekte `seo:doctor` komutu yoktur; bu ön kontrol için `seo:og-images` kullanın.

Debian/Ubuntu'da:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

3.15 öncesinde `--tag=seo-views` ile yayımlanmış kendi şablonlarınız çalışmayı sürdürür: yeni `$fontFamily` ve `$lang` değişkenlerini alırlar, ancak bunları kullanmak zorunda değildirler.

## Önemli noktalar {#caveats}

Canlı ortamda sorun çıkardıkları için açıkça belirtilmiştir:

- **Yalnızca ön oluşturma; canlı oluşturma uç noktası yoktur (v1).** İstek üzerine kart oluşturan rota bulunmaz. Web isteğinde hiçbir kart oluşturulmadığından **yapılandırılacak veya savunulacak imzalı URL / SSRF / DoS yüzeyi yoktur**. Bunun karşılığında kartların var olması için [`seo:og-images`](#the-seo-og-images-command) komutunu dağıtımda ve/veya zamanlanmış olarak çalıştırmalısınız.
- **`npm_module_path` Windows'ta etkisizdir.** Komutun başına POSIX `NODE_PATH=…` ekleyen Browsershot `setNodeModulePath()` metoduna karşılık gelir; Windows bunu yok sayar. Windows'ta `puppeteer` paketini **uygulama köküne** kurun; Node üst dizinleri arayarak bulsun. (Linux/macOS'ta ayar beklendiği gibi çalışır.)
- **Latin dışı yazı sistemleri makinede yazı tipi gerektirir.** Bkz. [Yazı tipleri ve Latin dışı yazı sistemleri](#fonts-and-non-latin-scripts): paket yazı tipi Latin, Kiril ve Yunancayı kapsar; diğerleri dağıtım imajına kurulan yazı tiplerinden gelir ve komut eksikliği bildirir.
- **Hata durumunda mevcut görselle devam eder.** Oluşturma başarısızsa (eksik paket, tarayıcı çökmesi, zaman aşımı) komut bunu raporlar; sayfa statik `default_og_image` görselini korur. Bozuk tarayıcı sayfada asla 500 hatasına yol açmaz.
