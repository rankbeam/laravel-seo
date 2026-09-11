---
description: "URL yayınlandığında veya güncellendiğinde arama motorlarına bildirin. Pro, katılan motorlara ileten ortak api.indexnow.org uç noktasına gönderir. Varsayılan olarak kapalıdır."
---

# IndexNow — yayında bildirimle dizine ekleme {#indexnow-—-push-on-publish-indexing}

Değişen sayfayı tarayıcının bulmasını beklemek yerine **IndexNow**, URL
yayınlandığı veya güncellendiği anda arama motorlarına *haber vermenizi* sağlar.
Pro, ortak `api.indexnow.org` uç noktasına gönderir; bu uç nokta tek çağrıyla
**katılan tüm motorlara iletir**. Her motor için ayrı gönderim yapılmaz.
[Resmî SSS](https://www.indexnow.org/faq), Amazon, Bing, Naver, Seznam,
Yandex ve Yep’i listeler. Bildirim, dizine eklenmeyi garanti etmez.

**Varsayılan olarak kapalıdır.** Etkinleştirip URL göndermedikçe ağ çağrısı yapılmaz.

## Kurulum {#setup}

### 1. Anahtar üretin {#_1-generate-a-key}

IndexNow, sunucu adı üzerindeki kontrolü doğrulamak için **anahtar** kullanır.
Pro, `[a-f0-9-]` kümesinden 8–128 karakter kabul eder; 32 karakterlik onaltılık
dize idealdir. Bir kez üretip sabit tutun, ardından ortam üzerinden sağlayın:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Anahtar yapılandırma üzerinden okunur; `config:cache` sonrasında kullanılabilir kalır
Search Console kimlik bilgilerinden farklı olarak IndexNow anahtarı **gizli
değildir**. Sunucu adının size ait olduğunu kanıtlamak için `/{key}.txt` adresinde
herkese açık sunulur. Bu yüzden Pro, anahtarı yapılandırma katmanından çözümler:
`indexnow.key`, varsayılan olarak `env('SEO_PRO_INDEXNOW_KEY')` kullanır. Bu bilinçlidir: **yalnızca
`.env` içinde tanımlı** değerler, `config:cache` sonrasında `env()`
için kullanılamaz; Laravel artık bu dosyayı yüklemez. Gerçek süreç ortamı
değişkenleri kullanılabilir kalır. Yapılandırma üzerinden okunduğunda anahtar
`config:cache` tarafından alınır ve her zaman kullanılabilir. Karşılığı şudur:
**anahtarı değiştirmek, `php artisan config:cache` komutunu yeniden çalıştırmayı gerektirir.**
Anahtar hiçbir zaman günlüğe yazılmaz. Anahtar dosyası canlı ortamda 404
veriyorsa [yapılandırması önbelleklenmiş sunuculara](#config-cached-servers) bakın.
:::

### 2. Anahtar dosyasını sunun {#_2-serve-the-key-file}

IndexNow, sahipliği doğrulamak için yalnızca anahtarı içeren `https://{host}/{key}.txt`
adresini getirir. `route` açıkken (varsayılan), **Pro bunu sizin için sunar**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Yalnızca yapılandırılan tek anahtar yolu içerik sunar. Bu rotanın yakaladığı
diğer yollar 404 döndürür; IndexNow kapalıyken rotanın tamamı 404 verir.
Dosyayı kendiniz veya CDN üzerinde mi barındırmak istiyorsunuz? `route`
ayarını kapatın ve `key_location` değerini URL’nize yöneltin.

## URL gönderme {#submitting-urls}

### Kaydederken otomatik gönderme (yayında bildirim yolu) {#automatically-on-save-the-push-on-publish-path}

Trait’i modele ekleyin ve `auto_submit` ayarını açın. Her kayıt,
modelin `getUrlForSEO()` değerinin gönderilmesini kuyruğa alır:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Trait, yayın koşuluna uyar: tam kontrol için `shouldSubmitToIndexNow(): bool` uygulayın.
Yoksa `is_published` niteliğini kullanır; o da yoksa her kayıtta gönderir.
Gönderim her zaman **kuyruğa alınır**; model kaydetme işlemi ağı beklemez.

### Elle {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` varsayılan olarak kuyruğa alır; aynı süreçte çalıştırmak için `queue: false` verin.

### Komut satırından {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Yalnızca aynı sunucu adı
Her URL’nin `http(s)` kullandığı **ve** yapılandırılmış `host`
değerine ait olduğu doğrulanır. Diğerleri **elenir**; sayılır ama asla
gönderilmez. Yalnızca size ait URL’leri gönderebilirsiniz; uç nokta zaten
sunucu adı uyuşmazlığını reddeder. `max_urls_per_request` değerinden (protokol sınırı
olan 10000) büyük listeler otomatik parçalara ayrılır.
:::

## Yapılandırma {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Yeniden denemeler nasıl çalışır? {#how-retries-work}

Kuyruktaki `SubmitToIndexNowJob`, yeniden denenmesi *gereken* durumları ele alır:
`429` (hız sınırı), `5xx` veya zaman aşımı, `backoff`
ile en fazla `tries` kez yeniden denenir. `400`/`403`/`422`
gibi kalıcı istemci hatası (yanlış anahtar, sunucu adı uyuşmazlığı) ise
kaydedilir ve gereksiz tekrarlar yerine işlem **durur**. `200` ve
`202` (alındı / anahtar kontrolü bekleniyor) başarılı sayılır.

Canlı ortamda işe **ayrı bir kuyruk** verin; yavaş uç nokta kullanıcıya
dönük işleri geciktirmesin:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Sorun giderme {#troubleshooting}

### Yapılandırması önbelleklenmiş sunucular {#config-cached-servers}

`indexnow.enabled` açıkça `true` olduğu hâlde canlı ortamda `/{key}.txt`
404 veriyorsa veya gönderimler sessizce hiçbir şey yapmıyorsa neden hemen
her zaman `php artisan config:cache` çalıştırılan sunucuda anahtarın **yalnızca `.env`
içinde** bulunmasıdır. Yapılandırma önbelleklendikten sonra Laravel
`.env` dosyasını ayrıştırmaz; bu yüzden `env('SEO_PRO_INDEXNOW_KEY')`,
`null` döndürür, anahtar dosyası rotası hiç kaydedilmez ve her gönderim
“yapılandırılmamış” olarak reddedilir.

Varsayılan yapılandırma `indexnow.key` değerini `env(...)` üzerinden çözümler;
normal kurulum önbellek oluşturulurken alınır ve çalışır. Sorun yalnızca
**yapılandırmayı yayınlayıp `env(...)` varsayılanını kaldırdıysanız** veya
anahtarı **yalnızca `.env` içinde bulunan özel `key_env` adıyla**
ayarladıysanız ortaya çıkar. İki çözüm vardır:

1. **Anahtarı yapılandırmada tutun** (önerilen): `indexnow.key` değerini
   `env('SEO_PRO_INDEXNOW_KEY')` olarak bırakın (veya sabit değer atayın), ardından
   `php artisan config:cache` komutunu yeniden çalıştırın. Anahtarı daha sonra değiştirmek,
   yeniden önbellekleme gerektirir.
2. **Gerçek ortam değişkeni sağlayın**: `SEO_PRO_INDEXNOW_KEY` değerini işletim sistemi
   veya süreç ortamı değişkeni olarak ayarlayın: PHP-FPM havuzunda `env[...]`,
   systemd’de `Environment=` veya platformunuzun ortam değişkeni ayarları.
   **Yalnızca `.env` yeterli değildir.** Gerçek işletim sistemi ortam
   değişkenleri, yapılandırma önbellekliyken de okunabilir.

Doğrulamak için `php artisan seo:doctor` çalıştırın. Bu durumu algıladığında
**“IndexNow etkin ancak geçerli anahtar çözümlenemiyor”** mesajını kesin
çözümle birlikte raporlar. Pro ayrıca, yapılandırması önbelleklenmiş uygulama
okunamayan anahtarla açıldığında süreç başına bir kez uyarı kaydeder.

::: tip Google
Google, IndexNow’a **katılmaz**. Google için [Search Console](/tr/pro/search-console)
entegrasyonunu ve güncel bir site haritasını kullanın.
:::
